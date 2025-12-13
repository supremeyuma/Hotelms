<template>
  <label :for="for" class="block text-sm font-medium text-gray-700 mb-1">
    <slot></slot>
  </label>
</template>

<script setup>
defineProps({
  for: String
});
</script>

<style scoped>
</style>
