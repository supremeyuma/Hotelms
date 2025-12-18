<template>
  <AuthenticatedLayout>
    <div class="space-y-6">

      <!-- Header -->
      <div class="flex justify-between items-center">
        <h1 class="text-2xl font-semibold">
          Inventory: {{ item.name }}
        </h1>

        <span
          v-if="item.quantity <= (item.low_stock_threshold ?? 10)"
          class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded"
        >
          Low Stock
        </span>
      </div>

      <!-- Item Summary -->
      <div class="grid grid-cols-2 gap-4 bg-white p-4 rounded shadow">
        <div><strong>SKU:</strong> {{ item.sku }}</div>
        <div><strong>Quantity:</strong> {{ item.quantity }}</div>
        <div><strong>Unit:</strong> {{ item.unit ?? '-' }}</div>
        <div><strong>Last Updated:</strong> {{ item.updated_at }}</div>
      </div>

      <!-- Usage History -->
      <div class="bg-white rounded shadow p-4">
        <h2 class="text-lg font-semibold mb-3">Usage History</h2>

        <table class="w-full text-sm border">
          <thead class="bg-gray-100">
            <tr>
              <th class="p-2 text-left">Date</th>
              <th class="p-2 text-left">Staff</th>
              <th class="p-2 text-left">Change</th>
              <th class="p-2 text-left">Reason</th>
              <th class="p-2 text-left">Action</th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="log in item.logs"
              :key="log.id"
              class="border-t"
            >
              <td class="p-2">{{ log.created_at }}</td>
              <td class="p-2">{{ log.staff?.name ?? 'System' }}</td>

              <td
                class="p-2 font-semibold"
                :class="log.change < 0 ? 'text-red-600' : 'text-green-600'"
              >
                {{ log.change }}
              </td>

              <td class="p-2">
                {{ log.meta?.reason ?? '-' }}
              </td>

              <td class="p-2">
                <PrimaryButton
                  v-if="canUndo(log)"
                  size="sm"
                  @click="undo(log)"
                >
                  Undo
                </PrimaryButton>
              </td>
            </tr>

            <tr v-if="!item.logs.length">
              <td colspan="5" class="p-4 text-center text-gray-500">
                No usage history yet
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { router } from '@inertiajs/vue3'
import { PrimaryButton } from '@/Components'

const props = defineProps({
  item: Object
})

function canUndo(log) {
  return log.change < 0 && !log.meta?.undone
}

function undo(log) {
  if (!confirm('Undo this inventory usage?')) return

  router.post(
    route('admin.inventory.logs.undo', log.id),
    {},
    { preserveScroll: true }
  )
}
</script>
