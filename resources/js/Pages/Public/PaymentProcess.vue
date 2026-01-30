<template>
  <PublicLayout>
    <Head title="Payment | MooreLife Resort" />
    <div class="min-h-screen bg-gray-50 py-12">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Payment Header -->
        <div class="text-center mb-8">
          <h1 class="text-3xl font-bold text-gray-900">Complete Payment</h1>
          <p class="mt-2 text-gray-600">Secure payment processing for your reservation</p>
        </div>

        <!-- Item Details -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
          <div class="p-8">
            <div v-if="type === 'ticket'" class="mb-8">
              <h2 class="text-xl font-bold text-gray-900 mb-4">Ticket Details</h2>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                  <span class="text-gray-600">Event:</span>
                  <span class="font-medium text-gray-900">{{ item.event.title }}</span>
                </div>
                <div>
                  <span class="text-gray-600">Ticket Type:</span>
                  <span class="font-medium text-gray-900">{{ item.ticket_type.name }}</span>
                </div>
                <div>
                  <span class="text-gray-600">Quantity:</span>
                  <span class="font-medium text-gray-900">{{ item.quantity }}</span>
                </div>
                <div>
                  <span class="text-gray-600">Guest:</span>
                  <span class="font-medium text-gray-900">{{ item.guest_name }}</span>
                </div>
              </div>
            </div>

            <div v-if="type === 'table'" class="mb-8">
              <h2 class="text-xl font-bold text-gray-900 mb-4">Table Reservation</h2>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                  <span class="text-gray-600">Event:</span>
                  <span class="font-medium text-gray-900">{{ item.event.title }}</span>
                </div>
                <div>
                  <span class="text-gray-600">Table:</span>
                  <span class="font-medium text-gray-900">{{ item.table_number || 'TBD' }}</span>
                </div>
                <div>
                  <span class="text-gray-600">Guests:</span>
                  <span class="font-medium text-gray-900">{{ item.number_of_guests }}</span>
                </div>
                <div>
                  <span class="text-gray-600">Guest Name:</span>
                  <span class="font-medium text-gray-900">{{ item.guest_name }}</span>
                </div>
              </div>
            </div>

            <!-- Payment Form -->
            <div class="space-y-6">
              <h3 class="text-xl font-bold text-gray-900 mb-6">Payment Information</h3>
              
              <div class="bg-gray-50 p-6 rounded-lg">
                <div class="mb-6">
                  <div class="text-2xl font-bold text-gray-900 mb-2">Total Amount</div>
                  <div class="text-3xl font-bold text-indigo-600">₦{{ formatNumber(item.amount) }}</div>
                </div>

                <!-- Mock Payment Form -->
                <form @submit.prevent="processMockPayment" class="space-y-6">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
                    <select v-model="form.payment_method" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                      <option value="flutterwave">Flutterwave</option>
                      <option value="paystack">Paystack</option>
                      <option value="card">Credit Card</option>
                      <option value="bank">Bank Transfer</option>
                    </select>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Card Number</label>
                    <input v-model="form.card_number" type="text" placeholder="4111 1111 1111 1111"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                  </div>

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">Expiry Date</label>
                      <input v-model="form.expiry_date" type="text" placeholder="MM/YY"
                             class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">CVV</label>
                      <input v-model="form.cvv" type="text" placeholder="123"
                             class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Cardholder Name</label>
                    <input v-model="form.cardholder_name" type="text" placeholder="John Doe"
                             class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                    <input v-model="form.email" type="email" placeholder="john@example.com"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                  </div>

                  <div class="text-sm text-gray-600 mb-6">
                    <p class="font-semibold text-yellow-800">⚠️ Demo Mode</p>
                    <p>This is a mock payment processor. In production, this would integrate with Flutterwave API for real payment processing.</p>
                  </div>

                  <button type="submit" :disabled="processing"
                          class="w-full flex items-center justify-center px-4 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg v-if="!processing" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h18a1 1 0 001 1v16a1 1 0 001-1H3a1 1 0 00-1-1V3a1 1 0 00-1-1z" />
                    </svg>
                    <span v-if="processing">Processing...</span>
                    <span v-else>Process Payment - ₦{{ formatNumber(item.amount) }}</span>
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </PublicLayout>
</template>

<script setup>
import { ref } from 'vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
  type: String, // 'ticket' or 'table'
  item: Object, // EventTicket or EventTableReservation
  reference: String,
})

const processing = ref(false)

const form = ref({
  payment_method: 'flutterwave',
  card_number: '',
  expiry_date: '',
  cvv: '',
  cardholder_name: '',
  email: props.item.guest_email,
})

const processMockPayment = () => {
  processing.value = true
  
  // Simulate payment processing
  setTimeout(() => {
    // Simulate successful payment
    window.location.href = `/events/payment/callback?tx_ref=${props.reference}&status=success&payment_method=flutterwave`
  }, 2000) // 2 seconds
}

const formatNumber = (num) => {
  return new Intl.NumberFormat('en-NG').format(num)
}
</script>