<template>
    <GuestLayout>
  <div class="max-w-xl mx-auto py-12">
    <h1 class="text-2xl font-bold mb-4">Payment</h1>

    <div class="border rounded p-4 mb-6">
      <p><strong>Booking #{{ booking.id }}</strong></p>
      <p>Total Amount: ₦{{ booking.total_amount }}</p>
      <p class="text-sm text-gray-500">
        Reservation expires at {{ expires_at }}
      </p>
    </div>

    <div class="space-y-4">
      <button
        @click="pay"
        class="w-full bg-green-600 text-white py-2 rounded"
      >
        Pay Now (Mock)
      </button>

      <p class="text-xs text-gray-500 text-center">
        This is a mock payment screen. No real transaction occurs.
      </p>
    </div>
  </div>
  </GuestLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const props = defineProps({
  booking: Object,
  expires_at: String,
})

function pay() {
  router.post(`/booking/payment/${props.booking.id}/confirm`)
}
</script>
