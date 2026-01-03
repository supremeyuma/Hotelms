<script setup>
import { ref } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import CleaningModal from './CleaningModal.vue'
import CleaningLayout from '@/Layouts/Staff/CleaningLayout.vue'
import { 
  Sparkles, 
  DoorOpen, 
  Clock, 
  AlertCircle, 
  CheckCircle2,
  Brush
} from 'lucide-vue-next'

const props = defineProps({ rooms: Array })

const selectedRoom = ref(null)

const openModal = (room) => {
  selectedRoom.value = room
}

const getStatusConfig = (status) => {
  const s = status?.toLowerCase() || 'dirty'
  switch (s) {
    case 'clean':
      return { 
        label: 'Clean', 
        bg: 'bg-emerald-50', 
        text: 'text-emerald-700', 
        border: 'border-emerald-100',
        icon: CheckCircle2 
      }
    case 'cleaning':
      return { 
        label: 'In Progress', 
        bg: 'bg-blue-50', 
        text: 'text-blue-700', 
        border: 'border-blue-100',
        icon: Clock 
      }
    case 'cleaner_requested':
      return { 
        label: 'Cleaner Requested', 
        bg: 'bg-yellow-50', 
        text: 'text-blue-900', 
        border: 'border-blue-100',
        icon: Clock 
      }
    default:
      return { 
        label: 'Dirty', 
        bg: 'bg-rose-50', 
        text: 'text-rose-700', 
        border: 'border-rose-100',
        icon: AlertCircle 
      }
  }
}
</script>

<template>
  <CleaningLayout>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
      <div class="flex items-center gap-4">
        <div class="p-3 bg-indigo-600 rounded-2xl text-white shadow-lg shadow-indigo-100">
          <Brush class="w-8 h-8" />
        </div>
        <div>
          <h1 class="text-3xl font-black text-slate-900 tracking-tight">Cleaning Dashboard</h1>
          <p class="text-slate-500 font-medium">Housekeeping & Room Maintenance Status</p>
        </div>
      </div>

      <div class="flex items-center gap-2 overflow-x-auto pb-2 md:pb-0 no-scrollbar">
        <div class="flex items-center gap-2 bg-white px-4 py-2 rounded-xl border border-slate-200 shadow-sm whitespace-nowrap">
          <span class="w-2 h-2 rounded-full bg-rose-500"></span>
          <span class="text-xs font-bold text-slate-600 uppercase tracking-wider">Dirty: {{ rooms.filter(r => !r.latest_cleaning || r.latest_cleaning.status === 'dirty').length }}</span>
        </div>
        <div class="flex items-center gap-2 bg-white px-4 py-2 rounded-xl border border-slate-200 shadow-sm whitespace-nowrap">
          <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
          <span class="text-xs font-bold text-slate-600 uppercase tracking-wider">Clean: {{ rooms.filter(r => r.latest_cleaning?.status === 'clean').length }}</span>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 md:gap-6">
      <div
        v-for="room in rooms"
        :key="room.id"
        @click="openModal(room)"
        class="group relative bg-white p-5 rounded-[2rem] border border-slate-200 shadow-sm cursor-pointer hover:shadow-xl hover:border-indigo-200 hover:-translate-y-1 transition-all duration-300"
      >
        <div class="flex flex-col h-full">
          <div class="flex items-start justify-between mb-4">
            <div class="flex flex-col">
              <span class="text-xs font-bold text-slate-400 uppercase tracking-tighter">Room</span>
              <span class="text-2xl font-black text-slate-900 leading-none">{{ room.room_number }}</span>
            </div>
            <div class="p-2 bg-slate-50 rounded-xl group-hover:bg-indigo-50 group-hover:text-indigo-600 transition-colors">
              <DoorOpen class="w-5 h-5" />
            </div>
          </div>

          <div 
            :class="[
              getStatusConfig(room.latest_cleaning?.status).bg,
              getStatusConfig(room.latest_cleaning?.status).text,
              getStatusConfig(room.latest_cleaning?.status).border,
              'mt-auto flex items-center justify-center gap-2 py-2 px-3 rounded-2xl border text-[10px] font-black uppercase tracking-widest'
            ]"
          >
            <component :is="getStatusConfig(room.latest_cleaning?.status).icon" class="w-3.5 h-3.5" />
            {{ getStatusConfig(room.latest_cleaning?.status).label }}
          </div>
        </div>

        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-indigo-400 to-transparent opacity-0 group-hover:opacity-100 transition-opacity rounded-t-full"></div>
      </div>
    </div>

    <CleaningModal
      v-if="selectedRoom"
      :room="selectedRoom"
      @close="selectedRoom = null"
    />
  </div>
  </CleaningLayout>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>