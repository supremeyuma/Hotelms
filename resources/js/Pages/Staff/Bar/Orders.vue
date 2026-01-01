<script setup>
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import BarLayout from '@/Layouts/Staff/BarLayout.vue'

const props = defineProps({ orders: Array })
const orders = ref(props.orders)

onMounted(() => {
  window.Echo.channel('orders.bar')
    .listen('.order.status.updated', e => {
      const index = orders.value.findIndex(o => o.id === e.order.id)
      if (index !== -1) orders.value[index] = e.order
      else orders.value.unshift(e.order)
    })
})

function setStatus(order, status) {
  router.patch(`/staff/bar/orders/${order.id}`, { status })
}
</script>

<template>
  <BarLayout>
    <div class="p-6">
      <h1 class="text-2xl font-bold mb-4">Bar Orders</h1>

      <div v-for="order in orders" :key="order.id" class="border p-4 mb-3">
        <div class="font-semibold">Order #{{ order.id }}</div>
        <div>Status: {{ order.status }}</div>

        <ul class="mt-2">
          <li v-for="item in order.items" :key="item.id">
            {{ item.menu_item.name }} × {{ item.quantity }}
          </li>
        </ul>

        <div class="flex gap-2 mt-3">
          <button @click="setStatus(order,'preparing')">Preparing</button>
          <button @click="setStatus(order,'ready')">Ready</button>
          <button @click="setStatus(order,'delivered')">Delivered</button>
        </div>
      </div>
    </div>
  </BarLayout>
</template>
