<!-- resources/js/Pages/Public/Gallery.vue -->
<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
defineProps({ items: Object })
</script>

<template>
  <PublicLayout>
    <div class="max-w-7xl mx-auto px-4 py-16 space-y-16">
      <div v-for="(images, category) in items" :key="category">
        <h2 class="text-2xl font-black mb-6 capitalize">{{ category }}</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <img
            v-for="img in images"
            :key="img.id"
            :src="`/storage/${img.image_path.replace('public/','')}`"
            class="rounded-2xl object-cover h-48 w-full"
          />
        </div>
      </div>
    </div>
  </PublicLayout>
</template>
