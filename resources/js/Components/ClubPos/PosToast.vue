<script setup>
import { computed } from 'vue'

const props = defineProps({
  message: { type: String, default: '' },
  type: { type: String, default: 'info' },
})

const toneClass = computed(() => ({
  error: 'bg-red-600 text-white',
  success: 'bg-emerald-600 text-white',
  queued: 'bg-amber-500 text-amber-950',
}[props.type] || 'bg-slate-700 text-white'))
</script>

<template>
  <transition
    enter-active-class="transition ease-out duration-200"
    enter-from-class="opacity-0 translate-y-2"
    leave-active-class="transition ease-in duration-200"
    leave-to-class="opacity-0 translate-y-2"
  >
    <div
      v-if="message"
      :role="type === 'error' ? 'alert' : 'status'"
      :aria-live="type === 'error' ? 'assertive' : 'polite'"
      class="fixed bottom-[76px] left-1/2 -translate-x-1/2 z-[120] max-w-[90vw] px-4 py-2.5 rounded-xl shadow-lg text-sm font-semibold text-center"
      :class="toneClass"
    >
      {{ message }}
    </div>
  </transition>
</template>
