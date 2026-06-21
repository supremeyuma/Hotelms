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
    class="block p-4 rounded-2xl transition duration-200 hover:bg-slate-50 hover:shadow-sm"
  >
    <div class="flex items-center justify-between gap-4">
      <div class="min-w-0">
        <p class="font-black text-sm text-slate-900 truncate">
          #{{ booking.booking_code }}
        </p>
        <p class="text-xs font-semibold text-slate-400 mt-0.5">
          {{ booking.room?.name ?? 'Unassigned' }}
        </p>
      </div>

      <p class="shrink-0 font-black text-sm text-rose-600 tabular-nums">
        ₦{{ outstandingAmount.toLocaleString() }}
      </p>
    </div>
  </Link>
</template>
