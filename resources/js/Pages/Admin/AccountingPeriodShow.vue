<template>
  <ManagerLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-slate-800 dark:text-slate-100">Accounting Period Detail</h1>
          <p class="text-sm text-slate-600 dark:text-slate-400">{{ formatDate(period.start_date) }} to {{ formatDate(period.end_date) }}</p>
        </div>
        <Link :href="route(`${routePrefix}.accounting-periods.index`)" class="rounded bg-slate-200 px-4 py-2 text-sm text-slate-800 hover:bg-slate-300 dark:bg-slate-700 dark:text-slate-100 dark:hover:bg-slate-600">
          Back to Periods
        </Link>
      </div>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-lg bg-white p-6 shadow-sm dark:bg-slate-900">
          <p class="text-sm text-slate-500 dark:text-slate-400">Status</p>
          <p class="mt-2 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ period.is_closed ? 'Closed' : 'Open' }}</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow-sm dark:bg-slate-900">
          <p class="text-sm text-slate-500 dark:text-slate-400">Entries</p>
          <p class="mt-2 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ journalEntries.length }}</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow-sm dark:bg-slate-900">
          <p class="text-sm text-slate-500 dark:text-slate-400">Created</p>
          <p class="mt-2 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ formatDate(period.created_at) }}</p>
        </div>
      </div>

      <div class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-slate-900">
        <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-700">
          <h2 class="text-lg font-medium text-slate-900 dark:text-slate-100">Journal Entries</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-slate-50 dark:bg-slate-800">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Description</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Reference</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Lines</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white dark:divide-slate-700 dark:bg-slate-900">
              <tr v-for="entry in journalEntries" :key="entry.id">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-slate-100">{{ formatDate(entry.entry_date) }}</td>
                <td class="px-6 py-4 text-sm text-slate-900 dark:text-slate-100">{{ entry.description || '-' }}</td>
                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ entry.reference_type || 'Manual' }} #{{ entry.reference_id || '-' }}</td>
                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                  <div v-for="line in entry.lines" :key="line.id">{{ line.account?.name || 'Account' }}: D {{ line.debit }} / C {{ line.credit }}</div>
                </td>
              </tr>
              <tr v-if="journalEntries.length === 0">
                <td colspan="4" class="px-6 py-8 text-center text-sm text-slate-500 dark:text-slate-400">No journal entries found for this accounting period.</td>
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
