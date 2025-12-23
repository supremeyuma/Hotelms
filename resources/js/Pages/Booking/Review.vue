<template>
  <div class="max-w-xl mx-auto py-12">
    <h1 class="text-2xl font-bold mb-6">Review Your Booking</h1>

    <div class="border p-4 rounded mb-4">
      <h2 class="text-xl font-semibold">{{ room_type.title }}</h2>
      <p>Name: {{ booking.guest_name }}</p>
      <p>Check-in: {{ booking.check_in }}</p>
      <p>Check-out: {{ booking.check_out }}</p>
      <p>Nights: {{ nights }}</p>
      <p>Adults: {{ booking.adults }}, Children: {{ booking.children }}</p>
      <p>Quantity: {{ booking.quantity }}</p>
      <p>Price per Night: {{ room_type.base_price }}</p>
      <p>Total Price: ₦{{ total_price }}</p>
    </div>

    <button  @click="proceed" class="btn btn-primary">Proceed to Payment</button>
  </div>
</template>

<script setup>
import { defineProps } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
  booking: Object,
  room_type: Object,
  nights: Number,
  total_price: Number,
});

const form = useForm({});

function proceed() {
  form.post(route('booking.create'), {
    preserveState: true,
    onSuccess: (page) => {
      console.log("Booking created, redirected to confirmation");
    },
    onError: (errors) => {
        console.log("Validation Failed:", errors);
    }
  });
}
</script>
