<template>
  <ManagerLayout>
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-5">
      <div class="rounded bg-white p-6 shadow">
        <p class="text-gray-500">Rooms</p>
        <p class="text-2xl font-bold">{{ stats.rooms }}</p>
      </div>
      <div class="rounded bg-white p-6 shadow">
        <p class="text-gray-500">Occupied</p>
        <p class="text-2xl font-bold">{{ stats.occupied_rooms }}</p>
      </div>
      <div class="rounded bg-white p-6 shadow">
        <p class="text-gray-500">Active Bookings</p>
        <p class="text-2xl font-bold">{{ stats.active_bookings }}</p>
      </div>
      <div class="rounded bg-white p-6 shadow">
        <p class="text-gray-500">Open Guest Requests</p>
        <p class="text-2xl font-bold">{{ stats.open_guest_requests }}</p>
      </div>
      <div class="rounded bg-white p-6 shadow">
        <p class="text-gray-500">Open Maintenance</p>
        <p class="text-2xl font-bold">{{ stats.open_maintenance }}</p>
      </div>
    </div>

    <div v-if="isExecutive && departmentSnapshots?.length" class="mt-10">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold">Department Overview</h2>
        <div class="flex flex-wrap gap-2">
          <a
            v-for="report in reportLinks"
            :key="report.label"
            :href="report.route"
            class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-200"
          >
            {{ report.label }}
          </a>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <a
          v-for="snapshot in departmentSnapshots"
          :key="snapshot.name"
          :href="snapshot.route"
          class="rounded bg-white p-5 shadow transition hover:-translate-y-0.5 hover:shadow-md"
        >
          <p class="text-sm font-semibold text-slate-500">{{ snapshot.name }}</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ snapshot.metric }}</p>
          <p class="mt-2 text-sm text-slate-500">{{ snapshot.secondary }}</p>
        </a>
      </div>
    </div>

    <div class="mt-10 rounded bg-white p-6 shadow">
      <h2 class="mb-4 text-lg font-semibold">Recent Bookings</h2>
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b text-left">
            <th>Code</th>
            <th>Room</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="b in recentBookings" :key="b.id" class="border-b">
            <td>{{ b.booking_code }}</td>
            <td>{{ b.room_id }}</td>
            <td>{{ b.status }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </ManagerLayout>
</template>

<script setup>
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'

defineProps({
  stats: Object,
  recentBookings: Array,
  isExecutive: Boolean,
  departmentSnapshots: Array,
  reportLinks: Array,
})
</script>
