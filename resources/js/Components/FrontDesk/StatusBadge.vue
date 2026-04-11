<template>
  <span
    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold"
    :class="badgeClass"
  >
    {{ label }}
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  status: {
    type: String,
    required: true,
  },
});

const label = computed(() => {
  return {
    pending_payment: 'Pending Payment',
    confirmed: 'Confirmed',
    checked_in: 'Checked In',
    checked_out: 'Checked Out',
    cancelled: 'Cancelled',
    active: 'Checked In',
  }[props.status] ?? props.status;
});

const badgeClass = computed(() => {
  return {
    pending_payment: 'bg-yellow-100 text-yellow-800',
    confirmed: 'bg-blue-100 text-blue-800',
    checked_in: 'bg-green-100 text-green-800',
    active: 'bg-green-100 text-green-800',
    checked_out: 'bg-gray-200 text-gray-700',
    cancelled: 'bg-red-100 text-red-800',
  }[props.status] ?? 'bg-gray-100 text-gray-700';
});
</script>
