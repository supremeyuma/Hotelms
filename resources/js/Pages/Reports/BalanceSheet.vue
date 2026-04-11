<template>
  <ManagerLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
          Balance Sheet
        </h1>
        <div class="flex items-center space-x-4">
          <label class="text-gray-600 dark:text-gray-400">As of date:</label>
          <input
            v-model="filters.as_of"
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
            Balance Sheet as of {{ report.as_of }}
          </h2>
        </div>

        <div class="p-6">
          <div class="grid grid-cols-1 gap-8 md:grid-cols-2">
            <div>
              <h3 class="mb-4 text-lg font-semibold text-blue-600 dark:text-blue-400">Assets</h3>
              <div class="space-y-2">
                <div
                  v-for="item in report.assets"
                  :key="item.account"
                  class="flex justify-between border-b border-gray-100 py-2 dark:border-gray-800"
                >
                  <span class="text-gray-700 dark:text-gray-300">{{ item.account }}</span>
                  <span class="font-medium text-blue-600 dark:text-blue-400">
                    ₦{{ formatNumber(item.balance) }}
                  </span>
                </div>
              </div>
            </div>

            <div>
              <h3 class="mb-4 text-lg font-semibold text-orange-600 dark:text-orange-400">Liabilities &amp; Equity</h3>

              <div class="mb-6">
                <h4 class="mb-2 text-md font-medium text-gray-600 dark:text-gray-400">Liabilities</h4>
                <div class="space-y-2">
                  <div
                    v-for="item in report.liabilities"
                    :key="item.account"
                    class="flex justify-between border-b border-gray-100 py-2 dark:border-gray-800"
                  >
                    <span class="text-gray-700 dark:text-gray-300">{{ item.account }}</span>
                    <span class="font-medium text-orange-600 dark:text-orange-400">
                      ₦{{ formatNumber(item.balance) }}
                    </span>
                  </div>
                </div>
              </div>

              <div>
                <h4 class="mb-2 text-md font-medium text-gray-600 dark:text-gray-400">Equity</h4>
                <div class="space-y-2">
                  <div
                    v-for="item in report.equity"
                    :key="item.account"
                    class="flex justify-between border-b border-gray-100 py-2 dark:border-gray-800"
                  >
                    <span class="text-gray-700 dark:text-gray-300">{{ item.account }}</span>
                    <span class="font-medium text-purple-600 dark:text-purple-400">
                      ₦{{ formatNumber(item.balance) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-8 border-t-2 border-gray-200 pt-6 dark:border-gray-700">
            <div class="grid grid-cols-1 gap-8 md:grid-cols-2">
              <div class="text-center">
                <div class="mb-2 text-lg font-semibold text-gray-700 dark:text-gray-300">Total Assets</div>
                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                  ₦{{ formatNumber(report.totals.assets) }}
                </div>
              </div>
              <div class="text-center">
                <div class="mb-2 text-lg font-semibold text-gray-700 dark:text-gray-300">Total Liabilities &amp; Equity</div>
                <div class="text-2xl font-bold text-orange-600 dark:text-orange-400">
                  ₦{{ formatNumber(report.totals.liabilities_equity) }}
                </div>
              </div>
            </div>

            <div class="mt-4 text-center">
              <div
                class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium"
                :class="report.totals.assets === report.totals.liabilities_equity
                  ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                  : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'"
              >
                <span v-if="report.totals.assets === report.totals.liabilities_equity">
                  Balanced
                </span>
                <span v-else>
                  Out of Balance: ₦{{ formatNumber(Math.abs(report.totals.assets - report.totals.liabilities_equity)) }}
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
  as_of: props.filters?.as_of || '',
})

const submit = () => {
  filters.get(route(`${props.routePrefix}.reports.balance-sheet`), {
    preserveState: true,
  })
}

const exportReport = () => {
  window.open(route(`${props.routePrefix}.reports.balance-sheet.export`, { as_of: filters.as_of }), '_blank')
}

const formatNumber = (num) => new Intl.NumberFormat('en-NG').format(Math.abs(num))
</script>
