<script setup>
import { computed } from 'vue';

const props = defineProps({
  status: {
    type: String,
    required: false,
    default: '',
  },
});

const label = computed(() =>
  props.status ? props.status.replace('_', ' ') : 'unknown'
);

const colors = {
  requested: 'bg-gray-200 text-gray-800',
  pickup_scheduled: 'bg-blue-100 text-blue-800',
  picked_up: 'bg-indigo-100 text-indigo-800',
  washing: 'bg-yellow-100 text-yellow-800',
  ready: 'bg-green-100 text-green-800',
  delivered: 'bg-green-600 text-white',
  cancelled: 'bg-red-100 text-red-800',
};
</script>

<template>
  <span
    class="px-3 py-1 rounded-full text-sm font-semibold inline-block capitalize"
    :class="colors[status] || 'bg-gray-100 text-gray-800'"
  >
    {{ label }}
  </span>
</template>
