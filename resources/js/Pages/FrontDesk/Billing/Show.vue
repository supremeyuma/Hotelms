<template>
  <FrontDeskLayout>
  <div class="p-6 space-y-6">
    <h1 class="text-2xl font-bold">Billing for {{ booking.guest_name }}</h1>

    <!-- Outstanding Amount -->
    <div class="text-lg font-semibold">
      Outstanding: ₦{{ billing.outstanding.toFixed(2) }}
    </div>

    <!-- Charges Table -->
    <h2 class="font-semibold">Charges</h2>
    <table class="min-w-full border">
      <thead>
        <tr class="bg-gray-100">
          <th class="px-4 py-2">Description</th>
          <th class="px-4 py-2">Amount</th>
          <th class="px-4 py-2">Date</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="charge in billing.charges" :key="charge.id">
          <td class="px-4 py-2">{{ charge.description }}</td>
          <td class="px-4 py-2">₦{{ charge.amount.toFixed(2) }}</td>
          <td class="px-4 py-2">{{ charge.created_at }}</td>
        </tr>
      </tbody>
    </table>

    <!-- Payments Table -->
    <h2 class="font-semibold mt-4">Payments</h2>
    <table class="min-w-full border">
      <thead>
        <tr class="bg-gray-100">
          <th class="px-4 py-2">Amount</th>
          <th class="px-4 py-2">Method</th>
          <th class="px-4 py-2">Notes</th>
          <th class="px-4 py-2">Date</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="payment in billing.payments" :key="payment.id">
          <td class="px-4 py-2">₦{{ payment.amount.toFixed(2) }}</td>
          <td class="px-4 py-2">{{ payment.method }}</td>
          <td class="px-4 py-2">{{ payment.notes }}</td>
          <td class="px-4 py-2">{{ payment.created_at }}</td>
        </tr>
      </tbody>
    </table>

    <!-- Payment Form -->
    <h2 class="font-semibold mt-4">Add Payment</h2>
    <form @submit.prevent="submitPayment" class="space-y-2">
      <div>
        <label>Amount</label>
        <input type="number" v-model="form.amount" min="0.01" step="0.01" required class="input"/>
      </div>
      <div>
        <label>Method</label>
        <select v-model="form.method" required class="input">
          <option>Cash</option>
          <option>Card</option>
          <option>Online</option>
        </select>
      </div>
      <div>
        <label>Notes (optional)</label>
        <input type="text" v-model="form.notes" class="input"/>
      </div>
      <button type="submit" class="btn-primary">Record Payment</button>
      <button type="button" @click="settleFull" class="btn-success">Settle Full Balance</button>
    </form>
  </div>
  </FrontDeskLayout>
</template>

<script setup>
import FrontDeskLayout from '@/Layouts/Staff/FrontDeskLayout.vue'
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  booking: Object,
  billing: Object,
});

const form = ref({
  amount: '',
  method: 'Cash',
  notes: ''
});

function submitPayment() {
  router.post(`/frontdesk/billing/${props.booking.id}/pay`, form.value, { preserveState: true });
}

function settleFull() {
  router.post(`/frontdesk/billing/${props.booking.id}/settle-full`, {}, { preserveState: true });
}
</script>

<style scoped>
.input { @apply border px-2 py-1 rounded w-full; }
.btn-primary { @apply bg-blue-500 text-white px-4 py-2 rounded; }
.btn-success { @apply bg-green-500 text-white px-4 py-2 rounded ml-2; }
</style>
