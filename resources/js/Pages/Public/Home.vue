<template>
  <GuestLayout>
    <section class="py-12">
      <h2 class="text-3xl font-bold mb-4">Welcome to HotelMS</h2>
      <p class="mb-6">Comfortable stays, easy bookings.</p>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white p-4 rounded shadow">
          <h3 class="font-semibold">Rooms</h3>
          <p class="text-sm">Explore comfortable rooms.</p>
          <inertia-link class="text-indigo-600 mt-2 inline-block" href="/rooms">View rooms →</inertia-link>
        </div>
        <div class="bg-white p-4 rounded shadow">
          <h3 class="font-semibold">Book</h3>
          <p class="text-sm">Quick booking flow.</p>
          <inertia-link class="text-indigo-600 mt-2 inline-block" href="/booking">Book now →</inertia-link>
        </div>
        <div class="bg-white p-4 rounded shadow">
          <h3 class="font-semibold">Gallery</h3>
          <p class="text-sm">See our property.</p>
          <inertia-link class="text-indigo-600 mt-2 inline-block" href="/gallery">Gallery →</inertia-link>
        </div>
      </div>
    </section>
  </GuestLayout>
</template>

<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
</script>
