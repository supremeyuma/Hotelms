<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { router } from '@inertiajs/vue3';
import {
  CheckCircle2,
  Calendar,
  MapPin,
  Printer,
  Home,
  Mail,
  KeyRound,
  BedDouble,
} from 'lucide-vue-next';

const props = defineProps({
  booking: Object,
  pre_check_in_url: String,
});

const formatCurrency = (value) => {
  const num = parseFloat(value);
  return Number.isNaN(num) ? value : new Intl.NumberFormat('en-NG').format(num);
};

function formatDate(dateString) {
  if (!dateString) return 'TBD';
  return new Date(dateString).toLocaleDateString('en-NG', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

function printPage() {
  window.print();
}

function primaryImage(room) {
  return room.images?.[0]?.url || null;
}
</script>

<template>
  <PublicLayout>
    <div class="min-h-screen bg-slate-50/50 py-12 md:py-20 px-4">
      <div class="max-w-3xl mx-auto">
        <div class="text-center mb-12">
          <div class="inline-flex items-center justify-center w-24 h-24 bg-emerald-100 text-emerald-600 rounded-[2.5rem] mb-6 shadow-xl shadow-emerald-100">
            <CheckCircle2 class="w-12 h-12" />
          </div>
          <h1 class="text-4xl font-black text-slate-900 tracking-tight mb-2">Booking Confirmed</h1>
          <p class="text-slate-500 font-medium">We're looking forward to your stay, {{ booking.guest_name }}.</p>
        </div>

        <div class="bg-white rounded-[3rem] border border-slate-200 shadow-2xl shadow-slate-200/50 overflow-hidden mb-8">
          <div class="bg-slate-900 px-8 py-6 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="text-center md:text-left">
              <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Reservation Code</p>
              <h2 class="text-2xl font-black text-white tracking-widest uppercase">{{ booking.booking_code }}</h2>
            </div>
            <button @click="printPage" class="p-3 bg-white/10 hover:bg-white/20 rounded-2xl text-white transition-colors">
              <Printer class="w-5 h-5" />
            </button>
          </div>

          <div class="p-8 md:p-12 space-y-10">
            <div class="flex items-start gap-6">
              <div class="w-16 h-16 bg-indigo-50 rounded-2xl flex items-center justify-center text-indigo-600 shrink-0">
                <Home class="w-8 h-8" />
              </div>
              <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Accommodation</p>
                <h3 class="text-xl font-black text-slate-900">
                  {{ booking.quantity }} x {{ booking.room_type?.title || 'Standard Room' }}
                </h3>
                <p class="text-slate-500 text-sm font-medium mt-1">
                  Payment status: <span class="text-slate-900 uppercase">{{ booking.payment_status || 'pending' }}</span>
                </p>
                <p class="text-slate-500 text-sm font-medium mt-1">
                  Total booking amount: ₦{{ formatCurrency(booking.total_amount) }}
                </p>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
              <div class="space-y-1">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                  <Calendar class="w-3 h-3 text-indigo-500" /> Check-in
                </p>
                <p class="font-bold text-slate-700">{{ formatDate(booking.check_in) }}</p>
              </div>
              <div class="space-y-1">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                  <Calendar class="w-3 h-3 text-indigo-500" /> Check-out
                </p>
                <p class="font-bold text-slate-700">{{ formatDate(booking.check_out) }}</p>
              </div>
            </div>

            <div class="rounded-[2rem] border border-slate-100 bg-slate-50 p-6">
              <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4">Reserved Rooms</p>
              <div class="space-y-4">
                <div
                  v-for="room in booking.rooms"
                  :key="room.id"
                  class="flex items-center gap-4 rounded-2xl bg-white px-4 py-3 border border-slate-100"
                >
                  <img
                    v-if="primaryImage(room)"
                    :src="primaryImage(room)"
                    :alt="room.name"
                    class="h-16 w-16 rounded-2xl object-cover"
                  />
                  <div v-else class="flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-300">
                    <BedDouble class="w-6 h-6" />
                  </div>
                  <div>
                    <p class="font-black text-slate-900">{{ room.display_name || room.name || `Room ${room.id}` }}</p>
                    <p class="text-xs font-bold uppercase tracking-widest text-slate-400">
                      <span v-if="room.floor">Floor {{ room.floor }}</span>
                      <span v-else>Reserved room</span>
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <div class="bg-slate-50 rounded-3xl p-6 flex items-center gap-4 border border-slate-100">
              <div class="w-10 h-10 rounded-full bg-white border-2 border-slate-50 flex items-center justify-center text-indigo-600 shadow-sm">
                <Mail class="w-4 h-4" />
              </div>
              <p class="text-xs font-bold text-slate-600 leading-relaxed">
                A confirmation has been sent to <span class="text-slate-900">{{ booking.guest_email }}</span>.
              </p>
            </div>

            <div v-if="pre_check_in_url" class="rounded-[2rem] border border-emerald-100 bg-emerald-50 p-6 flex items-start gap-4">
              <div class="mt-1 flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-emerald-600 shadow-sm">
                <KeyRound class="w-5 h-5" />
              </div>
              <div class="flex-1">
                <p class="text-[10px] font-black text-emerald-700 uppercase tracking-widest mb-2">Online Pre-Check-In</p>
                <p class="text-sm font-medium text-emerald-900 leading-relaxed">
                  Because this booking was paid online, you can complete pre-check-in before arrival. Room access will still be issued by the front desk after arrival verification.
                </p>
                <button
                  @click="router.visit(pre_check_in_url)"
                  class="mt-4 inline-flex items-center rounded-2xl bg-emerald-600 px-5 py-3 text-xs font-black uppercase tracking-widest text-white transition hover:bg-emerald-700"
                >
                  Start Pre-Check-In
                </button>
              </div>
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
