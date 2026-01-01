<script setup>
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'

defineProps({
  categories: Array,
  area: String
})

const categoryForm = reactive({
  name: '',
  type: ''
})

const subcategoryForm = reactive({
  menu_category_id: '',
  name: ''
})

const itemForm = reactive({
  menu_category_id: '',
  menu_subcategory_id: null,
  name: '',
  description: '',
  price: '',
  prep_time_minutes: '',
  service_area: ''
})

function createCategory() {
  categoryForm.type = area
  router.post('/staff/menu/categories', categoryForm)
}

function createSubcategory() {
  router.post('/staff/menu/subcategories', subcategoryForm)
}

function createItem() {
  router.post('/staff/menu/items', itemForm)
}

function deleteItem(id) {
  if (confirm('Delete item?')) {
    router.delete(`/staff/menu/items/${id}`)
  }
}
</script>

<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold mb-6">
      {{ area === 'kitchen' ? 'Kitchen' : 'Bar' }} Menu Management
    </h1>

    <!-- CREATE CATEGORY -->
    <h2 class="text-lg font-bold mb-2">Add Category</h2>
    <form @submit.prevent="createCategory" class="flex gap-3 mb-6">
      <input v-model="categoryForm.name" placeholder="Category name" required />
      <button class="bg-black text-white px-4">Add</button>
    </form>

    <!-- CREATE SUBCATEGORY -->
    <h2 class="text-lg font-bold mb-2">Add Subcategory</h2>
    <form @submit.prevent="createSubcategory" class="flex gap-3 mb-6">
      <select v-model="subcategoryForm.menu_category_id" required>
        <option value="">Select Category</option>
        <option v-for="c in categories" :key="c.id" :value="c.id">
          {{ c.name }}
        </option>
      </select>
      <input v-model="subcategoryForm.name" placeholder="Subcategory name" required />
      <button class="bg-black text-white px-4">Add</button>
    </form>

    <hr class="my-6" />

    <!-- MENU DISPLAY -->
    <div v-for="category in categories" :key="category.id" class="mb-8">
      <h2 class="text-xl font-bold">{{ category.name }}</h2>

      <!-- Direct Items -->
      <div v-for="item in category.items" :key="item.id" class="flex justify-between border p-2">
        <span>{{ item.name }} — {{ item.price }}</span>
        <button @click="deleteItem(item.id)" class="text-red-600">Delete</button>
      </div>

      <!-- Subcategories -->
      <div v-for="sub in category.subcategories" :key="sub.id" class="ml-4 mt-4">
        <h3 class="font-semibold">{{ sub.name }}</h3>

        <div v-for="item in sub.items" :key="item.id" class="flex justify-between border p-2">
          <span>{{ item.name }} — {{ item.price }}</span>
          <button @click="deleteItem(item.id)" class="text-red-600">Delete</button>
        </div>
      </div>
    </div>

    <hr class="my-6">

    <!-- CREATE ITEM -->
    <h2 class="text-lg font-bold mb-2">Add Menu Item</h2>
    <form @submit.prevent="createItem" class="grid grid-cols-2 gap-4">
      <select v-model="itemForm.menu_category_id" required>
        <option value="">Select Category</option>
        <option v-for="c in categories" :key="c.id" :value="c.id">
          {{ c.name }}
        </option>
      </select>

      <input v-model="itemForm.name" placeholder="Item name" required />
      <input v-model="itemForm.price" placeholder="Price" type="number" required />
      <input v-model="itemForm.prep_time_minutes" placeholder="Prep time (mins)" />

      <select v-model="itemForm.service_area" required>
        <option value="">Service Area</option>
        <option value="kitchen">Kitchen</option>
        <option value="bar">Bar</option>
      </select>

      <textarea v-model="itemForm.description" placeholder="Description" />

      <button class="col-span-2 bg-black text-white p-2">
        Create Item
      </button>
    </form>
  </div>
</template>
