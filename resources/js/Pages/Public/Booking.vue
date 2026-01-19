<template>
  <PublicLayout>
    <div class="max-w-xl mx-auto bg-white p-6 rounded shadow">
      <h2 class="text-2xl mb-4">Book a Room</h2>

      <form @submit.prevent="submit">
        <div class="mb-3">
          <label class="block text-sm">Room</label>
          <select v-model="form.room_id" class="w-full border p-2 rounded">
            <option v-for="r in rooms" :value="r.id" :key="r.id">{{ r.name }} — {{ r.room_type.title }}</option>
          </select>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-3">
          <div>
            <label class="block text-sm">Check-in</label>
            <input v-model="form.check_in" type="date" class="w-full border p-2 rounded" />
          </div>
          <div>
            <label class="block text-sm">Check-out</label>
            <input v-model="form.check_out" type="date" class="w-full border p-2 rounded" />
          </div>
        </div>

        <div class="mb-3">
          <label class="block text-sm">Guests</label>
          <input v-model.number="form.guests" type="number" class="w-24 border p-2 rounded" />
        </div>

        <div class="flex justify-end">
          <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded">Confirm booking</button>
        </div>
      </form>
    </div>
  </PublicLayout>
</template>

<script setup>
import { ref } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import {  } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const props = usePage().props;
const rooms = props.rooms ?? [];

const form = ref({
  room_id: new URLSearchParams(window.location.search).get('room_id') ?? (rooms[0]?.id ?? null),
  check_in: '',
  check_out: '',
  guests: 1,
});

function submit() {
  router.post('/bookings', form.value);
}
</script>
