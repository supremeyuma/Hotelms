<template>
<GuestLayout>
  <div class="max-w-xl mx-auto py-12 text-center">
    <h1 class="text-2xl font-bold mb-6">Booking Confirmed!</h1>

    <h1 class="text-2xl font-bold mb-6">Booking Code - {{ booking.booking_code }}</h1>

    <p class="mb-2">Thank you, {{ booking.guest_name }}.</p>
    <p class="mb-2">Your booking for <strong>{{ booking.quantity }} {{ booking.room_type.title}}</strong> is confirmed.</p>
    <p class="mb-2">Check-in: {{ formatDateTime(booking.check_in) }}</p>
    <p class="mb-2">Check-out: {{ formatDateTime(booking.check_out) }}</p>
    <p class="mb-2">Total Price: ₦{{ booking.total_amount }}</p>
    <p class="mt-4">A confirmation email/SMS has been sent to {{ booking.guest_email }} / {{ booking.guest_phone }}</p>
  </div>
</GuestLayout>
</template>

<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { defineProps } from 'vue';

const props = defineProps({
  booking: Object,
});
console.log(props.booking);

function formatDateTime(dateString) {
  const d = new Date(dateString);
  const hours = d.getHours();
  const minutes = d.getMinutes();

  let timeLabel;
  if (hours === 12 && minutes === 0) {
    timeLabel = '12 noon';
  } else if (hours === 0 && minutes === 0) {
    timeLabel = '12 noon';
  } else {
    timeLabel = timeLabel = '12 noon';/*d.toLocaleTimeString('en-NG', {
      hour: 'numeric',
      minute: '2-digit',
      hour12: true,
    });*/
  }

  const date = d.toLocaleDateString('en-NG', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });

  return `${date} · ${timeLabel}`;
}

</script>
