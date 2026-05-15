<script setup>
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  item: Object,
  categories: Array
})

const form = reactive({
  name: props.item.name,
  description: props.item.description,
  price: props.item.price,
  prep_time_adjustment: '',
  menu_category_id: props.item.menu_category_id,
  menu_subcategory_id: props.item.menu_subcategory_id,
  is_available: props.item.is_available,
  images: [],
  image_urls: [''],
})

function handleImages(event) {
  form.images = Array.from(event.target.files)
}

function addImageUrlField() {
  form.image_urls.push('')
}

function removeImageUrlField(index) {
  if (form.image_urls.length === 1) {
    form.image_urls[0] = ''
    return
  }

  form.image_urls.splice(index, 1)
}

function update() {
  const data = new FormData()

  Object.entries(form).forEach(([key, value]) => {
    if (key === 'images') {
      value.forEach((file, index) => data.append(`images[${index}]`, file))
      return
    }

    if (key === 'image_urls') {
      value
        .map(url => url.trim())
        .filter(Boolean)
        .forEach((url, index) => data.append(`image_urls[${index}]`, url))
      return
    }

    if (value !== null && value !== '') {
      data.append(key, typeof value === 'boolean' ? (value ? 1 : 0) : value)
    }
  })

  router.post(`/staff/menu/items/${props.item.id}?_method=PATCH`, data, {
    forceFormData: true,
  })
}
</script>

<template>
  <div class="p-6 max-w-xl">
    <h1 class="text-xl font-bold mb-4">Edit Menu Item</h1>

    <form @submit.prevent="update" class="space-y-4">
      <input v-model="form.name" required />
      <input v-model="form.price" type="number" required />
      <textarea v-model="form.description" />
      <input v-model="form.prep_time_adjustment" type="number" placeholder="Prep time adjustment" />

      <label class="flex items-center gap-2 text-sm">
        <input v-model="form.is_available" type="checkbox" />
        Available
      </label>

      <input type="file" multiple accept="image/*" @change="handleImages" />

      <div class="space-y-2">
        <div v-for="(imageUrl, index) in form.image_urls" :key="`edit-image-url-${index}`" class="flex gap-2">
          <input
            v-model="form.image_urls[index]"
            type="url"
            placeholder="https://example.com/menu-item.jpg"
            class="flex-1"
          />
          <button type="button" class="bg-gray-100 px-3 py-2" @click="removeImageUrlField(index)">
            Remove
          </button>
        </div>

        <button type="button" class="bg-gray-100 px-4 py-2" @click="addImageUrlField">
          Add Image URL
        </button>
      </div>

      <button class="bg-black text-white px-4 py-2">
        Save Changes
      </button>
    </form>
  </div>
</template>
