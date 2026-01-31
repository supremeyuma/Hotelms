<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { 
  CreditCard, 
  Clock, 
  ShieldCheck, 
  Lock, 
  ChevronLeft,
  ArrowRight,
  Receipt
} from 'lucide-vue-next';

const props = defineProps({
  booking: Object,
  expires_at: String,
})

const paymentMethod = ref('offline')
const processing = ref(false)

const loadFlutterwave = () => {
  return new Promise((resolve, reject) => {
    if (window.FlutterwaveCheckout) return resolve(window.FlutterwaveCheckout)
    const script = document.createElement('script')
    script.src = 'https://checkout.flutterwave.com/v3.js'
    script.async = true
    script.onload = () => resolve(window.FlutterwaveCheckout)
    script.onerror = () => reject(new Error('Failed to load Flutterwave'))
    document.head.appendChild(script)
  })
}

const processPayment = async () => {
  processing.value = true
  try {
    if (paymentMethod.value === 'offline') {
      router.post(`/booking/payment/${props.booking.id}/confirm`)
      return
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    const initRes = await fetch('/payments/initialize', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf || '' },
      body: JSON.stringify({
        booking_id: props.booking.id,
        amount: props.booking.total_amount,
        tx_ref: `BK-${props.booking.id}`,
        description: `Booking #${props.booking.id}`,
        customer_email: props.booking.guest_email,
        customer_name: props.booking.guest_name,
      }),
    })

    if (!initRes.ok) throw new Error('Failed to initialize payment')
    const data = await initRes.json()
    await loadFlutterwave()

    window.FlutterwaveCheckout({
      public_key: data.public_key,
      tx_ref: data.tx_ref,
      amount: data.amount,
      currency: 'NGN',
      payment_options: 'card,bank,ussd',
      customer: data.customer,
      customizations: { title: 'MooreLife Resort', description: 'Room Booking' },
      callback: (resp) => {
        window.location.href = `/booking/payment/${props.booking.id}/callback?tx_ref=${resp.tx_ref}&status=${resp.status}`
      },
      onclose: () => { processing.value = false }
    })
  } catch (e) {
    alert(e.message || 'Payment processing failed')
    processing.value = false
  }
}

// Simple formatter for the expiration time
const formatExpiry = (timeStr) => {
  return new Date(timeStr).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
  <PublicLayout>
    <div class="min-h-[85vh] bg-slate-50/50 flex items-center justify-center px-4 py-12">
      <div class="w-full max-w-md">
        
        <button 
          @click="window.history.back()"
          class="flex items-center gap-2 text-slate-400 font-bold text-sm hover:text-indigo-600 transition-colors mb-8 group"
        >
          <ChevronLeft class="w-4 h-4 group-hover:-translate-x-1 transition-transform" />
          Review Details
        </button>

        <div class="bg-white rounded-[2.5rem] border border-slate-200 shadow-2xl shadow-slate-200/60 overflow-hidden relative">
          <div class="h-2 bg-gradient-to-r from-emerald-400 to-teal-500"></div>

          <div class="p-8 md:p-10">
            <div class="text-center mb-10">
              <div class="w-16 h-16 bg-slate-900 rounded-2xl flex items-center justify-center text-white mx-auto mb-4 shadow-xl">
                <CreditCard class="w-8 h-8" />
              </div>
              <h1 class="text-2xl font-black text-slate-900 tracking-tight">Finalize Payment</h1>
              <p class="text-slate-500 text-sm font-medium mt-1">Secure Transaction</p>
            </div>

            <div class="bg-slate-50 rounded-3xl p-6 border border-slate-100 mb-8">
              <div class="flex justify-between items-center mb-4">
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                  <Receipt class="w-3.5 h-3.5" /> Order Reference
                </span>
                <span class="font-bold text-slate-900">#{{ booking.id }}</span>
              </div>
              
              <div class="flex justify-between items-end">
                <div>
                  <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Amount Due</p>
                  <p class="text-3xl font-black text-slate-900">₦{{ booking.total_amount.toLocaleString() }}</p>
                </div>
                <div class="text-right">
                   <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-50 text-amber-700 rounded-xl text-[10px] font-black uppercase tracking-tighter border border-amber-100 animate-pulse">
                    <Clock class="w-3 h-3" /> Expires {{ formatExpiry(expires_at) }}
                  </div>
                </div>
              </div>
            </div>

            <div class="space-y-4">
              <div class="space-y-3">
                <label class="block text-sm font-semibold text-slate-700 mb-3">Payment Method</label>
                <div class="space-y-2">
                  <label class="flex items-center gap-3 p-3 border-2 rounded-xl cursor-pointer transition" :class="paymentMethod === 'offline' ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200'">
                    <input v-model="paymentMethod" type="radio" value="offline" class="w-4 h-4">
                    <span class="font-semibold text-slate-700">Pay at Checkout</span>
                    <span class="ml-auto text-xs text-slate-500">Cash or card on arrival</span>
                  </label>
                  <label class="flex items-center gap-3 p-3 border-2 rounded-xl cursor-pointer transition" :class="paymentMethod === 'flutterwave' ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200'">
                    <input v-model="paymentMethod" type="radio" value="flutterwave" class="w-4 h-4">
                    <span class="font-semibold text-slate-700">Pay Online Now</span>
                    <span class="ml-auto text-xs text-slate-500">Card, Bank, USSD</span>
                  </label>
                </div>
              </div>
              <button
                @click="processPayment"
                :disabled="processing"
                class="w-full group flex items-center justify-center gap-3 py-5 bg-emerald-600 text-white rounded-[2rem] font-black text-lg hover:bg-emerald-700 transition-all shadow-xl shadow-emerald-100 active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <span v-if="processing">Processing...</span>
                <span v-else>Proceed to Payment</span>
                <ArrowRight class="w-5 h-5 group-hover:translate-x-1 transition-transform" />
              </button>

              <div class="flex items-center justify-center gap-4 py-4">
                <div class="flex items-center gap-1.5 text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                  <ShieldCheck class="w-4 h-4 text-emerald-500" /> Protected
                </div>
                <div class="w-1 h-1 rounded-full bg-slate-200"></div>
                <div class="flex items-center gap-1.5 text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                  <Lock class="w-4 h-4 text-slate-400" /> SSL Encrypted
                </div>
              </div>
            </div>
          </div>

          <div class="bg-slate-900 p-4 text-center">
            <p class="text-[10px] font-black text-white/40 uppercase tracking-[0.2em]">
              Environment: <span class="text-amber-400">Sandbox / Demo Mode</span>
            </p>
          </div>
        </div>

        <p class="mt-8 text-center text-xs text-slate-400 font-medium leading-relaxed max-w-[280px] mx-auto">
          No real funds will be deducted. This is part of the reservation demonstration flow.
        </p>
      </div>
    </div>
  </PublicLayout>
</template>