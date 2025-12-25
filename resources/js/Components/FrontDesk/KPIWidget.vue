<template>
  <Link :href="href" class="block p-4 rounded-lg shadow hover:shadow-lg transition bg-white">
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-sm font-medium text-gray-500">{{ title }}</h3>
        <p class="text-2xl font-bold">{{ value }}</p>
      </div>
      <Icon :name="icon" class="w-8 h-8 text-gray-400"/>
    </div>
</Link>
</template>

<script setup>
import Icon from '@/Components/Icon.vue';
import { Link } from '@inertiajs/vue3';
const props = defineProps({
  title: String,
  value: [String, Number],
  href: String,
  icon: String,
});
</script>
