<template>
  <GuestLayout>
    <div class="max-w-4xl mx-auto py-12">
      <h1 class="text-2xl font-bold mb-6">Available Rooms</h1>

      <div v-for="room in roomTypes" :key="room.id" class="border p-4 mb-4 rounded">
        <h2 class="text-xl font-semibold">{{ room.name }}</h2>
        <p>{{ room.description }}</p>
        <p>Price per night: ₦{{ room.price_per_night }}</p>
        <p>Available: {{ room.available_quantity }}</p>
        <p>Max Adults: {{ room.max_adults }}, Max Children: {{ room.max_children }}</p>

        <div class="mt-2">
          <label>Quantity:</label>
          <input
            type="number"
            v-model.number="quantities[room.id]"
            :max="room.available_quantity"
            min="1"
            class="input"
          />
        </div>

        <button
          class="btn btn-primary mt-2"
          :disabled="room.available_quantity === 0"
          @click="selectRoom(room.id)"
        >
          Select
        </button>
      </div>
    </div>
  </GuestLayout>
</template>

<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { defineProps } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const props = defineProps({
  roomTypes: Array,
  check_in: String,
  check_out: String,
  adults: Number,
  children: Number,
});

const quantities = useForm({});
props.roomTypes.forEach(r => (quantities[r.id] = 1));

function selectRoom(roomId) {
  router.post('/booking/select-room', {
    room_type_id: roomId,
    quantity: quantities[roomId],
    check_in: props.check_in,
    check_out: props.check_out,
    adults: props.adults,
    children: props.children,
  });
}
</script>

<style scoped>
.input {
  width: 60px;
  padding: 0.25rem;
  border: 1px solid #ccc;
  border-radius: 0.375rem;
}

.btn {
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  cursor: pointer;
}
</style>
