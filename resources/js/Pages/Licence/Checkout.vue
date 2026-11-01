<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import LicenceLayout from '@/Layouts/LicenceLayout.vue'
import { AlertCircle, ArrowLeft, Building2, CreditCard, Loader2, ShieldCheck } from 'lucide-vue-next'

const props = defineProps({
  product: { type: String, default: 'MooreLife' },
  plans: { type: Array, default: () => [] },
  checkoutEnabled: { type: Boolean, default: false },
  termMonths: { type: Number, default: 12 },
  defaultCurrency: { type: String, default: 'NGN' },
  providers: { type: Array, default: () => [] },
  defaultProvider: { type: String, default: null },
})

const page = usePage()

const requestedTier = (() => {
  try {
    return Number(new URL(page.url, window.location.origin).searchParams.get('tier') ?? 0)
  } catch {
    return 0
  }
})()

const requestedPlan = props.plans.find((plan) => plan.id === requestedTier) ?? null

const form = reactive({
  property_name: '',
  rooms: requestedPlan ? requestedPlan.min_rooms : 20,
  customer_name: '',
  customer_email: '',
  customer_phone: '',
  currency: props.defaultCurrency === 'USD' ? 'USD' : 'NGN',
  provider: props.defaultProvider ?? props.providers[0]?.value ?? null,
})

const errors = reactive({})
const submitting = ref(false)
const phase = ref('')

function format(amount, code = form.currency) {
  return new Intl.NumberFormat(code === 'USD' ? 'en-US' : 'en-NG', {
    style: 'currency',
    currency: code,
    maximumFractionDigits: 0,
  }).format(Number(amount ?? 0))
}

const rooms = computed(() => Number(form.rooms) || 0)

// Mirrors the server's band resolution so the buyer sees the price before
// submitting. The server recomputes it and never trusts these figures.
const quote = computed(() => {
  if (rooms.value < 1) return null

  const plan = props.plans.find((candidate) => {
    const min = Number(candidate.min_rooms ?? 0)
    const max = candidate.max_rooms === null ? null : Number(candidate.max_rooms)
    return rooms.value >= min && (max === null || rooms.value <= max)
  })

  if (!plan) return null

  const monthly = form.currency === 'USD' ? plan.monthly_usd : plan.monthly_naira

  return {
    plan,
    monthly,
    total: Number((monthly * props.termMonths).toFixed(2)),
  }
})

watch(quote, (value) => {
  if (value) delete errors.rooms
})

async function submit() {
  if (submitting.value) return

  submitting.value = true
  phase.value = 'Starting a secure checkout…'
  Object.keys(errors).forEach((key) => delete errors[key])

  try {
    const response = await fetch(route('licence.checkout.store'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
      },
      body: JSON.stringify(form),
    })

    if (response.status === 422) {
      const body = await response.json()
      Object.entries(body.errors ?? {}).forEach(([key, messages]) => {
        errors[key] = Array.isArray(messages) ? messages[0] : String(messages)
      })
      phase.value = ''
      return
    }

    if (!response.ok) {
      errors.provider = `Checkout could not start (${response.status}). Please try again.`
      phase.value = ''
      return
    }

    const body = await response.json()
    phase.value = 'Redirecting to your bank’s secure page…'

    if (body.redirect_url) {
      window.location.href = body.redirect_url
    } else {
      errors.provider = 'The payment provider did not return a checkout page.'
      phase.value = ''
    }
  } catch (error) {
    errors.provider = 'Could not reach the payment provider. Check your connection and try again.'
    phase.value = ''
  } finally {
    // On success the browser is navigating away; keeping the button disabled
    // prevents a second order if the redirect is slow.
    submitting.value = Boolean(phase.value)
  }
}
</script>

<template>
  <LicenceLayout :product="product">
    <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
      <Link :href="route('licence.pricing')" class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 hover:text-slate-900">
        <ArrowLeft class="h-4 w-4" />
        Back to pricing
      </Link>

      <div v-if="!checkoutEnabled" class="mt-8 rounded-2xl border border-amber-200 bg-amber-50 p-8">
        <h1 class="text-2xl font-black text-amber-900">Online purchase is not open yet</h1>
        <p class="mt-2 text-sm text-amber-800">
          Licences are sold directly at the moment. Contact us and we will send you a key.
        </p>
      </div>

      <div v-else class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_380px]">
        <div>
          <h1 class="text-4xl font-black tracking-tight text-slate-900">Buy your licence</h1>
          <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">
            Tell us about the property so the price matches its size. You will be redirected to a
            secure payment page; the key is issued the moment the payment succeeds.
          </p>

          <form class="mt-8 space-y-6" @submit.prevent="submit">
            <div class="rounded-2xl border border-slate-200 bg-white p-6">
              <h2 class="flex items-center gap-2 text-sm font-black uppercase tracking-[0.14em] text-slate-900">
                <Building2 class="h-4 w-4" />
                The property
              </h2>

              <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="sm:col-span-2">
                  <span class="text-sm font-bold text-slate-700">Hotel or property name</span>
                  <input
                    v-model="form.property_name"
                    type="text"
                    required
                    maxlength="255"
                    placeholder="Lagos Harbour Hotel"
                    class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10"
                  />
                  <span v-if="errors.property_name" class="mt-1 block text-xs font-semibold text-rose-600">{{ errors.property_name }}</span>
                </label>

                <label>
                  <span class="text-sm font-bold text-slate-700">Number of rooms</span>
                  <input
                    v-model.number="form.rooms"
                    type="number"
                    min="1"
                    max="5000"
                    required
                    class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10"
                  />
                  <span v-if="errors.rooms" class="mt-1 block text-xs font-semibold text-rose-600">{{ errors.rooms }}</span>
                </label>

                <label>
                  <span class="text-sm font-bold text-slate-700">Currency</span>
                  <select
                    v-model="form.currency"
                    class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10"
                  >
                    <option value="NGN">Naira (₦)</option>
                    <option value="USD">US Dollar ($)</option>
                  </select>
                  <span v-if="errors.currency" class="mt-1 block text-xs font-semibold text-rose-600">{{ errors.currency }}</span>
                </label>
              </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6">
              <h2 class="flex items-center gap-2 text-sm font-black uppercase tracking-[0.14em] text-slate-900">
                <CreditCard class="h-4 w-4" />
                Who should we send the key to
              </h2>

              <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="sm:col-span-2">
                  <span class="text-sm font-bold text-slate-700">Full name</span>
                  <input
                    v-model="form.customer_name"
                    type="text"
                    maxlength="255"
                    placeholder="Ada Obi"
                    class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10"
                  />
                  <span v-if="errors.customer_name" class="mt-1 block text-xs font-semibold text-rose-600">{{ errors.customer_name }}</span>
                </label>

                <label>
                  <span class="text-sm font-bold text-slate-700">Email</span>
                  <input
                    v-model="form.customer_email"
                    type="email"
                    required
                    maxlength="255"
                    placeholder="you@hotel.com"
                    class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10"
                  />
                  <span v-if="errors.customer_email" class="mt-1 block text-xs font-semibold text-rose-600">{{ errors.customer_email }}</span>
                </label>

                <label>
                  <span class="text-sm font-bold text-slate-700">Phone (optional)</span>
                  <input
                    v-model="form.customer_phone"
                    type="tel"
                    maxlength="40"
                    placeholder="+234 ..."
                    class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10"
                  />
                  <span v-if="errors.customer_phone" class="mt-1 block text-xs font-semibold text-rose-600">{{ errors.customer_phone }}</span>
                </label>
              </div>
            </div>

            <div v-if="providers.length > 1" class="rounded-2xl border border-slate-200 bg-white p-6">
              <h2 class="text-sm font-black uppercase tracking-[0.14em] text-slate-900">Payment method</h2>
              <div class="mt-4 flex flex-wrap gap-3">
                <label
                  v-for="provider in providers"
                  :key="provider.value"
                  class="flex cursor-pointer items-center gap-2 rounded-xl border px-4 py-3 text-sm font-bold transition"
                  :class="form.provider === provider.value ? 'border-slate-900 bg-slate-50 text-slate-900' : 'border-slate-200 text-slate-600'"
                >
                  <input v-model="form.provider" type="radio" :value="provider.value" class="h-4 w-4" />
                  {{ provider.label }}
                </label>
              </div>
            </div>

            <p v-if="errors.provider" class="flex items-start gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
              <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
              {{ errors.provider }}
            </p>

            <button
              type="submit"
              :disabled="submitting || !quote"
              class="flex w-full items-center justify-center gap-2 rounded-2xl bg-slate-900 px-6 py-4 text-sm font-bold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
              <template v-if="submitting">{{ phase || 'Starting checkout…' }}</template>
              <template v-else-if="quote">Pay {{ format(quote.total) }}</template>
              <template v-else>Enter the number of rooms</template>
            </button>

            <p class="text-center text-xs text-slate-500">
              Charged once for {{ termMonths }} months. The term starts when you activate the key.
            </p>
          </form>
        </div>

        <aside class="lg:sticky lg:top-8 lg:self-start">
          <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-black uppercase tracking-[0.14em] text-slate-900">Order summary</h2>

            <div v-if="quote" class="mt-5 space-y-3 text-sm">
              <div class="flex items-start justify-between gap-4">
                <span class="text-slate-500">Plan</span>
                <span class="text-right font-bold text-slate-900">{{ quote.plan.name }}</span>
              </div>
              <div class="flex items-start justify-between gap-4">
                <span class="text-slate-500">Property size</span>
                <span class="text-right font-bold text-slate-900">{{ quote.plan.band }}</span>
              </div>
              <div class="flex items-start justify-between gap-4">
                <span class="text-slate-500">Monthly rate</span>
                <span class="text-right font-bold text-slate-900">{{ format(quote.monthly) }}</span>
              </div>
              <div class="flex items-start justify-between gap-4">
                <span class="text-slate-500">Term</span>
                <span class="text-right font-bold text-slate-900">{{ termMonths }} months</span>
              </div>

              <div class="mt-4 flex items-center justify-between border-t border-slate-200 pt-4">
                <span class="font-bold text-slate-900">Total today</span>
                <span class="text-xl font-black text-slate-900">{{ format(quote.total) }}</span>
              </div>
            </div>

            <p v-else class="mt-5 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">
              Enter the room count to see the price for that property.
            </p>

            <ul class="mt-6 space-y-2 border-t border-slate-100 pt-5 text-xs text-slate-500">
              <li class="flex items-center gap-2"><ShieldCheck class="h-4 w-4 text-emerald-500" /> Secure payment through the gateway</li>
              <li class="flex items-center gap-2"><ShieldCheck class="h-4 w-4 text-emerald-500" /> Key shown on screen and emailed</li>
              <li class="flex items-center gap-2"><ShieldCheck class="h-4 w-4 text-emerald-500" /> No card details touch our servers</li>
            </ul>
          </div>
        </aside>
      </div>
    </section>
  </LicenceLayout>
</template>
