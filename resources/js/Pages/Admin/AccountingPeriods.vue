<template>
  <ManagerLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">Accounting Periods</h1>
        <div class="flex items-center space-x-4">
          <button class="rounded bg-purple-600 px-4 py-2 text-white hover:bg-purple-700" @click="initializeTaxAccounts">Initialize Tax Accounts</button>
          <button class="rounded bg-yellow-600 px-4 py-2 text-white hover:bg-yellow-700" @click="autoCloseExpired">Auto-Close Expired</button>
          <button class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700" @click="showCreateForm = true">New Period</button>
        </div>
      </div>

      <div v-if="currentPeriod" class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-900">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Current Period</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ formatDate(currentPeriod.start_date) }} to {{ formatDate(currentPeriod.end_date) }}</p>
          </div>
          <div class="flex items-center space-x-4">
            <span class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-800 dark:bg-green-900 dark:text-green-200">
              <div class="mr-2 h-2 w-2 rounded-full bg-green-500 animate-pulse"></div>
              Open
            </span>
            <Link :href="route(`${props.routePrefix}.accounting-periods.show`, currentPeriod.id)" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
              <Eye class="h-5 w-5" />
            </Link>
          </div>
        </div>
      </div>

      <div v-if="showCreateForm" class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-900">
        <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">Create New Accounting Period</h3>
        <form class="space-y-4" @submit.prevent="createPeriod">
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Start Date</label>
              <input v-model="form.start_date" type="date" :min="nextPeriodDate" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">End Date</label>
              <input v-model="form.end_date" type="date" :min="form.start_date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required />
            </div>
          </div>
          <div class="flex justify-end space-x-3">
            <button type="button" class="rounded-md border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700" @click="showCreateForm = false">Cancel</button>
            <button type="submit" :disabled="form.processing" class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50">{{ form.processing ? 'Creating...' : 'Create Period' }}</button>
          </div>
        </form>
      </div>

      <div class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-900">
        <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">Check Period Status</h3>
        <div class="flex items-center space-x-4">
          <input v-model="checkDate" type="date" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" @change="checkPeriodStatus" />
          <div v-if="periodStatus.status" class="flex items-center space-x-2">
            <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium" :class="periodStatus.is_open ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'">
              {{ periodStatus.is_open ? 'Open' : 'Closed' }}
            </span>
            <span v-if="periodStatus.period" class="text-sm text-gray-600 dark:text-gray-400">Period: {{ formatDate(periodStatus.period.start_date) }} - {{ formatDate(periodStatus.period.end_date) }}</span>
          </div>
        </div>
      </div>

      <div class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-900">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
          <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">All Periods</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-800">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Period</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Duration</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Notes</th>
                <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
              <tr v-for="period in periods" :key="period.id" class="hover:bg-gray-50 dark:hover:bg-gray-800">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ formatDate(period.start_date) }} - {{ formatDate(period.end_date) }}</div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">Created {{ formatDate(period.created_at) }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ calculateDuration(period.start_date, period.end_date) }} days</td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="period.is_closed ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'">
                    <div class="mr-2 h-2 w-2 rounded-full" :class="period.is_closed ? 'bg-red-500' : 'bg-green-500 animate-pulse'"></div>
                    {{ period.is_closed ? 'Closed' : 'Open' }}
                  </span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ period.notes || '-' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-center">
                  <div class="flex justify-center space-x-2">
                    <Link :href="route(`${props.routePrefix}.accounting-periods.show`, period.id)" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                      <Eye class="h-4 w-4" />
                    </Link>
                    <button v-if="!period.is_closed" class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300" title="Close Period" @click="closePeriod(period.id)">
                      <Lock class="h-4 w-4" />
                    </button>
                    <button v-if="period.is_closed" class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300" title="Reopen Period" @click="reopenPeriod(period.id)">
                      <Unlock class="h-4 w-4" />
                    </button>
                  </div>
                </td>
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
import { useForm, router, Link } from '@inertiajs/vue3'
import { ref } from 'vue'
import { Eye, Lock, Unlock } from 'lucide-vue-next'
import { useToast } from '@/Composables/useToast'

const { push: toast } = useToast()

const props = defineProps({
  periods: Array,
  openPeriods: Array,
  closedPeriods: Array,
  currentPeriod: Object,
  nextPeriodDate: String,
  routePrefix: {
    type: String,
    default: 'finance',
  },
})

const showCreateForm = ref(false)
const checkDate = ref('')
const periodStatus = ref({})

const form = useForm({
  start_date: props.nextPeriodDate || '',
  end_date: '',
})

const createPeriod = () => {
  form.post(route(`${props.routePrefix}.accounting-periods.store`), {
    onSuccess: () => {
      showCreateForm.value = false
      form.reset()
      toast.success('Accounting period created successfully')
    },
    onError: () => toast.error('Failed to create accounting period'),
  })
}

const closePeriod = (periodId) => {
  if (!confirm('Are you sure you want to close this accounting period? This cannot be undone without elevated privileges.')) return

  router.post(route(`${props.routePrefix}.accounting-periods.close`, periodId), {}, {
    onSuccess: () => toast.success('Accounting period closed successfully'),
    onError: () => toast.error('Failed to close accounting period'),
  })
}

const reopenPeriod = (periodId) => {
  if (!confirm('Are you sure you want to reopen this accounting period? This will allow new journal entries to be posted to this period.')) return

  router.post(route(`${props.routePrefix}.accounting-periods.reopen`, periodId), {}, {
    onSuccess: () => toast.success('Accounting period reopened successfully'),
    onError: () => toast.error('Failed to reopen accounting period'),
  })
}

const initializeTaxAccounts = () => {
  if (!confirm('Initialize default tax accounts (VAT Payable, Service Charge Payable, etc.)?')) return

  router.post(route(`${props.routePrefix}.accounting-periods.initialize-tax-accounts`), {}, {
    onSuccess: () => toast.success('Tax accounts initialized successfully'),
    onError: () => toast.error('Failed to initialize tax accounts'),
  })
}

const autoCloseExpired = () => {
  if (!confirm('Auto-close all expired periods older than 7 days?')) return

  router.post(route(`${props.routePrefix}.accounting-periods.auto-close-expired`), {}, {
    onSuccess: () => toast.success('Expired periods reviewed successfully'),
    onError: () => toast.error('Failed to auto-close expired periods'),
  })
}

const checkPeriodStatus = () => {
  if (!checkDate.value) return

  const url = route(`${props.routePrefix}.accounting-periods.check-status`, { date: checkDate.value })

  fetch(url, {
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
  })
    .then(response => response.json())
    .then(data => {
      periodStatus.value = data
    })
    .catch(() => {
      toast.error('Failed to check period status')
    })
}

const calculateDuration = (startDate, endDate) => {
  const start = new Date(startDate)
  const end = new Date(endDate)
  return Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1
}

const formatDate = (dateString) => new Date(dateString).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
</script>
