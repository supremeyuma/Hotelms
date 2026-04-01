<script setup>
import { Link, router } from '@inertiajs/vue3'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'

const props = defineProps({
  tickets: Object,
  staffOptions: Array,
  stats: Object,
})

const statusOptions = [
  { label: 'Open', value: 'open' },
  { label: 'In Progress', value: 'in_progress' },
  { label: 'Resolved', value: 'resolved' },
  { label: 'Closed', value: 'closed' },
]

function updateTicket(ticket, payload) {
  router.put(route('admin.maintenance.update', ticket.id), {
    staff_id: payload.staff_id ?? ticket.staff?.id ?? '',
    status: payload.status ?? ticket.status,
    manager_note: payload.manager_note ?? ticket.manager_note ?? '',
  }, {
    preserveScroll: true,
  })
}

function formatStatus(status) {
  return String(status ?? '').replace('_', ' ')
}
</script>

<template>
  <ManagerLayout>
    <div class="space-y-6">
      <div>
        <h1 class="text-3xl font-bold text-slate-900">Maintenance Control Room</h1>
        <p class="text-sm text-slate-500">Review guest-reported issues, assign technicians, and keep resolution moving.</p>
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
          <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Unassigned</div>
          <div class="mt-2 text-3xl font-bold text-slate-900">{{ stats.unassigned }}</div>
        </div>
      </div>

      <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 text-left text-slate-500">
            <tr>
              <th class="px-5 py-4 font-semibold">Ticket</th>
              <th class="px-5 py-4 font-semibold">Guest / Room</th>
              <th class="px-5 py-4 font-semibold">Assigned</th>
              <th class="px-5 py-4 font-semibold">Status</th>
              <th class="px-5 py-4 font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="ticket in tickets.data" :key="ticket.id" class="border-t border-slate-100 align-top">
              <td class="px-5 py-4">
                <div class="font-semibold text-slate-900">{{ ticket.title }}</div>
                <div class="mt-1 text-xs uppercase tracking-wider text-slate-400">{{ ticket.issue_type || 'General' }}</div>
                <div class="mt-2 text-sm text-slate-600">{{ ticket.description }}</div>
              </td>
              <td class="px-5 py-4 text-slate-600">
                <div>{{ ticket.guest_name || 'Guest not captured' }}</div>
                <div class="mt-1">{{ ticket.room?.name || ticket.room?.room_number || 'No room linked' }}</div>
              </td>
              <td class="px-5 py-4 text-slate-600">
                {{ ticket.staff?.name || 'Unassigned' }}
              </td>
              <td class="px-5 py-4">
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase text-slate-600">
                  {{ formatStatus(ticket.status) }}
                </span>
              </td>
              <td class="px-5 py-4">
                <div class="space-y-3">
                  <select
                    class="w-full rounded-xl border border-slate-300 px-3 py-2"
                    :value="ticket.staff?.id ?? ''"
                    @change="updateTicket(ticket, { staff_id: $event.target.value || null, status: ticket.status === 'open' && $event.target.value ? 'in_progress' : ticket.status })"
                  >
                    <option value="">Unassigned</option>
                    <option v-for="staff in staffOptions" :key="staff.id" :value="staff.id">
                      {{ staff.name }}
                    </option>
                  </select>

                  <select
                    class="w-full rounded-xl border border-slate-300 px-3 py-2"
                    :value="ticket.status"
                    @change="updateTicket(ticket, { status: $event.target.value })"
                  >
                    <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </option>
                  </select>

                  <Link
                    :href="route('admin.maintenance.show', ticket.id)"
                    class="inline-flex rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-white"
                  >
                    Open Ticket
                  </Link>
                </div>
              </td>
            </tr>
            <tr v-if="!tickets.data.length">
              <td colspan="5" class="px-5 py-10 text-center text-slate-500">No maintenance tickets found.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </ManagerLayout>
</template>
