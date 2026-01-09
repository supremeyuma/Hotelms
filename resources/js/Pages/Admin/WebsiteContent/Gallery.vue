<!-- resources/js/Pages/Admin/WebsiteContent/Gallery.vue -->
<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useForm } from '@inertiajs/vue3'

defineProps({ items: Array })

const form = useForm({
  category: '',
  image: null,
  caption: ''
})

const submit = () => form.post('/admin/website/gallery')
</script>

<template>
  <AdminLayout>
    <div class="max-w-6xl mx-auto p-6">
      <h1 class="text-2xl font-bold mb-6">Gallery</h1>

      <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <input v-model="form.category" placeholder="Category" class="input" />
        <input type="file" @change="e => form.image = e.target.files[0]" />
        <input v-model="form.caption" placeholder="Caption" class="input" />
        <button class="btn-primary col-span-full">Upload</button>
      </form>

      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div v-for="item in items" :key="item.id" class="relative">
          <img :src="`/storage/${item.image_path.replace('public/','')}`" class="rounded-xl" />
          <p class="text-sm mt-1">{{ item.caption }}</p>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
