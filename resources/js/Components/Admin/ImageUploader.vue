<!-- resources/js/Components/Admin/ImageUploader.vue -->
<script setup>
import { useForm } from '@inertiajs/vue3'

const props = defineProps({ contentKey: String })

const form = useForm({
  key: props.contentKey,
  image: null
})

const submit = () => form.post('/admin/website/content/image')
</script>

<template>
  <div class="space-y-2">
    <input type="file" @change="e => form.image = e.target.files[0]" />
    <button class="btn-primary" @click="submit">Upload</button>
  </div>
</template>
