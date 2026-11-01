<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import {
  AlertTriangle,
  ArrowLeft,
  Check,
  Copy,
  KeyRound,
  Monitor,
  RefreshCw,
  ShieldOff,
  ShieldCheck,
  Unlink,
} from 'lucide-vue-next'

const props = defineProps({
  licence: Object,
  order: { type: Object, default: null },
  issuedKey: { type: String, default: null },
})

const page = usePage()
const copied = ref(false)
const key = ref(props.issuedKey)

const statusClass = computed(() => ({
  'bg-emerald-100 text-emerald-700': props.licence.status === 'active',
  'bg-amber-100 text-amber-700': props.licence.status === 'expiring soon',
  'bg-sky-100 text-sky-700': props.licence.status === 'not activated',
  'bg-slate-200 text-slate-600': props.licence.status === 'expired',
  'bg-rose-100 text-rose-700': props.licence.status === 'revoked',
}))

function money(amount, code) {
  if (amount === null) return '—'

  return new Intl.NumberFormat(code === 'USD' ? 'en-US' : 'en-NG', {
    style: 'currency',
    currency: code ?? 'NGN',
    maximumFractionDigits: 0,
  }).format(Number(amount))
}

function date(value) {
  return value ? new Date(value).toLocaleString() : '—'
}

function act(action, confirmText) {
  if (confirmText && !window.confirm(confirmText)) return

  router.post(route(`admin.licences.${action}`, props.licence.id), {}, { preserveScroll: true })
}

async function copyKey() {
  if (!key.value) return

  try {
    await navigator.clipboard.writeText(key.value)
    copied.value = true
    window.setTimeout(() => { copied.value = false }, 2000)
  } catch {
    copied.value = false
  }
}
</script>

<template>
  <ManagerLayout>
    <Head :title="`Licence #${licence.id}`" />

    <div class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
      <Link :href="route('admin.licences.index')" class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 hover:text-slate-900">
        <ArrowLeft class="h-4 w-4" />
        All licences
      </Link>

      <div
        v-if="page.props.flash?.success"
        class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800"
      >
        {{ page.props.flash.success }}
      </div>

      <div
        v-if="page.props.flash?.error"
        class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-semibold text-rose-800"
      >
        {{ page.props.flash.error }}
      </div>

      <div v-if="key" class="rounded-3xl border border-slate-800 bg-slate-900 p-6 text-center">
        <p class="text-[11px] font-black uppercase tracking-[0.24em] text-slate-400">New licence key — copy it now</p>
        <p class="mt-3 break-all font-mono text-xl font-black tracking-[0.12em] text-white sm:text-2xl">{{ key }}</p>
        <button
          type="button"
          class="mt-4 inline-flex items-center gap-2 rounded-xl border border-slate-700 px-4 py-2 text-sm font-bold text-slate-200 transition hover:bg-slate-800"
          @click="copyKey"
        >
          <Check v-if="copied" class="h-4 w-4 text-emerald-400" />
          <Copy v-else class="h-4 w-4" />
          {{ copied ? 'Copied' : 'Copy key' }}
        </button>
        <p class="mt-4 text-xs text-slate-400">
          Only a hash is stored. This is the last time the key is shown; send it to the customer now.
        </p>
      </div>

      <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 p-6">
          <div>
            <div class="flex items-center gap-3">
              <h1 class="text-2xl font-black tracking-tight text-slate-900">{{ licence.property_name }}</h1>
              <span class="rounded-full px-3 py-1 text-[11px] font-black uppercase tracking-[0.12em]" :class="statusClass">
                {{ licence.status }}
              </span>
            </div>
            <p class="mt-1 text-sm text-slate-500">
              Licence #{{ licence.id }} · key prefix <span class="font-mono">{{ licence.key_prefix }}…</span>
            </p>
          </div>

          <p v-if="licence.days_left !== null" class="text-right">
            <span class="block text-3xl font-black text-slate-900">{{ licence.days_left }}</span>
            <span class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">days left</span>
          </p>
        </div>

        <dl class="grid gap-6 p-6 sm:grid-cols-2 lg:grid-cols-3">
          <div>
            <dt class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Plan</dt>
            <dd class="mt-1 font-bold text-slate-900">{{ licence.plan || 'Annual licence' }}</dd>
            <dd v-if="licence.plan_band" class="text-xs text-slate-500">{{ licence.plan_band }}</dd>
          </div>
          <div>
            <dt class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Rooms</dt>
            <dd class="mt-1 font-bold text-slate-900">{{ licence.rooms ?? '—' }}</dd>
          </div>
          <div>
            <dt class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Price paid</dt>
            <dd class="mt-1 font-bold text-slate-900">{{ money(licence.amount, licence.currency) }}</dd>
            <dd v-if="licence.monthly_amount !== null" class="text-xs text-slate-500">
              {{ money(licence.monthly_amount, licence.currency) }} / month × {{ licence.term_months }}
            </dd>
          </div>
          <div>
            <dt class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Customer</dt>
            <dd class="mt-1 font-bold text-slate-900">{{ licence.customer_name || '—' }}</dd>
            <dd class="text-xs text-slate-500">{{ licence.customer_email || 'No email recorded' }}</dd>
          </div>
          <div>
            <dt class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Issued</dt>
            <dd class="mt-1 font-semibold text-slate-700">{{ date(licence.issued_at) }}</dd>
          </div>
          <div>
            <dt class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Expires</dt>
            <dd class="mt-1 font-semibold text-slate-700">{{ date(licence.expires_at) }}</dd>
          </div>
          <div class="sm:col-span-2 lg:col-span-3">
            <dt class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Machine</dt>
            <dd class="mt-1 flex items-center gap-2 font-mono text-xs text-slate-600">
              <Monitor class="h-4 w-4 text-slate-400" />
              {{ licence.machine_id || 'Not activated on any machine yet' }}
            </dd>
            <dd v-if="licence.activated_at" class="mt-1 text-xs text-slate-500">Activated {{ date(licence.activated_at) }}</dd>
          </div>
          <div v-if="licence.payment_reference" class="sm:col-span-2 lg:col-span-3">
            <dt class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Payment reference</dt>
            <dd class="mt-1 font-mono text-xs text-slate-600">{{ licence.payment_reference }}</dd>
          </div>
          <div v-if="licence.notes" class="sm:col-span-2 lg:col-span-3">
            <dt class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Notes</dt>
            <dd class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ licence.notes }}</dd>
          </div>
        </dl>
      </section>

      <section v-if="order" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-[0.14em] text-slate-900">Order</h2>
        <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
          <div>
            <dt class="text-slate-500">Reference</dt>
            <dd class="font-mono font-bold text-slate-900">{{ order.reference }}</dd>
          </div>
          <div>
            <dt class="text-slate-500">Provider</dt>
            <dd class="font-bold capitalize text-slate-900">{{ order.provider || '—' }}</dd>
          </div>
          <div>
            <dt class="text-slate-500">Status</dt>
            <dd class="font-bold capitalize text-slate-900">{{ order.status }}</dd>
          </div>
          <div>
            <dt class="text-slate-500">Paid at</dt>
            <dd class="font-bold text-slate-900">{{ date(order.paid_at) }}</dd>
          </div>
        </dl>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-[0.14em] text-slate-900">Support actions</h2>

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
          <button
            v-if="licence.raw_status !== 'revoked'"
            type="button"
            class="flex items-start gap-3 rounded-xl border border-rose-200 px-4 py-3 text-left transition hover:bg-rose-50"
            @click="act('revoke', 'Revoke this licence? Any future activation will be refused.')"
          >
            <ShieldOff class="mt-0.5 h-4 w-4 text-rose-600" />
            <span>
              <span class="block text-sm font-bold text-rose-700">Revoke</span>
              <span class="block text-xs text-rose-600">Blocks activation and renewal. An already-installed copy runs until its expiry.</span>
            </span>
          </button>

          <button
            v-if="licence.raw_status === 'revoked'"
            type="button"
            class="flex items-start gap-3 rounded-xl border border-emerald-200 px-4 py-3 text-left transition hover:bg-emerald-50"
            @click="act('restore')"
          >
            <ShieldCheck class="mt-0.5 h-4 w-4 text-emerald-600" />
            <span>
              <span class="block text-sm font-bold text-emerald-700">Restore</span>
              <span class="block text-xs text-emerald-600">Undo a revocation; the expiry date is unchanged.</span>
            </span>
          </button>

          <button
            v-if="licence.machine_id"
            type="button"
            class="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 text-left transition hover:bg-slate-50"
            @click="act('clear-machine', 'Clear the machine binding so this key can be activated on a replacement PC?')"
          >
            <Unlink class="mt-0.5 h-4 w-4 text-slate-500" />
            <span>
              <span class="block text-sm font-bold text-slate-800">Move to another PC</span>
              <span class="block text-xs text-slate-500">Clears the binding. The term does not restart.</span>
            </span>
          </button>

          <button
            type="button"
            class="flex items-start gap-3 rounded-xl border border-amber-200 px-4 py-3 text-left transition hover:bg-amber-50"
            @click="act('reissue', 'Issue a replacement key? The current key is revoked and the replacement starts its term at activation.')"
          >
            <RefreshCw class="mt-0.5 h-4 w-4 text-amber-600" />
            <span>
              <span class="block text-sm font-bold text-amber-700">Re-issue key</span>
              <span class="block text-xs text-amber-700">For a lost key. Revokes this one and shows a new key once.</span>
            </span>
          </button>
        </div>

        <p v-if="licence.raw_status === 'revoked'" class="mt-4 flex items-start gap-2 rounded-xl bg-rose-50 px-4 py-3 text-xs text-rose-700">
          <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
          Revoked licences cannot be activated. Restore only if the revocation was a mistake.
        </p>

        <p v-if="licence.replaces_licence_id" class="mt-4 text-xs text-slate-500">
          This key replaced
          <Link :href="route('admin.licences.show', licence.replaces_licence_id)" class="font-bold text-slate-700 underline">
            licence #{{ licence.replaces_licence_id }}
          </Link>.
        </p>
      </section>

      <p class="flex items-center gap-2 text-xs text-slate-500">
        <KeyRound class="h-4 w-4" />
        The key itself is not stored, only its SHA-256 hash. A key can be shown once, at issue.
      </p>
    </div>
  </ManagerLayout>
</template>
