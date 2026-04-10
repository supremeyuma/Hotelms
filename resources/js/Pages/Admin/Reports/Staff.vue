<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ArrowUpRight, BriefcaseBusiness, Download, ShieldAlert, Users, Wrench } from 'lucide-vue-next'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import Pagination from '@/Components/Pagination.vue'

const props = defineProps({
  rows: Object,
  filters: Object,
  summary: Object,
  roles: Array,
  departments: Array,
  routePrefix: String,
})

const isFiltering = ref(false)

const cards = computed(() => [
  {
    label: 'Active staff',
    value: Number(props.summary?.active_staff ?? 0).toLocaleString(),
    hint: `${Number(props.summary?.total_staff ?? 0).toLocaleString()} total workforce records`,
    href: route('admin.staff.index', { status: 'active' }),
    icon: Users,
    tone: 'indigo',
  },
  {
    label: 'Suspended staff',
    value: Number(props.summary?.suspended_staff ?? 0).toLocaleString(),
    hint: 'Records currently not active',
    href: route('admin.staff.index', { status: 'suspended' }),
    icon: ShieldAlert,
    tone: 'rose',
  },
  {
    label: 'Bookings assigned',
    value: Number(props.summary?.bookings ?? 0).toLocaleString(),
    hint: 'Booking records linked to listed staff',
    href: route('admin.reports.staff'),
    icon: BriefcaseBusiness,
    tone: 'emerald',
  },
  {
    label: 'Maintenance load',
    value: Number(props.summary?.maintenance_tasks ?? 0).toLocaleString(),
    hint: `${Number(props.summary?.departments ?? 0).toLocaleString()} departments represented`,
    href: route('admin.reports.staff'),
    icon: Wrench,
    tone: 'amber',
  },
])

const exportHref = computed(() => {
  const params = new URLSearchParams()

  Object.entries(props.filters ?? {}).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) {
      params.set(key, value)
    }
  })

  const query = params.toString()

  return `${route('admin.reports.staff.export', 'xlsx')}${query ? `?${query}` : ''}`
})

function applyFilters(event) {
  const form = new FormData(event.target)

  router.get(route('admin.reports.staff'), {
    search: form.get('search') || undefined,
    role: form.get('role') || undefined,
    department: form.get('department') || undefined,
    status: form.get('status') || undefined,
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
  router.get(route('admin.reports.staff'), {}, {
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

function toneClasses(tone) {
  const tones = {
    indigo: 'from-indigo-500/15 via-indigo-500/5 to-white text-indigo-600 ring-indigo-100',
    emerald: 'from-emerald-500/15 via-emerald-500/5 to-white text-emerald-600 ring-emerald-100',
    amber: 'from-amber-500/15 via-amber-500/5 to-white text-amber-600 ring-amber-100',
    rose: 'from-rose-500/15 via-rose-500/5 to-white text-rose-600 ring-rose-100',
  }

  return tones[tone] ?? 'from-slate-500/10 via-slate-500/5 to-white text-slate-600 ring-slate-100'
}

function workload(row) {
  return Number(row.orders_count ?? 0) + Number(row.bookings_count ?? 0) + Number(row.maintenance_tasks_count ?? 0)
}

function statusClasses(row) {
  return row.suspended_at
    ? 'bg-rose-100 text-rose-700'
    : 'bg-emerald-100 text-emerald-700'
}
</script>

<template>
  <ManagerLayout>
    <Head title="Staff Report" />

    <div class="space-y-8">
      <section class="overflow-hidden rounded-[2rem] bg-slate-900 text-white shadow-2xl shadow-slate-200">
        <div class="grid gap-6 px-6 py-8 sm:px-8 xl:grid-cols-[1.25fr_0.75fr]">
          <div class="space-y-4">
            <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-[11px] font-black uppercase tracking-[0.22em] text-slate-200">
              <Users class="h-3.5 w-3.5" />
              Workforce reporting
            </div>
            <div class="space-y-3">
              <h1 class="text-3xl font-black tracking-tight sm:text-4xl">Staff report</h1>
              <p class="max-w-2xl text-sm leading-6 text-slate-300">
                Review staff coverage, workload, and status by department, then jump directly into the employee record or notes thread that needs attention.
              </p>
            </div>
            <div class="flex flex-wrap gap-3">
              <Link
                :href="route('admin.staff.index')"
                class="inline-flex items-center gap-2 rounded-2xl bg-white px-5 py-3 text-sm font-bold text-slate-900 transition hover:bg-slate-100"
              >
                Open staff directory
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
              <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-300">Active coverage</p>
              <p class="mt-3 text-4xl font-black">{{ summary.active_staff }}</p>
              <p class="mt-2 text-sm text-slate-300">
                {{ summary.departments }} departments represented across {{ summary.total_staff }} staff records.
              </p>
            </div>

            <div class="rounded-[1.75rem] border border-amber-300/25 bg-gradient-to-br from-amber-400/20 to-amber-300/5 p-5">
              <p class="text-[11px] font-black uppercase tracking-[0.22em] text-amber-100">Tracked workload</p>
              <div class="mt-4 grid grid-cols-3 gap-3">
                <div>
                  <p class="text-2xl font-black">{{ summary.orders }}</p>
                  <p class="text-xs text-amber-100/80">Orders</p>
                </div>
                <div>
                  <p class="text-2xl font-black">{{ summary.bookings }}</p>
                  <p class="text-xs text-amber-100/80">Bookings</p>
                </div>
                <div>
                  <p class="text-2xl font-black">{{ summary.maintenance_tasks }}</p>
                  <p class="text-xs text-amber-100/80">Tasks</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
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

      <form class="grid gap-3 rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-2 xl:grid-cols-5" @submit.prevent="applyFilters">
        <input
          name="search"
          :value="filters.search ?? ''"
          type="text"
          placeholder="Search name or email"
          class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
        />

        <select
          name="role"
          :value="filters.role ?? ''"
          class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
        >
          <option value="">All roles</option>
          <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.name }}</option>
        </select>

        <select
          name="department"
          :value="filters.department ?? ''"
          class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
        >
          <option value="">All departments</option>
          <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
        </select>

        <select
          name="status"
          :value="filters.status ?? ''"
          class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
        >
          <option value="">All statuses</option>
          <option value="active">Active only</option>
          <option value="suspended">Suspended only</option>
        </select>

        <div class="flex gap-2">
          <button
            type="button"
            class="flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="isFiltering"
            @click="resetFilters"
          >
            Reset
          </button>
          <button
            type="submit"
            class="flex-1 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-600 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="isFiltering"
          >
            {{ isFiltering ? 'Applying…' : 'Apply filters' }}
          </button>
        </div>
      </form>

      <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Staff performance table</p>
            <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-900">Team activity breakdown</h2>
          </div>
          <p class="text-sm text-slate-500">Showing {{ rows.data.length }} staff records on this page.</p>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">
              <tr>
                <th class="px-6 py-4">Staff member</th>
                <th class="px-6 py-4">Role</th>
                <th class="px-6 py-4">Department</th>
                <th class="px-6 py-4">Position</th>
                <th class="px-6 py-4">Status</th>
                <th class="px-6 py-4">Orders</th>
                <th class="px-6 py-4">Bookings</th>
                <th class="px-6 py-4">Maintenance</th>
                <th class="px-6 py-4">Workload</th>
                <th class="px-6 py-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="staff in rows.data"
                :key="staff.id"
                class="align-top transition hover:bg-slate-50/80"
              >
                <td class="px-6 py-4">
                  <div class="space-y-1">
                    <p class="font-semibold text-slate-900">{{ staff.name }}</p>
                    <p class="text-xs text-slate-500">{{ staff.email }}</p>
                  </div>
                </td>
                <td class="px-6 py-4 text-slate-700">{{ staff.roles?.[0]?.name || 'Unassigned' }}</td>
                <td class="px-6 py-4 text-slate-700">{{ staff.department?.name || 'Not set' }}</td>
                <td class="px-6 py-4 text-slate-700">{{ staff.staff_profile?.position || 'Not set' }}</td>
                <td class="px-6 py-4">
                  <span class="rounded-full px-3 py-1 text-xs font-bold" :class="statusClasses(staff)">
                    {{ staff.suspended_at ? 'Suspended' : 'Active' }}
                  </span>
                </td>
                <td class="px-6 py-4 font-semibold text-slate-700">{{ staff.orders_count }}</td>
                <td class="px-6 py-4 font-semibold text-slate-700">{{ staff.bookings_count }}</td>
                <td class="px-6 py-4 font-semibold text-slate-700">{{ staff.maintenance_tasks_count }}</td>
                <td class="px-6 py-4">
                  <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">
                    {{ workload(staff) }}
                  </span>
                </td>
                <td class="px-6 py-4 text-right">
                  <div class="flex justify-end gap-2">
                    <Link
                      :href="route(`${routePrefix}.edit`, staff.id)"
                      class="inline-flex items-center rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                    >
                      Profile
                    </Link>
                    <Link
                      :href="route(`${routePrefix}.threads.index`, staff.id)"
                      class="inline-flex items-center rounded-xl bg-slate-900 px-3 py-2 text-xs font-bold text-white transition hover:bg-indigo-600"
                    >
                      Notes
                    </Link>
                  </div>
                </td>
              </tr>

              <tr v-if="rows.data.length === 0">
                <td colspan="10" class="px-6 py-16 text-center text-slate-500">
                  No staff records match the selected filters.
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
