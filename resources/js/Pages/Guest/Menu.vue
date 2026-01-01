<script setup>
defineProps({ categories: Array })
</script>

<template>
  <div v-for="category in categories" :key="category.id" class="mb-8">
    <h2 class="text-xl font-bold">{{ category.name }}</h2>

    <!-- Direct Items -->
    <div v-for="item in category.items" :key="item.id" class="p-2 border-b">
      <div class="flex justify-between">
        <span>{{ item.name }}</span>
        <span>{{ item.price }}</span>
      </div>
    </div>

    <!-- Subcategories -->
    <div v-for="sub in category.subcategories" :key="sub.id" class="ml-4 mt-4">
      <h3 class="font-semibold">{{ sub.name }}</h3>

      <div v-for="item in sub.items" :key="item.id" class="p-2 border-b">
        <div class="flex justify-between">
          <span>{{ item.name }}</span>
          <span>{{ item.price }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
