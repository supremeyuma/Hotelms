<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  staff: { type: Object, default: null },
  session: { type: Object, default: null },
  device: { type: Object, default: null },
})

const emit = defineEmits(['lock', 'switch-staff'])

const now = ref(new Date())
let timer

onMounted(() => {
  timer = setInterval(() => { now.value = new Date() }, 10000)
})
onUnmounted(() => clearInterval(timer))

const timeDisplay = computed(() => {
  return now.value.toLocaleTimeString('en-KE', { hour: '2-digit', minute: '2-digit' })
})

const dateDisplay = computed(() => {
  return now.value.toLocaleDateString('en-KE', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
})
</script>

<template>
  <div class="flex items-center justify-between px-6 py-3 bg-slate-800 border-b border-slate-700 shrink-0 min-h-[60px]">
    <div class="flex items-center gap-4">
      <div class="flex items-center gap-2">
        <div class="w-3 h-3 rounded-full"
          :class="session?.status === 'open' ? 'bg-emerald-500 animate-pulse' : 'bg-slate-500'">
        </div>
        <span class="text-sm font-semibold text-slate-200">POS</span>
      </div>

      <div class="h-6 w-px bg-slate-600" />

      <button @click="$emit('switch-staff')"
        class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 transition-colors active:scale-95">
        <div class="w-7 h-7 rounded-full bg-indigo-500 text-xs flex items-center justify-center font-bold">
          {{ staff?.name?.charAt(0) || '?' }}
        </div>
        <div class="text-left leading-tight">
          <p class="text-sm font-medium text-white">{{ staff?.name || 'No Staff' }}</p>
          <p v-if="session" class="text-xs text-slate-400">Shift: #{{ session.id?.slice(0, 8) }}</p>
        </div>
      </button>
    </div>

    <div class="flex items-center gap-4">
      <div class="text-right">
        <p class="text-lg font-bold text-white tabular-nums">{{ timeDisplay }}</p>
        <p class="text-xs text-slate-400">{{ dateDisplay }}</p>
      </div>

      <button @click="$emit('lock')"
        class="px-4 py-2 rounded-lg bg-slate-700 hover:bg-red-600/20 hover:text-red-400 border border-slate-600 hover:border-red-500/40 transition-all active:scale-95 flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
        <span class="text-sm font-medium">Lock</span>
      </button>
    </div>
  </div>
</template>
