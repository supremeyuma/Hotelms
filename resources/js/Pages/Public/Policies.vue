<!-- resources/js/Pages/Public/Policies.vue -->
<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
</script>

<template>
  <PublicLayout>
    <div class="max-w-5xl mx-auto px-4 py-16">
      <h1 class="text-4xl font-black mb-10">Hotel Policies</h1>
      <div class="prose max-w-none" v-html="content('policies.content')"></div>
    </div>
  </PublicLayout>
</template>
