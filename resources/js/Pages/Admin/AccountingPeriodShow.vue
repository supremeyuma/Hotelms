<template>
  <ManagerLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">Accounting Period Detail</h1>
          <p class="text-sm text-gray-600 dark:text-gray-400">{{ formatDate(period.start_date) }} to {{ formatDate(period.end_date) }}</p>
        </div>
        <Link :href="route(`${routePrefix}.accounting-periods.index`)" class="rounded bg-gray-200 px-4 py-2 text-sm text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600">
          Back to Periods
        </Link>
      </div>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-900">
          <p class="text-sm text-gray-500 dark:text-gray-400">Status</p>
          <p class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ period.is_closed ? 'Closed' : 'Open' }}</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-900">
          <p class="text-sm text-gray-500 dark:text-gray-400">Entries</p>
          <p class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ journalEntries.length }}</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-900">
          <p class="text-sm text-gray-500 dark:text-gray-400">Created</p>
          <p class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ formatDate(period.created_at) }}</p>
        </div>
      </div>

      <div class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-900">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
          <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Journal Entries</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-800">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Description</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Reference</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Lines</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
              <tr v-for="entry in journalEntries" :key="entry.id">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ formatDate(entry.entry_date) }}</td>
                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ entry.description || '-' }}</td>
                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ entry.reference_type || 'Manual' }} #{{ entry.reference_id || '-' }}</td>
                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                  <div v-for="line in entry.lines" :key="line.id">{{ line.account?.name || 'Account' }}: D {{ line.debit }} / C {{ line.credit }}</div>
                </td>
              </tr>
              <tr v-if="journalEntries.length === 0">
                <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No journal entries found for this accounting period.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </ManagerLayout>
</template>

<script setup>
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import { Link } from '@inertiajs/vue3'

defineProps({
  period: Object,
  journalEntries: Array,
  routePrefix: {
    type: String,
    default: 'finance',
  },
})

const formatDate = (dateString) => new Date(dateString).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
</script>
