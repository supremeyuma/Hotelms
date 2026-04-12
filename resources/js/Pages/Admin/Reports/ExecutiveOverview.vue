<template>
  <AdminLayout title="Executive Overview Dashboard">
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold">Executive Dashboard</h1>
        <div class="text-sm text-gray-500">
          Last updated: {{ now.toLocaleString() }}
        </div>
      </div>

      <!-- Occupancy Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <SummaryCard
          title="Occupancy Rate"
          :value="`${overview.occupancy.occupancy_rate}%`"
          :subtext="`${overview.occupancy.occupied_rooms}/${overview.occupancy.total_rooms} rooms`"
          icon="Hotel"
          color="blue"
          @click="navigateToRoomList"
        />
        <SummaryCard
          title="Active Guests"
          :value="overview.occupancy.guest_count"
          subtext="In-house"
          icon="Users"
          color="green"
        />
        <SummaryCard
          title="Out of Service"
          :value="overview.occupancy.out_of_service"
          subtext="Rooms"
          icon="AlertTriangle"
          color="orange"
        />
        <SummaryCard
          title="Open Issues"
          :value="recentExceptions.length"
          subtext="Requiring attention"
          icon="Bell"
          color="red"
          @click="navigateToExceptions"
        />
      </div>

      <!-- Department Status -->
      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4">Department Status</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
          <DepartmentStatusCard
            v-for="dept in overview.department_status"
            :key="dept.department"
            :department="dept"
          />
        </div>
      </div>

      <!-- Backlog Alerts -->
      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4">Backlog Alerts</h2>
        <div v-if="overview.backlog_alerts.length" class="space-y-2">
          <div
            v-for="alert in overview.backlog_alerts"
            :key="alert.department"
            class="flex items-center justify-between p-3 bg-yellow-50 border border-yellow-200 rounded"
          >
            <span class="font-medium text-yellow-900">{{ alert.department }}</span>
            <span class="text-yellow-700 font-bold">{{ alert.backlog }} open items</span>
          </div>
        </div>
        <div v-else class="text-gray-500 text-center py-8">
          All departments have manageable backlogs
        </div>
      </div>

      <!-- Recent Exceptions -->
      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4">Recent Exceptions</h2>
        <table class="w-full text-sm">
          <thead class="border-b">
            <tr>
              <th class="text-left py-2">Type</th>
              <th class="text-left py-2">Severity</th>
              <th class="text-left py-2">Title</th>
              <th class="text-left py-2">Detected</th>
              <th class="text-left py-2">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="exc in recentExceptions"
              :key="exc.id"
              class="border-b hover:bg-gray-50 cursor-pointer"
              @click="navigateToException(exc.id)"
            >
              <td class="py-3">{{ exc.exception_type }}</td>
              <td class="py-3">
                <span :class="getSeverityBadge(exc.severity)">
                  {{ exc.severity }}
                </span>
              </td>
              <td class="py-3 truncate">{{ exc.title }}</td>
              <td class="py-3 text-gray-500">{{ formatDate(exc.detected_at) }}</td>
              <td class="py-3">
                <span :class="getStatusBadge(exc.status)">
                  {{ exc.status }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import SummaryCard from '@/Components/Reports/SummaryCard.vue'
import DepartmentStatusCard from '@/Components/Reports/DepartmentStatusCard.vue'

const page = usePage()
const overview = page.props.overview
const recentExceptions = page.props.recentExceptions
const now = new Date()

const getSeverityBadge = (severity) => {
  const badges = {
    critical: 'px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-bold',
    high: 'px-2 py-1 bg-orange-100 text-orange-800 rounded text-xs font-bold',
    normal: 'px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs font-bold',
    low: 'px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-bold',
  }
  return badges[severity] || badges.normal
}

const getStatusBadge = (status) => {
  const badges = {
    open: 'px-2 py-1 bg-red-100 text-red-800 rounded text-xs',
    acknowledged: 'px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs',
    resolved: 'px-2 py-1 bg-green-100 text-green-800 rounded text-xs',
  }
  return badges[status] || badges.open
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString() + ' ' + new Date(date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

const navigateToRoomList = () => {
  router.get(route('admin.rooms.index'))
}

const navigateToExceptions = () => {
  router.get(route('admin.reports.exceptions'))
}

const navigateToException = (id) => {
  // Would navigate to exception detail page
  console.log('Navigate to exception:', id)
}
</script>
