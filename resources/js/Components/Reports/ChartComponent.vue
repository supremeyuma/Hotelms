<template>
  <div class="w-full h-full flex items-center justify-center text-gray-400">
    <!-- Placeholder for chart library integration (e.g., Chart.js, Recharts) -->
    <div class="text-center">
      <p class="text-sm">Chart visualization</p>
      <p class="text-xs text-gray-400">{{ data ? data.length + ' data points' : 'No data' }}</p>
    </div>
  </div>
</template>

<script setup>
defineProps({
  data: Array,
  title: String,
  type: {
    type: String,
    default: 'line'
  }
})
</script>
