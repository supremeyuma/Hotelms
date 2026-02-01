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

                <!-- Provider Selection (when multiple providers enabled) -->
                <div v-if="paymentData && paymentData.show_provider_options" class="mb-6 p-4 bg-white rounded-lg border-2 border-indigo-100">
                  <label class="block text-sm font-bold text-gray-700 mb-3">Choose Payment Method:</label>
                  <div class="grid grid-cols-2 gap-3">
                    <button
                      v-for="prov in paymentData.available_providers"
                      :key="prov.value"
                      @click.prevent="selectedProvider = prov.value"
                      :class="{
                        'ring-2 ring-indigo-600 bg-indigo-50': selectedProvider === prov.value,
                        'border-2 border-gray-300 hover:border-indigo-300': selectedProvider !== prov.value,
                      }"
                      class="px-4 py-3 rounded-lg font-medium transition-all"
                    >
                      {{ prov.label }}
                    </button>
                  </div>
                </div>

                <!-- Payment Method Info -->
                <div v-if="selectedProvider" class="mb-4 p-3 bg-blue-50 border-l-4 border-blue-500 rounded text-sm text-blue-700">
                  <span v-if="selectedProvider === 'flutterwave'">
                    You will be redirected to Flutterwave to complete payment.
                  </span>
                  <span v-else-if="selectedProvider === 'paystack'">
                    You will be redirected to Paystack to complete payment.
                  </span>
                </div>

                <button
                  @click.prevent="processPayment"
                  :disabled="processing || !selectedProvider"
                  class="w-full flex items-center justify-center px-4 py-3 rounded-md text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50"
                >
                  <span v-if="processing">Processing…</span>
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
const paymentData = ref(null)
const selectedProvider = ref(null)
const flutterwaveReady = ref(false)
const paystackReady = ref(false)

/**
 * Load Flutterwave script
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

/**
 * Load Paystack script
 */
const loadPaystack = () =>
  new Promise((resolve, reject) => {
    if (window.PaystackPop) {
      paystackReady.value = true
      return resolve()
    }

    const script = document.createElement('script')
    script.src = 'https://js.paystack.co/v1/inline.js'
    script.onload = () => {
      paystackReady.value = true
      resolve()
    }
    script.onerror = reject
    document.head.appendChild(script)
  })

onMounted(async () => {
  try {
    // Initialize payment to get provider options
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
      throw new Error('Failed to load payment data')
    }

    paymentData.value = await res.json()
    
    // Filter out manual/venue payments for tickets and tables - only allow online providers
    if (props.type === 'ticket' || props.type === 'table') {
      paymentData.value.available_providers = paymentData.value.available_providers.filter(
        p => p.value !== 'manual' && p.value !== 'venue'
      )
    }
    
    selectedProvider.value = paymentData.value.provider

    // Pre-load payment libraries based on available providers
    const promises = []
    if (paymentData.value.available_providers.some(p => p.value === 'flutterwave')) {
      promises.push(loadFlutterwave().catch(() => console.warn('Flutterwave failed to load')))
    }
    if (paymentData.value.available_providers.some(p => p.value === 'paystack')) {
      promises.push(loadPaystack().catch(() => console.warn('Paystack failed to load')))
    }

    await Promise.all(promises)
  } catch (e) {
    console.error('Payment initialization error:', e)
  }
})

/**
 * Initialize payment and redirect to appropriate provider
 */
const processPayment = async () => {
  if (!selectedProvider.value) {
    alert('Please select a payment method')
    return
  }

  // Check if required library is loaded
  if (selectedProvider.value === 'flutterwave' && !flutterwaveReady.value) {
    alert('Flutterwave payment gateway not ready. Please refresh.')
    return
  }

  if (selectedProvider.value === 'paystack' && !paystackReady.value) {
    alert('Paystack payment gateway not ready. Please refresh.')
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
        provider: selectedProvider.value,
      }),
    })

    if (!res.ok) {
      const err = await res.json().catch(() => ({}))
      throw new Error(err.message || 'Payment initialization failed')
    }

    const data = await res.json()
    console.log('Payment Config:', data)

    if (!data.customer || !data.customer.email) {
      throw new Error('Customer information missing from server response')
    }

    if (selectedProvider.value === 'flutterwave') {
      handleFlutterwave(data)
    } else if (selectedProvider.value === 'paystack') {
      handlePaystack(data)
    }
  } catch (e) {
    alert(e.message || 'Payment failed to initialize')
    processing.value = false
  }
}

/**
 * Handle Flutterwave checkout
 */
const handleFlutterwave = (data) => {
  window.FlutterwaveCheckout({
    public_key: data.public_key,
    tx_ref: data.tx_ref,
    amount: parseFloat(data.amount),
    currency: data.currency || 'NGN',
    payment_options: data.payment_options || 'card,ussd,banktransfer',
    customer: data.customer,
    customizations: {
      title: 'MooreLife Resort',
      description: data.description || `${props.type} payment`,
      display_mode: 'inline',
      logo: 'https://mooreliferesort.com/storage/settings/1oKHlZ7TWLOGGuvBjENzXqDS0k9haZBoqoj2w4le.png',
    },
    callback: (res) => {
      window.location.href = `/events/payment/callback?transaction_id=${res.transaction_id}&tx_ref=${res.tx_ref}`
    },
    onclose: () => {
      processing.value = false
    },
  })
}

/**
 * Handle Paystack checkout
 */
const handlePaystack = (data) => {
  const handler = window.PaystackPop.setup({
    key: data.public_key,
    email: data.customer.email,
    amount: parseFloat(data.amount) * 100, // Paystack expects amount in kobo
    currency: data.currency || 'NGN',
    ref: data.reference,
    onClose: () => {
      processing.value = false
      alert('Payment cancelled')
    },
    onSuccess: (response) => {
      window.location.href = `/events/payment/callback?transaction_id=${response.reference}&tx_ref=${data.reference}&provider=paystack`
    },
  })
  handler.openIframe()
}

const formatNumber = (n) =>
  new Intl.NumberFormat('en-NG').format(n)
</script>

