<template>
  <div class="bg-white shadow rounded p-4 flex flex-col items-center">
    <p class="text-gray-500 text-sm">{{ title }}</p>
    <p class="text-2xl font-bold">{{ value }}</p>
  </div>
</template>

<script setup>
const props = defineProps({
  title: { type: String, required: true },
  value: { type: [String, Number], required: true }
});
</script>

<style scoped>
/* Optional additional styling */
</style>
