<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { 
  Bell, 
  CheckCircle2, 
  Clock, 
  User, 
  MapPin, 
  ArrowRight,
  MessageSquareText
} from 'lucide-vue-next'

const props = defineProps({
  request: { type: Object, required: true },
})

const emit = defineEmits(['acknowledge', 'complete']);

const label = computed(() =>
  props.request.type
    ? props.request.type.replace('_', ' ').toUpperCase()
    : 'GENERAL REQUEST'
)

const roomNumber = computed(() =>
  props.request.room?.name ?? props.request.name ?? '—'
)

// Dynamic styling based on status
const statusConfig = computed(() => {
  switch (props.request.status) {
    case 'requested':
      return {
        bg: 'bg-rose-50 border-rose-100',
        iconBg: 'bg-rose-500',
        textColor: 'text-rose-700',
        badge: 'bg-rose-100 text-rose-700',
        label: 'New Request'
      }
    case 'acknowledged':
      return {
        bg: 'bg-amber-50 border-amber-100',
        iconBg: 'bg-amber-500',
        textColor: 'text-amber-700',
        badge: 'bg-amber-100 text-amber-700',
        label: 'In Progress'
      }
    default:
      return {
        bg: 'bg-slate-50 border-slate-100',
        iconBg: 'bg-slate-400',
        textColor: 'text-slate-500',
        badge: 'bg-slate-200 text-slate-600',
        label: 'Completed'
      }
  }
})

function handleAcknowledge() {
  emit('acknowledge', props.request.id);
}

function handleComplete() {
  emit('complete', props.request.id);
}

function formatTime(date) {
  return new Date(date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
  <div 
    class="relative group overflow-hidden rounded-[2rem] border-2 transition-all duration-300"
    :class="[statusConfig.bg, request.status === 'requested' ? 'shadow-lg shadow-rose-100/50' : 'shadow-sm']"
  >
    <div class="p-6 flex flex-col md:flex-row md:items-center justify-between gap-6">
      
      <div class="flex items-start gap-5">
        <div 
          class="shrink-0 w-14 h-14 rounded-2xl flex items-center justify-center text-white shadow-lg transition-transform group-hover:scale-110"
          :class="statusConfig.iconBg"
        >
          <Bell v-if="request.status === 'requested'" class="w-6 h-6 animate-pulse" />
          <Clock v-else-if="request.status === 'acknowledged'" class="w-6 h-6" />
          <CheckCircle2 v-else class="w-6 h-6" />
        </div>

        <div>
          <div class="flex flex-wrap items-center gap-2 mb-1">
            <span class="text-[10px] font-black uppercase tracking-widest" :class="statusConfig.textColor">
              {{ label }}
            </span>
            <span class="px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-tighter" :class="statusConfig.badge">
              {{ statusConfig.label }}
            </span>
          </div>
          
          <h3 class="text-xl font-black text-slate-900 leading-tight mb-2">
            Room {{ roomNumber }} <span class="text-slate-300 mx-1">—</span> {{ request.guest_name || 'Guest' }}
          </h3>

          <div class="flex items-center gap-4 text-slate-500">
            <div class="flex items-center gap-1.5 text-xs font-bold">
              <Clock class="w-3.5 h-3.5 text-slate-400" />
              {{ formatTime(request.created_at) }}
            </div>
            <div v-if="request.notes" class="flex items-center gap-1.5 text-xs font-bold">
              <MessageSquareText class="w-3.5 h-3.5 text-slate-400" />
              Note Attached
            </div>
          </div>
        </div>
      </div>

      <div v-if="request.content || request.notes" class="flex-1 max-w-md">
        <p class="text-sm font-medium text-slate-600 line-clamp-2 italic bg-white/50 p-3 rounded-xl border border-white">
          "{{ request.content || request.notes }}"
        </p>
      </div>

      <div class="flex items-center gap-3">
        <button
          v-if="request.status === 'requested'"
          @click="handleAcknowledge"
          class="flex items-center gap-2 px-6 py-3 bg-slate-900 text-white rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-indigo-600 transition-all shadow-xl shadow-slate-200"
        >
          Acknowledge
          <ArrowRight class="w-4 h-4" />
        </button>

        <button
          v-if="request.status === 'acknowledged'"
          @click="handleComplete"
          class="flex items-center gap-2 px-6 py-3 bg-emerald-600 text-white rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-emerald-700 transition-all shadow-xl shadow-emerald-100"
        >
          <CheckCircle2 class="w-4 h-4" />
          Mark Complete
        </button>

        <div v-if="request.status === 'completed'" class="flex items-center gap-2 px-4 py-2 text-emerald-600 font-black text-xs uppercase tracking-widest">
          <CheckCircle2 class="w-4 h-4" />
          Resolved
        </div>
      </div>
    </div>

    <div 
      v-if="request.status === 'requested'" 
      class="absolute top-0 right-0 -mt-4 -mr-4 w-16 h-16 bg-rose-500/10 rounded-full blur-2xl"
    ></div>
  </div>
</template>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;  
  overflow: hidden;
}
</style>