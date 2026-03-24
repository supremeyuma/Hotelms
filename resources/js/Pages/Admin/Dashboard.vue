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

defineProps({ stats: Object, recentBookings: Array })
</script>
