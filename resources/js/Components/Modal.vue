<script setup>
import { ref, watch, computed, onMounted, onUnmounted, nextTick } from 'vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  maxWidth: { type: String, default: '2xl' },
  closeable: { type: Boolean, default: true },
  ariaLabel: { type: String, default: 'Dialog' },
})

const emit = defineEmits(['close'])
const dialog = ref(null)

const isVisible = ref(props.show)
let previouslyFocused = null

const focusableSelector = 'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'

const focusDialog = () => {
  const el = dialog.value
  if (!el) return
  const focusable = Array.from(el.querySelectorAll(focusableSelector))
    .find((n) => !n.hasAttribute('disabled'))
  ;(focusable || el).focus()
}

const trapFocus = (e) => {
  const el = dialog.value
  if (!el) return
  const focusables = Array.from(el.querySelectorAll(focusableSelector))
    .filter((n) => !n.hasAttribute('disabled'))
  if (focusables.length === 0) {
    e.preventDefault()
    el.focus()
    return
  }
  const first = focusables[0]
  const last = focusables[focusables.length - 1]
  if (e.shiftKey && document.activeElement === first) {
    e.preventDefault()
    last.focus()
  } else if (!e.shiftKey && document.activeElement === last) {
    e.preventDefault()
    first.focus()
  }
}

const handleKeydown = (e) => {
  if (!isVisible.value) return
  if (e.key === 'Escape') {
    close()
  } else if (e.key === 'Tab') {
    trapFocus(e)
  }
}

const close = () => {
  if (props.closeable) emit('close')
}

watch(() => props.show, async (val) => {
  isVisible.value = val
  document.body.style.overflow = val ? 'hidden' : ''
  if (val) {
    previouslyFocused = document.activeElement
    await nextTick()
    focusDialog()
  } else if (previouslyFocused && typeof previouslyFocused.focus === 'function') {
    previouslyFocused.focus()
  }
}, { flush: 'post' })

const maxWidthClass = computed(() => ({
  sm: 'sm:max-w-sm',
  md: 'sm:max-w-md',
  lg: 'sm:max-w-lg',
  xl: 'sm:max-w-xl',
  '2xl': 'sm:max-w-2xl',
}[props.maxWidth]))

onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
  if (props.show) {
    previouslyFocused = document.activeElement
    nextTick(focusDialog)
  }
})
onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown)
  document.body.style.overflow = ''
})
</script>

<template>
  <transition name="modal-fade">
    <div v-if="isVisible" class="fixed inset-0 z-50 overflow-y-auto" role="presentation">
      <!-- Backdrop -->
      <div class="absolute inset-0 bg-gray-500 bg-opacity-75" @click="close"></div>

      <div class="relative flex min-h-full items-start justify-center p-4 sm:items-center sm:p-6">
        <!-- Modal Content -->
        <div
          ref="dialog"
          role="dialog"
          aria-modal="true"
          :aria-label="ariaLabel"
          tabindex="-1"
          :class="[
            'relative flex max-h-[calc(100vh-2rem)] w-full flex-col overflow-hidden rounded-lg bg-white shadow-xl outline-none sm:max-h-[calc(100vh-3rem)]',
            maxWidthClass,
          ]"
        >
          <header class="flex shrink-0 items-center justify-between border-b px-4 py-3">
            <slot name="title" />
            <button
              type="button"
              @click="close"
              :aria-label="ariaLabel + ' close'"
              class="text-gray-500 hover:text-gray-700"
            >&times;</button>
          </header>
          <div class="min-h-0 overflow-y-auto p-4">
            <slot name="content" />
          </div>
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
