<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import InputError from '@/Components/InputError.vue'
import { ArrowLeft, KeyRound, Loader2 } from 'lucide-vue-next'

const props = defineProps({
  plans: { type: Array, default: () => [] },
  termMonths: { type: Number, default: 12 },
  currencies: { type: Array, default: () => ['NGN', 'USD'] },
  defaultCurrency: { type: String, default: 'NGN' },
})

const form = useForm({
  property_name: '',
  rooms: 20,
  currency: props.defaultCurrency === 'USD' ? 'USD' : 'NGN',
  term_months: props.termMonths,
  customer_name: '',
  customer_email: '',
  payment_reference: '',
  notes: '',
})

function format(amount, code = form.currency) {
  return new Intl.NumberFormat(code === 'USD' ? 'en-US' : 'en-NG', {
    style: 'currency',
    currency: code,
    maximumFractionDigits: 0,
  }).format(Number(amount ?? 0))
}

const band = computed(() => {
  const rooms = Number(form.rooms) || 0
  if (rooms < 1) return null

  return props.plans.find((plan) => {
    const max = plan.max_rooms === null ? null : Number(plan.max_rooms)
    return rooms >= Number(plan.min_rooms) && (max === null || rooms <= max)
  }) ?? null
})

const totals = computed(() => {
  if (!band.value) return null

  const monthly = form.currency === 'USD' ? band.value.monthly_usd : band.value.monthly_naira

  return { monthly, total: monthly * Number(form.term_months || 0) }
})

function submit() {
  form.post(route('admin.licences.store'), { preserveScroll: true })
}
</script>

<template>
  <ManagerLayout>
    <Head title="Issue a licence" />

    <div class="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
      <Link :href="route('admin.licences.index')" class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 hover:text-slate-900">
        <ArrowLeft class="h-4 w-4" />
        All licences
      </Link>

      <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center gap-3">
          <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900 text-white">
            <KeyRound class="h-5 w-5" />
          </span>
          <div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900">Issue a licence</h1>
            <p class="text-sm text-slate-500">For a bank transfer, a reseller, or a corrected sale. The key is shown once.</p>
          </div>
        </div>

        <form class="mt-6 space-y-6" @submit.prevent="submit">
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
              <label class="text-sm font-bold text-slate-700" for="property_name">Property name</label>
              <input
                id="property_name"
                v-model="form.property_name"
                type="text"
                required
                maxlength="255"
                class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10"
              />
              <InputError :message="form.errors.property_name" />
            </div>

            <div>
              <label class="text-sm font-bold text-slate-700" for="rooms">Rooms</label>
              <input
                id="rooms"
                v-model.number="form.rooms"
                type="number"
                min="1"
                max="5000"
                required
                class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10"
              />
              <InputError :message="form.errors.rooms" />
            </div>

            <div>
              <label class="text-sm font-bold text-slate-700" for="currency">Currency</label>
              <select
                id="currency"
                v-model="form.currency"
                class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm"
              >
                <option v-for="code in currencies" :key="code" :value="code">{{ code }}</option>
              </select>
              <InputError :message="form.errors.currency" />
            </div>

            <div>
              <label class="text-sm font-bold text-slate-700" for="term_months">Term (months)</label>
              <input
                id="term_months"
                v-model.number="form.term_months"
                type="number"
                min="1"
                max="60"
                required
                class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10"
              />
              <InputError :message="form.errors.term_months" />
            </div>

            <div>
              <label class="text-sm font-bold text-slate-700" for="payment_reference">Payment reference</label>
              <input
                id="payment_reference"
                v-model="form.payment_reference"
                type="text"
                maxlength="255"
                placeholder="Bank transfer / gateway reference"
                class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10"
              />
              <InputError :message="form.errors.payment_reference" />
            </div>

            <div>
              <label class="text-sm font-bold text-slate-700" for="customer_name">Customer name</label>
              <input
                id="customer_name"
                v-model="form.customer_name"
                type="text"
                maxlength="255"
                class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10"
              />
              <InputError :message="form.errors.customer_name" />
            </div>

            <div>
              <label class="text-sm font-bold text-slate-700" for="customer_email">Customer email</label>
              <input
                id="customer_email"
                v-model="form.customer_email"
                type="email"
                maxlength="255"
                class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10"
              />
              <InputError :message="form.errors.customer_email" />
            </div>

            <div class="sm:col-span-2">
              <label class="text-sm font-bold text-slate-700" for="notes">Internal notes</label>
              <textarea
                id="notes"
                v-model="form.notes"
                rows="3"
                maxlength="2000"
                class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10"
              />
              <InputError :message="form.errors.notes" />
            </div>
          </div>

          <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
            <div v-if="band" class="flex flex-wrap items-center justify-between gap-4">
              <div>
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Tier</p>
                <p class="mt-1 text-lg font-black text-slate-900">{{ band.name }}</p>
                <p class="text-xs text-slate-500">{{ band.band }} · {{ format(totals.monthly) }} per month</p>
              </div>
              <div class="text-right">
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Price for the term</p>
                <p class="mt-1 text-2xl font-black text-slate-900">{{ format(totals.total) }}</p>
              </div>
            </div>
            <p v-else class="text-sm font-semibold text-amber-700">
              No tier covers that room count. Adjust the room number or update the pricing bands.
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <button
              type="submit"
              :disabled="form.processing || !band"
              class="inline-flex items-center gap-2 rounded-2xl bg-slate-900 px-6 py-3 text-sm font-bold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
              {{ form.processing ? 'Issuing…' : 'Issue licence' }}
            </button>
            <Link :href="route('admin.licences.index')" class="text-sm font-bold text-slate-500 hover:text-slate-900">
              Cancel
            </Link>
          </div>
        </form>
      </section>
    </div>
  </ManagerLayout>
</template>
