<script setup>
import { computed } from 'vue'

const props = defineProps({
  dockets: { type: Array, default: () => [] },
  activeDocketId: { type: [String, null], default: null },
})

const emit = defineEmits(['select', 'create'])

const totalOpen = computed(() => props.dockets.length)
</script>

<template>
  <div class="flex items-center gap-2 px-4 py-2 bg-slate-800 border-t border-slate-700 overflow-x-auto shrink-0 min-h-[56px]" style="-webkit-overflow-scrolling: touch">
    <button @click="$emit('create')"
      class="flex items-center gap-1.5 px-3 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium transition-all active:scale-95 whitespace-nowrap min-h-[40px]">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
      </svg>
      New Docket
    </button>

    <div class="h-6 w-px bg-slate-600" />

    <button v-for="docket in dockets" :key="docket.id"
      @click="emit('select', docket.id)"
      class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all active:scale-95 whitespace-nowrap min-h-[40px]"
      :class="activeDocketId === docket.id
        ? 'bg-emerald-600/20 text-emerald-300 border border-emerald-600/40'
        : 'bg-slate-700 text-slate-300 hover:bg-slate-600 border border-transparent'">
      <span class="w-2 h-2 rounded-full bg-emerald-400" />
      <span>#{{ docket.docket_number }}</span>
      <span v-if="docket.table_identifier" class="text-xs text-slate-400">· {{ docket.table_identifier }}</span>
      <span class="text-xs text-slate-500">· {{ docket.items?.length || 0 }} items</span>
      <span class="text-xs font-bold text-emerald-400 tabular-nums">Ksh {{ Number(docket.total || 0).toLocaleString() }}</span>
    </button>

    <p v-if="totalOpen === 0" class="text-sm text-slate-500 px-2">
      No open dockets. Tap "New Docket" to start.
    </p>
  </div>
</template>
