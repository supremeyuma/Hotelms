<template>
  <GuestLayout>
    <div class="grid md:grid-cols-2 gap-6">
      <div>
        <img :src="room.images?.[0]?.path ?? '/images/room-placeholder.jpg'" class="w-full rounded" />
      </div>
      <div>
        <h2 class="text-2xl">{{ room.name }} — {{ room.room_type }}</h2>
        <p class="mt-2">{{ room.meta?.description }}</p>
        <div class="mt-4">
          <div class="text-xl font-bold">₦{{ room.room_type }}</div>
          <Link :href="`/booking?room_id=${room.id}`" class="inline-block mt-4 bg-indigo-600 text-white px-4 py-2 rounded">Book now</Link>
        </div>
      </div>
    </div>
  </GuestLayout>
</template>

<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Link, usePage } from '@inertiajs/vue3'; // Ensure this is @inertiajs/vue3
import { computed } from 'vue';

const page = usePage();
const room = computed(() => page.props.room);

console.log(room);
</script>
