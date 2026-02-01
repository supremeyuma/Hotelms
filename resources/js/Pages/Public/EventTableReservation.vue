<template>
  <PublicLayout>
    <Head :title="`Reserve Table | ${event.title}`" />

    <div class="min-h-screen bg-slate-50 py-8 md:py-12">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="bg-indigo-950 rounded-t-3xl overflow-hidden relative">
          <div class="absolute inset-0 opacity-10 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]"></div>
          <div class="relative z-10 p-10 md:p-8 flex flex-col md:flex-row gap-6 items-center">
            <div class="w-60 h-20 md:w-24 md:h-24 shrink-0 rounded-xl overflow-hidden shadow-xl border border-white/10">
              <img v-if="event.image" :src="'/storage/' + event.image" :alt="event.title" class="w-full h-full object-cover">
              <div v-else class="w-full h-full bg-gradient-to-br from-indigo-500 to-purple-600"></div>
            </div>
            <div class="text-center md:text-left">
              <Link :href="`/events/${event.id}`" class="text-indigo-400 text-[10px] font-black uppercase tracking-[0.2em] hover:text-white transition-colors">
                &larr; Back to Event
              </Link>
              <h1 class="text-2xl md:text-3xl font-black text-white tracking-tighter mt-1 mb-2">{{ event.title }}</h1>
              <div class="flex flex-wrap justify-center md:justify-start gap-4 text-indigo-100/60 text-xs font-bold">
                <span class="flex items-center">
                  <svg class="w-3.5 h-3.5 mr-1.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                  {{ formatEventDate(event.start_datetime) }}
                </span>
                <span class="flex items-center">
                  <svg class="w-3.5 h-3.5 mr-1.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  {{ formatEventTime(event.start_datetime) }}
                </span>
                <span v-if="event.venue" class="flex items-center">
                  <svg class="w-3.5 h-3.5 mr-1.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                  {{ event.venue }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-b-3xl shadow-2xl shadow-slate-200/60 border-x border-b border-slate-100 p-8 md:p-12">
          
          <div v-if="!event.has_table_reservations" class="text-center py-12">
            <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-6 text-slate-400">
              <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
            </div>
            <h3 class="text-2xl font-black text-slate-900">Reservations Unavailable</h3>
            <p class="text-slate-500 mt-2 max-w-sm mx-auto">This event is currently not accepting table bookings. Please contact concierge for assistance.</p>
          </div>

          <div v-else>
            <form @submit.prevent="submitReservation" class="space-y-12">
              
              <div>
                <h3 class="text-sm font-black text-indigo-600 uppercase tracking-widest mb-6 flex items-center">
                  <span class="w-6 h-6 rounded bg-indigo-50 flex items-center justify-center mr-3 text-[10px]">01</span>
                  Select Your Tier
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  <button 
                    v-for="tableType in event.table_types" 
                    :key="tableType.id"
                    type="button"
                    @click="form.table_type_id = tableType.id"
                    :class="[
                      'relative p-6 rounded-3xl border-2 text-left transition-all duration-300',
                      form.table_type_id === tableType.id 
                        ? 'border-indigo-600 bg-indigo-50/50 ring-4 ring-indigo-500/10' 
                        : 'border-slate-100 bg-slate-50 hover:border-slate-300'
                    ]"
                  >
                    <div v-if="form.table_type_id === tableType.id" class="absolute top-4 right-4">
                      <div class="bg-indigo-600 text-white rounded-full p-1">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/></svg>
                      </div>
                    </div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Level</p>
                    <h4 class="text-xl font-black text-slate-900 leading-tight mb-4">{{ tableType.name }}</h4>
                    <div class="space-y-1">
                      <p class="text-2xl font-black text-indigo-600">₦{{ formatNumber(tableType.price) }}</p>
                      <p class="text-xs font-bold text-slate-500 uppercase">{{ tableType.capacity }} Guests Included</p>
                    </div>
                  </button>
                </div>
              </div>

              <div v-if="form.table_type_id" class="animate-in fade-in slide-in-from-bottom-4 duration-500">
                <h3 class="text-sm font-black text-indigo-600 uppercase tracking-widest mb-6 flex items-center">
                  <span class="w-6 h-6 rounded bg-indigo-50 flex items-center justify-center mr-3 text-[10px]">02</span>
                  Reservation Details
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                  <div class="space-y-2">
                    <label class="text-xs font-bold text-slate-500 uppercase ml-1">Full Name</label>
                    <input v-model="form.guest_name" type="text" placeholder="Your name" required
                           class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none font-medium text-slate-900">
                  </div>
                  <div class="space-y-2">
                    <label class="text-xs font-bold text-slate-500 uppercase ml-1">Email Address</label>
                    <input v-model="form.guest_email" type="email" placeholder="email@address.com" required
                           class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none font-medium text-slate-900">
                  </div>
                  <div class="space-y-2">
                    <label class="text-xs font-bold text-slate-500 uppercase ml-1">Phone Number</label>
                    <input v-model="form.guest_phone" type="tel" placeholder="+234..." required
                           class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none font-medium text-slate-900">
                  </div>
                </div>
              </div>

              <div v-if="selectedTableType" class="space-y-8 pt-8 border-t border-slate-100">
                <div class="bg-slate-900 rounded-3xl p-8 text-white relative overflow-hidden">
                  <div class="absolute top-0 right-0 p-8 opacity-10">
                    <svg class="w-24 h-24" fill="currentColor" viewBox="0 0 24 24"><path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                  </div>
                  
                  <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 relative z-10">
                    <div>
                      <h3 class="text-sm font-black text-indigo-400 uppercase tracking-widest mb-2">Grand Total</h3>
                      <p class="text-4xl font-black">₦{{ formatNumber(selectedTableType.price) }}</p>
                    </div>
                    <div class="text-left md:text-right">
                      <p class="text-xs font-bold text-white/40 uppercase tracking-widest mb-1">Booking Confirmed For</p>
                      <p class="font-bold text-lg text-indigo-200">{{ form.guest_name || 'Valued Guest' }}</p>
                    </div>
                  </div>
                </div>

                <button type="submit" :disabled="processing"
                        class="group relative w-full flex items-center justify-center px-8 py-6 bg-indigo-600 text-white rounded-2xl font-black text-sm uppercase tracking-[0.2em] transition-all hover:bg-indigo-700 disabled:opacity-50">
                  <span v-if="!processing" class="flex items-center">
                    Proceed to Payment
                    <svg class="w-5 h-5 ml-3 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                  </span>
                  <span v-else class="flex items-center">
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Authorizing...
                  </span>
                </button>
                
                <p class="text-center text-[10px] text-slate-400 font-bold uppercase tracking-widest">
                  Secure checkout provided by MooreLife Resort
                </p>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </PublicLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'

const props = defineProps({
  event: Object,
})

const processing = ref(false)

const form = ref({
  guest_name: '',
  guest_email: '',
  guest_phone: '',
  table_type_id: '',
})

/**
 * Formats start_datetime into a readable date string
 */
const formatEventDate = (datetime) => {
  if (!datetime) return 'Date TBD'
  return new Date(datetime).toLocaleDateString('en-US', {
    weekday: 'short',
    month: 'long',
    day: 'numeric',
    year: 'numeric'
  })
}

/**
 * Formats start_datetime into a readable time string
 */
const formatEventTime = (datetime) => {
  if (!datetime) return 'Time TBD'
  return new Date(datetime).toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  })
}

const selectedTableType = computed(() => {
  if (!form.value.table_type_id || !props.event.table_types) return null
  return props.event.table_types.find(type => type.id === form.value.table_type_id)
})

const submitReservation = () => {
  processing.value = true
  
  router.post(`/events/${props.event.id}/tables/reserve`, form.value, {
    onSuccess: () => {
      processing.value = false
    },
    onError: () => {
      processing.value = false
    },
    preserveScroll: true
  })
}

const formatNumber = (num) => {
  return new Intl.NumberFormat('en-NG').format(num)
}
</script>