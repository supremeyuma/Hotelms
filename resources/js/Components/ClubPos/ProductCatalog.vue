<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import ProductCard from './ProductCard.vue'
import CategoryTabs from './CategoryTabs.vue'

const props = defineProps({
  menuItems: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  stockItems: { type: Array, default: () => [] },
})
const emit = defineEmits(['add-item'])

const selectedCategory = ref(null)
const search = ref('')

const filteredItems = computed(() => {
  let items = props.menuItems
  if (selectedCategory.value) {
    items = items.filter(i =>
      i.menu_category_id === selectedCategory.value ||
      i.category?.id === selectedCategory.value
    )
  }
  if (search.value) {
    const q = search.value.toLowerCase()
    items = items.filter(i =>
      i.name.toLowerCase().includes(q) ||
      i.description?.toLowerCase().includes(q)
    )
  }
  return items
})

const stockMap = computed(() => {
  const map = {}
  props.stockItems?.forEach(s => {
    map[s.menu_item_id] = s
  })
  return map
})
</script>

<template>
  <div class="flex flex-col gap-4 h-full">
    <div class="relative">
      <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
      </svg>
      <input v-model="search" type="text" placeholder="Search drinks..."
        class="w-full pl-10 pr-4 py-3 rounded-xl bg-slate-800 border border-slate-700 text-white text-base placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
    </div>

    <CategoryTabs :categories="categories" :selectedCategory="selectedCategory" @select="selectedCategory = $event" />

    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 overflow-y-auto flex-1 content-start pb-4" style="-webkit-overflow-scrolling: touch">
      <ProductCard v-for="item in filteredItems" :key="item.id"
        :item="item"
        :stock="stockMap[item.id]"
        @add="emit('add-item', $event)" />
    </div>

    <p v-if="filteredItems.length === 0" class="text-center text-slate-500 py-12">
      No items found
    </p>
  </div>
</template>
