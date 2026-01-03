<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { defineProps, reactive } from 'vue';
import { 
  BedDouble, 
  Users, 
  CheckCircle2, 
  Info, 
  ChevronRight, 
  Trash2,
  ArrowRight,
  TrendingUp
} from 'lucide-vue-next';

const props = defineProps({
  roomTypes: Array,
  check_in: String,
  check_out: String,
  adults: Number,
  children: Number,
});

// Using reactive for quantities to keep it lightweight, or useForm if you need validation
const quantities = reactive({});
props.roomTypes.forEach(r => quantities[r.id] = 1);

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

const getAmenityLabel = (name) => {
  // Simple logic to add "Luxury" feel to badges
  if (name.toLowerCase().includes('suite')) return 'Premium Suite';
  return 'Standard';
}
</script>

<template>
  <div class="max-w-5xl mx-auto px-4 py-12 md:py-20">
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-12">
      <div>
        <div class="flex items-center gap-2 text-indigo-600 font-black text-xs uppercase tracking-[0.3em] mb-3">
          <BedDouble class="w-4 h-4" /> Step 2: Choose Accommodation
        </div>
        <h1 class="text-4xl font-black text-slate-900 tracking-tight">Available Rooms</h1>
      </div>
      
      <div class="bg-white px-6 py-4 rounded-3xl border border-slate-200 shadow-sm flex items-center gap-6">
        <div class="text-center">
          <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Arrival</p>
          <p class="font-bold text-slate-700">{{ check_in }}</p>
        </div>
        <div class="h-8 w-px bg-slate-100"></div>
        <div class="text-center">
          <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Departure</p>
          <p class="font-bold text-slate-700">{{ check_out }}</p>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-8">
      <div 
        v-for="room in roomTypes" 
        :key="room.id" 
        class="group bg-white rounded-[3rem] border border-slate-200 overflow-hidden hover:shadow-2xl hover:border-indigo-100 transition-all duration-500 flex flex-col md:flex-row"
      >
        <div class="md:w-1/3 bg-slate-100 relative overflow-hidden h-64 md:h-auto">
          <div class="absolute inset-0 flex items-center justify-center text-slate-300">
            <BedDouble class="w-20 h-20 opacity-20 group-hover:scale-110 transition-transform duration-700" />
          </div>
          <div 
            class="absolute top-6 left-6 px-4 py-2 rounded-2xl text-[10px] font-black uppercase tracking-widest shadow-sm"
            :class="room.available_quantity > 0 ? 'bg-white text-emerald-600' : 'bg-rose-50 text-rose-600'"
          >
            {{ room.available_quantity }} Rooms Left
          </div>
        </div>

        <div class="flex-1 p-8 md:p-12">
          <div class="flex justify-between items-start mb-4">
            <div>
              <span class="text-indigo-600 text-[10px] font-black uppercase tracking-widest mb-1 block">
                {{ getAmenityLabel(room.name) }}
              </span>
              <h2 class="text-3xl font-black text-slate-900 tracking-tight">{{ room.name }}</h2>
            </div>
            <div class="text-right">
              <p class="text-sm text-slate-400 font-bold uppercase tracking-tighter">Per Night</p>
              <p class="text-2xl font-black text-slate-900">₦{{ room.price_per_night }}</p>
            </div>
          </div>

          <p class="text-slate-500 font-medium leading-relaxed mb-8 max-w-xl">
            {{ room.description }}
          </p>

          <div class="flex flex-col sm:flex-row items-center justify-between gap-6 pt-8 border-t border-slate-50">
            <form @submit.prevent="selectRoom(room.id)" class="w-full flex flex-col sm:flex-row items-center gap-4">
              <div class="flex items-center gap-3 bg-slate-50 p-2 rounded-2xl border border-slate-100 w-full sm:w-auto">
                <label class="pl-4 text-xs font-black text-slate-400 uppercase tracking-widest">Quantity</label>
                <input 
                  type="number" 
                  v-model.number="quantities[room.id]" 
                  :max="room.available_quantity" 
                  min="1" 
                  required 
                  class="w-20 bg-white border-none focus:ring-2 focus:ring-indigo-500 rounded-xl font-bold text-center py-2"
                />
              </div>

              <button 
                type="submit" 
                class="w-full sm:w-auto flex-1 group inline-flex items-center justify-center gap-3 px-8 py-4 bg-slate-900 text-white rounded-2xl font-black hover:bg-indigo-600 transition-all shadow-xl shadow-slate-200 active:scale-95 disabled:opacity-30 disabled:cursor-not-allowed" 
                :disabled="room.available_quantity === 0"
              >
                {{ room.available_quantity === 0 ? 'Sold Out' : 'Reserve Room' }}
                <ArrowRight class="w-5 h-5 group-hover:translate-x-1 transition-transform" />
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <div v-if="roomTypes.length === 0" class="text-center py-24 bg-white rounded-[3rem] border border-slate-200 shadow-sm">
      <div class="inline-flex p-6 bg-rose-50 text-rose-500 rounded-full mb-6">
        <Info class="w-12 h-12" />
      </div>
      <h2 class="text-2xl font-black text-slate-900 mb-2">No Rooms Available</h2>
      <p class="text-slate-500 font-medium mb-8">Try adjusting your dates or guest count.</p>
      <button @click="router.visit('/booking')" class="text-indigo-600 font-black flex items-center gap-2 mx-auto hover:gap-4 transition-all">
        Modify Search <ArrowRight class="w-5 h-5" />
      </button>
    </div>
  </div>
</template>

<style scoped>
/* Scoped polish for form controls */
input[type=number]::-webkit-inner-spin-button, 
input[type=number]::-webkit-outer-spin-button { 
  opacity: 1;
}
</style>