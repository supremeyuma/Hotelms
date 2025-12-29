<script setup>
import { router } from '@inertiajs/vue3';

const props = defineProps({
  orders: Array,
  statuses: Array,
  activeStatus: String,
});

function filter(status) {
  router.get(route('staff.laundry.index'), { status }, { preserveState: true });
}

function format(date) {
  return new Date(date).toLocaleString();
}
</script>

<template>
  <div class="p-6 space-y-6">
    <div class="flex justify-between">
      <h1 class="text-2xl font-bold">Laundry Dashboard</h1>

      <select
        class="border rounded px-3 py-1"
        @change="filter($event.target.value)"
      >
        <option value="">All</option>
        <option
          v-for="s in statuses"
          :key="s.value"
          :value="s.value"
          :selected="s.value === activeStatus"
        >
          {{ s.value }}
        </option>
      </select>
    </div>

    <div v-for="order in orders" :key="order.id"
      class="border p-4 rounded hover:bg-gray-50 cursor-pointer"
      @click="router.visit(route('staff.laundry.show', order.id))"
    >
      <p class="font-semibold">
        {{ order.order_code }} — Room {{ order.room.room_number }}
      </p>
      <p class="text-sm text-gray-500">
        Requested: {{ format(order.created_at) }}
      </p>
      <p>Status: <strong>{{ order.status.value }}</strong></p>
    </div>
  </div>
</template>
