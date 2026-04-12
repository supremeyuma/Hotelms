<template>
  <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg shadow p-4 border-l-4 border-blue-600">
    <h3 class="font-bold text-blue-900 mb-3 capitalize">{{ department.department }}</h3>
    
    <div class="space-y-2 text-sm">
      <div class="flex justify-between">
        <span class="text-blue-700">Received:</span>
        <span class="font-bold text-blue-900">{{ department.requests_received }}</span>
      </div>
      
      <div class="flex justify-between">
        <span class="text-blue-700">Backlog:</span>
        <span
          :class="[
            'font-bold',
            department.backlog_open > 5 ? 'text-red-600' : 'text-green-600'
          ]"
        >
          {{ department.backlog_open }}
        </span>
      </div>
      
      <div class="flex justify-between">
        <span class="text-blue-700">Avg Response:</span>
        <span class="font-mono text-blue-900">
          {{ department.avg_response_minutes || '-' }} min
        </span>
      </div>
      
      <div class="pt-2 mt-2 border-t border-blue-200">
        <div class="flex justify-between">
          <span :class="['text-xs font-bold', department.sla_breaches > 0 ? 'text-red-600' : 'text-green-600']">
            SLA Breaches: {{ department.sla_breaches }}
          </span>
        </div>
      </div>
    </div>

    <button
      @click="navigateToDepartment"
      class="mt-4 w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700 transition text-sm font-medium"
    >
      View Details
    </button>
  </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3'

defineProps({
  department: Object
})

const navigateToDepartment = () => {
  router.get(route('admin.reports.department-command', { department: department.department }))
}
</script>
