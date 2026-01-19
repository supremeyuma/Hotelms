<script setup>
import { defineProps } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { router } from '@inertiajs/vue3';
import { 
  CheckCircle2, 
  Calendar, 
  MapPin, 
  Printer, 
  Share2, 
  Home, 
  Mail, 
  Phone 
} from 'lucide-vue-next';

const props = defineProps({
  booking: Object,
});

// Helper to safely format currency strings
const formatCurrency = (value) => {
  const num = parseFloat(value);
  return isNaN(num) ? value : num.toLocaleString('en-NG');
};

function formatDateTime(dateString) {
  if (!dateString) return 'TBD';
  const d = new Date(dateString);
  const date = d.toLocaleDateString('en-NG', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
  return `${date} · 12:00 PM`;
}

function printPage() {
  window.print();
}
</script>

<template>
  <PublicLayout>
    <div class="min-h-screen bg-slate-50/50 py-12 md:py-20 px-4">
      <div class="max-w-2xl mx-auto">
        
        <div class="text-center mb-12">
          <div class="inline-flex items-center justify-center w-24 h-24 bg-emerald-100 text-emerald-600 rounded-[2.5rem] mb-6 shadow-xl shadow-emerald-100">
            <CheckCircle2 class="w-12 h-12" />
          </div>
          <h1 class="text-4xl font-black text-slate-900 tracking-tight mb-2">Booking Confirmed!</h1>
          <p class="text-slate-500 font-medium">We're looking forward to your stay, {{ booking.guest_name }}.</p>
        </div>

        <div class="bg-white rounded-[3rem] border border-slate-200 shadow-2xl shadow-slate-200/50 overflow-hidden mb-8">
          <div class="bg-slate-900 px-8 py-6 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="text-center md:text-left">
              <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Reservation Code</p>
              <h2 class="text-2xl font-black text-white tracking-widest uppercase">{{ booking.booking_code }}</h2>
            </div>
            <div class="flex gap-2">
              <button @click="printPage" class="p-3 bg-white/10 hover:bg-white/20 rounded-2xl text-white transition-colors">
                <Printer class="w-5 h-5" />
              </button>
            </div>
          </div>

          <div class="p-8 md:p-12 space-y-10">
            <div class="flex items-start gap-6">
              <div class="w-16 h-16 bg-indigo-50 rounded-2xl flex items-center justify-center text-indigo-600 shrink-0">
                <Home class="w-8 h-8" />
              </div>
              <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Accommodation</p>
                <h3 class="text-xl font-black text-slate-900">
                  {{ booking.quantity }} × {{ booking.room_type?.title || 'Standard Room' }}
                </h3>
                <p class="text-slate-500 text-sm font-medium mt-1 italic">
                  Total Paid: ₦{{ formatCurrency(booking.total_amount) }}
                </p>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
              <div class="space-y-1">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                  <Calendar class="w-3 h-3 text-indigo-500" /> Check-in
                </p>
                <p class="font-bold text-slate-700">{{ formatDateTime(booking.check_in) }}</p>
              </div>
              <div class="space-y-1">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                  <Calendar class="w-3 h-3 text-indigo-500" /> Check-out
                </p>
                <p class="font-bold text-slate-700">{{ formatDateTime(booking.check_out) }}</p>
              </div>
            </div>

            <div class="bg-slate-50 rounded-3xl p-6 flex items-center gap-4 border border-slate-100">
              <div class="flex -space-x-2">
                <div class="w-10 h-10 rounded-full bg-white border-2 border-slate-50 flex items-center justify-center text-indigo-600 shadow-sm">
                  <Mail class="w-4 h-4" />
                </div>
              </div>
              <p class="text-xs font-bold text-slate-600 leading-relaxed">
                A confirmation has been sent to <span class="text-slate-900">{{ booking.guest_email }}</span>.
              </p>
            </div>
          </div>
        </div>

        <div class="flex flex-col sm:flex-row items-center gap-4">
          <button 
            @click="router.visit('/')"
            class="w-full py-5 bg-slate-900 text-white rounded-[2rem] font-black text-sm uppercase tracking-widest hover:bg-indigo-600 transition-all shadow-xl shadow-slate-200"
          >
            Return Home
          </button>
        </div>

        <div class="mt-12 text-center text-slate-400">
          <div class="flex items-center justify-center gap-2 text-xs font-bold uppercase tracking-widest mb-2">
            <MapPin class="w-4 h-4" /> Victoria Island, Lagos
          </div>
        </div>
      </div>
    </div>
  </PublicLayout>
</template>