<template>
  <AdminLayout title="Exceptions Dashboard">
    <div class="space-y-6">
      <h1 class="text-3xl font-bold">Exceptions & Issues</h1>

      <!-- Filter Bar -->
      <div class="bg-white rounded-lg shadow p-4 flex space-x-4 items-center">
        <select
          v-model="filters.severity"
          class="px-3 py-2 border rounded-lg text-sm"
          @change="applyFilters"
        >
          <option value="">All Severities</option>
          <option value="critical">Critical</option>
          <option value="high">High</option>
          <option value="normal">Normal</option>
          <option value="low">Low</option>
        </select>

        <select
          v-model="filters.status"
          class="px-3 py-2 border rounded-lg text-sm"
          @change="applyFilters"
        >
          <option value="">All Statuses</option>
          <option value="open">Open</option>
          <option value="acknowledged">Acknowledged</option>
          <option value="resolved">Resolved</option>
        </select>

        <select
          v-model="filters.department"
          class="px-3 py-2 border rounded-lg text-sm"
          @change="applyFilters"
        >
          <option value="">All Departments</option>
          <option value="maintenance">Maintenance</option>
          <option value="laundry">Laundry</option>
          <option value="kitchen">Kitchen</option>
          <option value="bar">Bar</option>
        </select>
      </div>

      <!-- Summary Statistics -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div v-for="stat in summary" :key="stat.severity" class="bg-white rounded-lg shadow p-4">
          <div class="text-gray-500 text-sm">{{ stat.severity }}</div>
          <div class="text-3xl font-bold">{{ stat.count }}</div>
        </div>
      </div>

      <!-- Exception List -->
      <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-left px-6 py-3 text-sm font-bold">Type</th>
              <th class="text-left px-6 py-3 text-sm font-bold">Severity</th>
              <th class="text-left px-6 py-3 text-sm font-bold">Title</th>
              <th class="text-left px-6 py-3 text-sm font-bold">Detected</th>
              <th class="text-left px-6 py-3 text-sm font-bold">Status</th>
              <th class="text-left px-6 py-3 text-sm font-bold">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="exc in exceptions.data"
              :key="exc.id"
              class="border-b hover:bg-gray-50 transition"
            >
              <td class="px-6 py-4 text-sm">
                <span class="font-medium">{{ formatExceptionType(exc.exception_type) }}</span>
              </td>
              <td class="px-6 py-4 text-sm">
                <span :class="getSeverityBadge(exc.severity)">
                  {{ exc.severity }}
                </span>
              </td>
              <td class="px-6 py-4 text-sm truncate">{{ exc.title }}</td>
              <td class="px-6 py-4 text-sm text-gray-600">
                {{ formatDate(exc.detected_at) }}
              </td>
              <td class="px-6 py-4 text-sm">
                <span :class="getStatusBadge(exc.status)">
                  {{ exc.status }}
                </span>
              </td>
              <td class="px-6 py-4 text-sm space-x-2">
                <button
                  v-if="exc.status === 'open'"
                  @click="acknowledgeException(exc.id)"
                  class="text-blue-600 hover:text-blue-800 font-medium"
                >
                  Acknowledge
                </button>
                <button
                  @click="viewDetails(exc.id)"
                  class="text-gray-600 hover:text-gray-800 font-medium"
                >
                  Details
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="flex justify-between items-center">
        <div class="text-sm text-gray-600">
          Showing {{ exceptions.from }} to {{ exceptions.to }} of {{ exceptions.total }}
        </div>
        <div class="space-x-2">
          <Link
            v-if="exceptions.prev_page_url"
            :href="exceptions.prev_page_url"
            class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300"
          >
            Previous
          </Link>
          <Link
            v-if="exceptions.next_page_url"
            :href="exceptions.next_page_url"
            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
          >
            Next
          </Link>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { usePage, router, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const page = usePage()
const exceptions = page.props.exceptions
const summary = page.props.summary || []

const filters = ref({
  severity: '',
  status: '',
  department: '',
})

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

const formatExceptionType = (type) => {
  return type
    .split('_')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}

const formatDate = (date) => {
  const d = new Date(date)
  return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

const applyFilters = () => {
  const params = new URLSearchParams()
  if (filters.value.severity) params.append('severity', filters.value.severity)
  if (filters.value.status) params.append('status', filters.value.status)
  if (filters.value.department) params.append('department', filters.value.department)
  router.get(route('admin.reports.exceptions') + (params.toString() ? '?' + params.toString() : ''))
}

const acknowledgeException = (id) => {
  // Would implement PATCH request to acknowledge exception
  console.log('Acknowledge exception:', id)
}

const viewDetails = (id) => {
  // Would navigate to exception detail page
  console.log('View exception details:', id)
}
</script>
