<template>
  <nav v-if="links.length" class="flex justify-center space-x-2 mt-4" aria-label="Pagination">
    <button
      v-for="link in links"
      :key="link.label"
      :aria-current="link.active ? 'page' : null"
      :disabled="!link.url"
      @click.prevent="visit(link.url)"
      :class="{
        'px-3 py-1 border rounded hover:bg-slate-100 disabled:opacity-50': true,
        'bg-indigo-600 text-white': link.active
      }"
    >{{ labelText(link.label) }}</button>
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

function labelText(html) {
  const node = document.createElement('div');
  node.innerHTML = html;
  return node.textContent || '';
}

function visit(url) {
  if (!url) return;
  router.get(url, {}, { preserveScroll: true });
}
</script>
