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
  prep_time_minutes: props.item.prep_time_minutes,
  menu_category_id: props.item.menu_category_id,
  menu_subcategory_id: props.item.menu_subcategory_id,
})

function update() {
  router.patch(`/staff/menu/items/${props.item.id}`, form)
}
</script>

<template>
  <div class="p-6 max-w-xl">
    <h1 class="text-xl font-bold mb-4">Edit Menu Item</h1>

    <form @submit.prevent="update" class="space-y-4">
      <input v-model="form.name" required />
      <input v-model="form.price" type="number" required />
      <textarea v-model="form.description" />
      <input v-model="form.prep_time_minutes" />

      <button class="bg-black text-white px-4 py-2">
        Save Changes
      </button>
    </form>
  </div>
</template>
