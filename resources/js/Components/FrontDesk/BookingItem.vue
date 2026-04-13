<script setup>
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  booking: { type: Object, required: true },
  href: { type: String, required: true },
})

//console.log(props.booking)

const outstandingAmount = computed(() => {
  if (props.booking.status === 'cancelled') {
    return 0;
  }

  const charges = props.booking.charges?.reduce((sum, c) => {
    return sum + parseFloat(c.amount || 0);
  }, 0) || 0;

  const payments = props.booking.payments?.reduce((sum, p) => {
    return sum + parseFloat(p.amount_paid || p.amount || 0);
  }, 0) || 0;

  const bookingTotal = parseFloat(props.booking.total_amount || 0);
  const effectiveCharges = Math.max(charges, bookingTotal);

  return Math.max(effectiveCharges - payments, 0);
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
          Room {{ booking.room?.name ?? 'Unassigned' }}
        </p>
      </div>

      <p class="font-semibold text-red-600">
        ₦{{ outstandingAmount.toLocaleString() }}
      </p>
    </div>
  </Link>
</template>
