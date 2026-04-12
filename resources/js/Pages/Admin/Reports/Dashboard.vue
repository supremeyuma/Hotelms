<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { ArrowUpRight, BedDouble, Boxes, ClipboardList, Hotel, Users } from 'lucide-vue-next'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import TrendChart from '@/Components/TrendChart.vue'

const props = defineProps({
  mode: String,
  title: String,
  kpis: Object,
  links: Object,
  recentTransactions: {
    type: Array,
    default: () => [],
  },
  charts: Array,
})

const operationsCards = computed(() => [
  {
    label: 'Occupancy',
    value: `${Number(props.kpis?.occupancy?.occupancy ?? 0).toLocaleString()}%`,
    hint: `${props.kpis?.occupancy?.occupied_rooms ?? 0} occupied of ${props.kpis?.occupancy?.total_rooms ?? 0} rooms`,
    description: 'Track in-house volume, arrivals, departures, and daily occupancy trend.',
    href: props.links?.primary,
    icon: Hotel,
    tone: 'indigo',
  },
  {
    label: 'Staff coverage',
    value: Number(props.kpis?.staff?.active_staff ?? 0).toLocaleString(),
    hint: 'Active team members across operational departments',
    description: 'Review workload visibility, staffing coverage, and department-level support.',
    href: props.links?.secondary,
    icon: Users,
    tone: 'emerald',
  },
  {
    label: 'Inventory movement',
    value: Number(props.kpis?.inventory?.usage ?? 0).toLocaleString(),
    hint: 'Tracked stock movement in the current reporting summary',
    description: 'Audit stock movement history, transfer patterns, and usage pressure.',
    href: props.links?.tertiary,
    icon: Boxes,
    tone: 'amber',
  },
])

const financeCards = computed(() => [
  {
    label: 'Charges posted',
    value: formatCompactCurrency(props.kpis?.charges?.total ?? 0),
    hint: `${Number(props.kpis?.charges?.count ?? 0).toLocaleString()} charge transactions in the current 30-day summary`,
    href: props.links?.primary,
    icon: Hotel,
    tone: 'indigo',
  },
  {
    label: 'Payments recorded',
    value: formatCompactCurrency(props.kpis?.payments?.total ?? 0),
    hint: `${formatCompactCurrency(props.kpis?.payments?.today_total ?? 0)} posted today`,
    href: props.links?.secondary,
    icon: BedDouble,
    tone: 'amber',
  },
  {
    label: 'Outstanding bookings',
    value: Number(props.kpis?.outstanding?.count ?? 0).toLocaleString(),
    hint: `${formatCompactCurrency(props.kpis?.outstanding?.total ?? 0)} still unsettled`,
    href: props.links?.primary,
    icon: ClipboardList,
    tone: 'emerald',
  },
  {
    label: 'Open periods',
    value: Number(props.kpis?.periods?.open ?? 0).toLocaleString(),
    hint: 'Accounting periods currently open',
    href: props.links?.tertiary,
    icon: ArrowUpRight,
    tone: 'indigo',
  },
])

function toneClasses(tone) {
  const tones = {
    indigo: 'from-indigo-500/15 via-indigo-500/5 to-white text-indigo-600 ring-indigo-100',
    emerald: 'from-emerald-500/15 via-emerald-500/5 to-white text-emerald-600 ring-emerald-100',
    amber: 'from-amber-500/15 via-amber-500/5 to-white text-amber-600 ring-amber-100',
  }

  return tones[tone] ?? 'from-slate-500/10 via-slate-500/5 to-white text-slate-600 ring-slate-100'
}

function formatCompactCurrency(value) {
  return `NGN ${Number(value ?? 0).toLocaleString()}`
}

function formatCurrency(value) {
  return `NGN ${Number(value ?? 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`
}

function formatDateTime(value) {
  if (!value) {
    return 'No timestamp'
  }

  return new Date(value).toLocaleString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}
</script>

<template>
  <ManagerLayout>
    <Head :title="title" />

    <div class="space-y-8">
      <section
        v-if="mode === 'operations'"
        class="overflow-hidden rounded-[2rem] bg-slate-900 text-white shadow-2xl shadow-slate-200"
      >
        <div class="grid gap-6 px-6 py-8 sm:px-8 xl:grid-cols-[1.3fr_0.7fr]">
          <div class="space-y-4">
            <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-[11px] font-black uppercase tracking-[0.22em] text-slate-200">
              <ClipboardList class="h-3.5 w-3.5" />
              Operations reporting
            </div>
            <div class="space-y-3">
              <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ title }}</h1>
              <p class="max-w-2xl text-sm leading-6 text-slate-300">
                Review occupancy, staffing, and inventory movement from one place, then drill straight into the report that needs action.
              </p>
            </div>
            <div class="flex flex-wrap gap-3">
              <Link
                :href="links.primary"
                class="inline-flex items-center gap-2 rounded-2xl bg-white px-5 py-3 text-sm font-bold text-slate-900 transition hover:bg-slate-100"
              >
                Open occupancy report
                <ArrowUpRight class="h-4 w-4" />
              </Link>
              <Link
                :href="links.secondary"
                class="inline-flex items-center gap-2 rounded-2xl border border-white/15 px-5 py-3 text-sm font-bold text-white transition hover:bg-white/10"
              >
                Review staff report
                <ArrowUpRight class="h-4 w-4" />
              </Link>
            </div>
          </div>

          <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
            <Link
              :href="links.primary"
              class="rounded-[1.75rem] border border-white/10 bg-white/10 p-5 backdrop-blur transition hover:bg-white/15"
            >
              <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-300">Occupancy today</p>
              <p class="mt-3 text-4xl font-black">{{ kpis.occupancy.occupancy }}%</p>
              <p class="mt-2 text-sm text-slate-300">
                {{ kpis.occupancy.occupied_rooms }} occupied rooms, {{ kpis.occupancy.available_rooms }} currently available.
              </p>
            </Link>

            <Link
              :href="links.primary"
              class="rounded-[1.75rem] border border-emerald-300/25 bg-gradient-to-br from-emerald-400/20 to-emerald-300/5 p-5 transition hover:from-emerald-400/25 hover:to-emerald-300/10"
            >
              <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-100">Guest flow today</p>
              <div class="mt-4 grid grid-cols-2 gap-4">
                <div>
                  <p class="text-3xl font-black">{{ kpis.occupancy.arrivals_today }}</p>
                  <p class="text-xs text-emerald-100/80">Arrivals</p>
                </div>
                <div>
                  <p class="text-3xl font-black">{{ kpis.occupancy.departures_today }}</p>
                  <p class="text-xs text-emerald-100/80">Departures</p>
                </div>
              </div>
            </Link>
          </div>
        </div>
      </section>

      <section v-if="mode === 'operations'" class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        <Link
          v-for="card in operationsCards"
          :key="card.label"
          :href="card.href"
          class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md"
        >
          <div class="rounded-[1.5rem] bg-gradient-to-br p-5 ring-1" :class="toneClasses(card.tone)">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">{{ card.label }}</p>
                <p class="mt-3 text-3xl font-black tracking-tight text-slate-950">{{ card.value }}</p>
                <p class="mt-2 text-sm font-medium text-slate-600">{{ card.hint }}</p>
              </div>
              <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/80 shadow-sm">
                <component :is="card.icon" class="h-5 w-5" />
              </div>
            </div>
          </div>

          <div class="mt-4 flex items-start justify-between gap-4">
            <p class="text-sm leading-6 text-slate-500">{{ card.description }}</p>
            <ArrowUpRight class="mt-1 h-4 w-4 shrink-0 text-slate-300" />
          </div>
        </Link>
      </section>

      <section v-else class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
        <Link
          v-for="card in financeCards"
          :key="card.label"
          :href="card.href"
          class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md"
        >
          <div class="rounded-[1.5rem] bg-gradient-to-br p-5 ring-1" :class="toneClasses(card.tone)">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">{{ card.label }}</p>
                <p class="mt-3 text-3xl font-black tracking-tight text-slate-950">{{ card.value }}</p>
                <p class="mt-2 text-sm font-medium text-slate-600">{{ card.hint }}</p>
              </div>
              <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/80 shadow-sm">
                <component :is="card.icon" class="h-5 w-5" />
              </div>
            </div>
          </div>
        </Link>
      </section>

      <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <TrendChart
          v-for="chart in charts"
          :key="chart.title"
          :title="chart.title"
          :endpoint="chart.endpoint"
        />
      </section>

      <section
        v-if="mode === 'finance'"
        class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm"
      >
        <div class="flex flex-col gap-2 border-b border-slate-100 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Recent activity</p>
            <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-900">Latest posted transactions</h2>
          </div>
          <Link
            :href="links.secondary"
            class="inline-flex items-center gap-2 text-sm font-semibold text-indigo-600 transition hover:text-indigo-700"
          >
            Open finance audit
            <ArrowUpRight class="h-4 w-4" />
          </Link>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">
              <tr>
                <th class="px-6 py-4">Time</th>
                <th class="px-6 py-4">Type</th>
                <th class="px-6 py-4">Reference</th>
                <th class="px-6 py-4">Guest</th>
                <th class="px-6 py-4">Room</th>
                <th class="px-6 py-4 text-right">Amount</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="transaction in recentTransactions"
                :key="transaction.id"
                class="transition hover:bg-slate-50/80"
              >
                <td class="px-6 py-4 text-slate-600">{{ formatDateTime(transaction.occurred_at) }}</td>
                <td class="px-6 py-4">
                  <span
                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold uppercase tracking-wide"
                    :class="transaction.kind === 'payment' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                  >
                    {{ transaction.kind }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <div class="space-y-1">
                    <p class="font-semibold text-slate-900">{{ transaction.label }}</p>
                    <p class="text-xs text-slate-500">{{ transaction.booking_code || 'No booking reference' }}</p>
                  </div>
                </td>
                <td class="px-6 py-4 text-slate-700">{{ transaction.guest }}</td>
                <td class="px-6 py-4 text-slate-700">{{ transaction.room }}</td>
                <td class="px-6 py-4 text-right font-bold text-slate-900">{{ formatCurrency(transaction.amount) }}</td>
              </tr>

              <tr v-if="recentTransactions.length === 0">
                <td colspan="6" class="px-6 py-16 text-center text-slate-500">
                  No posted charges or payments have been captured yet.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </ManagerLayout>
</template>
