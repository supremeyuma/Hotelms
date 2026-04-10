<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import {
  Activity,
  ArrowUpRight,
  BedDouble,
  BellRing,
  BriefcaseBusiness,
  CalendarDays,
  ClipboardList,
  DoorOpen,
  Hotel,
  Sparkles,
  Wrench,
} from 'lucide-vue-next'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'

const props = defineProps({
  stats: { type: Object, required: true },
  todayLabel: { type: String, default: '' },
  focusItems: { type: Array, default: () => [] },
  quickLinks: { type: Array, default: () => [] },
  recentBookings: { type: Array, default: () => [] },
  isExecutive: Boolean,
  departmentSnapshots: { type: Array, default: () => [] },
  reportLinks: { type: Array, default: () => [] },
})

const primaryMetrics = computed(() => [
  {
    label: 'Occupancy',
    value: `${props.stats.occupancy_rate}%`,
    icon: Hotel,
    route: route('admin.rooms.index'),
  },
  {
    label: 'Available rooms',
    value: props.stats.available_rooms,
    icon: DoorOpen,
    route: route('admin.rooms.index'),
  },
  {
    label: 'Arrivals today',
    value: props.stats.arrivals_today,
    icon: CalendarDays,
    route: route('admin.bookings.index'),
  },
  {
    label: 'Departures today',
    value: props.stats.departures_today,
    icon: BriefcaseBusiness,
    route: route('admin.bookings.index'),
  },
])

function badgeClasses(status) {
  const value = String(status || '').toLowerCase()

  if (['active', 'checked_in', 'paid', 'completed', 'resolved'].includes(value)) {
    return 'bg-emerald-100 text-emerald-700'
  }

  if (['confirmed', 'pending', 'processing', 'acknowledged'].includes(value)) {
    return 'bg-amber-100 text-amber-700'
  }

  if (['cancelled', 'failed', 'unpaid', 'overdue'].includes(value)) {
    return 'bg-rose-100 text-rose-700'
  }

  return 'bg-slate-100 text-slate-700'
}

function cardToneClasses(tone) {
  const tones = {
    indigo: 'from-indigo-500/15 via-indigo-500/5 to-white text-indigo-600 ring-indigo-100',
    emerald: 'from-emerald-500/15 via-emerald-500/5 to-white text-emerald-600 ring-emerald-100',
    amber: 'from-amber-500/15 via-amber-500/5 to-white text-amber-600 ring-amber-100',
    rose: 'from-rose-500/15 via-rose-500/5 to-white text-rose-600 ring-rose-100',
    sky: 'from-sky-500/15 via-sky-500/5 to-white text-sky-600 ring-sky-100',
    violet: 'from-violet-500/15 via-violet-500/5 to-white text-violet-600 ring-violet-100',
  }

  return tones[tone] ?? 'from-slate-500/10 via-slate-500/5 to-white text-slate-600 ring-slate-100'
}
</script>

<template>
  <ManagerLayout>
    <Head title="Operations Dashboard" />

    <div class="space-y-8">
      <section class="overflow-hidden rounded-[2rem] bg-slate-900 text-white shadow-2xl shadow-slate-200">
        <div class="grid gap-8 px-6 py-8 sm:px-8 xl:grid-cols-[1.4fr_0.8fr]">
          <div class="space-y-5">
            <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-[11px] font-black uppercase tracking-[0.22em] text-slate-200">
              <Sparkles class="h-3.5 w-3.5" />
              Manager Operations Console
            </div>

            <div class="space-y-3">
              <h1 class="max-w-3xl text-3xl font-black tracking-tight sm:text-4xl">
                Run the hotel from one screen, with the issues and flow that matter right now.
              </h1>
            </div>

            <div class="flex flex-wrap gap-3">
              <Link
                :href="route('admin.bookings.index')"
                class="inline-flex items-center gap-2 rounded-2xl bg-white px-5 py-3 text-sm font-bold text-slate-900 transition hover:bg-slate-100"
              >
                Open booking board
                <ArrowUpRight class="h-4 w-4" />
              </Link>
              <Link
                :href="route('admin.reports.dashboard')"
                class="inline-flex items-center gap-2 rounded-2xl border border-white/15 px-5 py-3 text-sm font-bold text-white transition hover:bg-white/10"
              >
                View reports
                <Activity class="h-4 w-4" />
              </Link>
              <Link
                :href="route('admin.staff.index')"
                class="inline-flex items-center gap-2 rounded-2xl border border-emerald-300/40 bg-emerald-400/15 px-5 py-3 text-sm font-bold text-emerald-50 transition hover:bg-emerald-400/25"
              >
                Manage staff
                <ArrowUpRight class="h-4 w-4" />
              </Link>
            </div>
          </div>

          <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
            <div class="rounded-[1.75rem] border border-white/10 bg-white/10 p-5 backdrop-blur">
              <div class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.22em] text-slate-300">
                <CalendarDays class="h-3.5 w-3.5" />
                Today
              </div>
              <p class="mt-3 text-2xl font-black">{{ todayLabel }}</p>
            </div>

            <div class="rounded-[1.75rem] border border-white/10 bg-gradient-to-br from-amber-400/20 to-rose-400/10 p-5">
              <div class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.22em] text-amber-200">
                <BellRing class="h-3.5 w-3.5" />
                Pressure points
              </div>
              <div class="mt-4 grid grid-cols-2 gap-4">
                <div>
                  <p class="text-2xl font-black">{{ stats.open_guest_requests }}</p>
                  <p class="text-xs text-slate-300">Open requests</p>
                </div>
                <Link :href="route('admin.maintenance.index', { filter: 'open' })" class="block rounded-xl px-2 py-1 transition hover:bg-white/10">
                  <p class="text-2xl font-black">{{ stats.open_maintenance }}</p>
                  <p class="text-xs text-slate-300">Maintenance issues</p>
                </Link>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
        <Link
          v-for="metric in primaryMetrics"
          :key="metric.label"
          :href="metric.route"
          class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md"
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">
                {{ metric.label }}
              </p>
              <p class="mt-4 text-3xl font-black tracking-tight text-slate-900">
                {{ metric.value }}
              </p>
            </div>
            <div
              class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-slate-700"
            >
              <component :is="metric.icon" class="h-5 w-5" />
            </div>
          </div>
          <div class="mt-3 flex items-center justify-end">
            <ArrowUpRight class="h-4 w-4 text-slate-300" />
          </div>
        </Link>
      </section>

      <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1.15fr_0.85fr]">
        <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
          <div class="flex items-center justify-between gap-4">
            <div>
              <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Action board</p>
              <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-900">Operational watchlist</h2>
            </div>
            <Link
              :href="route('admin.reports.dashboard')"
              class="text-sm font-bold text-indigo-600 hover:text-indigo-700"
            >
              Open reports
            </Link>
          </div>

          <div class="mt-6 grid gap-4 md:grid-cols-2">
            <Link
              v-for="item in focusItems"
              :key="item.label"
              :href="item.route"
              class="rounded-[1.5rem] border border-slate-200 bg-gradient-to-br p-5 transition hover:-translate-y-0.5 hover:shadow-md"
              :class="cardToneClasses(item.tone)"
            >
              <p class="text-sm font-bold text-slate-700">{{ item.label }}</p>
              <div class="mt-4 flex items-end justify-between gap-4">
                <p class="text-4xl font-black tracking-tight text-slate-950">{{ item.value }}</p>
                <ArrowUpRight class="h-5 w-5 text-slate-400" />
              </div>
            </Link>
          </div>
        </div>

        <div class="space-y-6">
          <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center gap-3">
              <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-slate-700">
                <ClipboardList class="h-5 w-5" />
              </div>
              <div>
                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Quick access</p>
                <h2 class="text-xl font-black tracking-tight text-slate-900">Manager shortcuts</h2>
              </div>
            </div>

            <div class="mt-5 space-y-3">
              <Link
                v-for="link in quickLinks"
                :key="link.label"
                :href="link.route"
                class="flex items-start justify-between gap-4 rounded-[1.25rem] border border-slate-200 px-4 py-4 transition hover:border-slate-300 hover:bg-slate-50"
              >
                <div>
                  <p class="font-bold text-slate-900">{{ link.label }}</p>
                  <p class="mt-1 text-sm text-slate-500">{{ link.description }}</p>
                </div>
                <ArrowUpRight class="h-4 w-4 text-slate-400" />
              </Link>
            </div>
          </div>

          <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center gap-3">
              <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-rose-100 text-rose-600">
                <Wrench class="h-5 w-5" />
              </div>
              <div>
                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Queue summary</p>
                <h2 class="text-xl font-black tracking-tight text-slate-900">Live workload</h2>
              </div>
            </div>

            <div class="mt-5 grid grid-cols-2 gap-4">
              <Link :href="route('clean.dashboard')" class="rounded-[1.5rem] bg-slate-50 p-4 transition hover:bg-slate-100">
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Cleaning</p>
                <p class="mt-2 text-3xl font-black text-slate-900">{{ stats.cleaning_backlog }}</p>
              </Link>
              <Link :href="route('admin.bookings.index')" class="rounded-[1.5rem] bg-slate-50 p-4 transition hover:bg-slate-100">
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Service orders</p>
                <p class="mt-2 text-3xl font-black text-slate-900">{{ stats.pending_service_orders }}</p>
              </Link>
              <Link :href="route('frontdesk.dashboard')" class="rounded-[1.5rem] bg-slate-50 p-4 transition hover:bg-slate-100">
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Guest requests</p>
                <p class="mt-2 text-3xl font-black text-slate-900">{{ stats.open_guest_requests }}</p>
              </Link>
              <Link :href="route('admin.maintenance.index', { filter: 'open' })" class="rounded-[1.5rem] bg-slate-50 p-4 transition hover:bg-slate-100">
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Maintenance</p>
                <p class="mt-2 text-3xl font-black text-slate-900">{{ stats.open_maintenance }}</p>
              </Link>
              <Link :href="route('admin.bookings.index')" class="rounded-[1.5rem] bg-slate-50 p-4 transition hover:bg-slate-100">
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Unsettled stays</p>
                <p class="mt-2 text-3xl font-black text-slate-900">{{ stats.unsettled_bookings }}</p>
              </Link>
            </div>
          </div>
        </div>
      </section>

      <section
        v-if="isExecutive && departmentSnapshots.length"
        class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm"
      >
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Executive overview</p>
            <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-900">Department snapshot</h2>
          </div>

          <div class="flex flex-wrap gap-2">
            <Link
              v-for="report in reportLinks"
              :key="report.label"
              :href="report.route"
              class="rounded-full bg-slate-100 px-4 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-200"
            >
              {{ report.label }}
            </Link>
          </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
          <Link
            v-for="snapshot in departmentSnapshots"
            :key="snapshot.name"
            :href="snapshot.route"
            class="rounded-[1.5rem] border border-slate-200 p-5 transition hover:-translate-y-0.5 hover:shadow-md"
          >
            <p class="text-sm font-bold text-slate-500">{{ snapshot.name }}</p>
            <p class="mt-3 text-3xl font-black tracking-tight text-slate-900">{{ snapshot.metric }}</p>
            <p class="mt-2 text-sm text-slate-500">{{ snapshot.secondary }}</p>
          </Link>
        </div>
      </section>

      <section class="rounded-[2rem] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Booking activity</p>
            <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-900">Recent bookings</h2>
          </div>
          <Link
            :href="route('admin.bookings.index')"
            class="inline-flex items-center gap-2 text-sm font-bold text-indigo-600 hover:text-indigo-700"
          >
            View all bookings
            <ArrowUpRight class="h-4 w-4" />
          </Link>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">
              <tr>
                <th class="px-6 py-4">Booking</th>
                <th class="px-6 py-4">Guest</th>
                <th class="px-6 py-4">Room</th>
                <th class="px-6 py-4">Stay</th>
                <th class="px-6 py-4">Status</th>
                <th class="px-6 py-4">Payment</th>
                <th class="px-6 py-4 text-right">Amount</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="booking in recentBookings" :key="booking.id" class="hover:bg-slate-50/80">
                <td class="px-6 py-4">
                  <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-100 text-slate-700">
                      <BedDouble class="h-4 w-4" />
                    </div>
                    <div>
                      <p class="font-bold text-slate-900">{{ booking.booking_code }}</p>
                      <p class="text-xs text-slate-500">Reservation reference</p>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 font-medium text-slate-700">{{ booking.guest_name }}</td>
                <td class="px-6 py-4 text-slate-600">{{ booking.room_name }}</td>
                <td class="px-6 py-4 text-slate-600">
                  {{ booking.check_in }} to {{ booking.check_out }}
                </td>
                <td class="px-6 py-4">
                  <span class="rounded-full px-3 py-1 text-xs font-bold" :class="badgeClasses(booking.status)">
                    {{ booking.status }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <span class="rounded-full px-3 py-1 text-xs font-bold" :class="badgeClasses(booking.payment_status)">
                    {{ booking.payment_status }}
                  </span>
                </td>
                <td class="px-6 py-4 text-right font-bold text-slate-900">
                  NGN {{ Number(booking.total_amount || 0).toLocaleString() }}
                </td>
              </tr>
              <tr v-if="!recentBookings.length">
                <td colspan="7" class="px-6 py-16 text-center text-slate-500">
                  No recent bookings available yet.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </ManagerLayout>
</template>
