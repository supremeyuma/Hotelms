<script setup>
import { Link, usePage } from '@inertiajs/vue3'

const props = defineProps({
  item: Object,
})

const page = usePage()

const isActive = () =>
  route().current(props.item.route)
</script>

<template>
  <Link
    :href="route(item.route)"
    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition"
    :class="isActive()
      ? 'bg-blue-600 text-white'
      : 'text-gray-700 hover:bg-gray-100'"
  >
    <span class="w-5 h-5 text-center">{{ item.icon }}</span>
    <span>{{ item.label }}</span>
  </Link>
</template>
