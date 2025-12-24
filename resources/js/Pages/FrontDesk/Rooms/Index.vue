<template>
  <div class="p-6 space-y-6">
    <h1 class="text-2xl font-bold">Occupied Rooms</h1>

    <table class="min-w-full border border-gray-200">
      <thead class="bg-gray-100">
        <tr>
          <th class="px-4 py-2 border">Room</th>
          <th class="px-4 py-2 border">Guest</th>
          <th class="px-4 py-2 border">Booking Code</th>
          <th class="px-4 py-2 border">Actions</th>
        </tr>
      </thead>

      <tbody>
        <tr
          v-for="room in rooms"
          :key="room.id"
          class="hover:bg-gray-50"
        >
          <td class="px-4 py-2 border font-semibold">
            {{ room.room_number }}
          </td>

          <td class="px-4 py-2 border">
            {{ room.bookings[0]?.guest_name ?? '—' }}
          </td>

          <td class="px-4 py-2 border">
            {{ room.bookings[0]?.booking_code ?? '—' }}
          </td>

          <td class="px-4 py-2 border">
            <Link
              :href="`/frontdesk/rooms/${room.id}/billing`"
              class="text-blue-600 hover:underline"
            >
              View Billing
            </Link>
          </td>
        </tr>

        <tr v-if="rooms.length === 0">
          <td colspan="4" class="text-center py-6 text-gray-500">
            No occupied rooms
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
  rooms: Array
});
</script>
