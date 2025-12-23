<template>
  <AuthenticatedLayout>
    <div class="max-w-6xl mx-auto">
      <h2 class="text-2xl font-semibold mb-4">Bookings</h2>

      <table class="table-auto w-full border border-gray-200">
        <thead>
          <tr class="bg-gray-100">
            <th class="px-4 py-2">Booking ID</th>
            <th class="px-4 py-2">User</th>
            <th class="px-4 py-2">Room</th>
            <th class="px-4 py-2">Check In</th>
            <th class="px-4 py-2">Check Out</th>
            <th class="px-4 py-2">Guests</th>
            <th class="px-4 py-2">Status</th>
            <th class="px-4 py-2">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="booking in bookings.data" :key="booking.id" class="border-b">
            <td class="px-4 py-2">{{ booking.id }}</td>
            <td class="px-4 py-2">{{ booking.booking_code }}</td>
            <td class="px-4 py-2">{{ booking.room_type_id }} #{{ booking.room_type }}</td>
            <td class="px-4 py-2">{{ new Date(booking.check_in).toLocaleDateString() }}</td>
            <td class="px-4 py-2">{{ new Date(booking.check_out).toLocaleDateString() }}</td>
            <td class="px-4 py-2">{{ booking.guests }}</td>
            <td class="px-4 py-2">{{ booking.status }}</td>
            <td class="px-4 py-2 space-x-2">
              <Link :href="route('admin.bookings.edit', booking.id)" class="text-blue-600">Edit</Link>
              <button @click="deleteBooking(booking.id)" class="text-red-600">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>

      <pagination :links="bookings.links" class="mt-4"/>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';

const props = usePage().props;
const bookings = props.bookings;
console.log(bookings);

function deleteBooking(id) {
  if (!confirm('Are you sure you want to delete this booking?')) return;
  router.delete(`/admin/bookings/${id}`);
}
</script>
