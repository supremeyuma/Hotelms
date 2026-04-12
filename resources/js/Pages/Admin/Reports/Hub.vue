<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import {
  Activity,
  ArrowRight,
  CalendarRange,
  CreditCard,
  DoorClosed,
  Hotel,
  Receipt,
  Search,
  Users,
  Wallet,
} from 'lucide-vue-next'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'

const props = defineProps({
  filters: { type: Object, required: true },
  period: { type: Object, required: true },
  roomOptions: { type: Array, default: () => [] },
  hotelSummary: { type: Object, required: true },
  roomReport: { type: Object, default: null },
  departmentReports: { type: Array, default: () => [] },
  bookings: { type: Array, default: () => [] },
  charges: { type: Array, default: () => [] },
  payments: { type: Array, default: () => [] },
  reportLinks: { type: Array, default: () => [] },
})

const form = useForm({
  mode: props.filters.mode ?? 'day',
  day: props.filters.day ?? '',
  from: props.filters.from ?? '',
  to: props.filters.to ?? '',
  room_id: props.filters.room_id ?? '',
})

const isRangeMode = computed(() => form.mode === 'range')
const selectedRoomLabel = computed(() => props.period.selected_room?.label ?? 'All rooms')

const summaryCards = computed(() => [
  {
    label: 'Bookings in scope',
    value: Number(props.hotelSummary.bookings ?? 0).toLocaleString(),
    note: `${Number(props.hotelSummary.guest_volume ?? 0).toLocaleString()} guests across ${Number(props.hotelSummary.distinct_rooms ?? 0).toLocaleString()} rooms`,
    icon: Hotel,
    href: '#bookings-section',
    tone: 'indigo',
  },
  {
    label: 'Guest movement',
    value: `${Number(props.hotelSummary.arrivals ?? 0).toLocaleString()} / ${Number(props.hotelSummary.departures ?? 0).toLocaleString()}`,
    note: 'Arrivals and departures in the selected period',
    icon: Activity,
    href: '#departments-section',
    tone: 'emerald',
  },
  {
    label: 'Charges posted',
    value: formatCurrency(props.hotelSummary.charges_posted ?? 0),
    note: 'All posted charge lines within the selected report window',
    icon: Receipt,
    href: '#charges-section',
    tone: 'amber',
  },
  {
    label: 'Payments collected',
    value: formatCurrency(props.hotelSummary.payments_received ?? 0),
    note: 'Successful or completed payments captured in the period',
    icon: CreditCard,
    href: '#payments-section',
    tone: 'sky',
  },
  {
    label: 'Outstanding exposure',
    value: formatCurrency(props.hotelSummary.outstanding_exposure ?? 0),
    note: 'Open balances across the bookings included in this report',
    icon: Wallet,
    href: '#bookings-section',
    tone: 'rose',
  },
  {
    label: 'Booking value',
    value: formatCurrency(props.hotelSummary.booking_value ?? 0),
    note: 'Booked stay value tied to the stays covered by this report',
    icon: Users,
    href: '#bookings-section',
    tone: 'violet',
  },
])

function submitFilters() {
  const payload = {
    mode: form.mode,
    room_id: form.room_id || undefined,
  }

  if (form.mode === 'range') {
    payload.from = form.from
    payload.to = form.to
  } else {
    payload.day = form.day
  }

  form
    .transform(() => payload)
    .get(route('admin.reports.dashboard'), {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: ['filters', 'period', 'roomOptions', 'hotelSummary', 'roomReport', 'departmentReports', 'bookings', 'charges', 'payments', 'reportLinks'],
    })
}

function resetFilters() {
  form.mode = 'day'
  form.day = new Date().toISOString().slice(0, 10)
  form.from = new Date().toISOString().slice(0, 10)
  form.to = new Date().toISOString().slice(0, 10)
  form.room_id = ''
  submitFilters()
}

function toneClasses(tone) {
  const tones = {
    indigo: 'from-indigo-500/15 via-indigo-500/5 to-white text-indigo-600 ring-indigo-100',
    emerald: 'from-emerald-500/15 via-emerald-500/5 to-white text-emerald-600 ring-emerald-100',
    amber: 'from-amber-500/15 via-amber-500/5 to-white text-amber-600 ring-amber-100',
    sky: 'from-sky-500/15 via-sky-500/5 to-white text-sky-600 ring-sky-100',
    rose: 'from-rose-500/15 via-rose-500/5 to-white text-rose-600 ring-rose-100',
    violet: 'from-violet-500/15 via-violet-500/5 to-white text-violet-600 ring-violet-100',
  }

  return tones[tone] ?? 'from-slate-500/10 via-slate-500/5 to-white text-slate-600 ring-slate-100'
}

function formatCurrency(value) {
  return `NGN ${Number(value ?? 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`
}

function formatDate(value, options = {}) {
  if (!value) {
    return 'Not recorded'
  }

  return new Date(value).toLocaleString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    ...options,
  })
}

function statusClasses(status) {
  const value = String(status ?? '').toLowerCase()

  if (['successful', 'completed', 'paid', 'checked_in', 'resolved', 'delivered'].includes(value)) {
    return 'bg-emerald-100 text-emerald-700'
  }

  if (['pending', 'processing', 'confirmed', 'unpaid', 'postpaid'].includes(value)) {
    return 'bg-amber-100 text-amber-700'
  }

  if (['cancelled', 'failed', 'closed'].includes(value)) {
    return 'bg-rose-100 text-rose-700'
  }

  return 'bg-slate-100 text-slate-700'
}
</script>

<template>
  <ManagerLayout>
    <Head title="Reports" />

    <div class="space-y-8">
      <section class="overflow-hidden rounded-[2rem] bg-slate-900 text-white shadow-2xl shadow-slate-200">
        <div class="grid gap-8 px-6 py-8 sm:px-8 xl:grid-cols-[1.25fr_0.75fr]">
          <div class="space-y-5">
            <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-[11px] font-black uppercase tracking-[0.22em] text-slate-200">
              <CalendarRange class="h-3.5 w-3.5" />
              Unified reporting
            </div>

            <div class="space-y-3">
              <h1 class="text-3xl font-black tracking-tight sm:text-4xl">Reports</h1>
              <p class="max-w-3xl text-sm leading-6 text-slate-300">
                Review hotel-wide performance for a day or date range, then narrow the same report to one room when you need guest stay detail, posted charges, payments, and operational activity in one place.
              </p>
            </div>

            <div class="flex flex-wrap gap-3">
              <a
                href="#filters-section"
                class="inline-flex items-center gap-2 rounded-2xl bg-white px-5 py-3 text-sm font-bold text-slate-900 transition hover:bg-slate-100"
              >
                Adjust report filters
                <Search class="h-4 w-4" />
              </a>
              <a
                href="#bookings-section"
                class="inline-flex items-center gap-2 rounded-2xl border border-white/15 px-5 py-3 text-sm font-bold text-white transition hover:bg-white/10"
              >
                View booking detail
                <ArrowRight class="h-4 w-4" />
              </a>
            </div>
          </div>

          <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
            <div class="rounded-[1.75rem] border border-white/10 bg-white/10 p-5 backdrop-blur">
              <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-300">Reporting window</p>
              <p class="mt-3 text-2xl font-black">{{ period.label }}</p>
              <p class="mt-2 text-sm text-slate-300">
                {{ period.mode === 'range' ? 'Date range report' : 'Single-day report' }}
              </p>
            </div>

            <div class="rounded-[1.75rem] border border-cyan-300/20 bg-gradient-to-br from-cyan-400/15 to-slate-900 p-5">
              <p class="text-[11px] font-black uppercase tracking-[0.22em] text-cyan-100">Room focus</p>
              <p class="mt-3 text-2xl font-black">{{ selectedRoomLabel }}</p>
              <p class="mt-2 text-sm text-slate-200">
                {{ period.selected_room ? 'Room-specific operational report' : 'Hotel-wide report across all rooms' }}
              </p>
            </div>
          </div>
        </div>
      </section>

      <section
        id="filters-section"
        class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm"
      >
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Filter report</p>
            <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-900">Choose period and room</h2>
          </div>
          <p class="text-sm text-slate-500">
            Managers and MDs can switch between day-wide hotel reporting and room-specific reporting here.
          </p>
        </div>

        <form class="mt-6 grid gap-4 lg:grid-cols-5" @submit.prevent="submitFilters">
          <label class="space-y-2">
            <span class="text-sm font-semibold text-slate-700">Report mode</span>
            <select
              v-model="form.mode"
              class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400"
            >
              <option value="day">One day</option>
              <option value="range">Date range</option>
            </select>
          </label>

          <label v-if="!isRangeMode" class="space-y-2">
            <span class="text-sm font-semibold text-slate-700">Day</span>
            <input
              v-model="form.day"
              type="date"
              class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400"
            >
          </label>

          <label v-else class="space-y-2">
            <span class="text-sm font-semibold text-slate-700">From</span>
            <input
              v-model="form.from"
              type="date"
              class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400"
            >
          </label>

          <label v-if="isRangeMode" class="space-y-2">
            <span class="text-sm font-semibold text-slate-700">To</span>
            <input
              v-model="form.to"
              type="date"
              class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400"
            >
          </label>

          <label class="space-y-2 lg:col-span-2">
            <span class="text-sm font-semibold text-slate-700">Room</span>
            <select
              v-model="form.room_id"
              class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400"
            >
              <option value="">All rooms</option>
              <option
                v-for="room in roomOptions"
                :key="room.id"
                :value="String(room.id)"
              >
                {{ room.label }}
              </option>
            </select>
          </label>

          <div class="flex flex-wrap items-end gap-3 lg:col-span-5">
            <button
              type="submit"
              :disabled="form.processing"
              class="inline-flex items-center gap-2 rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <Search class="h-4 w-4" />
              {{ form.processing ? 'Loading report...' : 'Apply filters' }}
            </button>

            <button
              type="button"
              :disabled="form.processing"
              class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
              @click="resetFilters"
            >
              Reset
            </button>
          </div>
        </form>
      </section>

      <section class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        <a
          v-for="card in summaryCards"
          :key="card.label"
          :href="card.href"
          class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md"
        >
          <div class="rounded-[1.5rem] bg-gradient-to-br p-5 ring-1" :class="toneClasses(card.tone)">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">{{ card.label }}</p>
                <p class="mt-3 text-3xl font-black tracking-tight text-slate-950">{{ card.value }}</p>
                <p class="mt-2 text-sm font-medium text-slate-600">{{ card.note }}</p>
              </div>
              <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/80 shadow-sm">
                <component :is="card.icon" class="h-5 w-5" />
              </div>
            </div>
          </div>
        </a>
      </section>

      <section
        v-if="roomReport"
        id="room-report"
        class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm"
      >
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Room report</p>
            <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-900">{{ roomReport.label }}</h2>
          </div>
          <Link
            :href="route('admin.rooms.index')"
            class="inline-flex items-center gap-2 text-sm font-bold text-indigo-600 hover:text-indigo-700"
          >
            Open rooms
            <ArrowRight class="h-4 w-4" />
          </Link>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <div class="rounded-[1.5rem] bg-slate-50 p-5">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Bookings</p>
            <p class="mt-3 text-3xl font-black text-slate-900">{{ roomReport.bookings }}</p>
            <p class="mt-2 text-sm text-slate-500">{{ roomReport.guest_volume }} guests</p>
          </div>
          <div class="rounded-[1.5rem] bg-slate-50 p-5">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Cash in</p>
            <p class="mt-3 text-3xl font-black text-slate-900">{{ formatCurrency(roomReport.payments_received) }}</p>
            <p class="mt-2 text-sm text-slate-500">Payments recorded for the selected room</p>
          </div>
          <div class="rounded-[1.5rem] bg-slate-50 p-5">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Charges</p>
            <p class="mt-3 text-3xl font-black text-slate-900">{{ formatCurrency(roomReport.charges_posted) }}</p>
            <p class="mt-2 text-sm text-slate-500">Posted room-related charges</p>
          </div>
          <div class="rounded-[1.5rem] bg-slate-50 p-5">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Operational load</p>
            <p class="mt-3 text-3xl font-black text-slate-900">{{ roomReport.service_orders + roomReport.laundry_orders + roomReport.maintenance_tickets }}</p>
            <p class="mt-2 text-sm text-slate-500">Orders, laundry, and maintenance items</p>
          </div>
        </div>
      </section>

      <section
        id="departments-section"
        class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm"
      >
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Department overview</p>
            <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-900">Department-wide report signals</h2>
          </div>
          <p class="text-sm text-slate-500">
            These cards summarize the departments that usually need a manager or MD review first.
          </p>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <article
            v-for="department in departmentReports"
            :key="department.name"
            class="rounded-[1.5rem] border border-slate-200 p-5"
          >
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-sm font-bold text-slate-900">{{ department.name }}</p>
                <p class="mt-2 text-sm leading-6 text-slate-500">{{ department.summary }}</p>
              </div>
              <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-100 text-slate-700">
                <DoorClosed class="h-4 w-4" />
              </div>
            </div>

            <div class="mt-5 grid grid-cols-3 gap-3">
              <div class="rounded-2xl bg-slate-50 p-3">
                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">{{ department.primary_label }}</p>
                <p class="mt-2 text-2xl font-black text-slate-900">{{ department.primary_metric }}</p>
              </div>
              <div class="rounded-2xl bg-slate-50 p-3">
                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">{{ department.secondary_label }}</p>
                <p class="mt-2 text-2xl font-black text-slate-900">{{ department.secondary_metric }}</p>
              </div>
              <div class="rounded-2xl bg-slate-50 p-3">
                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">{{ department.tertiary_label }}</p>
                <p class="mt-2 text-2xl font-black text-slate-900">{{ department.tertiary_metric }}</p>
              </div>
            </div>

            <div v-if="department.amount_label" class="mt-4 rounded-2xl bg-slate-900 px-4 py-3 text-white">
              <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-300">{{ department.amount_label }}</p>
              <p class="mt-2 text-xl font-black">{{ formatCurrency(department.amount) }}</p>
            </div>
          </article>
        </div>
      </section>

      <section
        id="bookings-section"
        class="rounded-[2rem] border border-slate-200 bg-white shadow-sm"
      >
        <div class="flex flex-col gap-3 border-b border-slate-100 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Booking detail</p>
            <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-900">Stay and guest report detail</h2>
          </div>
          <Link
            :href="route('admin.bookings.index')"
            class="inline-flex items-center gap-2 text-sm font-bold text-indigo-600 hover:text-indigo-700"
          >
            Open booking board
            <ArrowRight class="h-4 w-4" />
          </Link>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">
              <tr>
                <th class="px-6 py-4">Booking</th>
                <th class="px-6 py-4">Room</th>
                <th class="px-6 py-4">Stay</th>
                <th class="px-6 py-4">Status</th>
                <th class="px-6 py-4 text-right">Booked</th>
                <th class="px-6 py-4 text-right">Charges</th>
                <th class="px-6 py-4 text-right">Payments</th>
                <th class="px-6 py-4 text-right">Outstanding</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="booking in bookings"
                :key="booking.id"
                class="align-top transition hover:bg-slate-50/80"
              >
                <td class="px-6 py-4">
                  <div class="space-y-1">
                    <p class="font-bold text-slate-900">{{ booking.booking_code }}</p>
                    <p class="text-sm text-slate-700">{{ booking.guest_name }}</p>
                    <p class="text-xs text-slate-500">{{ booking.guest_email || booking.guest_phone || 'No guest contact recorded' }}</p>
                  </div>
                </td>
                <td class="px-6 py-4 text-slate-700">{{ booking.room_summary || 'Unassigned room' }}</td>
                <td class="px-6 py-4">
                  <div class="space-y-1 text-slate-700">
                    <p>{{ formatDate(booking.check_in) }} to {{ formatDate(booking.check_out) }}</p>
                    <p class="text-xs text-slate-500">
                      Check-in: {{ formatDate(booking.actual_check_in, { hour: 'numeric', minute: '2-digit' }) }}
                    </p>
                    <p class="text-xs text-slate-500">
                      Check-out: {{ formatDate(booking.actual_check_out, { hour: 'numeric', minute: '2-digit' }) }}
                    </p>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <div class="space-y-2">
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold" :class="statusClasses(booking.status)">
                      {{ booking.status }}
                    </span>
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold" :class="statusClasses(booking.payment_status)">
                      {{ booking.payment_status }}
                    </span>
                  </div>
                </td>
                <td class="px-6 py-4 text-right font-bold text-slate-900">{{ formatCurrency(booking.booked_amount) }}</td>
                <td class="px-6 py-4 text-right font-bold text-slate-900">{{ formatCurrency(booking.extra_charges) }}</td>
                <td class="px-6 py-4 text-right font-bold text-slate-900">{{ formatCurrency(booking.payments_received) }}</td>
                <td class="px-6 py-4 text-right font-bold text-slate-900">{{ formatCurrency(booking.outstanding_balance) }}</td>
              </tr>

              <tr v-if="bookings.length === 0">
                <td colspan="8" class="px-6 py-16 text-center text-slate-500">
                  No bookings matched the selected day, range, and room filter.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="grid gap-6 xl:grid-cols-2">
        <div
          id="payments-section"
          class="rounded-[2rem] border border-slate-200 bg-white shadow-sm"
        >
          <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-6 py-5">
            <div>
              <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Payments</p>
              <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-900">Collected payments</h2>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-slate-50 text-left text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">
                <tr>
                  <th class="px-6 py-4">Reference</th>
                  <th class="px-6 py-4">Guest</th>
                  <th class="px-6 py-4">Room</th>
                  <th class="px-6 py-4">Method</th>
                  <th class="px-6 py-4 text-right">Amount</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="payment in payments" :key="payment.id" class="transition hover:bg-slate-50/80">
                  <td class="px-6 py-4">
                    <div class="space-y-1">
                      <p class="font-semibold text-slate-900">{{ payment.reference || payment.booking_code || `PAY-${payment.id}` }}</p>
                      <p class="text-xs text-slate-500">{{ formatDate(payment.recorded_at, { hour: 'numeric', minute: '2-digit' }) }}</p>
                    </div>
                  </td>
                  <td class="px-6 py-4 text-slate-700">{{ payment.guest_name }}</td>
                  <td class="px-6 py-4 text-slate-700">{{ payment.room_label }}</td>
                  <td class="px-6 py-4">
                    <div class="space-y-1">
                      <p class="font-medium text-slate-700">{{ payment.method }}</p>
                      <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold" :class="statusClasses(payment.status)">
                        {{ payment.status }}
                      </span>
                    </div>
                  </td>
                  <td class="px-6 py-4 text-right font-bold text-slate-900">{{ formatCurrency(payment.amount) }}</td>
                </tr>

                <tr v-if="payments.length === 0">
                  <td colspan="5" class="px-6 py-16 text-center text-slate-500">
                    No successful payments were recorded in this report window.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div
          id="charges-section"
          class="rounded-[2rem] border border-slate-200 bg-white shadow-sm"
        >
          <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-6 py-5">
            <div>
              <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Charges</p>
              <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-900">Posted charges</h2>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-slate-50 text-left text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">
                <tr>
                  <th class="px-6 py-4">Description</th>
                  <th class="px-6 py-4">Guest</th>
                  <th class="px-6 py-4">Room</th>
                  <th class="px-6 py-4">Status</th>
                  <th class="px-6 py-4 text-right">Amount</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="charge in charges" :key="charge.id" class="transition hover:bg-slate-50/80">
                  <td class="px-6 py-4">
                    <div class="space-y-1">
                      <p class="font-semibold text-slate-900">{{ charge.description }}</p>
                      <p class="text-xs text-slate-500">{{ charge.booking_code || `CHG-${charge.id}` }} - {{ formatDate(charge.created_at, { hour: 'numeric', minute: '2-digit' }) }}</p>
                    </div>
                  </td>
                  <td class="px-6 py-4 text-slate-700">{{ charge.guest_name }}</td>
                  <td class="px-6 py-4 text-slate-700">{{ charge.room_label }}</td>
                  <td class="px-6 py-4">
                    <div class="space-y-2">
                      <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold" :class="statusClasses(charge.status)">
                        {{ charge.status }}
                      </span>
                      <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold" :class="statusClasses(charge.payment_mode)">
                        {{ charge.payment_mode }}
                      </span>
                    </div>
                  </td>
                  <td class="px-6 py-4 text-right font-bold text-slate-900">{{ formatCurrency(charge.amount) }}</td>
                </tr>

                <tr v-if="charges.length === 0">
                  <td colspan="5" class="px-6 py-16 text-center text-slate-500">
                    No charges were posted in the selected report window.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Related reports</p>
            <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-900">Open a specialist report when needed</h2>
          </div>
          <p class="text-sm text-slate-500">
            The main reports page now acts as the landing page, but the specialist views are still available.
          </p>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <Link
            v-for="link in reportLinks"
            :key="link.label"
            :href="link.href"
            class="rounded-[1.5rem] border border-slate-200 p-5 transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md"
          >
            <p class="text-sm font-bold text-slate-900">{{ link.label }}</p>
            <p class="mt-2 text-sm leading-6 text-slate-500">{{ link.description }}</p>
            <div class="mt-4 inline-flex items-center gap-2 text-sm font-bold text-indigo-600">
              Open report
              <ArrowRight class="h-4 w-4" />
            </div>
          </Link>
        </div>
      </section>
    </div>
  </ManagerLayout>
</template>
