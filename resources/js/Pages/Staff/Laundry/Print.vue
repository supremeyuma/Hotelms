<script setup>
import LaundryLayout from '@/Layouts/Staff/LaundryLayout.vue'
const props = defineProps({ order: Object });

window.print();
</script>

<template>
  <LaundryLayout>
  <div class="p-6 text-sm">
    <h1 class="text-xl font-bold">Laundry Order {{ order.order_code }}</h1>
    <p>Room {{ order.room.room_number }}</p>

    <ul class="mt-4">
      <li v-for="i in order.items" :key="i.id">
        {{ i.quantity }} × {{ i.item.name }} — ₦{{ i.subtotal }}
      </li>
    </ul>

    <p class="mt-4">
      Total: ₦{{ order.total_amount }}
    </p>
  </div>
  </LaundryLayout>
</template>
