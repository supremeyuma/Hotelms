<template>
  <PublicLayout>
    <Head title="Payment | MooreLife Resort" />

    <div class="min-h-screen bg-slate-50 py-8 md:py-12">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="bg-indigo-950 rounded-t-3xl overflow-hidden relative">
          <div class="absolute inset-0 opacity-10 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]"></div>
          <div class="relative z-10 p-6 md:p-8 flex flex-col md:flex-row gap-6 items-center">
            <div class="w-16 h-16 md:w-20 md:h-20 shrink-0 rounded-xl bg-white/10 flex items-center justify-center shadow-xl border border-white/10">
              <svg class="w-10 h-10 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 002 2z" />
              </svg>
            </div>
            <div class="text-center md:text-left">
              <p class="text-indigo-400 text-[10px] font-black uppercase tracking-[0.2em] mb-1">Secure Checkout</p>
              <h1 class="text-2xl md:text-3xl font-black text-white tracking-tighter">Complete Payment</h1>
              <div class="flex flex-wrap justify-center md:justify-start gap-4 text-indigo-100/60 text-xs font-bold mt-2">
                <span class="flex items-center capitalize">
                  <svg class="w-3.5 h-3.5 mr-1.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                  {{ type }} Reservation
                </span>
                <span v-if="meta.event" class="flex items-center">
                  <svg class="w-3.5 h-3.5 mr-1.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                  {{ meta.event }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-b-3xl shadow-2xl shadow-slate-200/60 border-x border-b border-slate-100 overflow-hidden">
          <div class="p-8 md:p-12">
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 mb-12">
              
              <div>
                <h3 class="text-sm font-black text-indigo-600 uppercase tracking-widest mb-6 flex items-center">
                  <span class="w-6 h-6 rounded bg-indigo-50 flex items-center justify-center mr-3 text-[10px]">01</span>
                  Booking Review
                </h3>
                
                <div class="space-y-4">
                  <div v-for="(val, label) in getSummaryItems" :key="label" class="flex justify-between items-center p-4 bg-slate-50 rounded-2xl border border-slate-100">
                    <span class="text-[10px] font-black uppercase text-slate-400 tracking-wider">{{ label }}</span>
                    <span class="text-sm font-bold text-slate-900">{{ val }}</span>
                  </div>
                </div>
              </div>

              <div>
                <h3 class="text-sm font-black text-indigo-600 uppercase tracking-widest mb-6 flex items-center">
                  <span class="w-6 h-6 rounded bg-indigo-50 flex items-center justify-center mr-3 text-[10px]">02</span>
                  Payment Gateway
                </h3>

                <div class="bg-slate-900 rounded-3xl p-8 text-white relative overflow-hidden mb-6">
                  <div class="relative z-10 text-center">
                    <p class="text-xs font-bold text-white/40 uppercase tracking-[0.2em] mb-2">Amount Payable</p>
                    <p class="text-5xl font-black tracking-tighter mb-2">₦{{ formatNumber(amount) }}</p>
                    <div class="inline-flex items-center px-3 py-1 rounded-full bg-white/10 text-[10px] font-bold uppercase tracking-wider text-indigo-300">
                      Transaction Ref: {{ reference }}
                    </div>
                  </div>
                </div>

                <div v-if="paymentData?.show_provider_options" class="space-y-3 mb-6">
                  <button
                    v-for="prov in paymentData.available_providers"
                    :key="prov.value"
                    @click.prevent="selectedProvider = prov.value"
                    :class="[
                      'w-full flex items-center justify-between px-6 py-4 rounded-2xl border-2 transition-all duration-300',
                      selectedProvider === prov.value 
                        ? 'border-indigo-600 bg-indigo-50/50 ring-4 ring-indigo-500/10 text-indigo-900' 
                        : 'border-slate-100 bg-slate-50 text-slate-600 hover:border-slate-300'
                    ]"
                  >
                    <span class="font-bold text-sm uppercase tracking-wider">{{ prov.label }}</span>
                    <div v-if="selectedProvider === prov.value" class="w-5 h-5 bg-indigo-600 rounded-full flex items-center justify-center">
                      <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/></svg>
                    </div>
                  </button>
                </div>

                <div v-if="selectedProvider" class="mb-8 flex items-start gap-3 p-4 bg-indigo-50 rounded-2xl border border-indigo-100 text-xs font-bold text-indigo-700">
                  <svg class="w-5 h-5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                  <span>Redirecting you to {{ selectedProvider }} secure terminal. Do not close this page after clicking pay.</span>
                </div>

                <button
                  @click.prevent="processPayment"
                  :disabled="processing || !selectedProvider"
                  class="group relative w-full flex items-center justify-center px-8 py-6 bg-indigo-600 text-white rounded-2xl font-black text-sm uppercase tracking-[0.2em] transition-all hover:bg-indigo-700 disabled:opacity-50"
                >
                  <span v-if="!processing" class="flex items-center">
                    Pay Now
                    <svg class="w-5 h-5 ml-3 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                  </span>
                  <span v-else class="flex items-center">
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Authorizing...
                  </span>
                </button>
              </div>
            </div>

            <div class="pt-8 border-t border-slate-100 text-center">
              <p class="text-[10px] text-slate-400 font-black uppercase tracking-[0.3em]">MooreLife Resort &bull; 100% Secure Transaction</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </PublicLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
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

const getSummaryItems = computed(() => {
  const items = {
    'Customer': props.customer.name,
    'Email': props.customer.email,
  }
  
  if (props.type === 'ticket') {
    items['Category'] = props.meta.ticketType
    items['Quantity'] = props.meta.quantity
  } else {
    items['Table Tier'] = props.meta.table
    items['Phone'] = props.customer.phone
  }
  
  return items
})

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
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    const res = await fetch('/payments/initialize-by-reference', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({ reference: props.reference }),
    })

    if (!res.ok) throw new Error('Failed to load payment data')

    paymentData.value = await res.json()
    
    if (props.type === 'ticket' || props.type === 'table') {
      paymentData.value.available_providers = paymentData.value.available_providers.filter(
        p => p.value !== 'manual' && p.value !== 'venue'
      )
    }
    
    selectedProvider.value = paymentData.value.provider

    const promises = []
    if (paymentData.value.available_providers.some(p => p.value === 'flutterwave')) {
      promises.push(loadFlutterwave().catch(() => console.warn('Flutterwave load failed')))
    }
    if (paymentData.value.available_providers.some(p => p.value === 'paystack')) {
      promises.push(loadPaystack().catch(() => console.warn('Paystack load failed')))
    }

    await Promise.all(promises)
  } catch (e) {
    console.error('Payment initialization error:', e)
  }
})

const processPayment = async () => {
  if (!selectedProvider.value) return
  if (selectedProvider.value === 'flutterwave' && !flutterwaveReady.value) return
  if (selectedProvider.value === 'paystack' && !paystackReady.value) return

  processing.value = true
  try {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
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
      const callbackUrl = buildCallbackUrl(
        data.callback_url || `/events/payment/callback`,
        {
          transaction_id: res.transaction_id,
          tx_ref: res.tx_ref,
          status: res.status,
          provider: 'flutterwave',
        }
      )
      if (callbackUrl) {
        window.location.href = callbackUrl
      }
    },
    onclose: () => {
      processing.value = false
    },
  })
}

const handlePaystack = (data) => {
  const handler = window.PaystackPop.setup({
    key: data.public_key,
    email: data.customer.email,
    amount: parseFloat(data.amount) * 100,
    currency: data.currency || 'NGN',
    ref: data.reference,
    onClose: () => {
      processing.value = false
    },
    callback: (response) => {
      const callbackUrl = buildCallbackUrl(
        data.callback_url || `/events/payment/callback`,
        {
          transaction_id: response.reference,
          tx_ref: data.reference,
          provider: 'paystack',
          status: 'successful',
        }
      )
      if (callbackUrl) {
        window.location.href = callbackUrl
      }
    },
  })
  handler.openIframe()
}

const formatNumber = (n) => new Intl.NumberFormat('en-NG').format(n)

const buildCallbackUrl = (baseUrl, params) => {
  if (!baseUrl) return null
  const hasQuery = baseUrl.includes('?')
  const query = new URLSearchParams(params).toString()
  return `${baseUrl}${hasQuery ? '&' : '?'}${query}`
}
</script>
