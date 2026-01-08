<script setup>
import FrontDeskLayout from '@/Layouts/Staff/FrontDeskLayout.vue'
import { Link, Head } from '@inertiajs/vue3';
import { 
  DoorOpen, 
  User, 
  Receipt, 
  Sparkles, 
  Hammer, 
  Calendar,
  ChevronRight,
  Search,
  Filter
} from 'lucide-vue-next';

const props = defineProps({
  status: String,
  rooms: Array,
});

const nextBooking = (room) => {
  return room.bookings?.find(b => b.status === 'confirmed') ?? null;
};

// High-fidelity status styling
const getStatusConfig = (status) => {
  const configs = {
    available: { 
      bg: 'bg-emerald-50 border-emerald-100', 
      text: 'text-emerald-700', 
      dot: 'bg-emerald-500',
      icon: Sparkles,
      label: 'Ready for Guest' 
    },
    dirty: { 
      bg: 'bg-amber-50 border-amber-100', 
      text: 'text-amber-700', 
      dot: 'bg-amber-500',
      icon: Sparkles, 
      label: 'Needs Cleaning' 
    },
    maintenance: { 
      bg: 'bg-rose-50 border-rose-100', 
      text: 'text-rose-700', 
      dot: 'bg-rose-500',
      icon: Hammer, 
      label: 'Out of Order' 
    },
    occupied: { 
      bg: 'bg-indigo-50 border-indigo-100', 
      text: 'text-indigo-700', 
      dot: 'bg-indigo-500',
      icon: User, 
      label: 'Guest In-Room' 
    }
  };
  return configs[status] || { bg: 'bg-slate-50', text: 'text-slate-600', dot: 'bg-slate-400', label: status };
};
</script>

<template>
  <FrontDeskLayout>
    <Head :title="`${status} Rooms`" />

    <div class="p-8 max-w-[1400px] mx-auto space-y-8">
      
      <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
        <div>
          <div class="flex items-center gap-2 mb-2">
            <div class="p-1.5 bg-slate-100 text-slate-500 rounded-lg">
              <DoorOpen class="w-4 h-4" />
            </div>
            <span class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Inventory Management</span>
          </div>
          <h1 class="text-4xl font-black text-slate-900 tracking-tight capitalize">
            {{ status }} Rooms
          </h1>
        </div>

        <div class="flex items-center gap-3">
            <div class="relative">
                <Search class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                <input type="text" placeholder="Search room number..." class="pl-11 pr-4 py-3 bg-white border border-slate-200 rounded-2xl text-sm font-bold focus:ring-indigo-500 focus:border-indigo-500 w-64 shadow-sm" />
            </div>
            <button class="p-3 bg-white border border-slate-200 rounded-2xl text-slate-500 hover:bg-slate-50 transition-colors">
                <Filter class="w-5 h-5" />
            </button>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        <div v-for="room in rooms" :key="room.id" 
             class="group bg-white rounded-[2.5rem] border border-slate-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 overflow-hidden">
          
          <div class="p-8">
            <div class="flex justify-between items-start mb-6">
              <div>
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">
                  {{ room.room_type?.title || 'Standard Unit' }}
                </span>
                <h2 class="text-3xl font-black text-slate-900 tracking-tight">
                  Room {{ room.name }}
                </h2>
              </div>
              <div :class="['px-4 py-1.5 rounded-xl border-2 font-black text-[10px] uppercase tracking-widest flex items-center gap-2', getStatusConfig(room.status).bg, getStatusConfig(room.status).text]">
                <span :class="['w-1.5 h-1.5 rounded-full', getStatusConfig(room.status).dot]"></span>
                {{ getStatusConfig(room.status).label }}
              </div>
            </div>

            <div v-if="status === 'occupied'" class="space-y-4">
              <div class="p-4 bg-slate-50 rounded-2xl flex items-center gap-4">
                <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center border border-slate-100 text-slate-400">
                    <User class="w-5 h-5" />
                </div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-tighter">Current Guest</p>
                    <p class="font-bold text-slate-800">{{ room.bookings[0]?.guest_name || 'Anonymous' }}</p>
                </div>
              </div>

              <div class="flex items-center justify-between px-2">
                 <div class="text-xs font-bold text-slate-400">
                    Folio: <span class="text-slate-900">#{{ room.bookings[0]?.booking_code }}</span>
                 </div>
                 <Link :href="`/frontdesk/rooms/${room.id}/billing`" class="flex items-center gap-1 text-xs font-black text-indigo-600 hover:text-indigo-700 uppercase tracking-widest">
                    <Receipt class="w-3.5 h-3.5" /> Billing Ledger
                 </Link>
              </div>
            </div>

            <div v-else class="space-y-4">
              <div v-if="nextBooking(room)" class="p-4 bg-indigo-50/50 border border-indigo-100 rounded-2xl">
                <p class="text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                    <Calendar class="w-3 h-3" /> Upcoming Booking
                </p>
                <div class="flex justify-between items-center">
                    <span class="font-bold text-slate-800">#{{ nextBooking(room).booking_code }}</span>
                    <span class="text-xs font-black text-indigo-600 bg-white px-2 py-1 rounded-lg">
                        {{ nextBooking(room).check_in }}
                    </span>
                </div>
              </div>

              <div v-else class="p-4 bg-slate-50 rounded-2xl border border-dashed border-slate-200 text-center">
                <p class="text-xs font-bold text-slate-400 italic">No upcoming arrivals today</p>
              </div>
            </div>
          </div>

          <div class="px-8 py-4 bg-slate-50/50 border-t border-slate-50 flex justify-between items-center group-hover:bg-slate-50 transition-colors">
            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Floor: {{ room.floor || 'Ground' }}</span>
            <Link :href="route('frontdesk.bookings.show', room.bookings[0]?.id || 0)" 
                  v-if="room.bookings[0]"
                  class="p-2 bg-white rounded-xl border border-slate-200 text-slate-400 hover:text-indigo-600 hover:border-indigo-200 transition-all">
                <ChevronRight class="w-4 h-4" />
            </Link>
          </div>
        </div>
      </div>

      <div v-if="rooms.length === 0" class="py-20 text-center bg-white rounded-[3rem] border-2 border-dashed border-slate-100">
         <p class="text-slate-400 font-bold italic">No rooms found in the {{ status }} category.</p>
      </div>
    </div>
  </FrontDeskLayout>
</template>