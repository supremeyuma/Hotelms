<template>
  <ManagerLayout>
    <div class="space-y-6">
      <div class="flex justify-between items-center">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
          Outstanding Balances
        </h1>
        <div class="flex items-center space-x-4">
          <select
            v-model="filters.view"
            @change="submit"
            class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
          >
            <option value="room">Room View</option>
            <option value="booking">Booking View</option>
          </select>
          <button
            @click="exportReport"
            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
          >
            Export XLSX
          </button>
        </div>
      </div>

      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm p-6">
          <div class="flex items-center">
            <div class="p-3 bg-red-100 dark:bg-red-900/20 rounded-lg">
              <Banknote class="w-6 h-6 text-red-600 dark:text-red-400" />
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-600 dark:text-gray-400">Total Outstanding</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                ₦{{ formatNumber(summary.total_outstanding) }}
              </p>
            </div>
          </div>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm p-6">
          <div class="flex items-center">
            <div class="p-3 bg-yellow-100 dark:bg-yellow-900/20 rounded-lg">
              <AlertCircle class="w-6 h-6 text-yellow-600 dark:text-yellow-400" />
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ filters.view === 'booking' ? 'Bookings' : 'Rooms' }} With Balance
              </p>
              <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                {{ filters.view === 'booking' ? summary.total_bookings : summary.total_rooms }}
              </p>
            </div>
          </div>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm p-6">
          <div class="flex items-center">
            <div class="p-3 bg-blue-100 dark:bg-blue-900/20 rounded-lg">
              <TrendingUp class="w-6 h-6 text-blue-600 dark:text-blue-400" />
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-600 dark:text-gray-400">Average Outstanding</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                ₦{{ formatNumber(summary.average_outstanding) }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Search -->
      <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm p-4">
        <div class="flex items-center space-x-4">
          <div class="flex-1">
            <input
              type="text"
              v-model="filters.search"
              @input="debouncedSearch"
              placeholder="Search by room, guest, booking code..."
              class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            />
          </div>
        </div>
      </div>

      <!-- Results Table -->
      <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-800">
              <tr>
                <th v-if="filters.view === 'room'" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Room
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {{ filters.view === 'booking' ? 'Booking' : 'Guest' }}
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Contact
                </th>
                <th v-if="filters.view === 'room'" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Check-in
                </th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Outstanding
                </th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Status
                </th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="item in balances" :key="filters.view === 'booking' ? item.booking_id : item.room_id" 
                  class="hover:bg-gray-50 dark:hover:bg-gray-800">
                <td v-if="filters.view === 'room'" class="px-6 py-4 whitespace-nowrap">
                  <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                      {{ item.room_number }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                      {{ item.room_name }}
                    </div>
                  </div>
                </td>
                
                <td class="px-6 py-4 whitespace-nowrap">
                  <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                      {{ filters.view === 'booking' ? item.booking_code : item.guest }}
                    </div>
                    <div v-if="filters.view === 'room'" class="text-sm text-gray-500 dark:text-gray-400">
                      Booking: {{ item.booking.code }}
                    </div>
                  </div>
                </td>

                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900 dark:text-gray-100">
                    {{ item.email }}
                  </div>
                </td>

                <td v-if="filters.view === 'room'" class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900 dark:text-gray-100">
                    {{ formatDate(item.booking.check_in) }}
                  </div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ item.days_occupied }} days
                  </div>
                </td>

                <td class="px-6 py-4 whitespace-nowrap text-right">
                  <div class="text-lg font-bold text-red-600 dark:text-red-400">
                    ₦{{ formatNumber(item.outstanding) }}
                  </div>
                </td>

                <td class="px-6 py-4 whitespace-nowrap text-center">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                        :class="getStatusClass(item)">
                    {{ getStatusText(item) }}
                  </span>
                </td>

                <td class="px-6 py-4 whitespace-nowrap text-center">
                  <div class="flex justify-center space-x-2">
                    <button
                      @click="viewDetails(item)"
                      class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                    >
                      <Eye class="w-4 h-4" />
                    </button>
                    <Link
                      v-if="filters.view === 'booking'"
                      :href="`/admin/bookings/${item.booking_id}/edit`"
                      class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-300"
                    >
                      <Edit class="w-4 h-4" />
                    </Link>
                    <Link
                      v-if="filters.view === 'room'"
                      :href="`/frontdesk/billing/${item.booking.id}`"
                      class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
                    >
                      <CreditCard class="w-4 h-4" />
                    </Link>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="balances.length === 0" class="bg-white dark:bg-gray-900 rounded-lg shadow-sm p-12 text-center">
        <CheckCircle2 class="w-12 h-12 text-green-500 mx-auto mb-4" />
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
          No Outstanding Balances
        </h3>
        <p class="text-gray-600 dark:text-gray-400">
          All {{ filters.view === 'booking' ? 'bookings' : 'rooms' }} are settled.
        </p>
      </div>
    </div>
  </ManagerLayout>
</template>

<script setup>
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import { useForm, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { 
  Banknote, 
  AlertCircle, 
  TrendingUp, 
  Eye, 
  Edit, 
  CreditCard,
  CheckCircle2 
} from 'lucide-vue-next'
// import { debounce } from 'lodash'

const props = defineProps({
  view: String,
  search: String,
  summary: Object,
  balances: Array,
})

const filters = useForm({
  view: props.view,
  search: props.search,
})

const debouncedSearch = () => {
  submit()
}

const submit = () => {
  filters.get(route('admin.outstanding-balances.index'), {
    preserveState: true,
    preserveScroll: true,
  })
}

const exportReport = () => {
  window.open(`/admin/outstanding-balances/export?view=${filters.view}&format=xlsx`, '_blank')
}

const viewDetails = (item) => {
  if (filters.view === 'booking') {
    // Show booking details modal or navigate
    router.get(`/admin/bookings/${item.booking_id}/edit`)
  } else {
    // Show room billing details
    router.get(`/frontdesk/billing/${item.booking.id}`)
  }
}

const getStatusClass = (item) => {
  if (filters.view === 'booking') {
    switch (item.status) {
      case 'active': return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
      case 'checked_out': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
      case 'pending': return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
      default: return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
    }
  } else {
    switch (item.room_status) {
      case 'occupied': return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
      case 'dirty': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
      case 'maintenance': return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
      default: return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
    }
  }
}

const getStatusText = (item) => {
  if (filters.view === 'booking') {
    return item.status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())
  } else {
    return item.room_status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())
  }
}

const formatNumber = (num) => {
  return new Intl.NumberFormat('en-NG').format(Math.abs(num))
}

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString('en-GB', {
    day: 'numeric', 
    month: 'short', 
    year: 'numeric'
  })
}
</script>