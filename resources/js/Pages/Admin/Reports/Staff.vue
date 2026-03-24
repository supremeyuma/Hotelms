<!-- resources/js/Pages/Admin/Reports/Staff.vue -->
<script setup>
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import { ref } from 'vue'

const props = defineProps({
  rows: Object,
  filters: Object, // Ensure filters is defined in props
})

const search = ref(props.filters?.search ?? '')

</script>

<template>
  <ManagerLayout>
    <div class="space-y-4">
      <div class="flex justify-between items-center">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
          Staff Report
        </h1>
        <a
          :href="`/admin/reports/staff/export/xlsx`"
          class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
        >
          Export XLSX
        </a>
      </div>

      <div class="overflow-x-auto bg-white dark:bg-gray-900 rounded-lg shadow-sm">
        <table class="w-full table-auto">
          <thead class="bg-gray-100 dark:bg-gray-800 text-left">
            <tr>
              <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Name</th>
              <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Role</th>
              <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Department</th>
              <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Status</th>
              <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Orders</th>
              <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Bookings</th>
              <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Maintenance Tasks</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="s in rows.data"
              :key="s.id"
              class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800"
            >
              <td class="px-4 py-2">{{ s.name }}</td>
              <td class="px-4 py-2">{{ s.roles?.[0]?.name || 'Unassigned' }}</td>
              <td class="px-4 py-2">{{ s.department?.name || 'Not set' }}</td>
              <td class="px-4 py-2">{{ s.suspended_at ? 'Suspended' : 'Active' }}</td>
              <td class="px-4 py-2">{{ s.orders_count }}</td>
              <td class="px-4 py-2">{{ s.bookings_count }}</td>
              <td class="px-4 py-2">{{ s.maintenance_tasks_count }}</td>
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
