<script setup>
import { Link } from '@inertiajs/vue3'
import Icon from '@/Components/Icon.vue'

const props = defineProps({
  item: {
    type: Object,
    required: true,
  },
})
console.log(props.item);
</script>

<template>
  <!-- Named route -->
  <Link
    v-if="item.route"
    :href="route(item.route)"
    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition"
    :class="route().current(item.route)
      ? 'bg-blue-600 text-white'
      : 'text-gray-700 hover:bg-gray-100'"
  >
    <Icon :name="item.icon" class="w-5 h-5" />
    <span>{{ item.label }}</span>
  </Link>

  <!-- Raw href (query strings etc.) -->
  <Link
    v-else-if="item.href"
    :href="item.href"
    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition text-gray-700 hover:bg-gray-100"
  >
    <Icon :name="item.icon" class="w-5 h-5" />
    <span>{{ item.label }}</span>
  </Link>
</template>
