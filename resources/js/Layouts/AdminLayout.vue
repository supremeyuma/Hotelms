<script setup>
import AdminSidebar from '@/Components/Admin/AdminSidebar.vue'
</script>

<template>
  <div class="flex min-h-screen bg-gray-50">
    <AdminSidebar />

    <main class="flex-1">
      <slot />
    </main>
  </div>
</template>
