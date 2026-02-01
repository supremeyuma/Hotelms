<template>
  <PublicLayout>
    <Head title="Reservation Success | MooreLife Resort" />

    <div class="min-h-screen bg-slate-50 py-8 md:py-12">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        
        <div class="mb-8 animate-in fade-in zoom-in duration-700">
          <div class="mx-auto flex items-center justify-center h-24 w-24 rounded-full bg-green-100 shadow-inner">
            <svg class="h-12 w-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <h1 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tighter mt-6">Reservation Confirmed!</h1>
          <p class="mt-3 text-slate-500 font-medium">Your spot at the event is secured. We've sent a confirmation to your email.</p>
        </div>

        <div v-if="reservation" class="bg-white rounded-[2.5rem] shadow-2xl shadow-slate-200/60 border border-slate-100 overflow-hidden mb-10 text-left">
          
          <div class="bg-indigo-950 p-6 md:p-8 relative overflow-hidden">
            <div class="absolute inset-0 opacity-10 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]"></div>
            <div class="relative z-10">
              <p class="text-indigo-400 text-[10px] font-black uppercase tracking-[0.2em] mb-1">Event Reference: #{{ reservation.id }}</p>
              <h2 class="text-2xl font-black text-white tracking-tight">{{ reservation.event.title }}</h2>
              <p class="text-indigo-100/60 text-xs font-bold mt-1">Confirmed for {{ reservation.guest_name }}</p>
            </div>
          </div>

          <div class="p-8 md:p-10">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-y-6 gap-x-12 mb-10">
              <div v-for="(val, label) in summaryDetails" :key="label">
                <p class="text-[10px] font-black uppercase text-slate-400 tracking-widest mb-1">{{ label }}</p>
                <p class="text-sm font-bold text-slate-900">{{ val }}</p>
              </div>
              
              <div>
                <p class="text-[10px] font-black uppercase text-slate-400 tracking-widest mb-1">Payment Status</p>
                <span :class="[
                  'inline-flex items-center px-2.5 py-0.5 rounded text-[10px] font-black uppercase tracking-wider',
                  reservation.payment_status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'
                ]">
                  {{ reservation.payment_status }}
                </span>
              </div>
            </div>

            <div v-if="reservation.qr_code" class="bg-slate-50 rounded-[2rem] p-8 border border-slate-100 text-center relative">
              <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-white px-4 py-1 rounded-full border border-slate-100 text-[10px] font-black text-slate-400 uppercase tracking-widest shadow-sm">
                Entry Pass
              </div>
              
              <div class="inline-block p-4 bg-white rounded-3xl shadow-xl border border-slate-200 mb-4">
                <div class="w-40 h-40 bg-slate-900 rounded-xl flex flex-col items-center justify-center text-white p-4">
                   <p class="text-[10px] font-black uppercase tracking-[0.2em] opacity-40 mb-2">QR SCAN</p>
                   <p class="text-xl font-black tracking-widest">{{ reservation.qr_code }}</p>
                </div>
              </div>
              <p class="text-xs font-bold text-slate-500 uppercase tracking-tight">Present this code at the venue entrance</p>
            </div>
          </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <Link :href="`/events/${reservation.event.id}`" 
                class="flex items-center justify-center px-8 py-4 bg-white border border-slate-200 text-slate-700 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-slate-50 transition-all shadow-sm">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            Back to Event
          </Link>
          
          <button @click="printReservation" 
                  class="flex items-center justify-center px-8 py-4 bg-indigo-600 text-white rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-200">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
            Print Confirmation
          </button>
        </div>

        <p class="mt-12 text-[10px] text-slate-400 font-black uppercase tracking-[0.3em]">MooreLife Resort &bull; Experience Luxury</p>
      </div>
    </div>
  </PublicLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'

const props = defineProps({
  reservation: Object,
})

const summaryDetails = computed(() => ({
  'Guest Email': props.reservation.guest_email,
  'Phone Number': props.reservation.guest_phone || 'Not provided',
  'Table / Tier': props.reservation.table_number || 'Standard Tier',
  'Guests Included': props.reservation.number_of_guests,
  'Amount Paid': props.reservation.formatted_amount,
  'Booking Date': props.reservation.formatted_reservation_date
}))

const printReservation = () => {
  window.print()
}
</script>

<style scoped>
@media print {
  .bg-slate-50 { background-color: white !important; }
  button, a, .mx-auto.flex { display: none !important; }
  .max-w-3xl { max-width: 100% !important; margin: 0 !important; }
  .shadow-2xl, .shadow-xl { shadow: none !important; }
}

.animate-in {
  animation-duration: 0.7s;
  animation-fill-mode: both;
}
@keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }
@keyframes zoom-in { from { transform: scale(0.95); } to { transform: scale(1); } }
.fade-in { animation-name: fade-in; }
.zoom-in { animation-name: zoom-in; }
</style>