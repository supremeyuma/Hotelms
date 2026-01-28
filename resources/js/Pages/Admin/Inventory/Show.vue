<template>
  <ManagerLayout>
    <div class="space-y-6">

      <!-- Header -->
      <div class="flex justify-between items-center">
        <h1 class="text-2xl font-semibold">
          Inventory: {{ item.name }}
        </h1>

        <span
          v-if="item.low_stock"
          class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded"
        >
          Low Stock
        </span>
      </div>

      <!-- Summary -->
      <div class="grid grid-cols-2 gap-4 bg-white p-4 rounded shadow">
        <div><strong>SKU:</strong> {{ item.sku }}</div>
        <div><strong>Total Stock:</strong> {{ item.total_stock }}</div>
        <div><strong>Unit:</strong> {{ item.unit ?? '-' }}</div>
        <div><strong>Updated:</strong> {{ item.updated_at }}</div>
      </div>

      <!-- Stock by Location -->
      <div class="bg-white rounded shadow p-4">
        <h2 class="text-lg font-semibold mb-3">Stock by Location</h2>

        <div
          v-for="stock in item.stocks"
          :key="stock.location_id"
          class="flex justify-between border-b py-2 text-sm"
        >
          <span>{{ stock.location }}</span>
          <span class="font-semibold">{{ stock.quantity }}</span>
        </div>
      </div>

      <!-- Movement History -->
      <div class="bg-white rounded shadow p-4">
        <h2 class="text-lg font-semibold mb-3">Inventory Movements</h2>

        <table class="w-full text-sm border">
          <thead class="bg-gray-100">
            <tr>
              <th class="p-2 text-left">Date</th>
              <th class="p-2 text-left">Staff</th>
              <th class="p-2 text-left">Type</th>
              <th class="p-2 text-left">Qty</th>
              <th class="p-2 text-left">Location</th>
              <th class="p-2 text-left">Reason</th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="move in item.movements"
              :key="move.id"
              class="border-t"
            >
              <td class="p-2">{{ move.created_at }}</td>
              <td class="p-2">{{ move.staff?.name ?? 'System' }}</td>

              <td
                class="p-2 font-semibold"
                :class="move.type === 'out' ? 'text-red-600' : 'text-green-600'"
              >
                {{ move.type.toUpperCase() }}
              </td>

              <td class="p-2 font-semibold">{{ move.quantity }}</td>
              <td class="p-2">{{ move.location }}</td>
              <td class="p-2">{{ move.reason ?? '-' }}</td>
            </tr>

            <tr v-if="!item.movements.length">
              <td colspan="6" class="p-4 text-center text-gray-500">
                No inventory movements yet
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </ManagerLayout>
</template>

<script setup>
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'

const props = defineProps({
  item: Object
})

//console.log(props.item);
</script>
