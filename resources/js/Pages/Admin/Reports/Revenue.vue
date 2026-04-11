<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ArrowUpRight, Banknote, CalendarDays, Download, ReceiptText } from 'lucide-vue-next'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import TrendChart from '@/Components/TrendChart.vue'
import Pagination from '@/Components/Pagination.vue'

const props = defineProps({
  rows: Object,
  filters: Object,
  summary: Object,
  routePrefix: {
    type: String,
    default: 'admin',
  },
})

const isFiltering = ref(false)

const exportHref = computed(() => {
  const params = new URLSearchParams()

  Object.entries(props.filters ?? {}).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) {
      params.set(key, value)
    }
  })

  const query = params.toString()

  return `${route(`${props.routePrefix}.reports.revenue.export`, 'xlsx')}${query ? `?${query}` : ''}`
})

const cards = computed(() => [
  {
    label: 'Recognized revenue',
    value: formatCurrency(props.summary?.revenue ?? 0),
    hint: `${Number(props.summary?.bookings ?? 0).toLocaleString()} confirmed bookings in range`,
    href: route(`${props.routePrefix}.reports.revenue`),
    icon: Banknote,
    tone: 'indigo',
  },
  {
    label: 'Average booking value',
    value: formatCurrency(props.summary?.adr ?? 0),
    hint: 'Average confirmed booking amount',
    href: route(`${props.routePrefix}.reports.revenue`),
    icon: ReceiptText,
    tone: 'emerald',
  },
  {
    label: 'Revenue posted today',
    value: formatCurrency(props.summary?.today_revenue ?? 0),
    hint: 'Confirmed booking revenue created today',
    href: route('finance.reports.daily-revenue'),
    icon: CalendarDays,
    tone: 'amber',
  },
])

function formatDate(dateString) {
  return new Date(dateString).toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

function formatDateTime(dateString) {
  return new Date(dateString).toLocaleString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

function formatCurrency(value) {
  return `NGN ${Number(value ?? 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`
}

function roomLabel(row) {
  const assignedRoom = row.rooms?.[0]
  const room = assignedRoom ?? row.room
  const type = assignedRoom?.room_type?.title ?? row.room_type?.title ?? row.room?.room_type?.title
  const name = room?.name ?? room?.room_number

  return [type, name].filter(Boolean).join(' - ') || 'Unassigned'
}

function guestLabel(row) {
  return row.guest_name || row.user?.name || 'Walk-in guest'
}

function toneClasses(tone) {
  const tones = {
    indigo: 'from-indigo-500/15 via-indigo-500/5 to-white text-indigo-600 ring-indigo-100',
    emerald: 'from-emerald-500/15 via-emerald-500/5 to-white text-emerald-600 ring-emerald-100',
    amber: 'from-amber-500/15 via-amber-500/5 to-white text-amber-600 ring-amber-100',
  }

  return tones[tone] ?? 'from-slate-500/10 via-slate-500/5 to-white text-slate-600 ring-slate-100'
}

function applyFilters(event) {
  const form = new FormData(event.target)

  router.get(route(`${props.routePrefix}.reports.revenue`), {
    search: form.get('search') || undefined,
    from: form.get('from') || undefined,
    to: form.get('to') || undefined,
  }, {
    preserveState: true,
    replace: true,
    onStart: () => {
      isFiltering.value = true
    },
    onFinish: () => {
      isFiltering.value = false
    },
  })
}

function resetFilters() {
  router.get(route(`${props.routePrefix}.reports.revenue`), {}, {
    preserveState: true,
    replace: true,
    onStart: () => {
      isFiltering.value = true
    },
    onFinish: () => {
      isFiltering.value = false
    },
  })
}
</script>

<template>
  <ManagerLayout>
    <Head title="Revenue Report" />

    <div class="space-y-8">
      <section class="overflow-hidden rounded-[2rem] bg-slate-900 text-white shadow-2xl shadow-slate-200">
        <div class="grid gap-6 px-6 py-8 sm:px-8 xl:grid-cols-[1.25fr_0.75fr]">
          <div class="space-y-4">
            <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-[11px] font-black uppercase tracking-[0.22em] text-slate-200">
              <Banknote class="h-3.5 w-3.5" />
              Revenue reporting
            </div>
            <div class="space-y-3">
              <h1 class="text-3xl font-black tracking-tight sm:text-4xl">Revenue report</h1>
              <p class="max-w-2xl text-sm leading-6 text-slate-300">
                Review confirmed booking revenue, track the booking mix behind it, and export a date-filtered ledger for finance follow-up.
              </p>
            </div>
            <div class="flex flex-wrap gap-3">
              <Link
                :href="route('finance.dashboard')"
                class="inline-flex items-center gap-2 rounded-2xl bg-white px-5 py-3 text-sm font-bold text-slate-900 transition hover:bg-slate-100"
              >
                Open finance dashboard
                <ArrowUpRight class="h-4 w-4" />
              </Link>
              <a
                :href="exportHref"
                class="inline-flex items-center gap-2 rounded-2xl border border-white/15 px-5 py-3 text-sm font-bold text-white transition hover:bg-white/10"
              >
                Export XLSX
                <Download class="h-4 w-4" />
              </a>
            </div>
          </div>

          <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
            <div class="rounded-[1.75rem] border border-white/10 bg-white/10 p-5 backdrop-blur">
              <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-300">Recognized revenue</p>
              <p class="mt-3 text-4xl font-black">{{ formatCurrency(summary.revenue) }}</p>
              <p class="mt-2 text-sm text-slate-300">
                {{ summary.bookings }} confirmed bookings in the selected reporting range.
              </p>
            </div>

            <div class="rounded-[1.75rem] border border-emerald-300/25 bg-gradient-to-br from-emerald-400/20 to-emerald-300/5 p-5">
              <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-100">Latest recognized booking</p>
              <p class="mt-3 text-2xl font-black">{{ summary.latest_booking_at ? formatDate(summary.latest_booking_at) : 'No records' }}</p>
              <p class="mt-2 text-sm text-emerald-100/80">Most recent confirmed booking captured by this report.</p>
            </div>
          </div>
        </div>
      </section>

      <section class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        <Link
          v-for="card in cards"
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

      <form class="grid gap-3 rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm lg:grid-cols-4" @submit.prevent="applyFilters">
        <input
          name="search"
          :value="filters.search ?? ''"
          type="text"
          placeholder="Search booking code, guest name, or email"
          class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 lg:col-span-2"
        />

        <input
          name="from"
          :value="filters.from ?? ''"
          type="date"
          class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
        />

        <input
          name="to"
          :value="filters.to ?? ''"
          type="date"
          class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
        />

        <div class="flex gap-2 lg:col-span-4 lg:justify-end">
          <button
            type="button"
            class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="isFiltering"
            @click="resetFilters"
          >
            Reset
          </button>
          <button
            type="submit"
            class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-600 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="isFiltering"
          >
            {{ isFiltering ? 'Applying…' : 'Apply filters' }}
          </button>
        </div>
      </form>

      <TrendChart title="Revenue Trend" :endpoint="`/${props.routePrefix}/reports/charts/revenue`" />

      <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Revenue ledger</p>
            <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-900">Confirmed booking revenue</h2>
          </div>
          <p class="text-sm text-slate-500">Showing {{ rows.data.length }} records on this page.</p>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">
              <tr>
                <th class="px-6 py-4">Booking</th>
                <th class="px-6 py-4">Guest</th>
                <th class="px-6 py-4">Room</th>
                <th class="px-6 py-4">Stay</th>
                <th class="px-6 py-4">Revenue</th>
                <th class="px-6 py-4">Created</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="row in rows.data"
                :key="row.id"
                class="align-top transition hover:bg-slate-50/80"
              >
                <td class="px-6 py-4">
                  <div class="space-y-1">
                    <p class="font-semibold text-slate-900">{{ row.booking_code || `Booking #${row.id}` }}</p>
                    <p class="text-xs text-slate-500">Record ID {{ row.id }}</p>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <div class="space-y-1">
                    <p class="font-medium text-slate-800">{{ guestLabel(row) }}</p>
                    <p class="text-xs text-slate-500">{{ row.guest_email || row.user?.email || 'No email' }}</p>
                  </div>
                </td>
                <td class="px-6 py-4 text-slate-700">{{ roomLabel(row) }}</td>
                <td class="px-6 py-4 text-slate-700">
                  {{ row.check_in ? formatDate(row.check_in) : 'Not set' }} to {{ row.check_out ? formatDate(row.check_out) : 'Not set' }}
                </td>
                <td class="px-6 py-4 font-bold text-slate-900">{{ formatCurrency(row.total_amount) }}</td>
                <td class="px-6 py-4 text-slate-600">{{ formatDateTime(row.created_at) }}</td>
              </tr>

              <tr v-if="rows.data.length === 0">
                <td colspan="6" class="px-6 py-16 text-center text-slate-500">
                  No confirmed booking revenue matches the selected filters.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <Pagination :links="rows.links" />
    </div>
  </ManagerLayout>
</template>
