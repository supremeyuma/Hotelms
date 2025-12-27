<template>
  <div class="flex flex-wrap gap-3 items-center">
    <!-- Search -->
    <input
      v-model="localSearch"
      @input="apply"
      type="text"
      placeholder="Search bookings..."
      class="border rounded px-3 py-2 w-64"
    />

    <!-- Status -->
    <select
      v-model="localFilter"
      @change="apply"
      class="border rounded px-3 py-2"
    >
      <option value="all">All</option>
      <option
        v-for="f in filters"
        :key="f"
        :value="f"
      >
        {{ capitalize(f) }}
      </option>
    </select>

    <!-- Check-in Date -->
    <input
      v-model="localDate"
      @change="apply"
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
import { router } from '@inertiajs/vue3';

const props = defineProps({
  search: String,
  filter: String,
  date: String,
  filters: Array,
  routeName: String,
});

const emit = defineEmits([
  'update:search',
  'update:filter',
  'update:date',
]);

const localSearch = ref(props.search || '');
const localFilter = ref(props.filter || 'all');
const localDate   = ref(props.date || '');

watch(() => props.search, v => localSearch.value = v);
watch(() => props.filter, v => localFilter.value = v);
watch(() => props.date,   v => localDate.value = v);

function apply() {
  emit('update:search', localSearch.value);
  emit('update:filter', localFilter.value);
  emit('update:date',   localDate.value);

  router.get(
    route(props.routeName),
    {
      search: localSearch.value || undefined,
      filter: localFilter.value !== 'all' ? localFilter.value : undefined,
      date:   localDate.value || undefined,
    },
    { preserveState: true, replace: true }
  );
}

function clear() {
  localSearch.value = '';
  localFilter.value = 'all';
  localDate.value   = '';

  apply();
}

function capitalize(v) {
  return v.charAt(0).toUpperCase() + v.slice(1);
}
</script>
