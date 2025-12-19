<!-- resources/js/Components/KpiCard.vue -->
<template>
  <div class="bg-white dark:bg-gray-900 p-4 rounded-2xl shadow-sm">
    <div class="text-sm text-gray-500 dark:text-gray-400">
      {{ label }}
    </div>

    <div class="flex items-end justify-between mt-2">
      <div class="text-2xl font-bold text-gray-800 dark:text-gray-100">
        {{ value }}
      </div>

      <div
        v-if="change !== null"
        :class="change >= 0 ? 'text-green-600' : 'text-red-600'"
        class="text-sm font-medium"
      >
        {{ change >= 0 ? '+' : '' }}{{ change }}%
      </div>
    </div>

    <div v-if="hint" class="mt-1 text-xs text-gray-400">
      {{ hint }}
    </div>
  </div>
</template>

<script setup>
defineProps({
  label: String,
  value: [String, Number],
  change: { type: Number, default: null },
  hint: { type: String, default: null },
})
</script>
