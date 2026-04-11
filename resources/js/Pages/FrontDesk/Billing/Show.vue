<script setup>
import FrontDeskLayout from '@/Layouts/Staff/FrontDeskLayout.vue'
import InputError from '@/Components/InputError.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import { AlertCircle, ArrowLeft, CheckCircle2, CreditCard, FileText, PlusCircle } from 'lucide-vue-next'

const props = defineProps({
  booking: { type: Object, required: true },
  billing: { type: Object, required: true },
})

const defaultRoomId = computed(() => {
  if (props.billing.has_multiple_rooms) return ''
  return props.billing.assigned_room_options?.[0]?.id ?? props.booking.room_id ?? ''
})

const chargeForm = useForm({
  room_id: defaultRoomId.value,
  description: '',
  amount: '',
})

const paymentForm = useForm({
  room_id: defaultRoomId.value,
  amount: '',
  method: 'Cash',
  reference: '',
  notes: '',
})

function submitCharge() {
  chargeForm.post(route('frontdesk.billing.charge', props.booking.id), {
    preserveScroll: true,
    onSuccess: () => {
      chargeForm.reset('description', 'amount')
      chargeForm.room_id = defaultRoomId.value
    },
  })
}

function submitPayment() {
  paymentForm.post(route('frontdesk.billing.pay', props.booking.id), {
    preserveScroll: true,
    onSuccess: () => {
      paymentForm.reset('amount', 'reference', 'notes')
      paymentForm.room_id = defaultRoomId.value
      paymentForm.method = 'Cash'
    },
  })
}

function formatDate(value) {
  if (!value) return 'Not recorded'

  return new Date(value).toLocaleString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<template>
  <FrontDeskLayout>
    <Head :title="`Billing - ${booking.guest_name}`" />

    <div class="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-center gap-4">
          <Link :href="route('frontdesk.bookings.show', booking.id)" class="rounded-2xl p-2 transition hover:bg-slate-100">
            <ArrowLeft class="h-6 w-6 text-slate-400" />
          </Link>
          <div>
            <h1 class="text-3xl font-black tracking-tight text-slate-900">Guest ledger</h1>
            <p class="mt-2 text-sm font-medium text-slate-500">Account statement for {{ booking.guest_name }}</p>
          </div>
        </div>

        <div
          class="rounded-[2rem] border px-6 py-4"
          :class="billing.outstanding > 0 ? 'border-rose-100 bg-rose-50' : 'border-emerald-100 bg-emerald-50'"
        >
          <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Outstanding</p>
          <p class="mt-2 text-3xl font-black" :class="billing.outstanding > 0 ? 'text-rose-600' : 'text-emerald-600'">
            ₦{{ Number(billing.outstanding).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
          </p>
        </div>
      </div>

      <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="space-y-8">
          <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-5">
              <div class="flex items-center gap-3">
                <div class="rounded-2xl bg-slate-100 p-2 text-slate-600"><FileText class="h-5 w-5" /></div>
                <h2 class="text-xl font-black text-slate-900">Charges</h2>
              </div>
            </div>

            <div class="divide-y divide-slate-100">
              <div
                v-for="charge in billing.charges"
                :key="charge.id"
                class="grid gap-3 px-6 py-5 md:grid-cols-[1fr_auto]"
              >
                <div>
                  <p class="text-sm font-bold text-slate-900">{{ charge.description }}</p>
                  <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-400">{{ charge.room_label }}</p>
                  <p class="mt-1 text-sm text-slate-500">{{ formatDate(charge.created_at) }}</p>
                </div>
                <div class="text-right text-sm font-black text-slate-900">₦{{ Number(charge.amount).toLocaleString() }}</div>
              </div>
              <div v-if="billing.charges.length === 0" class="px-6 py-12 text-center text-sm text-slate-500">
                No charges recorded yet.
              </div>
            </div>
          </section>

          <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-5">
              <div class="flex items-center gap-3">
                <div class="rounded-2xl bg-emerald-50 p-2 text-emerald-600"><CheckCircle2 class="h-5 w-5" /></div>
                <h2 class="text-xl font-black text-slate-900">Payments</h2>
              </div>
            </div>

            <div class="divide-y divide-slate-100">
              <div
                v-for="payment in billing.payments"
                :key="payment.id"
                class="grid gap-3 px-6 py-5 md:grid-cols-[1fr_auto]"
              >
                <div>
                  <p class="text-sm font-bold text-slate-900">{{ payment.method }}</p>
                  <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-400">{{ payment.room_label }}</p>
                  <p class="mt-1 text-sm text-slate-500">{{ payment.notes || payment.reference || 'Payment recorded' }}</p>
                  <p class="mt-1 text-sm text-slate-500">{{ formatDate(payment.created_at) }}</p>
                </div>
                <div class="text-right text-sm font-black text-emerald-600">₦{{ Number(payment.amount).toLocaleString() }}</div>
              </div>
              <div v-if="billing.payments.length === 0" class="px-6 py-12 text-center text-sm text-slate-500">
                No payments recorded yet.
              </div>
            </div>
          </section>
        </div>

        <div class="space-y-6">
          <section id="charge-form" class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center gap-3">
              <div class="rounded-2xl bg-indigo-50 p-2 text-indigo-600"><PlusCircle class="h-5 w-5" /></div>
              <h2 class="text-xl font-black text-slate-900">Add charge</h2>
            </div>

            <form @submit.prevent="submitCharge" class="mt-6 space-y-4">
              <label class="space-y-2">
                <span class="text-sm font-bold text-slate-700">Room</span>
                <select
                  v-model="chargeForm.room_id"
                  class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
                >
                  <option value="" disabled>Select room</option>
                  <option v-for="room in billing.assigned_room_options" :key="room.id" :value="room.id">
                    {{ room.label }}
                  </option>
                </select>
                <InputError :message="chargeForm.errors.room_id" />
              </label>

              <label class="space-y-2">
                <span class="text-sm font-bold text-slate-700">Description</span>
                <input
                  v-model="chargeForm.description"
                  type="text"
                  class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
                />
                <InputError :message="chargeForm.errors.description" />
              </label>

              <label class="space-y-2">
                <span class="text-sm font-bold text-slate-700">Amount</span>
                <input
                  v-model="chargeForm.amount"
                  type="number"
                  min="0.01"
                  step="0.01"
                  class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
                />
                <InputError :message="chargeForm.errors.amount" />
              </label>

              <button
                type="submit"
                :disabled="chargeForm.processing"
                class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800 disabled:opacity-60"
              >
                {{ chargeForm.processing ? 'Posting charge...' : 'Post charge' }}
              </button>
            </form>
          </section>

          <section id="payment-form" class="rounded-[2rem] bg-[linear-gradient(145deg,_#0f172a,_#1e293b)] p-6 text-white shadow-sm">
            <div class="flex items-center gap-3">
              <CreditCard class="h-5 w-5 text-indigo-300" />
              <h2 class="text-xl font-black">Record payment</h2>
            </div>

            <form @submit.prevent="submitPayment" class="mt-6 space-y-4">
              <label class="space-y-2">
                <span class="text-sm font-bold text-slate-200">Room</span>
                <select
                  v-model="paymentForm.room_id"
                  class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200/20"
                >
                  <option value="" disabled class="text-slate-900">Select room</option>
                  <option v-for="room in billing.assigned_room_options" :key="room.id" :value="room.id" class="text-slate-900">
                    {{ room.label }}
                  </option>
                </select>
                <InputError :message="paymentForm.errors.room_id" />
              </label>

              <label class="space-y-2">
                <span class="text-sm font-bold text-slate-200">Amount</span>
                <input
                  v-model="paymentForm.amount"
                  type="number"
                  min="0.01"
                  step="0.01"
                  class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200/20"
                />
                <InputError :message="paymentForm.errors.amount" />
              </label>

              <label class="space-y-2">
                <span class="text-sm font-bold text-slate-200">Method</span>
                <select
                  v-model="paymentForm.method"
                  class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200/20"
                >
                  <option class="text-slate-900">Cash</option>
                  <option class="text-slate-900">Card</option>
                  <option class="text-slate-900">Online Transfer</option>
                  <option class="text-slate-900">POS</option>
                </select>
                <InputError :message="paymentForm.errors.method" />
              </label>

              <label class="space-y-2">
                <span class="text-sm font-bold text-slate-200">Reference</span>
                <input
                  v-model="paymentForm.reference"
                  type="text"
                  class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200/20"
                />
                <InputError :message="paymentForm.errors.reference" />
              </label>

              <label class="space-y-2">
                <span class="text-sm font-bold text-slate-200">Notes</span>
                <input
                  v-model="paymentForm.notes"
                  type="text"
                  class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200/20"
                />
                <InputError :message="paymentForm.errors.notes" />
              </label>

              <button
                type="submit"
                :disabled="paymentForm.processing"
                class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-indigo-500 disabled:opacity-60"
              >
                {{ paymentForm.processing ? 'Recording payment...' : 'Record payment' }}
              </button>
            </form>
          </section>

          <section class="rounded-[1.5rem] border border-amber-200 bg-amber-50 px-5 py-5 text-sm text-amber-900">
            <div class="flex items-start gap-3">
              <AlertCircle class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
              <p>Charges and payments are now posted against both the booking and the specific room to keep shared bookings unambiguous.</p>
            </div>
          </section>
        </div>
      </div>
    </div>
  </FrontDeskLayout>
</template>
