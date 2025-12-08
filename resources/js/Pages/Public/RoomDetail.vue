<template>
  <GuestLayout>
    <div class="grid md:grid-cols-2 gap-6">
      <div>
        <img :src="room.images?.[0]?.path ?? '/images/room-placeholder.jpg'" class="w-full rounded" />
      </div>
      <div>
        <h2 class="text-2xl">{{ room.room_number }} — {{ room.room_type.title }}</h2>
        <p class="mt-2">{{ room.meta?.description }}</p>
        <div class="mt-4">
          <div class="text-xl font-bold">₦{{ room.room_type.base_price }}</div>
          <inertia-link :href="`/booking?room_id=${room.id}`" class="inline-block mt-4 bg-indigo-600 text-white px-4 py-2 rounded">Book now</inertia-link>
        </div>
      </div>
    </div>
  </GuestLayout>
</template>

<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
const { props } = usePage();
const room = props.room;
</script>
