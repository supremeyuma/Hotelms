<template>
  <div class="overflow-x-auto">
    <table class="min-w-full bg-white shadow rounded">
      <thead class="bg-gray-100 text-left">
        <tr>
          <th v-for="h in headers" :key="h" class="px-4 py-2 text-sm">{{ h }}</th>
        </tr>
      </thead>
      <tbody>
        <slot />
      </tbody>
    </table>
  </div>
</template>

<script setup>
defineProps({ headers: { type: Array, default: () => [] }});
</script>
