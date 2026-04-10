<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ArrowUpRight, BedDouble, CalendarDays, DoorOpen, Download } from 'lucide-vue-next'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import TrendChart from '@/Components/TrendChart.vue'

const props = defineProps({
  rows: Array,
  summary: Object,
  filters: Object,
})

const isFiltering = ref(false)

const quickLinks = computed(() => [
  {
    label: 'Occupancy today',
    value: `${Number(props.summary?.occupancy ?? 0).toLocaleString()}%`,
    hint: `${props.summary?.occupied_rooms ?? 0} occupied rooms in house`,
    href: route('admin.bookings.index', { filter: 'in_house' }),
    icon: BedDouble,
    tone: 'indigo',
  },
  {
    label: 'Available rooms',
    value: Number(props.summary?.available_rooms ?? 0).toLocaleString(),
    hint: `${props.summary?.total_rooms ?? 0} total rooms in inventory`,
    href: route('admin.rooms.index'),
    icon: DoorOpen,
    tone: 'emerald',
  },
  {
    label: 'Arrivals today',
    value: Number(props.summary?.arrivals_today ?? 0).toLocaleString(),
    hint: 'Open bookings arriving today',
    href: route('admin.bookings.index', { filter: 'arrivals_today' }),
    icon: CalendarDays,
    tone: 'amber',
  },
  {
    label: 'Departures today',
    value: Number(props.summary?.departures_today ?? 0).toLocaleString(),
    hint: 'Bookings expected to check out today',
    href: route('admin.bookings.index', { filter: 'departures_today' }),
    icon: ArrowUpRight,
    tone: 'rose',
  },
])

const exportHref = computed(() => {
  const params = new URLSearchParams()

  Object.entries(props.filters ?? {}).forEach(([key, value]) => {
    if (value) {
      params.set(key, value)
    }
  })

  const query = params.toString()

  return `${route('admin.reports.occupancy.export', 'xlsx')}${query ? `?${query}` : ''}`
})

function applyFilters(event) {
  const form = new FormData(event.target)

  router.get(route('admin.reports.occupancy'), {
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
  router.get(route('admin.reports.occupancy'), {}, {
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

function formatDate(value) {
  return new Date(value).toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

function toneClasses(tone) {
  const tones = {
    indigo: 'from-indigo-500/15 via-indigo-500/5 to-white text-indigo-600 ring-indigo-100',
    emerald: 'from-emerald-500/15 via-emerald-500/5 to-white text-emerald-600 ring-emerald-100',
    amber: 'from-amber-500/15 via-amber-500/5 to-white text-amber-600 ring-amber-100',
    rose: 'from-rose-500/15 via-rose-500/5 to-white text-rose-600 ring-rose-100',
  }

  return tones[tone] ?? 'from-slate-500/10 via-slate-500/5 to-white text-slate-600 ring-slate-100'
}
</script>

<template>
  <ManagerLayout>
    <Head title="Occupancy Report" />

    <div class="space-y-8">
      <section class="overflow-hidden rounded-[2rem] bg-slate-900 text-white shadow-2xl shadow-slate-200">
        <div class="grid gap-6 px-6 py-8 sm:px-8 xl:grid-cols-[1.25fr_0.75fr]">
          <div class="space-y-4">
            <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-[11px] font-black uppercase tracking-[0.22em] text-slate-200">
              <BedDouble class="h-3.5 w-3.5" />
              Occupancy intelligence
            </div>
            <div class="space-y-3">
              <h1 class="text-3xl font-black tracking-tight sm:text-4xl">Occupancy report</h1>
              <p class="max-w-2xl text-sm leading-6 text-slate-300">
                Monitor in-house load, arrivals, departures, and room availability with a date-filtered occupancy view built for daily operations.
              </p>
            </div>
            <div class="flex flex-wrap gap-3">
              <Link
                :href="route('admin.bookings.index', { filter: 'in_house' })"
                class="inline-flex items-center gap-2 rounded-2xl bg-white px-5 py-3 text-sm font-bold text-slate-900 transition hover:bg-slate-100"
              >
                Open in-house bookings
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
              <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-300">Current occupancy</p>
              <p class="mt-3 text-4xl font-black">{{ summary.occupancy }}%</p>
              <p class="mt-2 text-sm text-slate-300">
                {{ summary.occupied_rooms }} occupied rooms, {{ summary.available_rooms }} rooms available now.
              </p>
            </div>

            <div class="rounded-[1.75rem] border border-emerald-300/25 bg-gradient-to-br from-emerald-400/20 to-emerald-300/5 p-5">
              <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-100">Guest flow today</p>
              <div class="mt-4 grid grid-cols-2 gap-4">
                <div>
                  <p class="text-3xl font-black">{{ summary.arrivals_today }}</p>
                  <p class="text-xs text-emerald-100/80">Arrivals</p>
                </div>
                <div>
                  <p class="text-3xl font-black">{{ summary.departures_today }}</p>
                  <p class="text-xs text-emerald-100/80">Departures</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
        <Link
          v-for="card in quickLinks"
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
        <div>
          <label class="text-xs font-black uppercase tracking-[0.2em] text-slate-400">From</label>
          <input
            name="from"
            :value="filters.from"
            type="date"
            class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
          />
        </div>

        <div>
          <label class="text-xs font-black uppercase tracking-[0.2em] text-slate-400">To</label>
          <input
            name="to"
            :value="filters.to"
            type="date"
            class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
          />
        </div>

        <div class="lg:col-span-2 flex flex-wrap items-end justify-end gap-2">
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

      <TrendChart title="Occupancy Trend" endpoint="/admin/reports/charts/occupancy" />

      <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Daily room position</p>
            <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-900">Occupancy breakdown</h2>
          </div>
          <p class="text-sm text-slate-500">Showing {{ rows.length }} reporting days.</p>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">
              <tr>
                <th class="px-6 py-4">Date</th>
                <th class="px-6 py-4">Occupied rooms</th>
                <th class="px-6 py-4">Availability</th>
                <th class="px-6 py-4">Occupancy rate</th>
                <th class="px-6 py-4">Arrivals</th>
                <th class="px-6 py-4">Departures</th>
                <th class="px-6 py-4">Bookings in house</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="row in rows"
                :key="row.date"
                class="align-top transition hover:bg-slate-50/80"
              >
                <td class="px-6 py-4 font-semibold text-slate-900">{{ formatDate(row.date) }}</td>
                <td class="px-6 py-4 text-slate-700">{{ row.occupied_rooms }}</td>
                <td class="px-6 py-4 text-slate-700">{{ row.available_rooms }}</td>
                <td class="px-6 py-4">
                  <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700">
                    {{ row.occupancy_rate }}%
                  </span>
                </td>
                <td class="px-6 py-4 text-slate-700">{{ row.arrivals }}</td>
                <td class="px-6 py-4 text-slate-700">{{ row.departures }}</td>
                <td class="px-6 py-4 text-slate-700">{{ row.bookings }}</td>
              </tr>

              <tr v-if="rows.length === 0">
                <td colspan="7" class="px-6 py-16 text-center text-slate-500">
                  No occupancy records are available for the selected range.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </ManagerLayout>
</template>
