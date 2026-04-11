<script setup>
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, Link } from '@inertiajs/vue3'
import {
  ArrowRight,
  BedDouble,
  BriefcaseBusiness,
  CalendarDays,
  CircleDollarSign,
  Clock3,
  CreditCard,
  Mail,
  Phone,
  UserRound,
} from 'lucide-vue-next'

const props = defineProps({
  bookings: { type: Object, required: true },
  summary: { type: Object, required: true },
  filters: { type: Object, required: true },
  todayLabel: { type: String, default: '' },
})

const summaryCards = [
  {
    key: 'arrivals_today',
    label: 'Arrivals today',
    value: props.summary.arrivals_today,
    helper: props.todayLabel,
    icon: CalendarDays,
    tone: 'indigo',
  },
  {
    key: 'in_house',
    label: 'In house',
    value: props.summary.in_house,
    helper: 'Guests currently staying',
    icon: BedDouble,
    tone: 'emerald',
  },
  {
    key: 'unsettled',
    label: 'Unsettled stays',
    value: props.summary.unsettled,
    helper: 'Payment still open',
    icon: CircleDollarSign,
    tone: 'amber',
  },
]

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

function cardToneClasses(tone, active) {
  const tones = {
    indigo: active
      ? 'border-indigo-300 bg-gradient-to-br from-indigo-100 via-white to-indigo-50 text-indigo-700 shadow-md'
      : 'border-slate-200 bg-gradient-to-br from-indigo-500/15 via-indigo-500/5 to-white text-indigo-600',
    emerald: active
      ? 'border-emerald-300 bg-gradient-to-br from-emerald-100 via-white to-emerald-50 text-emerald-700 shadow-md'
      : 'border-slate-200 bg-gradient-to-br from-emerald-500/15 via-emerald-500/5 to-white text-emerald-600',
    amber: active
      ? 'border-amber-300 bg-gradient-to-br from-amber-100 via-white to-amber-50 text-amber-700 shadow-md'
      : 'border-slate-200 bg-gradient-to-br from-amber-500/15 via-amber-500/5 to-white text-amber-600',
  }

  return tones[tone]
}

function filterHref(key) {
  return props.filters.active === key
    ? route('admin.bookings.index')
    : route('admin.bookings.index', { filter: key })
}

function filterLabel() {
  const labels = {
    all: 'All bookings',
    arrivals_today: 'Arrivals today',
    in_house: 'In house',
    unsettled: 'Unsettled stays',
  }

  return labels[props.filters.active] ?? labels.all
}

function formatCurrency(amount) {
  return `₦${Number(amount || 0).toLocaleString()}`
}

function guestBreakdown(booking) {
  if (booking.adults || booking.children) {
    const parts = []

    if (booking.adults) {
      parts.push(`${booking.adults} adult${booking.adults === 1 ? '' : 's'}`)
    }

    if (booking.children) {
      parts.push(`${booking.children} child${booking.children === 1 ? '' : 'ren'}`)
    }

    return parts.join(', ')
  }

  return `${booking.guests} guest${booking.guests === 1 ? '' : 's'}`
}
</script>

<template>
  <ManagerLayout>
    <Head title="Bookings" />

    <div class="space-y-8">
      <section class="overflow-hidden rounded-[2rem] bg-slate-900 text-white shadow-2xl shadow-slate-200">
        <div class="grid gap-6 px-6 py-8 sm:px-8 xl:grid-cols-[1.25fr_0.75fr]">
          <div class="space-y-4">
            <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-[11px] font-black uppercase tracking-[0.22em] text-slate-200">
              <BriefcaseBusiness class="h-3.5 w-3.5" />
              Manager Booking Board
            </div>

            <h1 class="max-w-3xl text-3xl font-black tracking-tight sm:text-4xl">
              Booking oversight at a glance.
            </h1>
          </div>

          <div class="grid gap-4">
            <div class="rounded-[1.75rem] border border-white/10 bg-white/10 p-5 backdrop-blur">
              <div class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.22em] text-slate-300">
                <CalendarDays class="h-3.5 w-3.5" />
                Today
              </div>
              <p class="mt-3 text-2xl font-black">{{ todayLabel }}</p>
            </div>

            <div class="rounded-[1.75rem] border border-white/10 bg-gradient-to-br from-white/10 to-white/5 p-5">
              <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-300">Active view</p>
              <p class="mt-3 text-2xl font-black">{{ filterLabel() }}</p>
              <p class="mt-2 text-sm text-slate-300">
                {{ bookings.total }} booking<span v-if="bookings.total !== 1">s</span> in this result.
              </p>
            </div>
          </div>
        </div>
      </section>

      <section class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
        <Link
          v-for="card in summaryCards"
          :key="card.key"
          :href="filterHref(card.key)"
          class="rounded-[1.75rem] border p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
          :class="cardToneClasses(card.tone, filters.active === card.key)"
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">
                {{ card.label }}
              </p>
              <p class="mt-4 text-3xl font-black tracking-tight text-slate-900">
                {{ card.value }}
              </p>
            </div>
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/80 shadow-sm">
              <component :is="card.icon" class="h-5 w-5" />
            </div>
          </div>
          <div class="mt-3 flex items-center justify-between gap-3">
            <p class="text-sm text-slate-500">{{ card.helper }}</p>
            <span class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">
              {{ filters.active === card.key ? 'Showing' : 'Filter' }}
            </span>
          </div>
        </Link>
      </section>

      <section class="rounded-[2rem] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Reservations</p>
            <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-900">Manager booking board</h2>
          </div>
          <div class="flex items-center gap-3">
            <Link
              v-if="filters.active !== 'all'"
              :href="route('admin.bookings.index')"
              class="rounded-full bg-slate-100 px-4 py-2 text-xs font-bold uppercase tracking-[0.18em] text-slate-600 transition hover:bg-slate-200"
            >
              Clear filter
            </Link>
            <div class="rounded-full bg-slate-100 px-4 py-2 text-xs font-bold uppercase tracking-[0.18em] text-slate-500">
              {{ filterLabel() }} · Page {{ bookings.current_page }} of {{ bookings.last_page }}
            </div>
          </div>
        </div>

        <div v-if="bookings.data.length" class="divide-y divide-slate-100">
          <article
            v-for="booking in bookings.data"
            :key="booking.id"
            class="px-6 py-6 transition hover:bg-slate-50/80"
          >
            <div class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
              <div class="min-w-0 flex-1 space-y-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                  <div class="flex min-w-0 items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-slate-700">
                      <BedDouble class="h-5 w-5" />
                    </div>
                    <div class="min-w-0">
                      <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-xl font-black tracking-tight text-slate-900">
                          {{ booking.booking_code }}
                        </h3>
                        <span class="rounded-full px-3 py-1 text-xs font-bold" :class="badgeClasses(booking.status)">
                          {{ booking.status }}
                        </span>
                        <span class="rounded-full px-3 py-1 text-xs font-bold" :class="badgeClasses(booking.payment_status)">
                          {{ booking.payment_status }}
                        </span>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">
                          {{ booking.stay_phase }}
                        </span>
                      </div>
                      <p class="mt-2 text-sm font-medium text-slate-500">
                        Created {{ booking.created_at }}
                      </p>
                    </div>
                  </div>

                  <Link
                    :href="route('admin.bookings.edit', booking.id)"
                    class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:border-slate-300 hover:bg-slate-100"
                  >
                    Open booking
                    <ArrowRight class="h-4 w-4" />
                  </Link>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                  <div class="rounded-[1.5rem] bg-slate-50 p-4">
                    <div class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">
                      <UserRound class="h-3.5 w-3.5" />
                      Guest
                    </div>
                    <p class="mt-3 text-base font-black text-slate-900">{{ booking.guest_name }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ guestBreakdown(booking) }}</p>
                  </div>

                  <div class="rounded-[1.5rem] bg-slate-50 p-4">
                    <div class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">
                      <CalendarDays class="h-3.5 w-3.5" />
                      Stay
                    </div>
                    <p class="mt-3 text-base font-black text-slate-900">{{ booking.check_in }}</p>
                    <p class="mt-1 text-sm text-slate-500">
                      to {{ booking.check_out }} - {{ booking.nights }} night<span v-if="booking.nights !== 1">s</span>
                    </p>
                  </div>

                  <div class="rounded-[1.5rem] bg-slate-50 p-4">
                    <div class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">
                      <BedDouble class="h-3.5 w-3.5" />
                      Rooming
                    </div>
                    <p class="mt-3 text-base font-black text-slate-900">{{ booking.room_label }}</p>
                    <p class="mt-1 text-sm text-slate-500">
                      {{ booking.room_count }} room<span v-if="booking.room_count !== 1">s</span>
                      <span v-if="booking.checked_in_rooms"> - {{ booking.checked_in_rooms }} checked in</span>
                    </p>
                  </div>

                  <div class="rounded-[1.5rem] bg-slate-50 p-4">
                    <div class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">
                      <CreditCard class="h-3.5 w-3.5" />
                      Payment
                    </div>
                    <p class="mt-3 text-base font-black text-slate-900">{{ formatCurrency(booking.total_amount) }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ booking.payment_method }}</p>
                  </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
                  <div class="rounded-[1.5rem] border border-slate-200 p-4">
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Assigned rooms</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                      <span
                        v-for="room in booking.room_labels"
                        :key="room"
                        class="rounded-full bg-slate-100 px-3 py-2 text-sm font-bold text-slate-700"
                      >
                        {{ room }}
                      </span>
                    </div>
                  </div>

                  <div class="rounded-[1.5rem] border border-slate-200 p-4">
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Guest contact</p>
                    <div class="mt-3 space-y-2 text-sm text-slate-600">
                      <div class="flex items-center gap-2">
                        <Mail class="h-4 w-4 text-slate-400" />
                        <span>{{ booking.guest_email || 'No email provided' }}</span>
                      </div>
                      <div class="flex items-center gap-2">
                        <Phone class="h-4 w-4 text-slate-400" />
                        <span>{{ booking.guest_phone || 'No phone provided' }}</span>
                      </div>
                    </div>
                  </div>
                </div>

                <div
                  v-if="booking.special_requests"
                  class="rounded-[1.5rem] border border-amber-200 bg-amber-50 px-4 py-4"
                >
                  <div class="flex items-start gap-3">
                    <Clock3 class="mt-0.5 h-4 w-4 text-amber-600" />
                    <div>
                      <p class="text-[11px] font-black uppercase tracking-[0.18em] text-amber-700">Special requests</p>
                      <p class="mt-2 text-sm leading-6 text-amber-900">{{ booking.special_requests }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </article>
        </div>

        <div v-else class="px-6 py-20 text-center">
          <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-slate-100 text-slate-500">
            <BedDouble class="h-7 w-7" />
          </div>
          <h3 class="mt-6 text-2xl font-black tracking-tight text-slate-900">No bookings available</h3>
          <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-slate-500">
            No bookings match the current filter.
          </p>
        </div>
      </section>

      <Pagination :links="bookings.links" />
    </div>
  </ManagerLayout>
</template>
