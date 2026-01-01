<template>
  <AdminLayout>
    <div class="grid grid-cols-4 gap-6">
      <div class="p-6 bg-white rounded shadow">
        <p class="text-gray-500">Rooms</p>
        <p class="text-2xl font-bold">{{ stats.rooms }}</p>
      </div>
      <div class="p-6 bg-white rounded shadow">
        <p class="text-gray-500">Occupied</p>
        <p class="text-2xl font-bold">{{ stats.occupied_rooms }}</p>
      </div>
      <div class="p-6 bg-white rounded shadow">
        <p class="text-gray-500">Active Bookings</p>
        <p class="text-2xl font-bold">{{ stats.active_bookings }}</p>
      </div>
      <div class="p-6 bg-white rounded shadow">
        <p class="text-gray-500">Revenue Today</p>
        <p class="text-2xl font-bold">₦{{ stats.revenue_today }}</p>
      </div>
    </div>

    <div class="mt-10 bg-white rounded shadow p-6">
      <h2 class="text-lg font-semibold mb-4">Recent Bookings</h2>
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left border-b">
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
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
defineProps({ stats: Object, recentBookings: Array })
</script>