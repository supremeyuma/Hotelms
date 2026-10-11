<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  session: { type: Object, required: true },
})

const emit = defineEmits(['close', 'closed'])

const cashDeclared = ref('')
const submitting = ref(false)
const error = ref('')

const expectedCash = computed(() => {
  return Number(props.session.cash_start || 0) + Number(props.session.total_sales || 0)
})

const variance = computed(() => {
  if (cashDeclared.value === '') return null
  return Number(cashDeclared.value) - expectedCash.value
})

function format(value) {
  return Number(value || 0).toLocaleString()
}

function submit() {
  if (submitting.value) return

  if (cashDeclared.value === '' || Number(cashDeclared.value) < 0) {
    error.value = 'Enter the counted cash before closing the shift.'
    return
  }

  submitting.value = true
  error.value = ''

  router.post(
    route('club.pos.shifts.close', { session: props.session.id }),
    { cash_declared: Number(cashDeclared.value) },
    {
      preserveScroll: true,
      onSuccess: () => emit('closed'),
      onError: (errors) => {
        error.value = errors.shift || errors.cash_declared || 'Could not close the shift.'
      },
      onFinish: () => { submitting.value = false },
    },
  )
}
</script>

<template>
  <div class="flex flex-col gap-5 p-6">
    <div class="text-center">
      <h2 class="text-xl font-bold text-white">Close Shift</h2>
      <p class="text-sm text-slate-400 mt-1">Count the drawer and close the current shift.</p>
    </div>

    <div class="rounded-xl bg-slate-700/50 border border-slate-600 divide-y divide-slate-600 text-sm">
      <div class="flex items-center justify-between px-4 py-2.5">
        <span class="text-slate-400">Shift</span>
        <span class="font-mono text-slate-200">#{{ String(session.id).slice(0, 8) }}</span>
      </div>
      <div class="flex items-center justify-between px-4 py-2.5">
        <span class="text-slate-400">Opening float</span>
        <span class="font-mono text-slate-200">Ksh {{ format(session.cash_start) }}</span>
      </div>
      <div class="flex items-center justify-between px-4 py-2.5">
        <span class="text-slate-400">Sales</span>
        <span class="font-mono text-slate-200">Ksh {{ format(session.total_sales) }}</span>
      </div>
      <div class="flex items-center justify-between px-4 py-2.5">
        <span class="text-slate-400">Dockets</span>
        <span class="font-mono text-slate-200">{{ session.docket_count || 0 }}</span>
      </div>
      <div class="flex items-center justify-between px-4 py-2.5">
        <span class="font-semibold text-slate-300">Expected cash</span>
        <span class="font-mono font-bold text-white">Ksh {{ format(expectedCash) }}</span>
      </div>
    </div>

    <div class="space-y-2">
      <label class="text-xs font-bold uppercase tracking-widest text-slate-400 ml-1">Counted cash</label>
      <input
        v-model="cashDeclared"
        type="number"
        min="0"
        step="0.01"
        inputmode="decimal"
        class="w-full rounded-xl bg-slate-700 border border-slate-600 px-4 py-3 text-white font-mono focus:border-indigo-500 focus:ring-0"
        placeholder="0"
        :disabled="submitting"
        @keyup.enter="submit"
      />
      <p v-if="variance !== null" class="text-sm ml-1" :class="variance === 0 ? 'text-slate-400' : 'text-amber-400'">
        Variance: <span class="font-mono font-bold">Ksh {{ format(variance) }}</span>
      </p>
    </div>

    <p v-if="error" class="text-sm text-red-400 text-center">{{ error }}</p>

    <div class="flex gap-3">
      <button
        type="button"
        @click="emit('close')"
        :disabled="submitting"
        class="flex-1 py-3 rounded-xl bg-slate-700 text-slate-300 font-medium hover:bg-slate-600 transition-all active:scale-95 disabled:opacity-50"
      >
        Cancel
      </button>
      <button
        type="button"
        @click="submit"
        :disabled="submitting"
        class="flex-1 py-3 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-500 transition-all active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
      >
        {{ submitting ? 'Closing...' : 'Close & Reconcile' }}
      </button>
    </div>
  </div>
</template>
