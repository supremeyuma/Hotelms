<template>
  <table class="min-w-full border-collapse border border-gray-200">
    <thead>
      <tr class="bg-gray-100">
        <th class="border px-4 py-2">Booking Code</th>
        <th class="border px-4 py-2">Guest Name</th>
        <th class="border px-4 py-2">Room</th>
        <th class="border px-4 py-2">Check-in</th>
        <th class="border px-4 py-2">Check-out</th>
        <th class="border px-4 py-2">Status</th>
        <th class="border px-4 py-2">Actions</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="booking in bookings" :key="booking.id" class="hover:bg-gray-50">
        <td class="border px-4 py-2">{{ booking.booking_code }}</td>
        <td class="border px-4 py-2">{{ booking.guest_name }}</td>
        <td class="border px-4 py-2">{{ booking.room?.number ?? '-' }}</td>
        <td class="border px-4 py-2">{{ booking.check_in }}</td>
        <td class="border px-4 py-2">{{ booking.check_out }}</td>
        <td class="border px-4 py-2">
          <span :class="statusClass(booking.status)">
            {{ booking.status }}
          </span>
        </td>
        <td class="border px-4 py-2 space-x-2">
          <button 
            v-if="booking.status === 'confirmed'" 
            @click="$emit('checkin', booking.id)"
            class="btn-checkin"
          >Check-in</button>

          <button 
            v-if="booking.status === 'active'" 
            @click="$emit('checkout', booking.id)"
            class="btn-checkout"
          >Check-out</button>

          <button @click="handleExtend(booking.id)" class="btn-extend">
            Extend
          </button>

          <button @click="$emit('edit', booking.id)" class="btn-edit">Edit</button>
        </td>
      </tr>
    </tbody>
  </table>
</template>

<script setup>
// Inside <script setup>
// 1. Define the actions this component can trigger
const emit = defineEmits(['checkin', 'checkout', 'extend', 'edit']);

const props = defineProps({
  bookings: Array
});

// 2. Wrap the window prompt in a proper function
function handleExtend(bookingId) {
  const newDate = window.prompt('New Checkout Date (YYYY-MM-DD)');
  if (newDate) {
    emit('extend', bookingId, newDate);
  }
}

function statusClass(status) {
  switch (status) {
    case 'active': return 'text-green-600 font-semibold';
    case 'confirmed': return 'text-blue-600 font-semibold';
    case 'checked_out': return 'text-gray-600';
    case 'cancelled': return 'text-red-600';
    default: return '';
  }
}
</script>

<style scoped>
.btn-checkin { @apply bg-green-500 text-white px-2 py-1 rounded; }
.btn-checkout { @apply bg-red-500 text-white px-2 py-1 rounded; }
.btn-extend { @apply bg-yellow-500 text-white px-2 py-1 rounded; }
.btn-edit { @apply bg-blue-500 text-white px-2 py-1 rounded; }
</style>
