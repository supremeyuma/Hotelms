<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { defineProps, reactive } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { 
  BedDouble, 
  Users, 
  Baby, 
  ChevronRight, 
  Info, 
  CheckCircle2,
  Calendar
} from 'lucide-vue-next';

const props = defineProps({
  roomTypes: Array,
  check_in: String,
  check_out: String,
  adults: Number,
  children: Number,
});

// Use reactive for local quantity tracking
const quantities = reactive({});
props.roomTypes.forEach(r => (quantities[r.id] = 1));

function selectRoom(roomId) {
  router.post('/booking/select-room', {
    room_type_id: roomId,
    quantity: quantities[roomId],
    check_in: props.check_in,
    check_out: props.check_out,
    adults: props.adults,
    children: props.children,
  }, {
    preserveScroll: true
  });
}

const formatDate = (dateStr) => {
  return new Date(dateStr).toLocaleDateString('en-US', { 
    month: 'short', 
    day: 'numeric', 
    year: 'numeric' 
  });
};
</script>

<template>
  <GuestLayout>
    <div class="min-h-screen bg-slate-50/50 pb-20">
      <div class="bg-white border-b border-slate-200 sticky top-0 z-10 shadow-sm">
        <div class="max-w-5xl mx-auto px-4 py-4 flex flex-wrap items-center justify-between gap-4">
          <div class="flex items-center gap-6">
            <div class="hidden md:flex items-center gap-2 text-slate-400">
              <Calendar class="w-5 h-5" />
              <span class="text-sm font-bold uppercase tracking-wider">Your Stay:</span>
            </div>
            <div class="flex items-center gap-3">
              <div class="text-sm">
                <p class="text-[10px] font-black text-indigo-600 uppercase tracking-widest">Check-in</p>
                <p class="font-bold text-slate-900">{{ formatDate(check_in) }}</p>
              </div>
              <ChevronRight class="w-4 h-4 text-slate-300" />
              <div class="text-sm">
                <p class="text-[10px] font-black text-indigo-600 uppercase tracking-widest">Check-out</p>
                <p class="font-bold text-slate-900">{{ formatDate(check_out) }}</p>
              </div>
            </div>
          </div>
          <div class="flex items-center gap-4 text-sm font-bold text-slate-600 bg-slate-100 px-4 py-2 rounded-2xl">
            <span class="flex items-center gap-1.5"><Users class="w-4 h-4" /> {{ adults }}</span>
            <span v-if="children > 0" class="flex items-center gap-1.5"><Baby class="w-4 h-4" /> {{ children }}</span>
          </div>
        </div>
      </div>

      <div class="max-w-5xl mx-auto px-4 pt-12">
        <div class="mb-10">
          <h1 class="text-4xl font-black text-slate-900 tracking-tight mb-2">Select Your Room</h1>
          <p class="text-slate-500 font-medium">Choose from our curated selection of luxury accommodations.</p>
        </div>

        <div class="space-y-6">
          <div 
            v-for="room in roomTypes" 
            :key="room.id" 
            class="group bg-white rounded-[2.5rem] border border-slate-200 overflow-hidden hover:shadow-2xl hover:border-indigo-200 transition-all duration-500 flex flex-col lg:flex-row"
          >
            <div class="lg:w-80 bg-slate-100 relative shrink-0 overflow-hidden">
              <div class="absolute inset-0 flex items-center justify-center text-slate-300">
                <BedDouble class="w-16 h-16 opacity-20 group-hover:scale-110 transition-transform duration-700" />
              </div>
              <div 
                v-if="room.available_quantity > 0"
                class="absolute top-6 left-6 px-4 py-1.5 bg-white/90 backdrop-blur rounded-full shadow-sm border border-slate-100"
              >
                <p class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">
                  {{ room.available_quantity }} Available
                </p>
              </div>
            </div>

            <div class="p-8 lg:p-10 flex-1 flex flex-col">
              <div class="flex flex-col md:flex-row justify-between items-start mb-6 gap-4">
                <div>
                  <h2 class="text-2xl font-black text-slate-900 mb-2">{{ room.name }}</h2>
                  <div class="flex flex-wrap gap-4 text-xs font-bold text-slate-500">
                    <span class="flex items-center gap-1.5">
                      <Users class="w-4 h-4 text-indigo-500" /> Max {{ room.max_adults }} Adults
                    </span>
                    <span v-if="room.max_children > 0" class="flex items-center gap-1.5">
                      <Baby class="w-4 h-4 text-indigo-500" /> Max {{ room.max_children }} Kids
                    </span>
                  </div>
                </div>
                <div class="text-left md:text-right">
                  <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Price Per Night</p>
                  <p class="text-3xl font-black text-slate-900">₦{{ room.price_per_night }}</p>
                </div>
              </div>

              <p class="text-slate-500 font-medium leading-relaxed mb-8">
                {{ room.description }}
              </p>

              <div class="mt-auto pt-8 border-t border-slate-50 flex flex-col sm:flex-row items-center justify-between gap-6">
                <div class="flex items-center gap-4 bg-slate-50 p-2 rounded-2xl border border-slate-100 w-full sm:w-auto">
                  <span class="pl-4 text-xs font-black text-slate-400 uppercase tracking-widest">Qty</span>
                  <input
                    type="number"
                    v-model.number="quantities[room.id]"
                    :max="room.available_quantity"
                    min="1"
                    class="w-20 bg-white border-none focus:ring-2 focus:ring-indigo-500 rounded-xl font-bold text-center py-2"
                  />
                </div>

                <button
                  @click="selectRoom(room.id)"
                  :disabled="room.available_quantity === 0"
                  class="w-full sm:w-auto px-10 py-4 bg-slate-900 text-white rounded-2xl font-black text-sm uppercase tracking-widest hover:bg-indigo-600 transition-all shadow-xl shadow-slate-200 active:scale-95 disabled:opacity-30 disabled:cursor-not-allowed group"
                >
                  <span v-if="room.available_quantity > 0" class="flex items-center justify-center gap-2">
                    Reserve Now <ChevronRight class="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                  </span>
                  <span v-else>Fully Booked</span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <div v-if="roomTypes.length === 0" class="mt-12 text-center py-20 bg-white rounded-[3rem] border border-slate-200">
          <div class="p-6 bg-slate-50 rounded-full inline-flex mb-6 text-slate-300">
            <Info class="w-12 h-12" />
          </div>
          <h2 class="text-2xl font-black text-slate-900 mb-2">No availability for these dates</h2>
          <p class="text-slate-500 font-medium mb-8">Try adjusting your check-in dates or guest count.</p>
          <button @click="router.visit(route('booking.index'))" class="text-indigo-600 font-black flex items-center gap-2 mx-auto hover:gap-4 transition-all">
            Modify Search <ChevronRight class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>
  </GuestLayout>
</template>

<style scoped>
/* Hide spin buttons for cleaner look on numeric input */
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
input[type=number] {
  -moz-appearance: textfield;
}
</style>