<template>
  <ManagerLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
          Daily Revenue by Department
        </h1>
        <div class="flex items-center space-x-4">
          <input
            v-model="filters.date"
            type="date"
            class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            @change="submit"
          />
          <button
            class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
            @click="exportReport"
          >
            Export CSV
          </button>
        </div>
      </div>

      <div class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-900">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
          <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            Revenue for {{ formatDate(date) }}
          </h2>
        </div>

        <div class="p-6">
          <div class="mb-8 grid grid-cols-1 gap-4 md:grid-cols-4">
            <div class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20">
              <div class="text-sm font-medium text-green-600 dark:text-green-400">Total Revenue</div>
              <div class="text-2xl font-bold text-green-700 dark:text-green-300">
                ₦{{ formatNumber(totalRevenue) }}
              </div>
            </div>

            <div
              v-for="dept in mainDepartments"
              :key="dept.department"
              class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20"
            >
              <div class="text-sm font-medium text-blue-600 dark:text-blue-400">{{ dept.department }}</div>
              <div class="text-xl font-bold text-blue-700 dark:text-blue-300">
                ₦{{ formatNumber(dept.amount) }}
              </div>
              <div class="mt-1 text-xs text-blue-500 dark:text-blue-400">
                {{ percentage(dept.amount, totalRevenue) }}% of total
              </div>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Department</th>
                  <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Revenue</th>
                  <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">% of Total</th>
                  <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Trend</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                <tr
                  v-for="(item, index) in revenue"
                  :key="item.department"
                  :class="index % 2 === 0 ? 'bg-white dark:bg-gray-900' : 'bg-gray-50 dark:bg-gray-800'"
                >
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ item.department }}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right">
                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">₦{{ formatNumber(item.amount) }}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right">
                    <div class="text-sm text-gray-600 dark:text-gray-400">{{ percentage(item.amount, totalRevenue) }}%</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center">
                    <span
                      v-if="item.amount > 0"
                      class="inline-flex items-center rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-200"
                    >
                      Active
                    </span>
                    <span
                      v-else
                      class="inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-800 dark:bg-gray-700 dark:text-gray-200"
                    >
                      No Revenue
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </ManagerLayout>
</template>

<script setup>
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  revenue: Array,
  date: String,
  filters: Object,
  routePrefix: {
    type: String,
    default: 'finance',
  },
})

const filters = useForm({
  date: props.filters?.date || props.date,
})

const totalRevenue = computed(() => props.revenue.reduce((sum, item) => sum + item.amount, 0))

const mainDepartments = computed(() =>
  props.revenue
    .filter(item => item.amount > 0)
    .sort((a, b) => b.amount - a.amount)
    .slice(0, 3)
)

const submit = () => {
  filters.get(route(`${props.routePrefix}.reports.daily-revenue`), {
    preserveState: true,
  })
}

const exportReport = () => {
  window.open(route(`${props.routePrefix}.reports.daily-revenue.export`, { date: filters.date }), '_blank')
}

const formatNumber = (num) => new Intl.NumberFormat('en-NG').format(Math.abs(num))
const percentage = (part, total) => (total > 0 ? ((part / total) * 100).toFixed(1) : '0.0')
const formatDate = (dateString) => new Date(dateString).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' })
</script>
