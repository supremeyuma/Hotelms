<template>
  <div class="p-6 space-y-6 max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold">Edit Booking</h1>

    <form @submit.prevent="submit" class="space-y-4 bg-white p-6 rounded shadow">
      <!-- Guest Name -->
      <div>
        <label class="block text-sm font-medium mb-1">Guest Name</label>
        <input
          v-model="form.guest_name"
          type="text"
          class="w-full border rounded px-3 py-2"
          required
        />
      </div>

      <!-- Check-in -->
      <div>
        <label class="block text-sm font-medium mb-1">Check-in Date</label>
        <input
          v-model="form.check_in"
          type="date"
          class="w-full border rounded px-3 py-2"
          required
        />
      </div>

      <!-- Check-out -->
      <div>
        <label class="block text-sm font-medium mb-1">Check-out Date</label>
        <input
          v-model="form.check_out"
          type="date"
          class="w-full border rounded px-3 py-2"
          required
        />
      </div>

      <!-- Status -->
      <div>
        <label class="block text-sm font-medium mb-1">Status</label>
        <select
          v-model="form.status"
          class="w-full border rounded px-3 py-2"
        >
          <option value="confirmed">Confirmed</option>
          <option value="pending_payment">Pending Payment</option>
          <option value="active">Active</option>
          <option value="checked_out">Checked Out</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>

      <!-- Actions -->
      <div class="flex gap-3 pt-4">
        <button
          type="submit"
          class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
        >
          Save Changes
        </button>

        <Link
          href="/frontdesk/bookings"
          class="bg-gray-200 px-4 py-2 rounded hover:bg-gray-300"
        >
          Cancel
        </Link>
      </div>
    </form>
  </div>
</template>

<script setup>
import { useForm, Link } from '@inertiajs/vue3';

const props = defineProps({
  booking: Object,
});

// helper to normalize date
function toDate(value) {
  return value ? value.substring(0, 10) : '';
}

const form = useForm({
  guest_name: props.booking.guest_name,
  check_in: toDate(props.booking.check_in),
  check_out: toDate(props.booking.check_out),
  status: props.booking.status,
});
console.log(form.status);

function submit() {
  form.put(
    route('frontdesk.bookings.update', props.booking.id),
    { preserveScroll: true }
  );
}
</script>
