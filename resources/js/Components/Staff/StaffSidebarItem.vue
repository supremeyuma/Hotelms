<script setup>
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  item: {
    type: Object,
    required: true,
  },
})
</script>

<template>
  <Link
    v-if="item.route"
    :href="route(item.route)"
    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition"
    :class="item.active
      ? 'bg-blue-600 text-white'
      : 'text-gray-700 hover:bg-gray-100'"
  >
    <component 
      :is="item.iconComponent" 
      v-if="item.iconComponent" 
      class="w-5 h-5 shrink-0" 
    />
    <div v-else class="w-5 h-5 bg-gray-200 rounded-full shrink-0" />
    
    <span>{{ item.label }}</span>
  </Link>

  <Link
    v-else-if="item.href"
    :href="item.href"
    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition text-gray-700 hover:bg-gray-100"
  >
    <component 
      :is="item.iconComponent" 
      v-if="item.iconComponent" 
      class="w-5 h-5 shrink-0" 
    />
    <div v-else class="w-5 h-5 bg-gray-200 rounded-full shrink-0" />

    <span>{{ item.label }}</span>
  </Link>
</template>
