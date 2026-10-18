<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  docket: { type: Object, default: null },
})

const emit = defineEmits(['pay', 'void', 'close', 'attach-room'])

const loading = ref(false)
const voiding = ref(false)
const error = ref(null)

const subtotal = computed(() => {
  if (!props.docket?.items) return 0
  return props.docket.items.reduce((sum, item) => sum + Number(item.subtotal || 0), 0)
})

const total = computed(() => Number(props.docket?.total || subtotal.value))

const roomLabel = computed(() => {
  const room = props.docket?.room
  if (!room) return null
  return room.display_name || room.name || room.code || null
})

const guestName = computed(() => props.docket?.booking?.guest_name || null)

function removeItem(item) {
  if (loading.value || voiding.value) return
  error.value = null
  loading.value = true
  router.delete(route('club.pos.dockets.items.destroy', { docket: props.docket.id, item: item.id }), {
    preserveScroll: true,
    onError: (errors) => {
      error.value = typeof errors.docket === 'string' && errors.docket
        ? errors.docket
        : 'Could not remove the item.'
    },
    onFinish: () => { loading.value = false },
  })
}

function confirmVoid() {
  if (voiding.value || loading.value) return
  if (!confirm('Void this entire docket?')) return
  const reason = prompt('Void reason:')
  if (!reason) return
  error.value = null
  voiding.value = true
  router.post(route('club.pos.dockets.void', { docket: props.docket.id }), {
    reason,
  }, {
    onError: (errors) => {
      error.value = typeof errors.docket === 'string' && errors.docket
        ? errors.docket
        : 'Could not void the docket.'
    },
    onFinish: () => { voiding.value = false },
  })
}

watch(() => props.docket?.id, () => { error.value = null })
</script>

<template>
  <div v-if="docket" class="flex flex-col h-full">
    <div class="flex items-center justify-between mb-3 shrink-0">
      <div>
        <h3 class="text-lg font-bold text-white">Docket #{{ docket.docket_number }}</h3>
        <p v-if="docket.table_identifier" class="text-sm text-slate-400">Table: {{ docket.table_identifier }}</p>
        <p v-if="docket.customer_name" class="text-sm text-slate-400">{{ docket.customer_name }}</p>
      </div>
      <div class="text-right text-xs text-slate-500">
        {{ new Date(docket.opened_at).toLocaleTimeString('en-KE', { hour: '2-digit', minute: '2-digit' }) }}
      </div>
    </div>

    <div class="flex-1 overflow-y-auto space-y-1.5 min-h-0" style="-webkit-overflow-scrolling: touch">
      <div v-for="item in docket.items" :key="item.id"
        class="flex items-center justify-between px-3 py-2 rounded-lg bg-slate-800/60 group hover:bg-slate-800 transition-colors">
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-white truncate">{{ item.item_name }}</p>
          <p class="text-xs text-slate-400">x{{ item.quantity }} @ Ksh {{ Number(item.unit_price).toLocaleString() }}</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <span class="text-sm font-semibold text-emerald-400 tabular-nums">Ksh {{ Number(item.subtotal).toLocaleString() }}</span>
          <button @click="removeItem(item)" :disabled="loading" aria-label="Remove item"
            class="opacity-0 group-focus-within:opacity-100 group-hover:opacity-100 p-1 rounded hover:bg-red-500/20 text-red-400 transition-all disabled:opacity-30">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
          </button>
        </div>
      </div>

      <p v-if="!docket.items?.length" class="text-center text-slate-500 py-8 text-sm">
        No items yet. Tap a product to add.
      </p>
    </div>

    <div class="border-t border-slate-700 pt-3 mt-3 space-y-2 shrink-0">
      <div class="flex justify-between text-sm text-slate-400">
        <span>Subtotal</span>
        <span class="tabular-nums">Ksh {{ Number(subtotal).toLocaleString() }}</span>
      </div>
      <div class="flex justify-between text-lg font-bold text-white">
        <span>Total</span>
        <span class="tabular-nums">Ksh {{ Number(total).toLocaleString() }}</span>
      </div>

      <button @click="emit('attach-room')"
        class="w-full py-2.5 rounded-xl border border-slate-600 text-sm font-medium transition-all active:scale-[0.99]"
        :class="roomLabel ? 'bg-indigo-600/20 border-indigo-500/40 text-indigo-200 hover:bg-indigo-600/30' : 'bg-slate-800 text-slate-300 hover:bg-slate-700'">
        <template v-if="roomLabel">
          Room {{ roomLabel }}<template v-if="guestName"> · {{ guestName }}</template>
          <span class="text-indigo-300/70">— change</span>
        </template>
        <template v-else>Charge to Room</template>
      </button>

      <p v-if="error" role="alert" class="text-sm text-red-400">
        {{ error }}
      </p>

      <div class="flex gap-2 pt-1">
        <button @click="emit('pay', docket)" :disabled="!docket.items?.length || loading || voiding"
          class="flex-1 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-lg transition-all active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed">
          Pay
        </button>
        <button @click="confirmVoid" :disabled="voiding || loading" aria-label="Void docket"
          class="px-4 py-3 rounded-xl bg-slate-700 text-slate-400 hover:bg-red-600/20 hover:text-red-400 transition-all active:scale-95 disabled:opacity-40">
          <svg v-if="voiding" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
          </svg>
          <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
        </button>
      </div>
    </div>
  </div>

  <div v-else class="flex items-center justify-center h-full text-slate-500">
    <div class="text-center">
      <svg class="w-12 h-12 mx-auto mb-3 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
      </svg>
      <p class="text-sm">Select or open a docket</p>
    </div>
  </div>
</template>
