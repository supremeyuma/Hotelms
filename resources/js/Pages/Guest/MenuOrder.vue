<template>
  <div class="p-4 border rounded space-y-4">
    <h2 class="font-semibold text-lg mb-2">{{ serviceName }} Menu</h2>

    <ul class="space-y-2">
      <li v-for="item in menuItems" :key="item.id" class="flex justify-between items-center">
        <span>{{ item.name }} — ₦{{ item.price }}</span>
        <input type="number" min="0" v-model.number="order[item.id]" class="w-16 border rounded px-1" />
      </li>
    </ul>

    <button @click="submitOrder" class="btn-primary mt-4">Submit Order</button>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { Inertia } from '@inertiajs/inertia';

const props = defineProps({
  serviceName: String, // Kitchen or Bar
  menuItems: Array,
  bookingToken: String
});

const order = reactive({});

function submitOrder() {
  const items = Object.entries(order)
    .filter(([id, qty]) => qty > 0)
    .map(([id, qty]) => ({ item_id: id, quantity: qty }));

  if (!items.length) {
    alert('Select at least one item');
    return;
  }

  Inertia.post(`/guest/room/${props.bookingToken}/service-request`, {
    type: props.serviceName.toLowerCase(),
    items
  });
}

// Example reactive data for menu items
const showMenuOrder = ref(false);
const menuType = ref('Kitchen'); // or 'Bar'
const menuItems = ref([
  { id: 1, name: 'Burger', price: 2500 },
  { id: 2, name: 'Pizza', price: 4000 },
  { id: 3, name: 'Coke', price: 500 },
]);

</script>

<style scoped>
.btn-primary {
  background-color: #2563eb;
  color: white;
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
}
</style>
