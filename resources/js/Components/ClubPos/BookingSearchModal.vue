<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  docket: { type: Object, required: true },
})

const emit = defineEmits(['close', 'attached'])

const query = ref('')
const results = ref([])
const loading = ref(false)
const submitting = ref(false)
const error = ref('')

let timer = null
let abort = null

async function search() {
  loading.value = true
  error.value = ''
  if (abort) abort.abort()
  abort = new AbortController()

  try {
    const url = route('club.pos.bookings.search', { q: query.value })
    const response = await fetch(url, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      signal: abort.signal,
    })
    if (!response.ok) throw new Error('Search failed')
    const data = await response.json()
    results.value = data.results || []
  } catch (e) {
    if (e.name !== 'AbortError') {
      error.value = 'Could not load bookings.'
      results.value = []
    }
  } finally {
    loading.value = false
  }
}

function onInput() {
  clearTimeout(timer)
  timer = setTimeout(search, 250)
}

function select(booking) {
  if (submitting.value) return
  submitting.value = true
  error.value = ''

  router.post(
    route('club.pos.dockets.booking', { docket: props.docket.id }),
    { booking_id: booking.id },
    {
      preserveScroll: true,
      onSuccess: () => emit('attached'),
      onError: (errors) => {
        error.value = errors.booking || errors.booking_id || 'Could not attach the booking.'
        submitting.value = false
      },
      onFinish: () => { submitting.value = false },
    },
  )
}

onMounted(search)
onBeforeUnmount(() => {
  clearTimeout(timer)
  if (abort) abort.abort()
})
</script>

<template>
  <div class="flex flex-col h-full max-h-[80vh]">
    <div class="flex items-center justify-between mb-4 shrink-0">
      <h3 class="text-xl font-bold text-white">Charge to Room</h3>
      <button @click="emit('close')" :disabled="submitting"
        class="text-slate-400 hover:text-white transition-colors p-1 disabled:opacity-50" aria-label="Close">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <input v-model="query" @input="onInput" type="text" autofocus
      placeholder="Search room or guest name"
      class="w-full bg-slate-700 text-white rounded-xl px-4 py-3 border border-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 min-h-[48px] shrink-0" />

    <div class="flex-1 overflow-y-auto mt-3 space-y-2 min-h-0" style="-webkit-overflow-scrolling: touch">
      <p v-if="loading" class="text-center text-slate-500 py-8 text-sm">Searching...</p>
      <p v-else-if="!results.length" class="text-center text-slate-500 py-8 text-sm">
        No in-house bookings found.
      </p>
      <button v-for="booking in results" :key="booking.id" @click="select(booking)" :disabled="submitting"
        class="w-full text-left px-4 py-3 rounded-xl bg-slate-800 border border-slate-700 hover:border-indigo-500 hover:bg-slate-700/60 transition-all active:scale-[0.99] disabled:opacity-50">
        <div class="flex items-center justify-between">
          <span class="font-semibold text-white">{{ booking.room_label || 'Unassigned room' }}</span>
          <span class="text-xs text-slate-400 capitalize">{{ (booking.status || '').replace('_', ' ') }}</span>
        </div>
        <div class="text-sm text-slate-400 mt-0.5">
          {{ booking.guest_name || 'Walk-in' }} · {{ booking.booking_code }}
        </div>
      </button>
    </div>

    <p v-if="error" class="text-sm text-red-400 text-center mt-3 shrink-0">{{ error }}</p>
  </div>
</template>
