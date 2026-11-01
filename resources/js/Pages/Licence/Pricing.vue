<script setup>
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import LicenceLayout from '@/Layouts/LicenceLayout.vue'
import { ArrowRight, Building2, CheckCircle2, HardDrive, ShieldCheck, WifiOff } from 'lucide-vue-next'

const props = defineProps({
  product: { type: String, default: 'MooreLife' },
  plans: { type: Array, default: () => [] },
  checkoutEnabled: { type: Boolean, default: false },
  termMonths: { type: Number, default: 12 },
  defaultCurrency: { type: String, default: 'NGN' },
})

const currency = ref(props.defaultCurrency === 'USD' ? 'USD' : 'NGN')

function format(amount, code = currency.value) {
  return new Intl.NumberFormat(code === 'USD' ? 'en-US' : 'en-NG', {
    style: 'currency',
    currency: code,
    maximumFractionDigits: 0,
  }).format(Number(amount ?? 0))
}

const monthly = (plan) => (currency.value === 'USD' ? plan.monthly_usd : plan.monthly_naira)
const termTotal = (plan) => (currency.value === 'USD' ? plan.term_usd : plan.term_naira)

const benefits = [
  { icon: HardDrive, title: 'One server, every device', text: 'The licence covers the machine that runs your server and every terminal and staff client paired with it.' },
  { icon: WifiOff, title: 'Works offline', text: 'Activation needs the internet once. After that the app verifies your licence without a connection.' },
  { icon: ShieldCheck, title: 'Nothing is deleted', text: 'If a licence lapses the app stops; your data stays intact and is available again the moment you activate.' },
]

const cheapest = computed(() => {
  const totals = props.plans.map((plan) => monthly(plan))
  return totals.length ? Math.min(...totals) : 0
})
</script>

<template>
  <LicenceLayout :product="product">
    <section class="mx-auto max-w-6xl px-4 pt-16 pb-10 sm:px-6 lg:px-8">
      <div class="max-w-3xl">
        <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] font-black uppercase tracking-[0.24em] text-slate-500">
          <Building2 class="h-3.5 w-3.5" />
          Priced by property size
        </span>
        <h1 class="mt-5 text-4xl font-black tracking-tight text-slate-900 sm:text-5xl">
          One annual licence for your hotel
        </h1>
        <p class="mt-4 max-w-2xl text-base leading-7 text-slate-600">
          Choose the band that matches the number of rooms you manage. The price is quoted per
          month and charged once for {{ termMonths }} months; the term starts the day you activate,
          not the day you buy.
        </p>
      </div>

      <div class="mt-8 inline-flex rounded-xl border border-slate-200 bg-white p-1">
        <button
          v-for="option in ['NGN', 'USD']"
          :key="option"
          type="button"
          class="rounded-lg px-4 py-2 text-sm font-bold transition"
          :class="currency === option ? 'bg-slate-900 text-white' : 'text-slate-600 hover:text-slate-900'"
          @click="currency = option"
        >
          {{ option === 'NGN' ? 'Naira (₦)' : 'US Dollar ($)' }}
        </button>
      </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 pb-16 sm:px-6 lg:px-8">
      <div class="grid gap-6 lg:grid-cols-3">
        <article
          v-for="(plan, index) in plans"
          :key="plan.id"
          class="flex flex-col rounded-3xl border bg-white p-7 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg"
          :class="index === 1 ? 'border-slate-900 ring-2 ring-slate-900/10' : 'border-slate-200'"
        >
          <div class="flex items-center justify-between">
            <h2 class="text-xl font-black tracking-tight text-slate-900">{{ plan.name }}</h2>
            <span
              v-if="index === 1"
              class="rounded-full bg-slate-900 px-3 py-1 text-[10px] font-black uppercase tracking-[0.2em] text-white"
            >
              Most chosen
            </span>
          </div>

          <p class="mt-1 text-sm font-semibold text-slate-500">{{ plan.band }}</p>

          <div class="mt-6">
            <p class="flex items-baseline gap-1">
              <span class="text-4xl font-black tracking-tight text-slate-900">{{ format(monthly(plan)) }}</span>
              <span class="text-sm font-bold text-slate-500">/ month</span>
            </p>
            <p class="mt-2 text-sm text-slate-600">
              {{ format(termTotal(plan)) }} billed once for {{ termMonths }} months
            </p>
          </div>

          <ul class="mt-6 space-y-3 text-sm text-slate-600">
            <li class="flex gap-2">
              <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" />
              Server, terminals and staff clients included
            </li>
            <li class="flex gap-2">
              <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" />
              Updates included while the licence runs
            </li>
            <li class="flex gap-2">
              <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" />
              Move to a replacement server once, free
            </li>
          </ul>

          <div class="mt-auto pt-8">
            <Link
              v-if="checkoutEnabled"
              :href="route('licence.checkout.create', { tier: plan.id })"
              class="flex w-full items-center justify-center gap-2 rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800"
            >
              Buy this licence
              <ArrowRight class="h-4 w-4" />
            </Link>
            <p
              v-else
              class="rounded-2xl border border-dashed border-slate-300 px-4 py-3 text-center text-sm font-semibold text-slate-500"
            >
              Online purchase is not open yet — contact us to buy.
            </p>
          </div>
        </article>

        <p v-if="!plans.length" class="rounded-2xl border border-dashed border-slate-300 p-10 text-center text-slate-500 lg:col-span-3">
          Licensing is not configured on this deployment yet.
        </p>
      </div>

      <div class="mt-14 grid gap-6 md:grid-cols-3">
        <div v-for="benefit in benefits" :key="benefit.title" class="rounded-2xl border border-slate-200 bg-white p-6">
          <component :is="benefit.icon" class="h-6 w-6 text-slate-400" />
          <h3 class="mt-4 text-sm font-black uppercase tracking-[0.14em] text-slate-900">{{ benefit.title }}</h3>
          <p class="mt-2 text-sm leading-6 text-slate-600">{{ benefit.text }}</p>
        </div>
      </div>

      <p v-if="plans.length" class="mt-10 text-center text-sm text-slate-500">
        Not sure which band fits? Licences start at {{ format(cheapest) }} per month for the
        smallest property.
      </p>
    </section>
  </LicenceLayout>
</template>
