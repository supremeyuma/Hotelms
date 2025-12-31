<!-- resources/js/Pages/FrontDesk/LaundryRequests.vue -->
<template>
  <FrontDeskLayout>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold">Laundry Requests</h1>

    <div v-for="req in requests" :key="req.id" class="p-4 border rounded shadow-sm">
      <p class="font-semibold">Order {{ req.requestable.order_code }} — Room {{ req.requestable.room.room_number }}</p>
      <p>Status: <span class="font-bold">{{ req.status }}</span></p>

      <ul class="mt-2 list-disc pl-6">
        <li v-for="item in req.requestable.items" :key="item.id">
          {{ item.quantity }} × {{ item.item.name }} (₦{{ item.subtotal }})
        </li>
      </ul>

      <div class="mt-2 flex space-x-2">
        <div v-for="img in req.requestable.images" :key="img.id">
          <img :src="`/storage/${img.path}`" class="h-20 w-20 object-cover rounded" />
        </div>
      </div>

      <div class="mt-2 text-sm text-gray-500">
        Status History:
        <ul class="list-disc pl-6">
          <li v-for="h in req.requestable.statusHistories" :key="h.id">
            {{ h.from_status || 'N/A' }} → {{ h.to_status }} by {{ h.changer?.name || 'Guest' }} at {{ h.created_at }}
          </li>
        </ul>
      </div>
    </div>
  </div>
  </FrontDeskLayout>
</template>

<script setup>
import FrontDeskLayout from '@/Layouts/Staff/FrontDeskLayout.vue'

const props = defineProps({
  requests: Array
})
</script>
