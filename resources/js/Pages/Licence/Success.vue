<script setup>
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import LicenceLayout from '@/Layouts/LicenceLayout.vue'
import { Check, CheckCircle2, Copy, MailCheck, MonitorCheck } from 'lucide-vue-next'

const props = defineProps({
  licenceKey: { type: String, default: null },
  order: { type: Object, required: true },
  licence: { type: Object, default: null },
  emailSentTo: { type: String, default: null },
})

const copied = ref(false)

function money(amount, code) {
  return new Intl.NumberFormat(code === 'USD' ? 'en-US' : 'en-NG', {
    style: 'currency',
    currency: code ?? 'NGN',
    maximumFractionDigits: 0,
  }).format(Number(amount ?? 0))
}

async function copyKey() {
  if (!props.licenceKey) return

  try {
    await navigator.clipboard.writeText(props.licenceKey)
    copied.value = true
    window.setTimeout(() => { copied.value = false }, 2000)
  } catch {
    copied.value = false
  }
}
</script>

<template>
  <LicenceLayout>
    <section class="mx-auto max-w-3xl px-4 py-14 sm:px-6 lg:px-8">
      <div class="flex items-center gap-3">
        <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
          <CheckCircle2 class="h-6 w-6" />
        </span>
        <div>
          <p class="text-[11px] font-black uppercase tracking-[0.24em] text-emerald-600">Payment received</p>
          <h1 class="text-3xl font-black tracking-tight text-slate-900">Your licence is ready</h1>
        </div>
      </div>

      <div v-if="licenceKey" class="mt-8 rounded-3xl border border-slate-800 bg-slate-900 p-8 text-center">
        <p class="text-[11px] font-black uppercase tracking-[0.24em] text-slate-400">Licence key</p>
        <p class="mt-3 break-all font-mono text-2xl font-black tracking-[0.14em] text-white sm:text-3xl">
          {{ licenceKey }}
        </p>

        <button
          type="button"
          class="mt-5 inline-flex items-center gap-2 rounded-xl border border-slate-700 px-4 py-2 text-sm font-bold text-slate-200 transition hover:bg-slate-800"
          @click="copyKey"
        >
          <Check v-if="copied" class="h-4 w-4 text-emerald-400" />
          <Copy v-else class="h-4 w-4" />
          {{ copied ? 'Copied' : 'Copy key' }}
        </button>

        <p class="mt-5 text-xs text-slate-400">
          Copy this now. For security it is not shown again on refresh, and it is not stored in a
          readable form on our side.
        </p>
      </div>

      <div v-else class="mt-8 rounded-2xl border border-amber-200 bg-amber-50 p-6">
        <p class="flex items-center gap-2 text-sm font-black text-amber-900">
          <MailCheck class="h-4 w-4" />
          Your key has already been handed out
        </p>
        <p class="mt-2 text-sm text-amber-800">
          A copy was emailed to <strong>{{ emailSentTo }}</strong>. If you cannot find it, contact
          support with reference <strong>{{ order.reference }}</strong> and we will issue a
          replacement.
        </p>
      </div>

      <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-6">
        <h2 class="text-sm font-black uppercase tracking-[0.14em] text-slate-900">What you bought</h2>
        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
          <div>
            <dt class="text-slate-500">Property</dt>
            <dd class="font-bold text-slate-900">{{ order.property_name }} ({{ order.rooms }} rooms)</dd>
          </div>
          <div>
            <dt class="text-slate-500">Plan</dt>
            <dd class="font-bold text-slate-900">{{ licence?.plan ?? 'Annual licence' }}</dd>
          </div>
          <div>
            <dt class="text-slate-500">Term</dt>
            <dd class="font-bold text-slate-900">{{ order.term_months }} months from activation</dd>
          </div>
          <div>
            <dt class="text-slate-500">Paid</dt>
            <dd class="font-bold text-slate-900">{{ money(order.amount, order.currency) }}</dd>
          </div>
          <div class="sm:col-span-2">
            <dt class="text-slate-500">Reference</dt>
            <dd class="font-mono font-bold text-slate-900">{{ order.reference }}</dd>
          </div>
        </dl>
      </div>

      <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6">
        <h2 class="flex items-center gap-2 text-sm font-black uppercase tracking-[0.14em] text-slate-900">
          <MonitorCheck class="h-4 w-4" />
          How to activate
        </h2>
        <ol class="mt-4 space-y-2 pl-5 text-sm text-slate-600" style="list-style: decimal">
          <li>Open MooreLife on the computer that runs your hotel server.</li>
          <li>Choose <strong>Activate licence</strong> from the trial banner, or from the screen shown when the trial ends.</li>
          <li>Paste the key and confirm. Activation needs the internet once.</li>
          <li>Every terminal and staff client paired with that server is covered by the same key.</li>
        </ol>
      </div>

      <div class="mt-8 flex flex-wrap gap-3">
        <Link
          :href="route('licence.pricing')"
          class="rounded-2xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-white"
        >
          Back to pricing
        </Link>
      </div>
    </section>
  </LicenceLayout>
</template>
