<template>
  <div class="p-6 space-y-8">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold">
        Room {{ room.number }} – Billing
      </h1>

      <Link
        href="/frontdesk/rooms"
        class="text-sm text-gray-600 hover:underline"
      >
        ← Back to Rooms
      </Link>
    </div>

    <!-- Outstanding -->
    <div class="p-4 bg-yellow-50 border rounded">
      <p class="text-lg font-semibold">
        Outstanding Balance:
        <span class="text-red-600">
          ₦{{ billing.outstanding.toFixed(2) }}
        </span>
      </p>
    </div>

    <!-- Charges -->
    <div>
      <h2 class="text-lg font-semibold mb-2">Charges</h2>

      <table class="min-w-full border">
        <thead class="bg-gray-100">
          <tr>
            <th class="px-4 py-2 border">Description</th>
            <th class="px-4 py-2 border">Amount</th>
            <th class="px-4 py-2 border">Date</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="charge in billing.charges" :key="charge.id">
            <td class="px-4 py-2 border">{{ charge.description }}</td>
            <td class="px-4 py-2 border">
              ₦{{ charge.amount.toFixed(2) }}
            </td>
            <td class="px-4 py-2 border">
              {{ formatDate(charge.created_at) }}
            </td>
          </tr>

          <tr v-if="billing.charges.length === 0">
            <td colspan="3" class="text-center py-4 text-gray-500">
              No charges yet
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Payments -->
    <div>
      <h2 class="text-lg font-semibold mb-2">Payments</h2>

      <table class="min-w-full border">
        <thead class="bg-gray-100">
          <tr>
            <th class="px-4 py-2 border">Amount</th>
            <th class="px-4 py-2 border">Method</th>
            <th class="px-4 py-2 border">Notes</th>
            <th class="px-4 py-2 border">Date</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="payment in billing.payments" :key="payment.id">
            <td class="px-4 py-2 border">
              ₦{{ payment.amount.toFixed(2) }}
            </td>
            <td class="px-4 py-2 border">{{ payment.method }}</td>
            <td class="px-4 py-2 border">{{ payment.notes ?? '—' }}</td>
            <td class="px-4 py-2 border">
              {{ formatDate(payment.created_at) }}
            </td>
          </tr>

          <tr v-if="billing.payments.length === 0">
            <td colspan="4" class="text-center py-4 text-gray-500">
              No payments recorded
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Payment Form -->
    <div class="border rounded p-4">
      <h2 class="text-lg font-semibold mb-4">Record Payment</h2>

      <form @submit.prevent="submitPayment" class="space-y-4 max-w-md">
        <div>
          <label class="block text-sm font-medium">Amount</label>
          <input
            v-model.number="form.amount"
            type="number"
            step="0.01"
            min="0.01"
            class="w-full border rounded px-3 py-2"
            required
          />
        </div>

        <div>
          <label class="block text-sm font-medium">Method</label>
          <select
            v-model="form.method"
            class="w-full border rounded px-3 py-2"
          >
            <option>Cash</option>
            <option>Card</option>
            <option>Transfer</option>
            <option>Online</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium">Notes</label>
          <input
            v-model="form.notes"
            type="text"
            class="w-full border rounded px-3 py-2"
          />
        </div>

        <input
          type="hidden"
          v-model="form.booking_id"
        />

        <button
          type="submit"
          class="bg-blue-600 text-white px-4 py-2 rounded"
        >
          Record Payment
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
  room: Object,
  billing: Object
});

const form = ref({
  booking_id: props.billing.charges[0]?.booking_id ?? null,
  amount: '',
  method: 'Cash',
  notes: ''
});

function submitPayment() {
  router.post(
    `/frontdesk/rooms/${props.room.id}/billing/pay`,
    form.value,
    { preserveScroll: true }
  );
}

function formatDate(date) {
  return new Date(date).toLocaleString();
}
</script>
