<!-- resources/js/Pages/Admin/Reports/Inventory.vue -->
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { ref, onMounted } from 'vue'

const props = defineProps({
  rows: Object,
  filters: Object, // Ensure filters is defined in props
})

const search = ref(props.filters?.search ?? '')

// Helper to format date
function formatDate(dateString) {
  const options = { year: 'numeric', month: 'short', day: 'numeric' }
  return new Date(dateString).toLocaleDateString(undefined, options)
}

// Log the rows data
onMounted(() => {
  console.log('Rows data:', props.rows.data)
})

</script>

<template>
  <AuthenticatedLayout>
    <div class="space-y-4">
      <div class="flex justify-between items-center">
        <h1 class="text-2xl font-semibold text-gray-800 ">
          Inventory Report
        </h1>
        <a
          :href="`/admin/reports/inventory/export/xlsx`"
          class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
        >
          Export XLSX
        </a>
      </div>

      <div class="overflow-x-auto bg-white  rounded-lg shadow-sm">
        <table class="w-full table-auto">
          <thead class="bg-gray-100 dark:bg-gray-800 text-left text-gray-800">
            <tr>
              <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Item</th>
              <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Change</th>
              <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Staff</th>
              <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Date</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="i in rows.data"
              :key="i.id"
              class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800"
            >
                <td class="px-4 py-2">{{ i.inventory_item?.name ?? '—' }}</td>
                <td class="px-4 py-2">{{ i.change }}</td>
                <td class="px-4 py-2">{{ i.staff?.name ?? '—' }}</td>
                <td class="px-4 py-2">{{ formatDate(i.created_at) }}</td>

            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-4">
        <Pagination :data="rows" :links="rows.links"/>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
