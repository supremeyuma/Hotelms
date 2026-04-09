<script setup>
import FrontDeskLayout from '@/Layouts/Staff/FrontDeskLayout.vue'
import { Link, Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { 
  DoorOpen, User, Receipt, Sparkles, Hammer, 
  Calendar, ChevronRight, Search, Filter 
} from 'lucide-vue-next';

const props = defineProps({
  view: String,
  rooms: Array,
  search: String,
});

const search = ref(props.search || '');
const currentView = ref(props.view || 'occupied');

// Update when clicking the slider or typing in search
watch([search, currentView], ([newSearch, newView]) => {
  router.get(
    route('frontdesk.rooms.index'),
    { search: newSearch, view: newView }, 
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    }
  );
});

// Sync local state if the server changes the view (e.g. initial load)
watch(() => props.view, (newVal) => {
  currentView.value = newVal;
});

const nextBooking = (room) => {
  // Logic to find the confirmed booking that isn't currently active 
  // or simply the first booking in the collection if the room isn't occupied
  return room.bookings?.[0] ?? null;
};

const getStatusConfig = (status) => {
  const configs = {
    available: { 
        bg: 'bg-emerald-50 border-emerald-100', 
        text: 'text-emerald-700', 
        dot: 'bg-emerald-500', 
        label: 'Ready' 
    },
    dirty: { 
        bg: 'bg-amber-50 border-amber-100', 
        text: 'text-amber-700', 
        dot: 'bg-amber-500', 
        label: 'Dirty' 
    },
    maintenance: { 
        bg: 'bg-rose-50 border-rose-100', 
        text: 'text-rose-700', 
        dot: 'bg-rose-500', 
        label: 'O.O.O' 
    },
    reserved: {
        bg: 'bg-amber-50 border-amber-100',
        text: 'text-amber-700',
        dot: 'bg-amber-500',
        label: 'Reserved'
    },
    unavailable: {
        bg: 'bg-slate-100 border-slate-200',
        text: 'text-slate-700',
        dot: 'bg-slate-500',
        label: 'Blocked'
    },
    occupied: { 
        bg: 'bg-indigo-50 border-indigo-100', 
        text: 'text-indigo-700', 
        dot: 'bg-indigo-500', 
        label: 'Occupied' 
    }
  };
  return configs[status] || { bg: 'bg-slate-50', text: 'text-slate-600', dot: 'bg-slate-400', label: status };
};

const updateRoomStatus = (roomId, newStatus) => {
  if (confirm(`Change room status to ${newStatus}?`)) {
    router.patch(route('frontdesk.rooms.update-status', roomId), {
      status: newStatus
    }, {
      preserveScroll: true,
      onSuccess: () => {
        // Optional: show a toast notification here
      }
    });
  }
};
</script>

<template>
  <FrontDeskLayout>
    <Head :title="`${currentView} Rooms`" />

    <div class="p-8 max-w-[1400px] mx-auto space-y-8">
      
      <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
        <div>
          <div class="flex items-center gap-2 mb-2">
            <div class="p-1.5 bg-slate-100 text-slate-500 rounded-lg">
              <DoorOpen class="w-4 h-4" />
            </div>
            <span class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Front Desk Inventory</span>
          </div>
          <h1 class="text-4xl font-black text-slate-900 tracking-tight capitalize">
            {{ currentView }} Rooms
          </h1>
        </div>

        <div class="flex items-center gap-4">
          <div class="relative">
            <Search class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input 
              v-model="search" 
              type="text" 
              placeholder="Search room name..." 
              class="pl-11 pr-4 py-3 bg-white border border-slate-200 rounded-2xl text-sm font-bold w-64 shadow-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all" 
            />
          </div>

          <div class="flex bg-slate-200/50 p-1.5 rounded-[1.25rem] border border-slate-200">
            <button 
              @click="currentView = 'occupied'"
              :class="['px-6 py-2 rounded-xl text-[11px] font-black uppercase tracking-widest transition-all duration-200', 
                        currentView === 'occupied' ? 'bg-white shadow-sm text-indigo-600' : 'text-slate-500 hover:text-slate-700']"
            >
              Occupied
            </button>
            <button 
              @click="currentView = 'unoccupied'"
              :class="['px-6 py-2 rounded-xl text-[11px] font-black uppercase tracking-widest transition-all duration-200', 
                        currentView === 'unoccupied' ? 'bg-white shadow-sm text-emerald-600' : 'text-slate-500 hover:text-slate-700']"
            >
              Unoccupied
            </button>
          </div>
        </div>
      </div>

      <div v-if="rooms.length > 0" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
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
              <div class="flex flex-col items-end gap-2">
                <div :class="['px-4 py-1.5 rounded-xl border-2 font-black text-[10px] uppercase flex items-center gap-2', getStatusConfig(room.status).bg, getStatusConfig(room.status).text]">
                  <span :class="['w-1.5 h-1.5 rounded-full', getStatusConfig(room.status).dot]"></span>
                  {{ getStatusConfig(room.status).label }}
                </div>

                <button 
                  v-if="room.status === 'dirty'"
                  @click="updateRoomStatus(room.id, 'available')"
                  class="text-[9px] font-black uppercase tracking-tighter text-emerald-600 hover:text-white hover:bg-emerald-500 border border-emerald-200 px-2 py-1 rounded-lg transition-all"
                >
                  Mark Ready
                </button>
                
                <button 
                  v-if="room.status === 'maintenance'"
                  @click="updateRoomStatus(room.id, 'available')"
                  class="text-[9px] font-black uppercase tracking-tighter text-rose-600 hover:text-white hover:bg-rose-500 border border-rose-200 px-2 py-1 rounded-lg transition-all"
                >
                  Complete Maintenance
                </button>
              </div>
            </div>

            <div v-if="room.status === 'occupied'" class="space-y-4">
              <div class="p-4 bg-slate-50 rounded-2xl flex items-center gap-4">
                <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center border border-slate-100 text-slate-400">
                    <User class="w-5 h-5" />
                </div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-tighter">Current Guest</p>
                    <p class="font-bold text-slate-800">{{ room.bookings?.[0]?.guest_name || 'Checked In' }}</p>
                </div>
              </div>
              <div class="flex items-center justify-between px-2">
                 <div class="text-xs font-bold text-slate-400">
                    ID: <span class="text-slate-900">#{{ room.bookings?.[0]?.booking_code?.substring(0,8) || 'N/A' }}</span>
                 </div>
                 <Link :href="`/frontdesk/rooms/${room.id}/billing`" class="flex items-center gap-1 text-xs font-black text-indigo-600 hover:text-indigo-700 uppercase tracking-widest">
                    <Receipt class="w-3.5 h-3.5" /> Ledger
                 </Link>
              </div>
            </div>

            <div v-else class="space-y-4">
              <div v-if="nextBooking(room)" class="p-4 bg-indigo-50/50 border border-indigo-100 rounded-2xl">
                <p class="text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                    <Calendar class="w-3 h-3" /> Booking Reference
                </p>
                <div class="flex justify-between items-center">
                    <span class="font-bold text-slate-800">{{ nextBooking(room).guest_name || 'Scheduled Guest' }}</span>
                    <span class="text-[10px] font-black text-indigo-600 bg-white px-2 py-1 rounded-lg border border-indigo-100">
                        #{{ nextBooking(room).booking_code?.substring(0,8) }}
                    </span>
                </div>
              </div>

              <div v-else class="p-4 bg-slate-50 rounded-2xl border border-dashed border-slate-200 text-center">
                <p class="text-xs font-bold text-slate-400 italic">No pending arrivals</p>
              </div>
            </div>
          </div>

          <div class="px-8 py-4 bg-slate-50/50 border-t border-slate-50 flex justify-between items-center group-hover:bg-slate-50 transition-colors">
            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Floor: {{ room.floor || 'G' }}</span>
            <Link v-if="room.bookings && room.bookings[0]" 
                  :href="route('frontdesk.bookings.show', room.bookings[0].id)" 
                  class="p-2 bg-white rounded-xl border border-slate-200 text-slate-400 hover:text-indigo-600 transition-all shadow-sm">
                <ChevronRight class="w-4 h-4" />
            </Link>
          </div>
        </div>
      </div>

      <div v-else class="py-20 text-center bg-white rounded-[3rem] border-2 border-dashed border-slate-100">
          <div class="inline-flex p-4 bg-slate-50 rounded-full mb-4">
            <DoorOpen class="w-8 h-8 text-slate-300" />
          </div>
          <p class="text-slate-400 font-bold italic">No rooms found in the {{ currentView }} category.</p>
      </div>
    </div>
  </FrontDeskLayout>
</template>
