<template>
  <div class="flex flex-wrap gap-3 items-center bg-white p-4 rounded shadow">
    <!-- Search -->
    <input
      v-model="filters.search"
      @keyup.enter="apply"
      type="text"
      placeholder="Search booking code or guest name..."
      class="border rounded px-3 py-2 w-64"
    />

    <!-- Status Filter -->
    <select
      v-model="filters.status"
      @change="apply"
      class="border rounded px-3 py-2"
    >
      <option value="">All Statuses</option>
      <option value="active">Active</option>
      <option value="confirmed">Confirmed</option>
      <option value="checked_in">Checked In</option>
      <option value="checked_out">Checked Out</option>
      <option value="cancelled">Cancelled</option>
    </select>

    <!-- Date Filter -->
    <input
      v-model="filters.date"
      @change="apply"
      type="date"
      class="border rounded px-3 py-2"
    />

    <!-- Reset -->
    <button
      @click="reset"
      class="bg-gray-200 px-4 py-2 rounded hover:bg-gray-300"
    >
      Reset
    </button>
  </div>
</template>

<script setup>
import { reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  routeName: {
    type: String,
    required: true,
  },
  modelValue: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['update:modelValue']);

// Reactive filters
const filters = reactive({
  search: props.modelValue,
  status: '',
  date: '',
});

// Sync v-model search with parent
watch(
  () => props.modelValue,
  val => {
    filters.search = val;
  }
);

watch(
  () => filters.search,
  val => emit('update:modelValue', val)
);

// Apply filter by sending request to backend
function apply() {
  router.get(
    route(props.routeName),
    {
      search: filters.search || undefined,
      status: filters.status || undefined,
      date: filters.date || undefined,
    },
    { preserveState: true, replace: true }
  );
}

// Reset all filters
function reset() {
  filters.search = '';
  filters.status = '';
  filters.date = '';
  apply();
}
</script>
