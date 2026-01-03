<script setup>
import FrontDeskLayout from '@/Layouts/Staff/FrontDeskLayout.vue'
import { useForm, Link, Head } from '@inertiajs/vue3';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { 
    User, 
    Calendar, 
    ChevronLeft, 
    Save, 
    Tag, 
    AlertCircle,
    ArrowRight
} from 'lucide-vue-next';

const props = defineProps({
    booking: Object,
});

// helper to normalize date
function toDate(value) {
    return value ? value.substring(0, 10) : '';
}

const form = useForm({
    guest_name: props.booking.guest_name,
    check_in: toDate(props.booking.check_in),
    check_out: toDate(props.booking.check_out),
    status: props.booking.status,
});

function submit() {
    form.put(
        route('frontdesk.bookings.update', props.booking.id),
        { 
            preserveScroll: true,
            onSuccess: () => {
                // Optional: trigger a success toast
            }
        }
    );
}
</script>

<template>
    <FrontDeskLayout>
        <Head title="Edit Reservation" />

        <div class="min-h-screen bg-slate-50/50 p-6 md:p-12">
            <div class="max-w-3xl mx-auto">
                
                <Link 
                    :href="route('frontdesk.bookings.index')" 
                    class="inline-flex items-center gap-2 text-slate-400 font-bold text-sm hover:text-indigo-600 transition-colors mb-8 group"
                >
                    <ChevronLeft class="w-4 h-4 group-hover:-translate-x-1 transition-transform" />
                    Back to Reservations
                </Link>

                <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <span class="bg-indigo-100 text-indigo-700 text-[10px] font-black uppercase tracking-widest px-3 py-1 rounded-full">
                                Booking Ref: {{ booking.booking_code }}
                            </span>
                        </div>
                        <h1 class="text-3xl font-black text-slate-900 tracking-tight">Modify Reservation</h1>
                    </div>
                </div>

                <div class="bg-white rounded-[2.5rem] shadow-xl shadow-slate-200/60 border border-slate-100 overflow-hidden">
                    <form @submit.prevent="submit" class="p-8 md:p-12 space-y-8">
                        
                        <div class="space-y-4">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 flex items-center gap-2">
                                <User class="w-3 h-3" /> Guest Information
                            </label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-600 transition-colors">
                                    <User class="w-5 h-5" />
                                </div>
                                <input
                                    v-model="form.guest_name"
                                    type="text"
                                    class="block w-full pl-12 pr-5 py-4 bg-slate-50 border-2 border-slate-50 rounded-2xl focus:bg-white focus:border-indigo-600 focus:ring-0 transition-all font-bold text-slate-700 shadow-none"
                                    required
                                    placeholder="Guest Full Name"
                                />
                            </div>
                            <InputError :message="form.errors.guest_name" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-4">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 flex items-center gap-2">
                                    <Calendar class="w-3 h-3" /> Check-In
                                </label>
                                <input
                                    v-model="form.check_in"
                                    type="date"
                                    class="block w-full px-5 py-4 bg-slate-50 border-2 border-slate-50 rounded-2xl focus:bg-white focus:border-indigo-600 focus:ring-0 transition-all font-bold text-slate-700 shadow-none"
                                    required
                                />
                            </div>

                            <div class="space-y-4">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 flex items-center gap-2">
                                    <ArrowRight class="w-3 h-3" /> Check-Out
                                </label>
                                <input
                                    v-model="form.check_out"
                                    type="date"
                                    class="block w-full px-5 py-4 bg-slate-50 border-2 border-slate-50 rounded-2xl focus:bg-white focus:border-indigo-600 focus:ring-0 transition-all font-bold text-slate-700 shadow-none"
                                    required
                                />
                            </div>
                        </div>

                        <div class="space-y-4">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 flex items-center gap-2">
                                <Tag class="w-3 h-3" /> Reservation Status
                            </label>
                            <div class="relative">
                                <select
                                    v-model="form.status"
                                    class="block w-full px-5 py-4 bg-slate-50 border-2 border-slate-50 rounded-2xl focus:bg-white focus:border-indigo-600 focus:ring-0 transition-all font-bold text-slate-700 appearance-none shadow-none"
                                >
                                    <option value="confirmed">Confirmed</option>
                                    <option value="pending_payment">Pending Payment</option>
                                    <option value="active">Active (Checked-In)</option>
                                    <option value="checked_out">Checked Out</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                                <div class="absolute inset-y-0 right-0 pr-5 flex items-center pointer-events-none text-slate-400">
                                    <Tag class="w-5 h-5" />
                                </div>
                            </div>
                        </div>

                        <div class="pt-6 flex flex-col sm:flex-row gap-4">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="flex-[2] group flex items-center justify-center gap-3 py-5 bg-slate-900 text-white rounded-3xl font-black text-lg hover:bg-indigo-600 transition-all shadow-xl shadow-slate-200 active:scale-[0.98] disabled:opacity-50"
                            >
                                <Save class="w-5 h-5" />
                                {{ form.processing ? 'Saving...' : 'Update Reservation' }}
                            </button>

                            <Link
                                :href="route('frontdesk.bookings.index')"
                                class="flex-1 flex items-center justify-center py-5 bg-slate-100 text-slate-600 rounded-3xl font-black text-lg hover:bg-slate-200 transition-all"
                            >
                                Cancel
                            </Link>
                        </div>
                    </form>

                    <div class="bg-slate-50 px-8 py-4 border-t border-slate-100 flex items-center gap-2">
                        <AlertCircle class="w-4 h-4 text-amber-500" />
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                            Changes will be logged to the staff audit trail
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </FrontDeskLayout>
</template>

<style scoped>
/* Standard overrides for clean boutique look */
:deep(input), :deep(select) {
    outline: none !important;
    box-shadow: none !important;
}

input[type="date"]::-webkit-calendar-picker-indicator {
    cursor: pointer;
    opacity: 0.6;
    filter: invert(0.2);
}
</style>