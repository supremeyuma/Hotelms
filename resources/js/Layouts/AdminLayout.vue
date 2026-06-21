<script setup>
import AdminSidebar from '@/Components/Admin/AdminSidebar.vue'
</script>

<template>
  <div class="flex min-h-screen bg-slate-50 dark:bg-slate-950">
    <AdminSidebar />

    <main class="flex-1">
      <slot />
    </main>
  </div>
</template>
