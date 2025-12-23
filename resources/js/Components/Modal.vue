<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  maxWidth: { type: String, default: '2xl' },
  closeable: { type: Boolean, default: true },
})

const emit = defineEmits(['close'])
const dialog = ref(null)

const isVisible = ref(props.show)

watch(() => props.show, (val) => {
  isVisible.value = val
  document.body.style.overflow = val ? 'hidden' : ''
})

const maxWidthClass = computed(() => ({
  sm: 'sm:max-w-sm',
  md: 'sm:max-w-md',
  lg: 'sm:max-w-lg',
  xl: 'sm:max-w-xl',
  '2xl': 'sm:max-w-2xl',
}[props.maxWidth]))

const close = () => {
  if (props.closeable) emit('close')
}

const handleEscape = (e) => {
  if (e.key === 'Escape') close()
}

onMounted(() => document.addEventListener('keydown', handleEscape))
onUnmounted(() => {
  document.removeEventListener('keydown', handleEscape)
  document.body.style.overflow = ''
})
</script>

<template>
  <transition name="modal-fade">
    <div v-if="isVisible" class="fixed inset-0 z-50 flex items-center justify-center">
      <!-- Backdrop -->
      <div class="absolute inset-0 bg-gray-500 bg-opacity-75" @click="close"></div>

      <!-- Modal Content -->
      <div :class="['relative bg-white rounded-lg shadow-xl w-full mx-4 sm:mx-auto', maxWidthClass]">
        <header class="px-4 py-3 border-b flex justify-between items-center">
          <slot name="title" />
          <button @click="close" class="text-gray-500 hover:text-gray-700">&times;</button>
        </header>
        <div class="p-4">
          <slot name="content" />
        </div>
      </div>
    </div>
  </transition>
</template>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.3s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
</style>
