<template>
  <div
    class="flex justify-between items-center p-3 bg-white rounded shadow hover:bg-gray-50 transition"
  >
    <div>
      <p class="font-semibold">{{ booking.guest_name }}</p>
      <p class="text-sm text-gray-500">Code: {{ booking.booking_code }}</p>
    </div>

    <div class="flex gap-4 items-center">
      <span class="text-red-600 font-bold">
        ₦{{ outstandingAmount }}
      </span>
      <Link
        :href="href"
        class="text-blue-600 hover:underline text-sm"
      >
        View
      </Link>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  booking: {
    type: Object,
    required: true,
  },
  href: {
    type: String,
    required: true,
  },
});

const outstandingAmount = computed(() => {
  const charges = props.booking.charges?.reduce((sum, c) => sum + c.amount, 0) || 0;
  const payments = props.booking.payments?.reduce((sum, p) => sum + p.amount, 0) || 0;
  return charges - payments;
});
</script>
