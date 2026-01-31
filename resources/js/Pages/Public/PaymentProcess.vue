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

                <!-- Real Flutterwave Checkout -->
                <div class="space-y-6">
                  <p class="text-sm text-gray-600 mb-4">You will be redirected to a secure payment gateway to complete the transaction.</p>

                  <button @click.prevent="processPayment" :disabled="processing"
                          class="w-full flex items-center justify-center px-4 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg v-if="!processing" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h18a1 1 0 001 1v16a1 1 0 001-1H3a1 1 0 00-1-1V3a1 1 0 00-1-1z" />
                    </svg>
                    <span v-if="processing">Redirecting...</span>
                    <span v-else>Pay Now - ₦{{ formatNumber(item.amount) }}</span>
                  </button>
                </div>
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

const loadFlutterwave = () => {
  return new Promise((resolve, reject) => {
    if (window.FlutterwaveCheckout) return resolve(window.FlutterwaveCheckout)

    const script = document.createElement('script')
    script.src = 'https://checkout.flutterwave.com/v3.js'
    script.async = true
    script.onload = () => resolve(window.FlutterwaveCheckout)
    script.onerror = () => reject(new Error('Failed to load Flutterwave script'))
    document.head.appendChild(script)
  })
}

const processPayment = async () => {
  processing.value = true

  try {
    const payload = {
      amount: item.amount,
      currency: 'NGN',
      tx_ref: props.reference,
      description: `${props.type} payment`,
      customer_email: form.value.email,
      customer_name: form.value.cardholder_name || form.value.email,
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    const initRes = await fetch('/payments/initialize', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf || '' },
      body: JSON.stringify(payload),
    })

    if (!initRes.ok) {
      const err = await initRes.json().catch(() => ({}))
      throw new Error(err.message || 'Failed to initialize payment')
    }

    const data = await initRes.json()

    await loadFlutterwave()

    window.FlutterwaveCheckout({
      public_key: data.public_key,
      tx_ref: data.tx_ref,
      amount: data.amount,
      currency: data.currency || 'NGN',
      payment_options: 'card,bank,ussd',
      customer: data.customer,
      customizations: { title: 'MooreLife Resort', description: data.description || '' },
      callback: function (resp) {
        // Redirect to server-side callback to finalize
        const tx = encodeURIComponent(resp.tx_ref || data.tx_ref)
        const status = encodeURIComponent(resp.status || 'unknown')
        window.location.href = `/events/payment/callback?tx_ref=${tx}&status=${status}&payment_method=flutterwave`
      },
      onclose: function () {
        processing.value = false
      }
    })

  } catch (e) {
    alert(e.message || 'Payment initialization failed')
    processing.value = false
  }
}

const formatNumber = (num) => {
  return new Intl.NumberFormat('en-NG').format(num)
}
</script>