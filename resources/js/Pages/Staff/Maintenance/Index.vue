<script setup>
import { router, Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  tickets: Object,
  stats: Object,
  canManageAll: Boolean,
})

const statusOptions = [
  { label: 'Open', value: 'open' },
  { label: 'In Progress', value: 'in_progress' },
  { label: 'Resolved', value: 'resolved' },
  { label: 'Closed', value: 'closed' },
]

function updateStatus(ticket, status) {
  router.patch(route('staff.maintenance.updateStatus', ticket.id), { status }, {
    preserveScroll: true,
  })
}

function formatStatus(status) {
  return String(status ?? '').replace('_', ' ')
}

function photoUrl(path) {
  return path ? `/storage/${path}` : null
}
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Maintenance Dashboard" />

    <div class="space-y-6">
      <div>
        <h1 class="text-3xl font-bold text-slate-900">Maintenance Dashboard</h1>
        <p class="text-sm text-slate-500">Track guest-reported issues, pick up work, and move tickets through resolution.</p>
      </div>

      <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-2xl bg-white p-5 shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Open</div>
          <div class="mt-2 text-3xl font-bold text-slate-900">{{ stats.open }}</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">In Progress</div>
          <div class="mt-2 text-3xl font-bold text-slate-900">{{ stats.in_progress }}</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Resolved</div>
          <div class="mt-2 text-3xl font-bold text-slate-900">{{ stats.resolved }}</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Assigned To Me</div>
          <div class="mt-2 text-3xl font-bold text-slate-900">{{ stats.mine }}</div>
        </div>
      </div>

      <div class="space-y-4">
        <article
          v-for="ticket in tickets.data"
          :key="ticket.id"
          class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"
        >
          <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="space-y-2">
              <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-xl font-semibold text-slate-900">{{ ticket.title }}</h2>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase text-slate-600">
                  {{ formatStatus(ticket.status) }}
                </span>
              </div>
              <p class="text-sm text-slate-500">
                {{ ticket.room?.name || ticket.room?.room_number || 'No room linked' }}
                <span v-if="ticket.guest_name"> • {{ ticket.guest_name }}</span>
                <span v-if="ticket.issue_type"> • {{ ticket.issue_type }}</span>
              </p>
              <p class="text-sm leading-6 text-slate-700">{{ ticket.description }}</p>
            </div>

            <div class="min-w-[220px] space-y-3">
              <div class="text-sm text-slate-500">
                <span class="font-medium text-slate-700">Assigned:</span>
                {{ ticket.staff?.name || 'Unassigned' }}
              </div>

              <select
                class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                :value="ticket.status"
                @change="updateStatus(ticket, $event.target.value)"
              >
                <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
            </div>
          </div>

          <div v-if="ticket.photo_path" class="mt-4">
            <a :href="photoUrl(ticket.photo_path)" target="_blank" class="text-sm font-medium text-indigo-600 hover:text-indigo-700">
              View attached photo
            </a>
          </div>
        </article>

        <div v-if="!tickets.data.length" class="rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">
          No maintenance tickets are waiting right now.
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
