<template>
  <div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">Laundry Items</h1>

        <a
            :href="route('staff.laundry.index')"
            class="btn-secondary"
        >
            Return to Laundry Dashboard
        </a>
    </div>

    <!-- Create -->
    <form @submit.prevent="createItem" class="flex gap-2">
      <input v-model="form.name" placeholder="Item name" class="border rounded px-2 py-1" />
      <input v-model.number="form.price" type="number" placeholder="Price" class="border rounded px-2 py-1 w-28" />
      <input v-model="form.description" placeholder="Description" class="border rounded px-2 py-1 flex-1" />
      <button class="btn-primary">Add</button>
    </form>

    <!-- List -->
    <div class="space-y-2">
      <div
        v-for="item in items"
        :key="item.id"
        class="flex items-center gap-2 border p-3 rounded"
      >
        <input v-model="item.name" class="border rounded px-2 py-1 w-40" />
        <input v-model.number="item.price" type="number" class="border rounded px-2 py-1 w-24" />
        <input v-model="item.description" class="border rounded px-2 py-1 flex-1" />

        <button class="btn-secondary" @click="updateItem(item)">Save</button>
        <button class="btn-danger" @click="deleteItem(item)">Delete</button>
      </div>
    </div>

    <p v-if="items.length === 0" class="text-gray-500">
      No laundry items yet.
    </p>
  </div>
</template>

<script setup>
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  items: Array,
})

const items = reactive(props.items)

const form = reactive({
  name: '',
  price: '',
  description: '',
})

function createItem() {
  router.post(route('staff.laundry-items.store'), form, {
    onSuccess: () => {
      form.name = ''
      form.price = ''
      form.description = ''
    },
  })
}

function updateItem(item) {
  router.put(route('staff.laundry-items.update', item.id), {
    name: item.name,
    price: item.price,
    description: item.description,
  })
}

function deleteItem(item) {
  if (!confirm('Delete this laundry item?')) return

  router.delete(route('staff.laundry-items.destroy', item.id))
}
</script>
