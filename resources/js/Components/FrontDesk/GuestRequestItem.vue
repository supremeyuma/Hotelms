<template>
  <div class="p-4 border rounded flex justify-between items-center hover:bg-gray-50">
    <div>
      <p class="font-semibold">{{ request.type | capitalize }}</p>
      <p class="text-sm text-gray-600">Room: {{ request.room.number ?? '-' }}</p>
      <p class="text-sm text-gray-600">Guest: {{ request.booking.guest_name }}</p>
      <p class="text-sm text-gray-500">Created: {{ formatDate(request.created_at) }}</p>
    </div>
    <div class="space-x-2">
      <button 
        v-if="request.status === 'pending'" 
        @click="$emit('acknowledge', request.id)"
        class="btn-acknowledge"
      >Acknowledge</button>

      <button 
        v-if="request.status !== 'completed'" 
        @click="$emit('complete', request.id)"
        class="btn-complete"
      >Complete</button>

      <span v-if="request.status === 'completed'" class="text-green-600 font-semibold">Completed</span>
    </div>
  </div>
</template>

<script setup>
import { format } from 'date-fns';

const props = defineProps({
  request: Object
});

function formatDate(date) {
  return format(new Date(date), 'yyyy-MM-dd HH:mm');
}
</script>

<style scoped>
.btn-acknowledge { @apply bg-blue-500 text-white px-2 py-1 rounded; }
.btn-complete { @apply bg-green-500 text-white px-2 py-1 rounded; }
</style>
