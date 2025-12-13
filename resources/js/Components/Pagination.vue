<template>
  <nav v-if="links.length" class="flex justify-center space-x-2 mt-4">
    <button
      v-for="link in links"
      :key="link.label"
      v-html="link.label"
      :disabled="!link.url"
      @click.prevent="visit(link.url)"
       :class="{
            'px-3 py-1 border rounded hover:bg-gray-100 disabled:opacity-50': true,
            'bg-indigo-600 text-white': link.active
        }"
    ></button>
  </nav>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
defineProps({
  links: {
    type: Array,
    required: true
  }
});

function visit(url) {
  if (!url) return;
  router.get(url, {}, { preserveScroll: true });
}
</script>
