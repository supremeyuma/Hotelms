<script setup>
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  booking: { type: Object, required: true },
  href: { type: String, required: true },
})

console.log(props.booking)

const outstandingAmount = computed(() => {
  // Convert each charge amount string to a float number
  const charges = props.booking.charges?.reduce((sum, c) => {
    return sum + parseFloat(c.amount || 0);
  }, 0) || 0;

  // Do the same for payments just in case they are also strings
  const payments = props.booking.payments?.reduce((sum, p) => {
    return sum + parseFloat(p.amount || 0);
  }, 0) || 0;

  return charges - payments;
});

//console.log(props.booking);
</script>

<template>
  <Link
    :href="href"
    class="block p-3 rounded border hover:bg-gray-50"
  >
    <div class="flex justify-between">
      <div>
        <p class="font-semibold">
          Booking #{{ booking.booking_code }}
        </p>
        <p class="text-sm text-gray-500">
          Room {{ booking.room?.room_number ?? 'Unassigned' }}
        </p>
      </div>

      <p class="font-semibold text-red-600">
        ₦{{ outstandingAmount.toLocaleString() }}
      </p>
    </div>
  </Link>
</template>
