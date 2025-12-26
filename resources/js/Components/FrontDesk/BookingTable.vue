<template>
  <div class="bg-white shadow rounded overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-100">
        <tr>
          <th class="px-4 py-2 text-left">Code</th>
          <th class="px-4 py-2">Guest</th>
          <th class="px-4 py-2">Dates</th>
          <th class="px-4 py-2">Rooms</th>
          <th class="px-4 py-2">Status</th>
          <th class="px-4 py-2 text-right">Actions</th>
        </tr>
      </thead>

      <tbody>
        <tr
          v-for="booking in bookings"
          :key="booking.id"
          class="border-t"
        >
          <td class="px-4 py-2 font-mono">{{ booking.booking_code }}</td>
          <td class="px-4 py-2">{{ booking.guest_name }}</td>

          <td class="px-4 py-2">
            {{ formatDate(booking.check_in) }} → {{ formatDate(booking.check_out) }}
          </td>

          <td class="px-4 py-2 text-center">
            {{ checkedInRoomsCount(booking) }} / {{ booking.quantity }}
          </td>

          <td class="px-4 py-2">
            <StatusBadge :status="booking.status" />
          </td>

          <td class="px-4 py-2 text-right space-x-2">
            <button
              v-if="canCheckIn(booking)"
              type="button"
              @click="$emit('checkin', booking)"
              class="btn-primary"
            >
              Check-in
            </button>

            <button
              v-if="booking.status === 'checked_in'"
              type="button"
              @click="$emit('checkout', booking)"
              class="btn-danger"
            >
              Check-out
            </button>

            <button
              @click="$emit('edit', booking)"
              type="button"
              class="btn-secondary"
            >
              Edit
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import StatusBadge from './StatusBadge.vue';

const props = defineProps({
  bookings: Array,
});

function checkedInRoomsCount(booking) {
  if (!booking.rooms) return 0;
  return booking.rooms.filter(r => r.pivot.checked_in_at).length;
}

function canCheckIn(booking) {
  return booking.status === 'confirmed' && checkedInRoomsCount(booking) < booking.quantity;
}

function formatDate(d) {
  return new Date(d).toLocaleDateString();
}
</script>

