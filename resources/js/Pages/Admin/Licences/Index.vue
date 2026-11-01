<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import {
  AlertTriangle,
  BadgeCheck,
  CalendarClock,
  CheckCircle2,
  KeyRound,
  Plus,
  Search,
  ShieldOff,
  ShoppingCart,
  Tag,
  Wallet,
} from 'lucide-vue-next'

const props = defineProps({
  licences: Object,
  filters: Object,
  stats: Object,
  recentOrders: { type: Array, default: () => [] },
  checkoutEnabled: { type: Boolean, default: false },
})

const page = usePage()

const search = ref(props.filters?.q ?? '')
const status = ref(props.filters?.status ?? '')
const currency = ref(props.filters?.currency ?? '')

function applyFilters() {
  router.get(
    route('admin.licences.index'),
    {
      q: search.value || undefined,
      status: status.value || undefined,
      currency: currency.value || undefined,
    },
    { preserveState: true, replace: true },
  )
}

function statusClass(value) {
  return {
    'bg-emerald-100 text-emerald-700': value === 'active',
    'bg-amber-100 text-amber-700': value === 'expiring soon',
    'bg-sky-100 text-sky-700': value === 'not activated',
    'bg-slate-200 text-slate-600': value === 'expired',
    'bg-rose-100 text-rose-700': value === 'revoked',
  }
}

function money(amount, code) {
  return new Intl.NumberFormat(code === 'USD' ? 'en-US' : 'en-NG', {
    style: 'currency',
    currency: code ?? 'NGN',
    maximumFractionDigits: 0,
  }).format(Number(amount ?? 0))
}

function date(value) {
  return value ? new Date(value).toLocaleDateString() : '—'
}

const revenueCards = computed(() => {
  const currencyTotals = props.stats?.revenue ?? {}
  return Object.entries(currencyTotals).map(([code, total]) => ({ code, total }))
})

const cards = computed(() => [
  {
    key: 'active',
    label: 'Active licences',
    value: props.stats?.active ?? 0,
    icon: CheckCircle2,
    tone: 'text-emerald-600 bg-emerald-50',
    href: route('admin.licences.index', { status: 'active' }),
  },
  {
    key: 'expiring',
    label: 'Expiring in 30 days',
    value: props.stats?.expiring ?? 0,
    icon: CalendarClock,
    tone: 'text-amber-600 bg-amber-50',
    href: route('admin.licences.index', { status: 'expiring' }),
  },
  {
    key: 'not_activated',
    label: 'Not activated',
    value: props.stats?.not_activated ?? 0,
    icon: KeyRound,
    tone: 'text-sky-600 bg-sky-50',
    href: route('admin.licences.index', { status: 'issued' }),
  },
  {
    key: 'expired',
    label: 'Expired',
    value: props.stats?.expired ?? 0,
    icon: AlertTriangle,
    tone: 'text-slate-600 bg-slate-100',
    href: route('admin.licences.index', { status: 'expired' }),
  },
  {
    key: 'revoked',
    label: 'Revoked',
    value: props.stats?.revoked ?? 0,
    icon: ShieldOff,
    tone: 'text-rose-600 bg-rose-50',
    href: route('admin.licences.index', { status: 'revoked' }),
  },
])

watch(status, applyFilters)
watch(currency, applyFilters)
</script>

<template>
  <ManagerLayout>
    <Head title="Licences" />

    <div class="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
      <section class="overflow-hidden rounded-2xl border border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(15,23,42,0.06),_transparent_32%),linear-gradient(135deg,_#ffffff,_#f8fafc_50%,_#eef2ff)] p-8 shadow-sm">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
          <div class="max-w-3xl">
            <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white/80 px-3 py-1 text-[11px] font-black uppercase tracking-[0.24em] text-slate-500">
              <BadgeCheck class="h-3.5 w-3.5" />
              Platform
            </div>
            <h1 class="text-4xl font-black tracking-tight text-slate-900">Licence desk</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">
              Every licence sold, which machine it is bound to, and when it lapses. Sales made here
              are issued immediately; online sales appear as soon as the gateway confirms payment.
            </p>
            <p v-if="!checkoutEnabled" class="mt-3 inline-flex items-center gap-2 rounded-xl bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800">
              <AlertTriangle class="h-4 w-4" />
              Online checkout is switched off (LICENCE_CHECKOUT_ENABLED=false).
            </p>
          </div>

          <div class="flex flex-wrap gap-3">
            <Link
              :href="route('admin.licences.orders')"
              class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
            >
              <ShoppingCart class="h-4 w-4" />
              Orders
            </Link>
            <Link
              :href="route('admin.licences.plans')"
              class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
            >
              <Tag class="h-4 w-4" />
              Pricing
            </Link>
            <Link
              :href="route('admin.licences.create')"
              class="inline-flex items-center gap-2 rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800"
            >
              <Plus class="h-4 w-4" />
              Issue a licence
            </Link>
          </div>
        </div>
      </section>

      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <Link
          v-for="card in cards"
          :key="card.key"
          :href="card.href"
          class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
        >
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">{{ card.label }}</span>
            <span class="flex h-9 w-9 items-center justify-center rounded-xl" :class="card.tone">
              <component :is="card.icon" class="h-4 w-4" />
            </span>
          </div>
          <p class="mt-3 text-3xl font-black text-slate-900">{{ card.value }}</p>
          <p class="mt-1 text-xs font-semibold text-slate-500 group-hover:text-slate-700">View</p>
        </Link>
      </section>

      <section class="grid gap-4 sm:grid-cols-2">
        <Link
          :href="route('admin.licences.orders')"
          class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
        >
          <div class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">
            <Wallet class="h-3.5 w-3.5" />
            Paid to date
          </div>
          <div v-if="revenueCards.length" class="mt-3 flex flex-wrap gap-6">
            <p v-for="row in revenueCards" :key="row.code" class="text-2xl font-black text-slate-900">
              {{ money(row.total, row.code) }}
              <span class="text-xs font-bold text-slate-400">{{ row.code }}</span>
            </p>
          </div>
          <p v-else class="mt-3 text-sm text-slate-500">No paid orders yet.</p>
        </Link>

        <Link
          :href="route('admin.licences.orders', { status: 'paid' })"
          class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
        >
          <div class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">
            <ShoppingCart class="h-3.5 w-3.5" />
            Last 30 days
          </div>
          <div v-if="Object.keys(stats?.revenue_last_30_days ?? {}).length" class="mt-3 flex flex-wrap gap-6">
            <p
              v-for="(total, code) in stats.revenue_last_30_days"
              :key="code"
              class="text-2xl font-black text-slate-900"
            >
              {{ money(total, code) }}
              <span class="text-xs font-bold text-slate-400">{{ code }}</span>
            </p>
          </div>
          <p v-else class="mt-3 text-sm text-slate-500">Nothing sold in the last 30 days.</p>
        </Link>
      </section>

      <section v-if="recentOrders.length" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-black uppercase tracking-[0.14em] text-slate-900">Recent orders</h2>
          <Link :href="route('admin.licences.orders')" class="text-xs font-bold text-slate-500 hover:text-slate-900">All orders →</Link>
        </div>
        <div class="mt-4 divide-y divide-slate-100">
          <div v-for="order in recentOrders" :key="order.id" class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
            <div>
              <p class="font-bold text-slate-900">{{ order.property_name }}</p>
              <p class="font-mono text-xs text-slate-500">{{ order.reference }}</p>
            </div>
            <div class="text-right">
              <p class="font-bold text-slate-900">{{ money(order.amount, order.currency) }}</p>
              <p class="text-xs font-semibold capitalize" :class="order.status === 'paid' ? 'text-emerald-600' : 'text-slate-500'">
                {{ order.status }}
              </p>
            </div>
          </div>
        </div>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 p-5 lg:flex-row lg:items-center lg:justify-between">
          <h2 class="text-sm font-black uppercase tracking-[0.14em] text-slate-900">Licences</h2>

          <div class="flex flex-wrap gap-2">
            <div class="relative">
              <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
              <input
                v-model="search"
                type="search"
                placeholder="Property, customer, reference"
                class="w-full rounded-xl border border-slate-300 py-2 pl-9 pr-3 text-sm outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10 sm:w-64"
                @keyup.enter="applyFilters"
              />
            </div>

            <select v-model="status" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
              <option value="">All statuses</option>
              <option value="active">Active</option>
              <option value="expiring">Expiring soon</option>
              <option value="issued">Not activated</option>
              <option value="expired">Expired</option>
              <option value="revoked">Revoked</option>
            </select>

            <select v-model="currency" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
              <option value="">All currencies</option>
              <option value="NGN">NGN</option>
              <option value="USD">USD</option>
            </select>
          </div>
        </div>

        <div v-if="licences.data.length" class="overflow-x-auto">
          <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-[11px] font-black uppercase tracking-[0.14em] text-slate-400">
              <tr>
                <th class="px-5 py-3">Property</th>
                <th class="px-5 py-3">Plan</th>
                <th class="px-5 py-3">Paid</th>
                <th class="px-5 py-3">Status</th>
                <th class="px-5 py-3">Expires</th>
                <th class="px-5 py-3">Machine</th>
                <th class="px-5 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="licence in licences.data" :key="licence.id" class="hover:bg-slate-50/70">
                <td class="px-5 py-4">
                  <p class="font-bold text-slate-900">{{ licence.property_name }}</p>
                  <p class="text-xs text-slate-500">{{ licence.customer_email || 'No email recorded' }}</p>
                </td>
                <td class="px-5 py-4 text-slate-600">
                  <p class="font-semibold">{{ licence.plan || 'Annual' }}</p>
                  <p v-if="licence.rooms" class="text-xs text-slate-400">{{ licence.rooms }} rooms</p>
                </td>
                <td class="px-5 py-4">
                  <p v-if="licence.amount !== null" class="font-bold text-slate-900">
                    {{ money(licence.amount, licence.currency) }}
                  </p>
                  <p v-else class="text-slate-400">—</p>
                  <p v-if="licence.payment_reference" class="font-mono text-[11px] text-slate-400">
                    {{ licence.payment_reference }}
                  </p>
                </td>
                <td class="px-5 py-4">
                  <span class="rounded-full px-3 py-1 text-[11px] font-black uppercase tracking-[0.12em]" :class="statusClass(licence.status)">
                    {{ licence.status }}
                  </span>
                </td>
                <td class="px-5 py-4">
                  <p class="font-semibold text-slate-700">{{ date(licence.expires_at) }}</p>
                  <p v-if="licence.days_left !== null" class="text-xs" :class="licence.days_left <= 30 ? 'text-amber-600' : 'text-slate-400'">
                    {{ licence.days_left >= 0 ? `${licence.days_left} days left` : 'past' }}
                  </p>
                </td>
                <td class="px-5 py-4">
                  <span v-if="licence.machine_bound" class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold text-slate-600">bound</span>
                  <span v-else class="text-xs text-slate-400">not activated</span>
                </td>
                <td class="px-5 py-4 text-right">
                  <Link :href="route('admin.licences.show', licence.id)" class="text-xs font-black uppercase tracking-[0.14em] text-slate-500 hover:text-slate-900">
                    Open
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="px-6 py-16 text-center">
          <KeyRound class="mx-auto h-10 w-10 text-slate-300" />
          <h3 class="mt-4 text-lg font-black text-slate-900">No licences match</h3>
          <p class="mt-2 text-sm text-slate-500">Adjust the filters, or issue a licence for a customer who paid offline.</p>
        </div>

        <div v-if="licences.links?.length > 3" class="border-t border-slate-100 p-4">
          <Pagination :links="licences.links" />
        </div>
      </section>
    </div>
  </ManagerLayout>
</template>
