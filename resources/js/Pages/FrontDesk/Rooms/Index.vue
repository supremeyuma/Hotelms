<template>
  <div class="p-6 space-y-6">
    <h1 class="text-2xl font-bold capitalize">
      {{ status }} Rooms
    </h1>

    <!-- OCCUPIED ROOMS TABLE (unchanged) -->
    <table
      v-if="status === 'occupied'"
      class="min-w-full border border-gray-200"
    >
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

    <!-- UNOCCUPIED ROOMS TABLE -->
    <table
      v-else
      class="min-w-full border border-gray-200"
    >
      <thead class="bg-gray-100">
        <tr>
          <th class="px-4 py-2 border">Room</th>
          <th class="px-4 py-2 border">Room Type</th>
          <th class="px-4 py-2 border">Status</th>
          <th class="px-4 py-2 border">Upcoming Booking</th>
          <th class="px-4 py-2 border">Expected Check-in</th>
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
            {{ room.room_type?.title ?? '—' }}
          </td>

          <td class="px-4 py-2 border">
            <span
              class="px-2 py-1 rounded text-xs font-semibold"
              :class="statusBadge(room.status)"
            >
              {{ room.status }}
            </span>
          </td>

          <td class="px-4 py-2 border">
            {{ nextBooking(room)?.booking_code ?? '—' }}
          </td>

          <td class="px-4 py-2 border">
            {{ nextBooking(room)?.check_in ?? '—' }}
          </td>
        </tr>

        <tr v-if="rooms.length === 0">
          <td colspan="5" class="text-center py-6 text-gray-500">
            No {{ status }} rooms
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  status: String,
  rooms: Array,
});
console.log(props.rooms);

const nextBooking = (room) => {
  return room.bookings?.find(b => b.status === 'confirmed') ?? null;
};

const statusBadge = (status) => {
  return {
    available: 'bg-green-100 text-green-800',
    dirty: 'bg-yellow-100 text-yellow-800',
    maintenance: 'bg-red-100 text-red-800',
  }[status] ?? 'bg-gray-100 text-gray-800';
};
</script>
