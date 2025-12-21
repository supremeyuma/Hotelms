<script setup>
import { ref } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import CleaningModal from './CleaningModal.vue'

defineProps({ rooms: Array })

const selectedRoom = ref(null)

const openModal = (room) => {
  selectedRoom.value = room
}
</script>

<template>

  <h1 class="text-2xl font-bold mb-6">Cleaning Dashboard</h1>

  <div class="grid grid-cols-4 gap-4">
    <div
      v-for="room in rooms"
      :key="room.id"
      class="bg-white p-4 rounded shadow cursor-pointer hover:ring-2 hover:ring-green-500"
      @click="openModal(room)"
    >
      <div class="font-semibold">Room {{ room.room_number }}</div>
      <div class="text-sm mt-1">
        {{ room.latest_cleaning?.status ?? 'dirty' }}
      </div>
    </div>
  </div>

  <CleaningModal
    v-if="selectedRoom"
    :room="selectedRoom"
    @close="selectedRoom = null"
  />

</template>
