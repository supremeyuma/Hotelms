<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import { 
  CheckCircle2, 
  BedDouble, 
  ArrowRight, 
  X,
  Loader2
} from 'lucide-vue-next';

const props = defineProps({
    show: Boolean,
    booking: Object,
});

const emit = defineEmits(['checked-in', 'close']);

const rooms = ref(1);
const processing = ref(false);

const remaining = computed(() =>
    props.booking ? Math.max(props.booking.quantity - (props.booking.checked_in_rooms || 0), 0) : 0
);

watch(
    () => props.show,
    (newVal) => {
        if (newVal) {
            rooms.value = remaining.value > 0 ? 1 : 0;
            processing.value = false;
        }
    }
);

function submit() {
    if (rooms.value < 1 || rooms.value > remaining.value) return;

    processing.value = true;
    router.post(
        `/frontdesk/bookings/${props.booking.id}/checkin`,
        { rooms: rooms.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                processing.value = false;
                emit('close');
            },
            onError: () => {
                processing.value = false;
            }
        }
    );
}
</script>

<template>
    <Modal :show="show" @close="$emit('close')" max-width="md">
        <template #content>
            <div v-if="booking" class="-m-4 overflow-hidden rounded-b-lg">
                <div class="bg-slate-900 p-8 text-white relative">
                    <button 
                        type="button"
                        @click="$emit('close')" 
                        class="absolute top-6 right-6 p-2 hover:bg-white/10 rounded-full transition-colors"
                    >
                        <X class="w-5 h-5" />
                    </button>
                    
                    <div class="flex items-center gap-4 mb-4">
                        <div class="p-3 bg-emerald-500 rounded-2xl shadow-lg shadow-emerald-500/20">
                            <CheckCircle2 class="w-6 h-6 text-white" />
                        </div>
                        <div class="text-left">
                            <h2 class="text-xl font-black tracking-tight">Guest Arrival</h2>
                            <p class="text-slate-400 text-xs font-bold uppercase tracking-widest">
                                Ref: {{ booking.booking_code }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-between p-4 bg-white/5 rounded-2xl border border-white/10 text-left">
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Primary Guest</p>
                            <p class="text-lg font-bold">{{ booking.guest_name }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</p>
                            <p class="text-emerald-400 font-bold">Confirmed</p>
                        </div>
                    </div>
                </div>

                <div class="p-8 bg-white text-left">
                    <div class="mb-8 flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100">
                        <div class="flex items-center gap-3 text-left">
                            <BedDouble class="w-5 h-5 text-slate-400" />
                            <div>
                                <p class="text-xs font-black text-slate-400 uppercase tracking-tighter">Allocation</p>
                                <p class="text-sm font-bold text-slate-700">
                                    {{ booking.checked_in_rooms || 0 }} / {{ booking.quantity }} Rooms
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-black text-slate-400 uppercase tracking-tighter">Remaining</p>
                            <p class="text-sm font-black text-indigo-600">{{ remaining }} Units</p>
                        </div>
                    </div>

                    <div v-if="remaining > 0" class="space-y-6">
                        <div class="space-y-3">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Units to Check-In</label>
                            <div class="relative">
                                <input
                                    type="number"
                                    v-model.number="rooms"
                                    :max="remaining"
                                    min="1"
                                    class="block w-full px-6 py-4 bg-slate-50 border-2 border-slate-100 rounded-2xl focus:bg-white focus:border-indigo-600 focus:ring-0 transition-all font-black text-slate-700 text-xl"
                                />
                            </div>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button 
                                type="button"
                                @click="$emit('close')"
                                class="flex-1 py-4 px-6 border-2 border-slate-100 rounded-2xl font-black text-slate-500 hover:bg-slate-50 transition-all uppercase tracking-widest text-xs"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                @click="submit"
                                :disabled="rooms < 1 || rooms > remaining || processing"
                                class="flex-[2] group flex items-center justify-center gap-3 py-4 px-6 bg-slate-900 text-white rounded-2xl font-black text-sm hover:bg-indigo-600 transition-all shadow-xl shadow-slate-200 disabled:opacity-50"
                            >
                                <Loader2 v-if="processing" class="w-4 h-4 animate-spin" />
                                <span v-else>Confirm Check-In</span>
                                <ArrowRight v-if="!processing" class="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                            </button>
                        </div>
                    </div>

                    <div v-else class="text-center py-6">
                        <CheckCircle2 class="w-12 h-12 text-emerald-500 mx-auto mb-4" />
                        <h3 class="text-lg font-black text-slate-900">All Rooms Checked In</h3>
                        <button @click="$emit('close')" class="mt-6 w-full py-4 bg-slate-100 text-slate-600 rounded-2xl font-black uppercase tracking-widest text-xs">Close</button>
                    </div>
                </div>
            </div>
        </template>
    </Modal>
</template>

<style scoped>
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
</style>