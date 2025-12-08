<template>
  <GuestLayout>
    <div class="max-w-xl mx-auto bg-white p-6 rounded shadow text-center">
      <h2 class="text-2xl mb-4">Booking Confirmed</h2>
      <p class="mb-4">Your booking <strong>{{ booking.booking_code }}</strong> is confirmed.</p>
      <inertia-link href="/rooms" class="text-indigo-600">Back to rooms</inertia-link>
    </div>
  </GuestLayout>
</template>

<script setup>
const { props } = usePage();
const booking = props.booking;
</script>
