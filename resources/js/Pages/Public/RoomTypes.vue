<template>
  <GuestLayout>
    <div>
      <h2 class="text-2xl mb-4">Rooms</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div v-for="room in rooms" :key="room.id" class="bg-white p-4 rounded shadow">
          <h3 class="font-semibold">{{ room.room_number }} — {{ room.room_type.title }}</h3>
          <p class="text-sm">{{ room.meta?.description }}</p>
          <div class="mt-4 flex justify-between items-center">
            <div class="text-lg font-bold">₦{{ room.room_type.base_price }}</div>
            <Link :href="`/rooms/${room.id}`" class="text-indigo-600">View</Link>
          </div>
        </div>
      </div>
    </div>
  </GuestLayout>
</template>

<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';

// rooms would be passed from the controller as page props (Inertia)
const props = usePage().props;
const rooms = ref(props.rooms ?? []);
</script>
