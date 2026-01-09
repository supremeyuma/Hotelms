<!-- resources/js/Pages/Public/Amenities.vue -->
<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
</script>

<template>
  <PublicLayout>
    <div class="max-w-6xl mx-auto px-4 py-16">
      <h1 class="text-4xl font-black mb-10">Amenities & Facilities</h1>
      <div class="space-y-6 text-slate-700">
        <div v-html="content('amenities.content')"></div>
      </div>
    </div>
  </PublicLayout>
</template>
