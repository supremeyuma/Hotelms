<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold">Room {{ room.room_number }} Intelligence</h1>
        <Link :href="route('admin.rooms.index')" class="text-blue-600 hover:text-blue-800">
          Back to Rooms
        </Link>
      </div>

      <!-- Room Status Overview -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-gray-500 text-sm font-medium mb-2">Status</h3>
          <p class="text-2xl font-bold capitalize">{{ room.status }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-gray-500 text-sm font-medium mb-2">Room Type</h3>
          <p class="text-2xl font-bold">{{ room.room_type?.name }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-gray-500 text-sm font-medium mb-2">Current Guest</h3>
          <p class="text-2xl font-bold">{{ room.currentGuest?.name || 'Vacant' }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-gray-500 text-sm font-medium mb-2">Maintenance Issues</h3>
          <p class="text-2xl font-bold text-orange-600">{{ maintenanceCount }}</p>
        </div>
      </div>

      <!-- Room Timeline -->
      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4">Timeline (Last 30 Days)</h2>
        <div class="space-y-3 max-h-96 overflow-y-auto">
          <div
            v-if="!facts.length"
            class="text-center text-gray-500 py-8"
          >
            No events recorded
          </div>
          <div
            v-for="fact in facts"
            :key="fact.id"
            class="flex items-start space-x-4 pb-4 border-b"
          >
            <div class="text-sm text-gray-500 min-w-24">
              {{ formatDate(fact.date) }}
            </div>
            <div class="flex-1">
              <div class="font-medium">
                {{ formatFactType(fact) }}
              </div>
              <div class="text-sm text-gray-600 mt-1">
                <span v-if="fact.occupied" class="mr-4">
                  👥 {{ fact.guest_count }} guests
                </span>
                <span v-if="fact.housekeeping_completed" class="mr-4">
                  🧹 Cleaned
                </span>
                <span v-if="fact.maintenance_issue_count > 0" class="mr-4 text-orange-600">
                  ⚠️ {{ fact.maintenance_issue_count }} issues
                </span>
                <span v-if="fact.charges_posted > 0" class="mr-4 text-green-600">
                  💰 ${{ fact.charges_posted }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Room Statistics -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-gray-500 text-sm font-medium mb-4">Financial Summary</h3>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-600">Total Revenue:</span>
              <span class="font-bold">${{ totalRevenue }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Charges Posted:</span>
              <span class="font-bold">${{ totalCharges }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Payments Received:</span>
              <span class="font-bold">${{ totalPayments }}</span>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-gray-500 text-sm font-medium mb-4">Service Activity</h3>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-600">Kitchen Orders:</span>
              <span class="font-bold">{{ totalKitchenOrders }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Laundry Requests:</span>
              <span class="font-bold">{{ totalLaundryRequests }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Complaints:</span>
              <span class="font-bold text-red-600">{{ totalComplaints }}</span>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-gray-500 text-sm font-medium mb-4">Availability</h3>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-600">Occupancy Rate:</span>
              <span class="font-bold">{{ occupancyRate }}%</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Days Occupied:</span>
              <span class="font-bold">{{ daysOccupied }}/30</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Out of Service:</span>
              <span class="font-bold">{{ daysOutOfService }}/30</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const page = usePage()
const room = page.props.room
const facts = page.props.facts || []

const maintenanceCount = computed(() => {
  return facts.reduce((sum, f) => sum + f.maintenance_open_count, 0)
})

const totalRevenue = computed(() => {
  return facts.reduce((sum, f) => sum + f.room_revenue, 0).toFixed(2)
})

const totalCharges = computed(() => {
  return facts.reduce((sum, f) => sum + f.charges_posted, 0).toFixed(2)
})

const totalPayments = computed(() => {
  return facts.reduce((sum, f) => sum + f.payments_received, 0).toFixed(2)
})

const totalKitchenOrders = computed(() => {
  return facts.reduce((sum, f) => sum + f.kitchen_order_count, 0)
})

const totalLaundryRequests = computed(() => {
  return facts.reduce((sum, f) => sum + f.laundry_request_count, 0)
})

const totalComplaints = computed(() => {
  return facts.reduce((sum, f) => sum + f.complaints_count, 0)
})

const daysOccupied = computed(() => {
  return facts.filter(f => f.occupied).length
})

const daysOutOfService = computed(() => {
  return facts.filter(f => f.out_of_service).length
})

const occupancyRate = computed(() => {
  const available = facts.length - daysOutOfService.value
  return available > 0 ? ((daysOccupied.value / available) * 100).toFixed(1) : 0
})

const formatDate = (date) => {
  return new Date(date).toLocaleDateString()
}

const formatFactType = (fact) => {
  if (fact.occupied) return 'Room Occupied'
  if (fact.out_of_service) return 'Out of Service'
  if (fact.housekeeping_completed) return 'Housekeeping Completed'
  return 'Day Record'
}
</script>
