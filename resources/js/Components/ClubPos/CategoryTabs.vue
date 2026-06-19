<script setup>
import { computed } from 'vue'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  selectedCategory: { type: [Number, String, null], default: null },
})

const emit = defineEmits(['select'])

const allOption = { id: null, name: 'All' }

const tabs = computed(() => [allOption, ...props.categories])
</script>

<template>
  <div class="flex gap-2 overflow-x-auto pb-2 scrollbar-none shrink-0" style="-webkit-overflow-scrolling: touch">
    <button v-for="tab in tabs" :key="tab.id ?? 'all'"
      @click="emit('select', tab.id)"
      class="px-5 py-2.5 rounded-full text-sm font-medium whitespace-nowrap transition-all active:scale-95 min-h-[44px]"
      :class="selectedCategory === tab.id
        ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30'
        : 'bg-slate-700 text-slate-300 hover:bg-slate-600'">
      {{ tab.name }}
    </button>
  </div>
</template>
