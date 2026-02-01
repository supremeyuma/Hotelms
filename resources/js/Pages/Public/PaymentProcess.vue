<template>
  <PublicLayout>
    <Head title="Payment | MooreLife Resort" />

    <div class="min-h-screen bg-gray-50 py-12">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="text-center mb-8">
          <h1 class="text-3xl font-bold text-gray-900">Complete Payment</h1>
          <p class="mt-2 text-gray-600">
            Secure payment processing for your reservation
          </p>
        </div>

        <!-- Details -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
          <div class="p-8">
            <!-- Ticket -->
            <div v-if="type === 'ticket'" class="mb-8">
              <h2 class="text-xl font-bold text-gray-900 mb-4">Ticket Details</h2>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <span class="text-gray-600">Event:</span>
                  <span class="font-medium text-gray-900">
                    {{ meta.event }}
                  </span>
                </div>

                <div>
                  <span class="text-gray-600">Ticket Type:</span>
                  <span class="font-medium text-gray-900">
                    {{ meta.ticketType }}
                  </span>
                </div>

                <div>
                  <span class="text-gray-600">Quantity:</span>
                  <span class="font-medium text-gray-900">
                    {{ meta.quantity }}
                  </span>
                </div>

                <div>
                  <span class="text-gray-600">Guest:</span>
                  <span class="font-medium text-gray-900">
                    {{ customer.name }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Table -->
            <div v-if="type === 'table'" class="mb-8">
              <h2 class="text-xl font-bold text-gray-900 mb-4">
                Table Reservation
              </h2>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <span class="text-gray-600">Event:</span>
                  <span class="font-medium text-gray-900">
                    {{ meta.event }}
                  </span>
                </div>

                <div>
                  <span class="text-gray-600">Table:</span>
                  <span class="font-medium text-gray-900">
                    {{ meta.table }}
                  </span>
                </div>

                <div>
                  <span class="text-gray-600">Guest:</span>
                  <span class="font-medium text-gray-900">
                    {{ customer.name }}
                  </span>
                </div>

                <div>
                  <span class="text-gray-600">Phone number:</span>
                  <span class="font-medium text-gray-900">
                    {{ customer.phone }}
                  </span>
                </div>

                <div>
                  <span class="text-gray-600">Guest:</span>
                  <span class="font-medium text-gray-900">
                    {{ customer.email }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Payment -->
            <div class="space-y-6">
              <h3 class="text-xl font-bold text-gray-900">
                Payment Information
              </h3>

              <div class="bg-gray-50 p-6 rounded-lg">
                <div class="mb-6">
                  <div class="text-2xl font-bold text-gray-900">
                    Total Amount
                  </div>
                  <div class="text-3xl font-bold text-indigo-600">
                    ₦{{ formatNumber(amount) }}
                  </div>
                </div>

                <p class="text-sm text-gray-600 mb-4">
                  You will be redirected to Flutterwave to complete payment.
                </p>

                <button
                  @click.prevent="processPayment"
                  :disabled="processing"
                  class="w-full flex items-center justify-center px-4 py-3 rounded-md text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50"
                >
                  <span v-if="processing">Redirecting…</span>
                  <span v-else>
                    Pay Now – ₦{{ formatNumber(amount) }}
                  </span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </PublicLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import PublicLayout from '@/Layouts/PublicLayout.vue'

const props = defineProps({
  type: { type: String, required: true },
  reference: { type: String, required: true },
  amount: {type: Number, required: true},
  customer: {type: Object, required: true},
  meta: {type: Object, required: true},
})

const processing = ref(false)
const flutterwaveReady = ref(false)

/**
 * Load Flutterwave script ONCE
 */
const loadFlutterwave = () =>
  new Promise((resolve, reject) => {
    if (window.FlutterwaveCheckout) {
      flutterwaveReady.value = true
      return resolve()
    }

    const script = document.createElement('script')
    script.src = 'https://checkout.flutterwave.com/v3.js'
    script.onload = () => {
      flutterwaveReady.value = true
      resolve()
    }
    script.onerror = reject
    document.head.appendChild(script)
  })

onMounted(() => {
  loadFlutterwave().catch(() => {
    alert('Failed to load payment gateway')
  })
})

/**
 * Initialize payment via BACKEND, then open Flutterwave
 */
const processPayment = async () => {
  if (!flutterwaveReady.value) {
    alert('Payment gateway not ready. Please refresh.')
    return
  }

  processing.value = true

  try {
    const csrf = document
      .querySelector('meta[name="csrf-token"]')
      ?.getAttribute('content')

    const res = await fetch('/payments/initialize-by-reference', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({
        reference: props.reference,
      }),
    })

    if (!res.ok) {
      const err = await res.json().catch(() => ({}))
      throw new Error(err.message || 'Payment initialization failed')
    }

    const data = await res.json()

    console.log('Flutterwave Config:', data) // ADD THIS LINE

    // Check if data.customer exists before calling
    if (!data.customer || !data.customer.email) {
        throw new Error('Customer information missing from server response');
    }

    window.FlutterwaveCheckout({
      public_key: data.public_key,
      tx_ref: data.tx_ref,
      amount: parseFloat(data.amount),
      currency: data.currency || 'NGN',
      payment_options: data.payment_options || "card,ussd,banktransfer",

      customer: data.customer,

      customizations: {
        title: 'MooreLife Resort',
        description: data.description || `${props.type} payment`,
        display_mode: "inline",
        logo: "https://mooreliferesort.com/storage/settings/1oKHlZ7TWLOGGuvBjENzXqDS0k9haZBoqoj2w4le.png", // Adding a logo helps branding
      },

      callback: (res) => {
        window.location.href =
          `/events/payment/callback?transaction_id=${res.transaction_id}&tx_ref=${res.tx_ref}`
      },

      onclose: () => {
        processing.value = false
      },
    })
  } catch (e) {
    alert(e.message || 'Payment failed to initialize')
    processing.value = false
  }
}

const formatNumber = (n) =>
  new Intl.NumberFormat('en-NG').format(n)
</script>

