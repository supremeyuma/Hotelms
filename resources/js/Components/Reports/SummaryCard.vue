<template>
  <div
    @click="$emit('click')"
    :class="[
      'bg-white rounded-lg shadow p-6 transition hover:shadow-lg',
      { 'cursor-pointer hover:bg-gray-50': clickable }
    ]"
  >
    <div class="flex items-center justify-between">
      <div>
        <p class="text-gray-500 text-sm font-medium">{{ title }}</p>
        <p class="text-3xl font-bold mt-2">{{ value }}</p>
        <p class="text-gray-500 text-sm mt-2">{{ subtext }}</p>
      </div>
      <div :class="['p-3 rounded-lg', colorClasses]">
        <component :is="icon" :size="32" :stroke-width="1.5" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { AlertTriangle, Bell, Hotel, Users } from 'lucide-vue-next'

defineProps({
  title: String,
  value: [String, Number],
  subtext: String,
  icon: String,
  color: {
    type: String,
    default: 'blue'
  }
})

defineEmits(['click'])

const colorClasses = computed(() => {
  const colors = {
    blue: 'bg-blue-100 text-blue-600',
    green: 'bg-green-100 text-green-600',
    orange: 'bg-orange-100 text-orange-600',
    red: 'bg-red-100 text-red-600'
  }
  return colors[color] || colors.blue
})

const clickable = computed(() => {
  // Can be extended to check if onClick is provided
  return true
})
</script>
