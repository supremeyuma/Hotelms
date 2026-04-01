<script setup>
import { router } from '@inertiajs/vue3'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import { reactive } from 'vue'

const props = defineProps({
  ticket: Object,
  staffOptions: Array,
})

const form = reactive({
  staff_id: props.ticket.staff?.id ?? '',
  status: props.ticket.status,
  manager_note: props.ticket.manager_note ?? '',
})

function submit() {
  router.put(route('admin.maintenance.update', props.ticket.id), form, {
    preserveScroll: true,
  })
}
</script>

<template>
  <ManagerLayout>
    <div class="mx-auto max-w-4xl space-y-6">
      <div>
        <h1 class="text-3xl font-bold text-slate-900">{{ ticket.title }}</h1>
        <p class="mt-2 text-sm text-slate-500">Maintenance ticket #{{ ticket.id }}</p>
      </div>

      <div class="grid gap-6 md:grid-cols-[2fr_1fr]">
        <section class="rounded-3xl bg-white p-6 shadow-sm">
          <h2 class="text-lg font-semibold text-slate-900">Issue Details</h2>
          <dl class="mt-4 space-y-4 text-sm text-slate-600">
            <div>
              <dt class="font-medium text-slate-900">Description</dt>
              <dd class="mt-1 leading-6">{{ ticket.description }}</dd>
            </div>
            <div>
              <dt class="font-medium text-slate-900">Guest</dt>
              <dd class="mt-1">{{ ticket.guest_name || 'Guest not captured' }}</dd>
            </div>
            <div>
              <dt class="font-medium text-slate-900">Room</dt>
              <dd class="mt-1">{{ ticket.room?.name || ticket.room?.room_number || 'No room linked' }}</dd>
            </div>
            <div>
              <dt class="font-medium text-slate-900">Issue Type</dt>
              <dd class="mt-1">{{ ticket.issue_type || 'General' }}</dd>
            </div>
            <div v-if="ticket.photo_path">
              <dt class="font-medium text-slate-900">Attachment</dt>
              <dd class="mt-1">
                <a :href="`/storage/${ticket.photo_path}`" target="_blank" class="text-indigo-600 hover:text-indigo-700">
                  View uploaded photo
                </a>
              </dd>
            </div>
          </dl>
        </section>

        <section class="rounded-3xl bg-white p-6 shadow-sm">
          <h2 class="text-lg font-semibold text-slate-900">Manager Action</h2>
          <form class="mt-4 space-y-4" @submit.prevent="submit">
            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">Assign Staff</label>
              <select v-model="form.staff_id" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                <option value="">Unassigned</option>
                <option v-for="staff in staffOptions" :key="staff.id" :value="staff.id">
                  {{ staff.name }}
                </option>
              </select>
            </div>

            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">Status</label>
              <select v-model="form.status" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                <option value="open">Open</option>
                <option value="in_progress">In Progress</option>
                <option value="resolved">Resolved</option>
                <option value="closed">Closed</option>
              </select>
            </div>

            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">Manager Note</label>
              <textarea v-model="form.manager_note" rows="5" class="w-full rounded-xl border border-slate-300 px-3 py-2"></textarea>
            </div>

            <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold uppercase tracking-wider text-white">
              Save Update
            </button>
          </form>
        </section>
      </div>
    </div>
  </ManagerLayout>
</template>
