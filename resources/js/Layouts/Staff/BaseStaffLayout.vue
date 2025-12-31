<script setup>
import StaffSidebar from '@/Components/Staff/StaffSidebar.vue'
</script>

<template>
  <div class="flex h-screen bg-gray-100">
    <StaffSidebar />

    <main class="flex-1 overflow-y-auto">
      <slot />
    </main>
  </div>
</template>
