<template>
  <div class="flex flex-wrap gap-3 items-center">
    <!-- Search -->
    <input
      v-model="localSearch"
      @input="emitChange"
      type="text"
      placeholder="Search bookings..."
      class="border rounded px-3 py-2 w-64"
    />

    <!-- Status -->
    <select
      v-model="localFilter"
      @change="emitChange"
      class="border rounded px-3 py-2"
    >
      <option value="all">All</option>
      <option v-for="f in filters" :key="f" :value="f">
        {{ capitalize(f) }}
      </option>
    </select>

    <!-- Date -->
    <input
      v-model="localDate"
      @change="emitChange"
      type="date"
      class="border rounded px-3 py-2"
    />

    <!-- Clear -->
    <button
      v-if="localSearch || localFilter !== 'all' || localDate"
      @click="clear"
      type="button"
      class="text-sm text-gray-600 hover:underline"
    >
      Clear
    </button>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  search: String,
  filter: String,
  date: String,
  filters: Array,
});

const emit = defineEmits(['change']);

const localSearch = ref(props.search || '');
const localFilter = ref(props.filter || 'all');
const localDate   = ref(props.date || '');

watch(() => props.search, v => localSearch.value = v);
watch(() => props.filter, v => localFilter.value = v);
watch(() => props.date,   v => localDate.value = v);

function emitChange() {
  emit('change', {
    search: localSearch.value,
    filter: localFilter.value,
    date: localDate.value,
  });
}

function clear() {
  localSearch.value = '';
  localFilter.value = 'all';
  localDate.value   = '';
  emitChange();
}

function capitalize(v) {
  return v.charAt(0).toUpperCase() + v.slice(1);
}
</script>
