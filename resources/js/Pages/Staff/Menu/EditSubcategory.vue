<script setup>
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({ subcategory: Object })

const form = reactive({
  name: props.subcategory.name
})

function update() {
  router.patch(`/staff/menu/subcategories/${props.subcategory.id}`, form)
}
</script>

<template>
  <div class="p-6 max-w-md">
    <h1 class="text-xl font-bold mb-4">Edit Subcategory</h1>

    <form @submit.prevent="update">
      <input v-model="form.name" required />
      <button class="bg-black text-white px-4 py-2 mt-4">
        Save
      </button>
    </form>
  </div>
</template>
