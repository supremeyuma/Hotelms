<script setup>
defineProps({ orders: Array })
</script>

<template>
  <div class="max-w-xl mx-auto p-6 space-y-4">
    <h1 class="text-xl font-black uppercase">Your Orders</h1>

    <div v-for="order in orders" :key="order.id" class="border rounded-xl p-4 bg-white shadow-sm">
      <div class="flex justify-between mb-2">
        <span class="font-bold text-sm">{{ order.order_code }}</span>
        <span class="text-xs uppercase">{{ order.status }}</span>
      </div>

      <div v-for="item in order.items" :key="item.id" class="text-sm flex justify-between">
        <span>{{ item.quantity }} × {{ item.name }}</span>
        <span>₦{{ item.subtotal }}</span>
      </div>

      <div class="text-right font-black mt-2">
        ₦{{ order.total }}
      </div>
    </div>
  </div>
</template>
