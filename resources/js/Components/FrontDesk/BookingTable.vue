<script setup>
import { Link } from '@inertiajs/vue3';
import StatusBadge from './StatusBadge.vue';
import { 
  ArrowRight, 
  ExternalLink, 
  Calendar, 
  User, 
  BedDouble, 
  Settings2,
  CheckCircle2,
  LogOut
} from 'lucide-vue-next';

const props = defineProps({
    bookings: Array,
});

defineEmits(['checkin', 'checkout', 'edit']);

function checkedInRoomsCount(booking) {
    if (!booking.rooms) return 0;
    return booking.rooms.filter(r => r.pivot.checked_in_at).length;
}

function canCheckIn(booking) {
    return (booking.status === 'confirmed' || booking.status === 'active' || booking.status === 'checked_in') && 
           checkedInRoomsCount(booking) < booking.quantity;
}

function formatDate(d) {
    return new Date(d).toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric',
        year: 'numeric' 
    });
}
</script>

<template>
    <div class="w-full">
        <table class="w-full border-separate border-spacing-y-3">
            <thead>
                <tr class="text-slate-400">
                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-[0.2em]">Booking Reference</th>
                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-[0.2em]">Primary Guest</th>
                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-[0.2em]">Stay Duration</th>
                    <th class="px-6 py-3 text-center text-[10px] font-black uppercase tracking-[0.2em]">Inventory</th>
                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-[0.2em]">Status</th>
                    <th class="px-6 py-3 text-right text-[10px] font-black uppercase tracking-[0.2em]">Operations</th>
                </tr>
            </thead>

            <tbody>
                <tr
                    v-for="booking in bookings"
                    :key="booking.id"
                    class="group bg-white hover:bg-slate-50 transition-all duration-200"
                >
                    <td class="px-6 py-5 rounded-l-[1.5rem] border-y border-l border-slate-100">
                        <Link 
                            :href="route('frontdesk.bookings.show', booking.id)"
                            class="flex items-center gap-2 group/link"
                        >
                            <span class="font-mono font-black text-indigo-600 bg-indigo-50 px-3 py-1 rounded-lg text-sm group-hover/link:bg-indigo-600 group-hover/link:text-white transition-colors">
                                {{ booking.booking_code }}
                            </span>
                            <ExternalLink class="w-3 h-3 text-slate-300 opacity-0 group-hover/link:opacity-100 transition-opacity" />
                        </Link>
                    </td>

                    <td class="px-6 py-5 border-y border-slate-100">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500">
                                <User class="w-4 h-4" />
                            </div>
                            <span class="font-bold text-slate-700">{{ booking.guest_name }}</span>
                        </div>
                    </td>

                    <td class="px-6 py-5 border-y border-slate-100">
                        <div class="flex flex-col">
                            <div class="flex items-center gap-2 text-sm font-bold text-slate-700">
                                <span>{{ formatDate(booking.check_in) }}</span>
                                <ArrowRight class="w-3 h-3 text-slate-300" />
                                <span>{{ formatDate(booking.check_out) }}</span>
                            </div>
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-tighter mt-1 flex items-center gap-1">
                                <Calendar class="w-3 h-3" />
                                Schedule
                            </span>
                        </div>
                    </td>

                    <td class="px-6 py-5 border-y border-slate-100 text-center">
                        <div class="inline-flex flex-col items-center">
                            <div class="flex items-center gap-2 mb-1">
                                <BedDouble class="w-4 h-4 text-slate-400" />
                                <span class="text-sm font-black text-slate-700">
                                    {{ checkedInRoomsCount(booking) }} <span class="text-slate-300">/</span> {{ booking.quantity }}
                                </span>
                            </div>
                            <div class="w-16 h-1 bg-slate-100 rounded-full overflow-hidden">
                                <div 
                                    class="h-full bg-indigo-500 transition-all duration-500"
                                    :style="{ width: (checkedInRoomsCount(booking) / booking.quantity) * 100 + '%' }"
                                ></div>
                            </div>
                        </div>
                    </td>

                    <td class="px-6 py-5 border-y border-slate-100">
                        <StatusBadge :status="booking.status" />
                    </td>

                    <td class="px-6 py-5 rounded-r-[1.5rem] border-y border-r border-slate-100 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <button
                                v-if="canCheckIn(booking)"
                                @click="$emit('checkin', booking)"
                                class="flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-xl text-xs font-black hover:bg-emerald-700 transition-all active:scale-95 shadow-lg shadow-emerald-100"
                            >
                                <CheckCircle2 class="w-3.5 h-3.5" />
                                Check-in
                            </button>

                            <button
                                v-if="booking.status === 'active' || booking.status === 'checked_in'"
                                @click="$emit('checkout', booking)"
                                class="flex items-center gap-2 px-4 py-2 bg-rose-50 text-rose-600 rounded-xl text-xs font-black hover:bg-rose-600 hover:text-white transition-all active:scale-95"
                            >
                                <LogOut class="w-3.5 h-3.5" />
                                Check-out
                            </button>

                            <button
                                @click="$emit('edit', booking)"
                                class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-xl transition-all"
                                title="Edit Booking"
                            >
                                <Settings2 class="w-5 h-5" />
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<style scoped>
/* Scoped styles for the specialized table layout */
tr {
    transition: transform 0.2s ease;
}
tr:hover {
    transform: translateY(-2px);
}
</style>
