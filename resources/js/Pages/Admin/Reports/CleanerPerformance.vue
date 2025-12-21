<!-- resources/js/Pages/Admin/Reports/CleanerPerformance.vue -->
<script setup>
defineProps({ rows: Array })
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
</script>

<template>
    <AuthenticatedLayout>
      <h1 class="text-2xl font-bold mb-6">Cleaner Performance Report</h1>
<table class="w-full bg-white rounded shadow">
  <thead>
    <tr class="text-left border-b">
      <th class="p-2">Staff</th>
      <th class="p-2">Rooms Cleaned</th>
      <th class="p-2">Total Actions</th>
    </tr>
  </thead>
  <tbody>
    <tr v-for="r in rows" :key="r.user_id" class="border-b">
      <td class="p-2">{{ r.user.name }}</td>
      <td class="p-2">{{ r.rooms_cleaned }}</td>
      <td class="p-2">{{ r.total_actions }}</td>
    </tr>
  </tbody>
</table>
    </AuthenticatedLayout>
</template>
