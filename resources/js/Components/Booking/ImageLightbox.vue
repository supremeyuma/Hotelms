<script setup>
import { computed, onMounted, onUnmounted, watch } from 'vue'
import { ChevronLeft, ChevronRight, X } from 'lucide-vue-next'

const props = defineProps({
  show: { type: Boolean, default: false },
  images: { type: Array, default: () => [] },
  startIndex: { type: Number, default: 0 },
  title: { type: String, default: 'Image gallery' },
})

const emit = defineEmits(['close', 'update:startIndex'])

const currentIndex = computed({
  get: () => {
    if (!props.images.length) {
      return 0
    }

    return Math.min(Math.max(props.startIndex, 0), props.images.length - 1)
  },
  set: (value) => emit('update:startIndex', value),
})

const currentImage = computed(() => props.images[currentIndex.value] ?? null)

function close() {
  emit('close')
}

function nextImage() {
  if (props.images.length <= 1) {
    return
  }

  currentIndex.value = (currentIndex.value + 1) % props.images.length
}

function previousImage() {
  if (props.images.length <= 1) {
    return
  }

  currentIndex.value = (currentIndex.value - 1 + props.images.length) % props.images.length
}

function selectImage(index) {
  currentIndex.value = index
}

function handleKeydown(event) {
  if (!props.show) {
    return
  }

  if (event.key === 'Escape') {
    close()
  }

  if (event.key === 'ArrowRight') {
    nextImage()
  }

  if (event.key === 'ArrowLeft') {
    previousImage()
  }
}

watch(
  () => props.show,
  (isOpen) => {
    document.body.style.overflow = isOpen ? 'hidden' : ''
  }
)

onMounted(() => document.addEventListener('keydown', handleKeydown))
onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown)
  document.body.style.overflow = ''
})
</script>

<template>
  <transition name="gallery-fade">
    <div
      v-if="show && currentImage"
      class="fixed inset-0 z-[100] flex flex-col bg-slate-950/95 backdrop-blur-xl"
      @click="close"
    >
      <div class="flex items-center justify-between gap-4 px-4 py-4 text-white md:px-8" @click.stop>
        <div class="min-w-0">
          <p class="truncate text-xs font-black uppercase tracking-[0.28em] text-white/60">
            {{ title }}
          </p>
          <p class="mt-1 text-sm font-semibold text-white/80">
            Image {{ currentIndex + 1 }} of {{ images.length }}
          </p>
        </div>

        <button
          type="button"
          @click="close"
          class="inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-white/10 bg-white/5 text-white transition hover:bg-white/10"
        >
          <X class="h-5 w-5" />
        </button>
      </div>

      <div class="relative flex flex-1 items-center justify-center px-4 pb-4 md:px-8">
        <button
          v-if="images.length > 1"
          type="button"
          @click="previousImage"
          @click.stop
          class="absolute left-4 z-10 inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-white/10 bg-white/10 text-white transition hover:bg-white/20 md:left-8"
        >
          <ChevronLeft class="h-5 w-5" />
        </button>

        <img
          :src="currentImage.url"
          :alt="currentImage.caption || title"
          @click.stop
          class="max-h-full max-w-full rounded-[2rem] object-contain shadow-[0_35px_120px_rgba(15,23,42,0.55)]"
        />

        <button
          v-if="images.length > 1"
          type="button"
          @click="nextImage"
          @click.stop
          class="absolute right-4 z-10 inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-white/10 bg-white/10 text-white transition hover:bg-white/20 md:right-8"
        >
          <ChevronRight class="h-5 w-5" />
        </button>
      </div>

      <div
        v-if="images.length > 1"
        class="flex gap-3 overflow-x-auto px-4 pb-6 md:px-8"
        @click.stop
      >
        <button
          v-for="(image, index) in images"
          :key="image.id ?? `${image.url}-${index}`"
          type="button"
          @click="selectImage(index)"
          class="shrink-0 overflow-hidden rounded-2xl border-2 transition"
          :class="index === currentIndex ? 'border-white' : 'border-transparent opacity-70 hover:opacity-100'"
        >
          <img
            :src="image.thumb_url || image.url"
            :alt="image.caption || `${title} ${index + 1}`"
            class="h-20 w-24 object-cover"
          />
        </button>
      </div>
    </div>
  </transition>
</template>

<style scoped>
.gallery-fade-enter-active,
.gallery-fade-leave-active {
  transition: opacity 0.2s ease;
}

.gallery-fade-enter-from,
.gallery-fade-leave-to {
  opacity: 0;
}
</style>
