<template>
  <ManagerLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">Outstanding Balances</h1>
        <div class="flex items-center space-x-4">
          <select v-model="filters.view" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" @change="submit">
            <option value="room">Room View</option>
            <option value="booking">Booking View</option>
          </select>
          <button class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700" @click="exportReport">
            Export CSV
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        <div class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-900">
          <div class="flex items-center">
            <div class="rounded-lg bg-red-100 p-3 dark:bg-red-900/20">
              <Banknote class="h-6 w-6 text-red-600 dark:text-red-400" />
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-600 dark:text-gray-400">Total Outstanding</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">₦{{ formatNumber(summary.total_outstanding) }}</p>
            </div>
          </div>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-900">
          <div class="flex items-center">
            <div class="rounded-lg bg-yellow-100 p-3 dark:bg-yellow-900/20">
              <AlertCircle class="h-6 w-6 text-yellow-600 dark:text-yellow-400" />
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-600 dark:text-gray-400">{{ filters.view === 'booking' ? 'Bookings' : 'Rooms' }} With Balance</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ filters.view === 'booking' ? summary.total_bookings : summary.total_rooms }}</p>
            </div>
          </div>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-900">
          <div class="flex items-center">
            <div class="rounded-lg bg-blue-100 p-3 dark:bg-blue-900/20">
              <TrendingUp class="h-6 w-6 text-blue-600 dark:text-blue-400" />
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-600 dark:text-gray-400">Average Outstanding</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">₦{{ formatNumber(summary.average_outstanding) }}</p>
            </div>
          </div>
        </div>
      </div>

      <div class="rounded-lg bg-white p-4 shadow-sm dark:bg-gray-900">
        <input v-model="filters.search" type="text" placeholder="Search by room, guest, booking code..." class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" @input="submit" />
      </div>

      <div class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-900">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-800">
              <tr>
                <th v-if="filters.view === 'room'" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Room</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ filters.view === 'booking' ? 'Booking' : 'Guest' }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Contact</th>
                <th v-if="filters.view === 'room'" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Check-in</th>
                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Outstanding</th>
                <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
              <tr v-for="item in balances" :key="filters.view === 'booking' ? item.booking_id : item.room_id" class="hover:bg-gray-50 dark:hover:bg-gray-800">
                <td v-if="filters.view === 'room'" class="px-6 py-4 whitespace-nowrap">
                  <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ item.room_number }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ item.room_name }}</div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ filters.view === 'booking' ? item.booking_code : item.booking.guest }}</div>
                    <div v-if="filters.view === 'room'" class="text-sm text-gray-500 dark:text-gray-400">Booking: {{ item.booking.code }}</div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ filters.view === 'booking' ? item.email : item.booking.email }}</td>
                <td v-if="filters.view === 'room'" class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900 dark:text-gray-100">{{ formatDate(item.booking.check_in) }}</div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">{{ item.days_occupied }} days</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right">
                  <div class="text-lg font-bold text-red-600 dark:text-red-400">₦{{ formatNumber(item.outstanding) }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center">
                  <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="getStatusClass(item)">{{ getStatusText(item) }}</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center">
                  <div class="flex justify-center space-x-2">
                    <button class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300" @click="viewDetails(item)">
                      <Eye class="h-4 w-4" />
                    </button>
                    <Link v-if="filters.view === 'booking'" :href="route('admin.bookings.edit', item.booking_id)" class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-300">
                      <Edit class="h-4 w-4" />
                    </Link>
                    <Link v-if="filters.view === 'room'" :href="`/frontdesk/billing/${item.booking.id}`" class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300">
                      <CreditCard class="h-4 w-4" />
                    </Link>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="balances.length === 0" class="rounded-lg bg-white p-12 text-center shadow-sm dark:bg-gray-900">
        <CheckCircle2 class="mx-auto mb-4 h-12 w-12 text-green-500" />
        <h3 class="mb-2 text-lg font-medium text-gray-900 dark:text-gray-100">No Outstanding Balances</h3>
        <p class="text-gray-600 dark:text-gray-400">All {{ filters.view === 'booking' ? 'bookings' : 'rooms' }} are settled.</p>
      </div>
    </div>
  </ManagerLayout>
</template>

<script setup>
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import { useForm, router, Link } from '@inertiajs/vue3'
import { Banknote, AlertCircle, TrendingUp, Eye, Edit, CreditCard, CheckCircle2 } from 'lucide-vue-next'

const props = defineProps({
  view: String,
  search: String,
  summary: Object,
  balances: Array,
  routePrefix: {
    type: String,
    default: 'finance',
  },
})

const filters = useForm({
  view: props.view,
  search: props.search,
})

const submit = () => {
  filters.get(route(`${props.routePrefix}.outstanding-balances.index`), {
    preserveState: true,
    preserveScroll: true,
  })
}

const exportReport = () => {
  window.open(route(`${props.routePrefix}.outstanding-balances.export`, { view: filters.view, format: 'csv' }), '_blank')
}

const viewDetails = (item) => {
  if (filters.view === 'booking') {
    router.get(route('admin.bookings.edit', item.booking_id))
    return
  }

  router.get(`/frontdesk/billing/${item.booking.id}`)
}

const getStatusClass = (item) => {
  if (filters.view === 'booking') {
    switch (item.status) {
      case 'active':
      case 'checked_in':
        return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
      case 'checked_out':
        return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
      case 'pending':
        return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
      default:
        return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
    }
  }

  switch (item.room_status) {
    case 'occupied':
      return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
    case 'dirty':
      return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
    case 'maintenance':
      return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
    default:
      return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
  }
}

const getStatusText = (item) => {
  const status = filters.view === 'booking' ? item.status : item.room_status
  return status.replace('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase())
}

const formatNumber = (num) => new Intl.NumberFormat('en-NG').format(Math.abs(num))
const formatDate = (dateString) => new Date(dateString).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
</script>
