<script setup>
import { useForm, router } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import {
  CreditCard,
  Calendar,
  Users,
  BedDouble,
  Moon,
  Wallet,
  ChevronLeft,
  CheckCircle2,
  ShieldCheck,
  RefreshCw,
} from 'lucide-vue-next';

const props = defineProps({
  booking: Object,
  room_type: Object,
  selected_rooms: Array,
  nights: Number,
  total_price: Number,
  payment_booking_id: {
    type: Number,
    default: null,
  },
  discount_preview: Object,
  image_settings: {
    type: Object,
    default: () => ({
      show_room_images: true,
      show_room_type_images: true,
    }),
  },
});

const form = useForm({});
const discountForm = useForm({
  discount_code: props.booking.discount_code ?? '',
});

const formatDate = (dateStr) =>
  new Date(dateStr).toLocaleDateString('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
  });

const formatCurrency = (amount) => new Intl.NumberFormat('en-NG').format(amount || 0);

const isExistingPendingBooking = () => Boolean(props.payment_booking_id);

function proceed() {
  if (isExistingPendingBooking()) {
    router.visit(route('booking.payment', props.payment_booking_id));
    return;
  }

  form.post(route('booking.create'), {
    preserveState: true,
  });
}

function applyDiscount() {
  discountForm.post(route('booking.discount.apply'), {
    preserveScroll: true,
  });
}

function removeDiscount() {
  router.delete(route('booking.discount.remove'), {
    preserveScroll: true,
  });
}
</script>

<template>
  <PublicLayout header-mode="static">
    <div class="min-h-screen bg-slate-50/50 py-12 px-4">
      <div class="max-w-4xl mx-auto">
        <button
          type="button"
          @click="router.visit(route('booking.guest'))"
          class="mb-8 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-600 transition hover:border-indigo-200 hover:text-indigo-600"
        >
          <ChevronLeft class="w-4 h-4" />
          Back to Guest Details
        </button>

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
          <p class="text-slate-500 font-medium">Your selected room is reserved for this booking while you complete payment.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
          <div class="lg:col-span-3 space-y-6">
            <div class="bg-white rounded-[2.5rem] p-8 border border-slate-200 shadow-sm">
              <div class="flex items-start justify-between mb-8">
                <div>
                  <p class="text-[10px] font-black text-indigo-600 uppercase tracking-[0.2em] mb-1">Accommodation</p>
                  <h2 class="text-2xl font-black text-slate-900">{{ room_type.title }}</h2>
                  <p class="mt-2 text-sm font-medium text-slate-500">
                    {{ selected_rooms.length }} selected room<span v-if="selected_rooms.length !== 1">s</span>
                  </p>
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
                    <Users class="w-4 h-4 text-indigo-500" /> {{ booking.adults }} Adult<span v-if="booking.adults > 1">s</span>, {{ booking.children }} Child<span v-if="booking.children !== 1">ren</span>
                  </p>
                </div>
                <div class="space-y-1">
                  <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Duration</p>
                  <p class="font-bold text-slate-700 flex items-center gap-2">
                    <Moon class="w-4 h-4 text-indigo-500" /> {{ nights }} Night<span v-if="nights > 1">s</span>
                  </p>
                </div>
              </div>

              <div class="mb-8 rounded-3xl bg-slate-50 p-5 border border-slate-100">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Selected Rooms</p>
                <div class="space-y-3">
                  <div
                    v-for="room in selected_rooms"
                    :key="room.id"
                    class="flex items-center gap-4 rounded-2xl bg-white px-4 py-3 border border-slate-100"
                  >
                    <img
                      v-if="image_settings.show_room_images && room.primary_image_url"
                      :src="room.primary_image_url"
                      :alt="room.name"
                      class="h-14 w-14 rounded-2xl object-cover"
                    />
                    <div v-else class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-300">
                      <BedDouble class="w-6 h-6" />
                    </div>
                    <div>
                      <p class="font-black text-slate-900">{{ room.name }}</p>
                      <p class="text-xs font-bold uppercase tracking-widest text-slate-400">
                        <span v-if="room.floor">Floor {{ room.floor }}</span>
                        <span v-else>Reserved room</span>
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <div class="pt-6 border-t border-slate-50">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Guest Contact</p>
                <div class="flex items-center justify-between">
                  <p class="font-black text-slate-900 text-lg">{{ booking.guest_name }}</p>
                  <button
                    v-if="!isExistingPendingBooking()"
                    @click="router.visit('/booking/guest')"
                    class="text-xs font-bold text-indigo-600 hover:underline"
                  >
                    Edit
                  </button>
                  <span
                    v-else
                    class="text-xs font-bold uppercase tracking-widest text-slate-400"
                  >
                    Locked for payment
                  </span>
                </div>
                <p class="text-sm text-slate-500 font-medium">{{ booking.guest_email }}</p>
              </div>

              <div class="mt-8 rounded-3xl border border-slate-200 bg-slate-50 p-5">
                <div class="flex items-center justify-between gap-3">
                  <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Discount code</p>
                    <p class="mt-2 text-sm font-medium text-slate-500">Guests can apply a room discount before payment.</p>
                  </div>
                  <span
                    v-if="discount_preview"
                    class="rounded-full bg-emerald-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700"
                  >
                    Applied
                  </span>
                </div>

                <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                  <input
                    v-model="discountForm.discount_code"
                    type="text"
                    class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm uppercase text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
                    placeholder="Enter code"
                  />
                  <button
                    type="button"
                    @click="applyDiscount"
                    :disabled="discountForm.processing"
                    class="rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800 disabled:opacity-50"
                  >
                    {{ discountForm.processing ? 'Applying...' : 'Apply code' }}
                  </button>
                  <button
                    v-if="discount_preview"
                    type="button"
                    @click="removeDiscount"
                    class="rounded-2xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-white"
                  >
                    Remove
                  </button>
                </div>
                <p v-if="discountForm.errors.discount_code" class="mt-2 text-sm font-medium text-rose-600">
                  {{ discountForm.errors.discount_code }}
                </p>

                <div v-if="discount_preview" class="mt-4 rounded-2xl bg-white p-4 border border-emerald-100">
                  <p class="text-sm font-black text-slate-900">{{ discount_preview.name }} ({{ discount_preview.code }})</p>
                  <p class="mt-1 text-sm text-slate-500">
                    {{ discount_preview.scope_label }} discount saving ₦{{ formatCurrency(discount_preview.discount_amount) }}
                  </p>
                </div>
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
                  <span class="font-bold">₦{{ formatCurrency(room_type.base_price * booking.quantity) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="opacity-60">Nights</span>
                  <span class="font-bold">{{ nights }}</span>
                </div>
                <div v-if="discount_preview" class="flex justify-between text-sm text-emerald-300">
                  <span>{{ discount_preview.code }}</span>
                  <span class="font-bold">-â‚¦{{ formatCurrency(discount_preview.discount_amount) }}</span>
                </div>
                <div class="w-full h-px bg-white/10 my-4"></div>
                <div class="flex justify-between items-end">
                  <span class="text-xs font-black uppercase tracking-widest opacity-60">Grand Total</span>
                  <span class="text-3xl font-black">₦{{ formatCurrency(total_price) }}</span>
                </div>
              </div>

              <button
                @click="proceed"
                :disabled="form.processing"
                class="w-full group flex items-center justify-center gap-3 py-5 bg-indigo-600 text-white rounded-3xl font-black text-lg hover:bg-indigo-500 transition-all active:scale-[0.98] disabled:opacity-50"
              >
                <CreditCard v-if="!form.processing" class="w-5 h-5" />
                <RefreshCw v-else class="w-5 h-5 animate-spin" />
                {{ form.processing ? 'Processing...' : (isExistingPendingBooking() ? 'Return to Payment' : 'Complete Booking') }}
              </button>

              <div class="mt-6 flex items-center gap-2 justify-center text-[10px] font-black uppercase tracking-widest opacity-40">
                <ShieldCheck class="w-4 h-4" />
                Secure Checkout
              </div>
            </div>

            <p class="px-6 text-[11px] text-slate-400 font-medium text-center leading-relaxed italic">
              After online payment, guests can complete pre-check-in before arrival. Final room access is still issued by the front desk.
            </p>
          </div>
        </div>
      </div>
    </div>
  </PublicLayout>
</template>
