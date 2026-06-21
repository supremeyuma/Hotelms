<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold capitalize">{{ department }} Command Center</h1>
        <Link href="/admin/reports/executive-overview" class="text-blue-600 hover:text-blue-800">
          Back to Dashboard
        </Link>
      </div>

      <!-- Key Metrics -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <MetricCard
          title="SLA Compliance"
          :value="`${data.sla.sla_compliance_rate.toFixed(1)}%`"
          :trend="data.sla.sla_compliance_rate > 95 ? 'up' : 'down'"
          color="green"
        />
        <MetricCard
          title="Avg Response Time"
          :value="`${Math.round(data.sla.average_response_minutes)}min`"
          :trend="data.sla.average_response_minutes < 30 ? 'down' : 'up'"
          color="blue"
        />
        <MetricCard
          title="Current Backlog"
          :value="currentBacklog"
          :trend="currentBacklog < 5 ? 'down' : 'up'"
          color="orange"
        />
        <MetricCard
          title="Daily Revenue"
          :value="`$${(data.revenue.average_daily_revenue).toFixed(0)}`"
          :trend="true"
          color="green"
        />
      </div>

      <!-- SLA Performance Chart -->
      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4">SLA Compliance Trend</h2>
        <div class="h-64 bg-gray-50 rounded flex items-center justify-center text-gray-400">
          <ChartComponent :data="data.backlog" />
        </div>
      </div>

      <!-- Performance Details -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Backlog Trend -->
        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-lg font-bold mb-4">Backlog Trend</h3>
          <table class="w-full text-sm">
            <thead class="border-b">
              <tr>
                <th class="text-left py-2">Date</th>
                <th class="text-right py-2">Open</th>
                <th class="text-right py-2">Received</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="item in data.backlog.slice(0, 7)"
                :key="item.date"
                class="border-b hover:bg-gray-50"
              >
                <td class="py-2">{{ formatDate(item.date) }}</td>
                <td class="text-right">{{ item.backlog }}</td>
                <td class="text-right">{{ item.requests_received }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Revenue Impact -->
        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-lg font-bold mb-4">Revenue Impact</h3>
          <div class="space-y-4">
            <div class="flex justify-between items-center pb-4 border-b">
              <span class="text-gray-600">Total Revenue</span>
              <span class="text-2xl font-bold text-green-600">
                ${{ data.revenue.total_revenue.toFixed(2) }}
              </span>
            </div>
            <div class="flex justify-between items-center pb-4 border-b">
              <span class="text-gray-600">Total Refunds</span>
              <span class="text-lg font-bold text-red-600">
                -${{ data.revenue.total_refunds.toFixed(2) }}
              </span>
            </div>
            <div class="flex justify-between items-center pb-4 border-b">
              <span class="text-gray-600">Net Revenue</span>
              <span class="text-2xl font-bold text-blue-600">
                ${{ data.revenue.net_revenue.toFixed(2) }}
              </span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-gray-600">Avg Daily</span>
              <span class="text-lg font-bold">
                ${{ data.revenue.average_daily_revenue.toFixed(2) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Staffing -->
        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-lg font-bold mb-4">Staffing Metrics</h3>
          <div class="space-y-4">
            <div class="flex justify-between items-center pb-4 border-b">
              <span class="text-gray-600">Avg Staff on Duty</span>
              <span class="text-2xl font-bold">
                {{ Math.round(data.staffing.average_staff_on_duty) }}
              </span>
            </div>
            <div class="flex justify-between items-center pb-4 border-b">
              <span class="text-gray-600">Assignments/Staff</span>
              <span class="text-lg font-bold">
                {{ (data.staffing.average_assignments_per_staff || 0).toFixed(1) }}
              </span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-gray-600">Total Assignments</span>
              <span class="text-lg font-bold">
                {{ data.staffing.total_assignments }}
              </span>
            </div>
          </div>
        </div>

        <!-- SLA Details -->
        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-lg font-bold mb-4">SLA Details</h3>
          <div class="space-y-4">
            <div class="flex justify-between items-center pb-4 border-b">
              <span class="text-gray-600">Days Tracked</span>
              <span class="text-2xl font-bold">
                {{ data.sla.total_days_tracked }}
              </span>
            </div>
            <div class="flex justify-between items-center pb-4 border-b">
              <span class="text-gray-600">SLA Breach Days</span>
              <span class="text-lg font-bold text-orange-600">
                {{ data.sla.sla_breach_days }}
              </span>
            </div>
            <div class="flex justify-between items-center pb-4 border-b">
              <span class="text-gray-600">Avg Completion</span>
              <span class="text-lg font-mono">
                {{ Math.round(data.sla.average_completion_minutes) }}min
              </span>
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
import MetricCard from '@/Components/Reports/MetricCard.vue'
import ChartComponent from '@/Components/Reports/ChartComponent.vue'

const page = usePage()
const department = page.props.department
const data = page.props.data || {}

const currentBacklog = computed(() => {
  return data.backlog && data.backlog.length > 0
    ? data.backlog[0].backlog
    : 0
})

const formatDate = (date) => {
  return new Date(date).toLocaleDateString()
}
</script>
