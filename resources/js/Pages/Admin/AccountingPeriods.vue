<template>
  <ManagerLayout>
    <div class="space-y-6">
      <div class="flex justify-between items-center">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
          Accounting Periods
        </h1>
        <div class="flex items-center space-x-4">
          <button
            @click="initializeTaxAccounts"
            class="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700"
          >
            Initialize Tax Accounts
          </button>
          <button
            @click="autoCloseExpired"
            class="px-4 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700"
          >
            Auto-Close Expired
          </button>
          <button
            @click="showCreateForm = true"
            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
          >
            New Period
          </button>
        </div>
      </div>

      <!-- Current Period Status -->
      <div v-if="currentPeriod" class="bg-white dark:bg-gray-900 rounded-lg shadow-sm p-6">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              Current Period
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400">
              {{ formatDate(currentPeriod.start_date) }} to {{ formatDate(currentPeriod.end_date) }}
            </p>
          </div>
          <div class="flex items-center space-x-4">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
              <div class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></div>
              Open
            </span>
            <Link
              :href="`/admin/accounting-periods/${currentPeriod.id}`"
              class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
            >
              <Eye class="w-5 h-5" />
            </Link>
          </div>
        </div>
      </div>

      <!-- Create Period Form -->
      <div v-if="showCreateForm" class="bg-white dark:bg-gray-900 rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
          Create New Accounting Period
        </h3>
        <form @submit.prevent="createPeriod" class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Start Date
              </label>
              <input
                type="date"
                v-model="form.start_date"
                :min="nextPeriodDate"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                required
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                End Date
              </label>
              <input
                type="date"
                v-model="form.end_date"
                :min="form.start_date"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                required
              />
            </div>
          </div>
          <div class="flex justify-end space-x-3">
            <button
              type="button"
              @click="showCreateForm = false"
              class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="form.processing"
              class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
            >
              {{ form.processing ? 'Creating...' : 'Create Period' }}
            </button>
          </div>
        </form>
      </div>

      <!-- Period Status Check -->
      <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
          Check Period Status
        </h3>
        <div class="flex items-center space-x-4">
          <input
            type="date"
            v-model="checkDate"
            @change="checkPeriodStatus"
            class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
          />
          <div v-if="periodStatus.status" class="flex items-center space-x-2">
            <span 
              class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
              :class="periodStatus.is_open ? 
                'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'"
            >
              {{ periodStatus.is_open ? 'Open' : 'Closed' }}
            </span>
            <span v-if="periodStatus.period" class="text-sm text-gray-600 dark:text-gray-400">
              Period: {{ formatDate(periodStatus.period.start_date) }} - {{ formatDate(periodStatus.period.end_date) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Periods List -->
      <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            All Periods
          </h2>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-800">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Period
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Duration
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Status
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Notes
                </th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="period in periods" :key="period.id" 
                  class="hover:bg-gray-50 dark:hover:bg-gray-800">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                    {{ formatDate(period.start_date) }} - {{ formatDate(period.end_date) }}
                  </div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">
                    Created {{ formatDate(period.created_at) }}
                  </div>
                </td>

                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900 dark:text-gray-100">
                    {{ calculateDuration(period.start_date, period.end_date) }} days
                  </div>
                </td>

                <td class="px-6 py-4 whitespace-nowrap">
                  <span 
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                    :class="period.is_closed ? 
                      'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 
                      'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'"
                  >
                    <div 
                      class="w-2 h-2 rounded-full mr-2"
                      :class="period.is_closed ? 'bg-red-500' : 'bg-green-500 animate-pulse'"
                    ></div>
                    {{ period.is_closed ? 'Closed' : 'Open' }}
                  </span>
                </td>

                <td class="px-6 py-4">
                  <div class="text-sm text-gray-600 dark:text-gray-400">
                    {{ period.notes || '-' }}
                  </div>
                </td>

                <td class="px-6 py-4 whitespace-nowrap text-center">
                  <div class="flex justify-center space-x-2">
                    <Link
                      :href="`/admin/accounting-periods/${period.id}`"
                      class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                    >
                      <Eye class="w-4 h-4" />
                    </Link>
                    <button
                      v-if="!period.is_closed"
                      @click="closePeriod(period.id)"
                      class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300"
                      title="Close Period"
                    >
                      <Lock class="w-4 h-4" />
                    </button>
                    <button
                      v-if="period.is_closed"
                      @click="reopenPeriod(period.id)"
                      class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
                      title="Reopen Period"
                    >
                      <Unlock class="w-4 h-4" />
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
import { useForm, router } from '@inertiajs/vue3'
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
})

const showCreateForm = ref(false)
const checkDate = ref('')
const periodStatus = ref({})

const form = useForm({
  start_date: props.nextPeriodDate || '',
  end_date: '',
})

const createPeriod = () => {
  form.post(route('admin.accounting-periods.store'), {
    onSuccess: () => {
      showCreateForm.value = false
      form.reset()
      toast.success('Accounting period created successfully')
    },
    onError: () => {
      toast.error('Failed to create accounting period')
    },
  })
}

const closePeriod = (periodId) => {
  if (confirm('Are you sure you want to close this accounting period? This cannot be undone without admin privileges.')) {
    router.post(`/admin/accounting-periods/${periodId}/close`, {}, {
      onSuccess: () => toast.success('Accounting period closed successfully'),
      onError: () => toast.error('Failed to close accounting period'),
    })
  }
}

const reopenPeriod = (periodId) => {
  if (confirm('Are you sure you want to reopen this accounting period? This will allow new journal entries to be posted to this period.')) {
    router.post(`/admin/accounting-periods/${periodId}/reopen`, {}, {
      onSuccess: () => toast.success('Accounting period reopened successfully'),
      onError: () => toast.error('Failed to reopen accounting period'),
    })
  }
}

const initializeTaxAccounts = () => {
  if (confirm('Initialize default tax accounts (VAT Payable, Service Charge Payable, etc.)?')) {
    router.post('/admin/accounting-periods/initialize-tax-accounts', {}, {
      onSuccess: () => toast.success('Tax accounts initialized successfully'),
      onError: () => toast.error('Failed to initialize tax accounts'),
    })
  }
}

const autoCloseExpired = () => {
  if (confirm('Auto-close all expired periods (older than 7 days)?')) {
    router.post('/admin/accounting-periods/auto-close-expired', {}, {
      onSuccess: (response) => {
        const closedCount = response.props.flash?.success?.match(/\d+/)?.[0] || '0'
        toast.success(`Auto-closed ${closedCount} expired periods`)
      },
      onError: () => toast.error('Failed to auto-close expired periods'),
    })
  }
}

const checkPeriodStatus = () => {
  if (!checkDate.value) return

  router.get('/admin/accounting-periods/check-status', { date: checkDate.value }, {
    preserveState: false,
    onSuccess: (response) => {
      periodStatus.value = response.props
    },
  })
}

const calculateDuration = (startDate, endDate) => {
  const start = new Date(startDate)
  const end = new Date(endDate)
  return Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1
}

const formatDate = (dateString) => {
  const options = { year: 'numeric', month: 'short', day: 'numeric' }
  return new Date(dateString).toLocaleDateString(undefined, options)
}
</script>