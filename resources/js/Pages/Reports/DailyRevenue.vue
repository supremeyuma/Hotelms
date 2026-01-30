<template>
  <ManagerLayout>
    <div class="space-y-6">
      <div class="flex justify-between items-center">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
          Daily Revenue by Department
        </h1>
        <div class="flex items-center space-x-4">
          <input
            type="date"
            v-model="filters.date"
            class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            @change="submit"
          />
          <button
            @click="exportReport"
            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
          >
            Export PDF
          </button>
        </div>
      </div>

      <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            Revenue for {{ formatDate(date) }}
          </h2>
        </div>

        <div class="p-6">
          <!-- Revenue Cards -->
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 border border-green-200 dark:border-green-800">
              <div class="text-sm text-green-600 dark:text-green-400 font-medium">Total Revenue</div>
              <div class="text-2xl font-bold text-green-700 dark:text-green-300">
                ₦{{ formatNumber(totalRevenue) }}
              </div>
            </div>
            
            <div v-for="dept in mainDepartments" :key="dept.name" 
                 class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
              <div class="text-sm text-blue-600 dark:text-blue-400 font-medium">{{ dept.name }}</div>
              <div class="text-xl font-bold text-blue-700 dark:text-blue-300">
                ₦{{ formatNumber(dept.amount) }}
              </div>
              <div class="text-xs text-blue-500 dark:text-blue-400 mt-1">
                {{ percentage(dept.amount, totalRevenue) }}% of total
              </div>
            </div>
          </div>

          <!-- Detailed Revenue Table -->
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Department
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Revenue
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    % of Total
                  </th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Trend
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="(item, index) in revenue" :key="item.department" 
                    :class="index % 2 === 0 ? 'bg-white dark:bg-gray-900' : 'bg-gray-50 dark:bg-gray-800'">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                      {{ item.department }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right">
                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                      ₦{{ formatNumber(item.amount) }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                      {{ percentage(item.amount, totalRevenue) }}%
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center">
                    <div class="flex justify-center">
                      <span v-if="item.amount > 0" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                        Active
                      </span>
                      <span v-else class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                        No Revenue
                      </span>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Revenue Distribution Chart -->
          <div class="mt-8">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Revenue Distribution</h3>
            <div class="relative h-64">
              <div class="absolute inset-0 flex items-end justify-between space-x-2">
                <div v-for="item in revenue.filter(r => r.amount > 0)" 
                     :key="item.department"
                     class="flex-1 bg-blue-500 dark:bg-blue-600 rounded-t hover:bg-blue-600 dark:hover:bg-blue-700 transition-colors relative group"
                     :style="{ height: (item.amount / totalRevenue * 100) + '%' }">
                  <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-800 dark:bg-gray-700 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
                    {{ item.department }}: ₦{{ formatNumber(item.amount) }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </ManagerLayout>
</template>

<script setup>
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
  revenue: Array,
  date: String,
  filters: Object,
})

const filters = useForm({
  date: props.filters?.date || props.date,
})

const totalRevenue = computed(() => {
  return props.revenue.reduce((sum, item) => sum + item.amount, 0)
})

const mainDepartments = computed(() => {
  return props.revenue
    .filter(item => item.amount > 0)
    .sort((a, b) => b.amount - a.amount)
    .slice(0, 3)
})

const submit = () => {
  filters.get(route('admin.reports.daily-revenue'), {
    preserveState: true,
  })
}

const exportReport = () => {
  window.open(`/admin/reports/daily-revenue/export/pdf?date=${filters.date}`, '_blank')
}

const formatNumber = (num) => {
  return new Intl.NumberFormat('en-NG').format(Math.abs(num))
}

const percentage = (part, total) => {
  return total > 0 ? ((part / total) * 100).toFixed(1) : '0.0'
}

const formatDate = (dateString) => {
  const options = { year: 'numeric', month: 'long', day: 'numeric' }
  return new Date(dateString).toLocaleDateString(undefined, options)
}
</script>