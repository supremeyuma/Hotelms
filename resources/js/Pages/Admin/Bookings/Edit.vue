<!-- resources/js/Pages/Admin/Bookings/Edit.vue -->
<template>
  <AuthenticatedLayout>
    <div class="max-w-4xl mx-auto">
      <h2 class="text-2xl font-semibold mb-4">Edit Booking #{{ booking.id }}</h2>

      <form @submit.prevent="submit" class="bg-white shadow rounded-lg p-6 space-y-4">
        <div>
          <FormLabel for="room">Room</FormLabel>
          <SelectInput id="room" v-model="form.room_id" :options="roomOptions" required />
        </div>

        <div>
          <FormLabel for="check_in">Check In</FormLabel>
          <TextInput type="date" id="check_in" v-model="form.check_in" required />
        </div>

        <div>
          <FormLabel for="check_out">Check Out</FormLabel>
          <TextInput type="date" id="check_out" v-model="form.check_out" required />
        </div>

        <div>
          <FormLabel for="guests">Guests</FormLabel>
          <TextInput type="number" id="guests" v-model="form.guests" min="1" />
        </div>

        <div>
          <FormLabel for="status">Status</FormLabel>
          <SelectInput id="status" v-model="form.status" :options="statusOptions" required />
        </div>

        <PrimaryButton :disabled="form.processing">Update Booking</PrimaryButton>
      </form>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { FormLabel, TextInput, SelectInput, PrimaryButton } from '@/Components/Index';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
  booking: Object,
  rooms: Array
});

const form = useForm({
  room_id: props.booking.room_id,
  check_in: props.booking.check_in,
  check_out: props.booking.check_out,
  status: props.booking.status,
  guests: props.booking.guests.toString() || 1 //Convert to string for v-model binding
});

const roomOptions = props.rooms.map(r => ({ label: `${r.room_type.title} #${r.name}`, value: r.id }));
const statusOptions = [
  { label: 'Pending', value: 'pending' },
  { label: 'Confirmed', value: 'confirmed' },
  { label: 'Cancelled', value: 'cancelled' }
];

function submit() {
  form.put(`/admin/bookings/${props.booking.id}`);
}
</script>
