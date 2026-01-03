<script setup>
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({ category: Object })

const form = reactive({
  name: props.category.name
})

function update() {
  router.patch(`/staff/menu/categories/${props.category.id}`, form)
}
</script>

<template>
  <div class="p-6 max-w-md">
    <h1 class="text-xl font-bold mb-4">Edit Category</h1>

    <form @submit.prevent="update">
      <input v-model="form.name" required />
      <button class="bg-black text-white px-4 py-2 mt-4">
        Save
      </button>
    </form>
  </div>
</template>
