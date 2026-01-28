<!-- resources/js/Pages/Admin/Reports/Occupancy.vue -->
<script setup>
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import TrendChart from '@/Components/TrendChart.vue'

defineProps({
  rows: Array,
  summary: Object,
})
</script>

<template>
  <ManagerLayout>
    <div class="space-y-6">
      <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
        Occupancy Report
      </h1>

      <div class="bg-white dark:bg-gray-900 p-4 rounded-2xl shadow-sm">
        <h2 class="text-xl font-bold mb-2">
          Occupancy Today: {{ summary.occupancy }}%
        </h2>
        <TrendChart title="Occupancy Trend" endpoint="/admin/reports/charts/occupancy" />
      </div>

      <div class="overflow-x-auto bg-white dark:bg-gray-900 rounded-lg shadow-sm">
        <table class="w-full table-auto">
          <thead class="bg-gray-100 dark:bg-gray-800 text-left">
            <tr>
              <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Date</th>
              <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Bookings</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="r in rows"
              :key="r.date"
              class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800"
            >
              <td class="px-4 py-2">{{ r.date }}</td>
              <td class="px-4 py-2">{{ r.bookings }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </ManagerLayout>
</template>
