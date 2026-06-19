<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  docket: { type: Object, required: true },
})
const emit = defineEmits(['close', 'done'])

const loading = ref(false)
const payments = ref([
  { method: 'cash', amount: '', reference: '', change_given: '' },
])

const remainingTotal = computed(() => {
  const paid = payments.value.reduce((sum, p) => sum + (Number(p.amount) || 0), 0)
  return Math.max(0, Number(props.docket?.total || 0) - paid)
})

const overPaid = computed(() => {
  const paid = payments.value.reduce((sum, p) => sum + (Number(p.amount) || 0), 0)
  return Math.max(0, paid - Number(props.docket?.total || 0))
})

function addPayment() {
  if (payments.value.length >= 4) return
  payments.value.push({ method: 'cash', amount: '', reference: '', change_given: '' })
}

function removePayment(index) {
  if (payments.value.length <= 1) return
  payments.value.splice(index, 1)
}

function submit() {
  const clean = payments.value.map(p => ({
    method: p.method,
    amount: Number(p.amount),
    reference: p.reference || null,
    change_given: p.method === 'cash' ? (Number(p.change_given) || 0) : null,
  }))

  const totalPaid = clean.reduce((s, p) => s + p.amount, 0)
  if (Math.abs(totalPaid - Number(props.docket.total)) > 0.01) {
    alert('Payment total must match the docket total.')
    return
  }

  loading.value = true
  router.post(route('club.pos.dockets.pay', { docket: props.docket.id }), {
    payments: clean,
  }, {
    preserveScroll: true,
    onSuccess: () => { emit('done') },
    onFinish: () => { loading.value = false },
  })
}

const methodColors = {
  cash: 'bg-emerald-600',
  card: 'bg-blue-600',
  room_charge: 'bg-purple-600',
  mobile_money: 'bg-amber-600',
  voucher: 'bg-pink-600',
}
</script>

<template>
  <div class="flex flex-col h-full">
    <div class="flex items-center justify-between mb-4 shrink-0">
      <h3 class="text-xl font-bold text-white">Payment</h3>
      <p class="text-lg font-bold text-emerald-400 tabular-nums">Ksh {{ Number(docket.total).toLocaleString() }}</p>
    </div>

    <div class="flex-1 overflow-y-auto space-y-3 min-h-0" style="-webkit-overflow-scrolling: touch">
      <div v-for="(payment, i) in payments" :key="i"
        class="p-4 rounded-xl bg-slate-800 border border-slate-700 space-y-3">
        <div class="flex items-center justify-between">
          <select v-model="payment.method"
            class="bg-slate-700 text-white rounded-lg px-3 py-2 text-sm font-medium border border-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 min-h-[44px]">
            <option value="cash">Cash</option>
            <option value="card">Card</option>
            <option value="room_charge">Room Charge</option>
            <option value="mobile_money">Mobile Money</option>
            <option value="voucher">Voucher</option>
          </select>
          <button v-if="payments.length > 1" @click="removePayment(i)"
            class="text-slate-500 hover:text-red-400 p-1 transition-colors">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div>
          <label class="text-xs text-slate-400 mb-1 block">Amount</label>
          <input v-model="payment.amount" type="number" step="0.01" min="0" placeholder="0.00"
            class="w-full bg-slate-700 text-white rounded-lg px-3 py-2.5 text-lg font-bold border border-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 min-h-[44px]" />
        </div>

        <div v-if="payment.method === 'cash'">
          <label class="text-xs text-slate-400 mb-1 block">Change Given</label>
          <input v-model="payment.change_given" type="number" step="0.01" min="0" placeholder="0.00"
            class="w-full bg-slate-700 text-white rounded-lg px-3 py-2.5 text-sm border border-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 min-h-[44px]" />
        </div>

        <div v-if="payment.method === 'room_charge' || payment.method === 'card' || payment.method === 'mobile_money'">
          <label class="text-xs text-slate-400 mb-1 block">Reference</label>
          <input v-model="payment.reference" type="text" placeholder="Reference number"
            class="w-full bg-slate-700 text-white rounded-lg px-3 py-2.5 text-sm border border-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 min-h-[44px]" />
        </div>
      </div>

      <button @click="addPayment" v-if="payments.length < 4"
        class="w-full py-3 rounded-xl border-2 border-dashed border-slate-600 text-slate-400 hover:border-slate-500 hover:text-slate-300 transition-all text-sm font-medium active:scale-[0.99] min-h-[44px]">
        + Split Payment
      </button>
    </div>

    <div class="border-t border-slate-700 pt-3 mt-3 space-y-2 shrink-0">
      <div class="flex justify-between text-sm">
        <span class="text-slate-400">Remaining</span>
        <span class="font-bold tabular-nums" :class="remainingTotal <= 0 ? 'text-emerald-400' : 'text-white'">
          Ksh {{ Number(remainingTotal).toLocaleString() }}
        </span>
      </div>
      <div v-if="overPaid > 0" class="flex justify-between text-sm">
        <span class="text-amber-400">Overpayment</span>
        <span class="font-bold text-amber-400 tabular-nums">Ksh {{ Number(overPaid).toLocaleString() }}</span>
      </div>

      <div class="flex gap-2 pt-1">
        <button @click="$emit('close')"
          class="flex-1 py-3 rounded-xl bg-slate-700 text-slate-300 font-medium hover:bg-slate-600 transition-all active:scale-95 min-h-[48px]">
          Back
        </button>
        <button @click="submit" :disabled="loading || remainingTotal > 0"
          class="flex-[2] py-3 rounded-xl bg-emerald-600 text-white font-bold text-lg hover:bg-emerald-500 transition-all active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed min-h-[48px]">
          {{ loading ? 'Processing...' : `Complete Payment` }}
        </button>
      </div>
    </div>
  </div>
</template>
