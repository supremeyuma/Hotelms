<!-- resources/js/Pages/Admin/WebsiteContent/Index.vue -->
<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Wysiwyg from '@/Components/Admin/Wysiwyg.vue'
import { useForm } from '@inertiajs/vue3'

defineProps({ contents: Object })

const form = useForm({
  key: '',
  value: '',
  type: 'html'
})

const submit = () => form.post('/admin/website/content')
</script>

<template>
  <AdminLayout>
    <div class="max-w-5xl mx-auto p-6 space-y-6">
      <h1 class="text-2xl font-black">Website Content</h1>

      <input v-model="form.key" placeholder="content.key" class="input" />

      <Wysiwyg v-if="form.type === 'html'" v-model="form.value" />
      <textarea v-else v-model="form.value" class="input" rows="6" />

      <select v-model="form.type" class="input">
        <option value="text">Text</option>
        <option value="html">HTML</option>
        <option value="image">Image</option>
      </select>

      <button class="btn-primary">Save</button>
    </div>
  </AdminLayout>
</template>
