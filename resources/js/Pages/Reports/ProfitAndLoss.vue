<template>
  <ManagerLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
          Profit &amp; Loss
        </h1>
        <div class="flex items-center space-x-4">
          <input
            v-model="filters.from"
            type="date"
            class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            @change="submit"
          />
          <span class="text-gray-500">to</span>
          <input
            v-model="filters.to"
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
            Period: {{ report.period.from }} to {{ report.period.to }}
          </h2>
        </div>

        <div class="space-y-6 p-6">
          <div>
            <h3 class="mb-4 text-lg font-semibold text-green-600 dark:text-green-400">Revenue</h3>
            <div class="space-y-2">
              <div
                v-for="item in report.revenue"
                :key="item.account"
                class="flex justify-between border-b border-gray-100 py-2 dark:border-gray-800"
              >
                <span class="text-gray-700 dark:text-gray-300">{{ item.account }}</span>
                <span class="font-medium text-green-600 dark:text-green-400">
                  NGN {{ formatNumber(item.amount) }}
                </span>
              </div>
            </div>
          </div>

          <div>
            <h3 class="mb-4 text-lg font-semibold text-red-600 dark:text-red-400">Expenses</h3>
            <div class="space-y-2">
              <div
                v-for="item in report.expenses"
                :key="item.account"
                class="flex justify-between border-b border-gray-100 py-2 dark:border-gray-800"
              >
                <span class="text-gray-700 dark:text-gray-300">{{ item.account }}</span>
                <span class="font-medium text-red-600 dark:text-red-400">
                  NGN {{ formatNumber(item.amount) }}
                </span>
              </div>
            </div>
          </div>

          <div class="mt-6 border-t-2 border-gray-200 pt-6 dark:border-gray-700">
            <div class="space-y-2">
              <div class="flex justify-between py-2">
                <span class="text-lg font-semibold text-gray-700 dark:text-gray-300">Total Revenue</span>
                <span class="text-lg font-semibold text-green-600 dark:text-green-400">
                  NGN {{ formatNumber(report.totals.revenue) }}
                </span>
              </div>
              <div class="flex justify-between py-2">
                <span class="text-lg font-semibold text-gray-700 dark:text-gray-300">Total Expenses</span>
                <span class="text-lg font-semibold text-red-600 dark:text-red-400">
                  NGN {{ formatNumber(report.totals.expenses) }}
                </span>
              </div>
              <div class="flex justify-between border-t-2 border-gray-300 py-3 text-xl font-bold dark:border-gray-600">
                <span class="text-gray-900 dark:text-gray-100">Net Profit</span>
                <span :class="report.totals.net_profit >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                  NGN {{ formatNumber(report.totals.net_profit) }}
                </span>
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
  report: Object,
  filters: Object,
  routePrefix: {
    type: String,
    default: 'finance',
  },
})

const filters = useForm({
  from: props.filters?.from || '',
  to: props.filters?.to || '',
})

const submit = () => {
  filters.get(route(`${props.routePrefix}.reports.profit-loss`), {
    preserveState: true,
  })
}

const exportReport = () => {
  window.open(route(`${props.routePrefix}.reports.profit-loss.export`, { from: filters.from, to: filters.to }), '_blank')
}

const formatNumber = (num) => new Intl.NumberFormat('en-NG').format(Math.abs(num))
</script>
