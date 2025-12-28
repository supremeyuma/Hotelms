<template>
  <div class="space-y-6 p-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">Laundry Dashboard</h1>

        <a
            :href="route('staff.laundry-items.index')"
            class="btn-secondary"
        >
            Manage Laundry Items
        </a>
    </div>


    <div v-if="orders.length === 0" class="text-gray-500 text-center py-12">
        No laundry orders yet.
    </div>
    <div v-for="order in orders" :key="order.id" class="p-4 border rounded shadow-sm">
      <div class="flex justify-between">
        <div>
          <p class="font-semibold">Order {{ order.order_code }} — Room {{ order.room.room_number }}</p>
          <p>Status: <span class="font-bold">{{ order.status.value }}</span></p>
        </div>
        <form @submit.prevent="updateStatus(order)" class="flex space-x-2">
          <select v-model="order.newStatus" class="border rounded px-2 py-1">
            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.value }}</option>
          </select>
          <button type="submit" class="btn-primary">Update</button>
        </form>
      </div>

      <div class="mt-2 flex space-x-2">
        <div v-for="img in order.images" :key="img.id">
          <img :src="`/storage/${img.path}`" alt="Laundry" class="h-20 w-20 object-cover rounded" />
        </div>
      </div>

      <ul class="mt-2 list-disc pl-6">
        <li v-for="item in order.items" :key="item.id">
          {{ item.quantity }} × {{ item.item.name }} (₦{{ item.subtotal }})
        </li>
      </ul>

      <div class="mt-2 text-sm text-gray-500">
        Status History:
        <ul class="list-disc pl-6">
          <li v-for="h in order.statusHistories" :key="h.id">
            {{ h.from_status || 'N/A' }} → {{ h.to_status }} by {{ h.changer?.name || 'Guest' }} at {{ h.created_at }}
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  orders: Array
});

// Make orders reactive
const orders = ref([...props.orders]);

const statuses = [
  { value: 'requested' },
  { value: 'pickup_scheduled' },
  { value: 'picked_up' },
  { value: 'washing' },
  { value: 'ready' },
  { value: 'delivered' },
  { value: 'cancelled' },
];

// Update order status via POST
function updateStatus(order) {
  if (!order.newStatus) return;
  router.post(route('staff.laundry.updateStatus', order.id), { status: order.newStatus });
}

// Laravel Echo listener
onMounted(() => {
  if (!window.Echo) return;

  window.Echo.channel('laundry-orders')
    .listen('.LaundryOrderUpdated', (e) => {
      const updatedOrder = e.order;

      orders.value = orders.value.filter(o => o.id !== updatedOrder.id);
      orders.value.unshift(updatedOrder);
    });
});

onBeforeUnmount(() => {
  window.Echo?.leave('laundry-orders');
});
</script>
