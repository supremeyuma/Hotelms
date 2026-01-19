<script setup>
import { defineProps } from 'vue';
import { useForm } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { 
  CreditCard, 
  Calendar, 
  Users, 
  BedDouble, 
  Moon, 
  Wallet, 
  CheckCircle2,
  ArrowRight,
  ShieldCheck
} from 'lucide-vue-next';

const props = defineProps({
  booking: Object,
  room_type: Object,
  nights: Number,
  total_price: Number,
});

const form = useForm({});

const formatDate = (dateStr) => {
  return new Date(dateStr).toLocaleDateString('en-US', { 
    weekday: 'short',
    month: 'short', 
    day: 'numeric' 
  });
};

function proceed() {
  form.post(route('booking.create'), {
    preserveState: true,
  });
}
</script>

<template>
  <PublicLayout>
    <div class="min-h-screen bg-slate-50/50 py-12 px-4">
      <div class="max-w-3xl mx-auto">
        
        <div class="flex items-center justify-center gap-4 mb-10">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center">
              <CheckCircle2 class="w-5 h-5" />
            </div>
            <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Select Room</span>
          </div>
          <div class="w-12 h-px bg-emerald-200"></div>
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center">
              <CheckCircle2 class="w-5 h-5" />
            </div>
            <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Guest Info</span>
          </div>
          <div class="w-12 h-px bg-emerald-200"></div>
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center shadow-lg shadow-indigo-100 font-black text-xs">
              3
            </div>
            <span class="text-xs font-bold text-slate-900 uppercase tracking-widest">Review</span>
          </div>
        </div>

        <div class="text-center mb-10">
          <h1 class="text-3xl font-black text-slate-900 tracking-tight mb-3">Review & Confirm</h1>
          <p class="text-slate-500 font-medium">Double check your details before we finalize your stay.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
          <div class="lg:col-span-3 space-y-6">
            <div class="bg-white rounded-[2.5rem] p-8 border border-slate-200 shadow-sm">
              <div class="flex items-start justify-between mb-8">
                <div>
                  <p class="text-[10px] font-black text-indigo-600 uppercase tracking-[0.2em] mb-1">Accommodation</p>
                  <h2 class="text-2xl font-black text-slate-900">{{ room_type.title }}</h2>
                </div>
                <div class="w-12 h-12 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-400">
                  <BedDouble class="w-6 h-6" />
                </div>
              </div>

              <div class="grid grid-cols-2 gap-8 mb-8">
                <div class="space-y-1">
                  <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Check-in</p>
                  <p class="font-bold text-slate-700 flex items-center gap-2">
                    <Calendar class="w-4 h-4 text-indigo-500" /> {{ formatDate(booking.check_in) }}
                  </p>
                </div>
                <div class="space-y-1">
                  <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Check-out</p>
                  <p class="font-bold text-slate-700 flex items-center gap-2">
                    <Calendar class="w-4 h-4 text-indigo-500" /> {{ formatDate(booking.check_out) }}
                  </p>
                </div>
                <div class="space-y-1">
                  <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Guests</p>
                  <p class="font-bold text-slate-700 flex items-center gap-2">
                    <Users class="w-4 h-4 text-indigo-500" /> {{ booking.adults }} Adult<span v-if="booking.adults > 1">s</span>, {{ booking.children }} Child
                  </p>
                </div>
                <div class="space-y-1">
                  <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Duration</p>
                  <p class="font-bold text-slate-700 flex items-center gap-2">
                    <Moon class="w-4 h-4 text-indigo-500" /> {{ nights }} Night<span v-if="nights > 1">s</span>
                  </p>
                </div>
              </div>

              <div class="pt-6 border-t border-slate-50">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Guest Contact</p>
                <div class="flex items-center justify-between">
                  <p class="font-black text-slate-900 text-lg">{{ booking.guest_name }}</p>
                  <button @click="router.visit('/booking/guest')" class="text-xs font-bold text-indigo-600 hover:underline">Edit</button>
                </div>
                <p class="text-sm text-slate-500 font-medium">{{ booking.guest_email }}</p>
              </div>
            </div>
          </div>

          <div class="lg:col-span-2 space-y-6">
            <div class="bg-slate-900 rounded-[2.5rem] p-8 text-white shadow-xl shadow-slate-200 sticky top-24">
              <h3 class="text-sm font-black uppercase tracking-[0.2em] opacity-50 mb-6 flex items-center gap-2">
                <Wallet class="w-4 h-4" /> Price Breakdown
              </h3>
              
              <div class="space-y-4 mb-8">
                <div class="flex justify-between text-sm">
                  <span class="opacity-60">{{ room_type.title }} ({{ booking.quantity }}x)</span>
                  <span class="font-bold">₦{{ (room_type.base_price * booking.quantity).toLocaleString() }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="opacity-60">Nights</span>
                  <span class="font-bold">{{ nights }}</span>
                </div>
                <div class="w-full h-px bg-white/10 my-4"></div>
                <div class="flex justify-between items-end">
                  <span class="text-xs font-black uppercase tracking-widest opacity-60">Grand Total</span>
                  <span class="text-3xl font-black">₦{{ total_price.toLocaleString() }}</span>
                </div>
              </div>

              <button
                @click="proceed"
                :disabled="form.processing"
                class="w-full group flex items-center justify-center gap-3 py-5 bg-indigo-600 text-white rounded-3xl font-black text-lg hover:bg-indigo-500 transition-all active:scale-[0.98] disabled:opacity-50"
              >
                <CreditCard v-if="!form.processing" class="w-5 h-5" />
                <RefreshCw v-else class="w-5 h-5 animate-spin" />
                {{ form.processing ? 'Processing...' : 'Complete Booking' }}
              </button>

              <div class="mt-6 flex items-center gap-2 justify-center text-[10px] font-black uppercase tracking-widest opacity-40">
                <ShieldCheck class="w-4 h-4" />
                Secure Checkout
              </div>
            </div>

            <p class="px-6 text-[11px] text-slate-400 font-medium text-center leading-relaxed italic">
              By clicking "Complete Booking", you agree to our Terms of Service and Cancellation Policy.
            </p>
          </div>
        </div>
      </div>
    </div>
  </PublicLayout>
</template>

<style scoped>
/* No specific styles needed as Tailwind handles the high-end look */
</style>