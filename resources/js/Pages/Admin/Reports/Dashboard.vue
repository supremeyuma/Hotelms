<!-- File: resources/js/Pages/Admin/Reports/Dashboard.vue -->
<script setup>
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
    defineProps({ kpis: Object })
</script>

<template>
    <AuthenticatedLayout>
  <div class="grid grid-cols-4 gap-4">
    <div v-for="(k,v) in kpis" :key="v" class="bg-white p-4 rounded shadow">
      <h3 class="text-sm text-gray-500">{{ v }}</h3>
      <div class="text-2xl font-bold">{{ k.total ?? k }}</div>
    </div>
  </div>
    </AuthenticatedLayout>
</template>
