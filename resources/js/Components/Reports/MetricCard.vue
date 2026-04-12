<template>
  <div class="bg-white rounded-lg shadow p-4 border-l-4" :class="borderColorClass">
    <div class="flex items-start justify-between">
      <div>
        <p class="text-gray-500 text-sm font-medium">{{ title }}</p>
        <p class="text-2xl font-bold mt-1">{{ value }}</p>
      </div>
      <div :class="['px-2 py-1 rounded text-xs font-bold', trendClass]">
        {{ trend ? '↓' : '↑' }} {{ trend ? 'Improving' : 'Increasing' }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

defineProps({
  title: String,
  value: [String, Number],
  trend: Boolean, // true for good, false for bad
  color: {
    type: String,
    default: 'blue'
  }
})

const borderColorClass = computed(() => {
  const colors = {
    blue: 'border-blue-600',
    green: 'border-green-600',
    orange: 'border-orange-600',
    red: 'border-red-600'
  }
  return colors[color] || colors.blue
})

const trendClass = computed(() => {
  if (trend) return 'bg-green-100 text-green-800'
  return 'bg-red-100 text-red-800'
})
</script>
