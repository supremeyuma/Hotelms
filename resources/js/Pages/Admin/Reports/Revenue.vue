<script setup>
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import TrendChart from '@/Components/TrendChart.vue'

const props = defineProps({
  rows: Object,
  filters: Object,
  routePrefix: {
    type: String,
    default: 'admin',
  },
})

function formatDate(dateString) {
  const options = { year: 'numeric', month: 'short', day: 'numeric' }
  return new Date(dateString).toLocaleDateString(undefined, options)
}
</script>

<template>
  <ManagerLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
          Revenue Report
        </h1>
        <a
          :href="route(`${props.routePrefix}.reports.revenue.export`, 'xlsx')"
          class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
        >
          Export XLSX
        </a>
      </div>

      <TrendChart title="Revenue Trend" :endpoint="`/${props.routePrefix}/reports/charts/revenue`" />

      <div class="overflow-x-auto rounded-lg bg-white shadow-sm dark:bg-gray-900">
        <table class="w-full table-auto">
          <thead class="bg-gray-100 text-left dark:bg-gray-800">
            <tr>
              <th class="px-4 py-2 text-gray-700 dark:text-gray-200">ID</th>
              <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Total Amount</th>
              <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Customer</th>
              <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Date</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="r in rows.data"
              :key="r.id"
              class="border-b border-gray-200 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
            >
              <td class="px-4 py-2">{{ r.id }}</td>
              <td class="px-4 py-2">NGN {{ r.total_amount }}</td>
              <td class="px-4 py-2">{{ r.customer_name ?? '-' }}</td>
              <td class="px-4 py-2">{{ formatDate(r.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-4">
        <pagination :data="rows" />
      </div>
    </div>
  </ManagerLayout>
</template>
