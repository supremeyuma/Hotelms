<template>
  <PublicLayout>
    <Head :title="`Get Tickets | ${event.title}`" />

    <div class="min-h-screen bg-slate-50 py-8 md:py-12">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div v-if="Object.keys($page.props.errors).length > 0" class="mb-6 animate-in fade-in slide-in-from-top-4">
          <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-xl shadow-sm">
            <div class="flex">
              <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
              </div>
              <div class="ml-3">
                <h3 class="text-sm font-bold text-red-800">Registration Errors</h3>
                <div class="mt-1 text-sm text-red-700">
                  <ul class="list-disc pl-5 space-y-1">
                    <li v-for="(error, key) in $page.props.errors" :key="key">{{ error }}</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-indigo-950 rounded-t-3xl overflow-hidden relative">
          <div class="absolute inset-0 opacity-10 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]"></div>
          <div class="relative z-10 p-6 md:p-8 flex flex-col md:flex-row gap-6 items-center">
            <div class="w-20 h-20 md:w-24 md:h-24 shrink-0 rounded-xl overflow-hidden shadow-xl border border-white/10">
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
                  {{ formattedStartDateTime }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-b-3xl shadow-2xl shadow-slate-200/60 border-x border-b border-slate-100 p-8 md:p-12">
          
          <div v-if="ticketTypes.length === 0" class="text-center py-12">
            <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-6 text-slate-400">
              <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
            </div>
            <h3 class="text-2xl font-black text-slate-900">No Tickets Available</h3>
            <p class="text-slate-500 mt-2 max-w-sm mx-auto">Tickets are not currently available for online purchase for this event.</p>
          </div>

          <div v-else class="space-y-12">
            <div>
              <h3 class="text-sm font-black text-indigo-600 uppercase tracking-widest mb-6 flex items-center">
                <span class="w-6 h-6 rounded bg-indigo-50 flex items-center justify-center mr-3 text-[10px]">01</span>
                Choose Ticket Type
              </h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <button 
                  v-for="ticket in ticketTypes" 
                  :key="ticket.id"
                  type="button"
                  @click="selectTicket(ticket)"
                  :disabled="ticket.quantity_available <= 0"
                  :class="[
                    'relative p-6 rounded-3xl border-2 text-left transition-all duration-300 group',
                    selectedTicket?.id === ticket.id 
                      ? 'border-indigo-600 bg-indigo-50/50 ring-4 ring-indigo-500/10' 
                      : 'border-slate-100 bg-slate-50 hover:border-slate-300',
                    ticket.quantity_available <= 0 ? 'opacity-60 grayscale cursor-not-allowed' : ''
                  ]"
                >
                  <div class="flex justify-between items-start">
                    <div class="pr-8">
                      <h4 class="text-xl font-black text-slate-900 leading-tight mb-1">{{ ticket.name }}</h4>
                      <p class="text-xs text-slate-500 font-medium mb-4 line-clamp-2">{{ ticket.description || 'General Admission access to the event.' }}</p>
                    </div>
                    <div class="text-right shrink-0">
                      <p class="text-2xl font-black text-indigo-600">₦{{ formatNumber(ticket.price) }}</p>
                    </div>
                  </div>

                  <div class="flex items-center gap-4 mt-4 pt-4 border-t border-slate-200/60">
                    <span :class="[
                      'text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded',
                      ticket.quantity_available > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                    ]">
                      {{ ticket.quantity_available > 0 ? 'Available' : 'Sold Out' }}
                    </span>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tight">
                      Limit: {{ ticket.max_per_person }} / person
                    </span>
                  </div>

                  <div v-if="selectedTicket?.id === ticket.id" class="absolute top-4 right-4">
                    <div class="bg-indigo-600 text-white rounded-full p-1 shadow-lg">
                      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/></svg>
                    </div>
                  </div>
                </button>
              </div>
            </div>

            <div v-if="selectedTicket" class="animate-in fade-in slide-in-from-bottom-4 duration-500">
              <h3 class="text-sm font-black text-indigo-600 uppercase tracking-widest mb-6 flex items-center">
                <span class="w-6 h-6 rounded bg-indigo-50 flex items-center justify-center mr-3 text-[10px]">02</span>
                Attendee Details
              </h3>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="space-y-2">
                  <label class="text-xs font-bold text-slate-500 uppercase ml-1">Full Name</label>
                  <input v-model="form.guest_name" type="text" placeholder="John Doe" required
                         class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none font-medium text-slate-900">
                </div>
                <div class="space-y-2">
                  <label class="text-xs font-bold text-slate-500 uppercase ml-1">Email Address</label>
                  <input v-model="form.guest_email" type="email" placeholder="john@example.com" required
                         class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none font-medium text-slate-900">
                </div>
                <div class="space-y-2">
                  <label class="text-xs font-bold text-slate-500 uppercase ml-1">Quantity</label>
                  <div class="relative">
                    <input v-model="form.quantity" type="number" :min="1" :max="selectedTicket.max_per_person" required
                           class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none font-medium text-slate-900">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[10px] font-black text-slate-400 uppercase">Tickets</span>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="selectedTicket" class="space-y-8 pt-8 border-t border-slate-100">
              <div class="bg-slate-900 rounded-3xl p-8 text-white relative overflow-hidden">
                <div class="absolute top-0 right-0 p-8 opacity-10">
                  <svg class="w-24 h-24" fill="currentColor" viewBox="0 0 24 24"><path d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                </div>
                
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 relative z-10">
                  <div>
                    <h3 class="text-sm font-black text-indigo-400 uppercase tracking-widest mb-2">Order Total</h3>
                    <p class="text-4xl font-black">₦{{ formatNumber(selectedTicket.price * form.quantity) }}</p>
                    <p class="text-xs text-white/40 mt-1">{{ form.quantity }} x {{ selectedTicket.name }}</p>
                  </div>
                  <div class="text-left md:text-right">
                    <p class="text-xs font-bold text-white/40 uppercase tracking-widest mb-1">Billing To</p>
                    <p class="font-bold text-lg text-indigo-200 line-clamp-1">{{ form.guest_name || 'Attendee' }}</p>
                  </div>
                </div>
              </div>

              <button @click="submitPurchase" :disabled="processingPurchase"
                      class="group relative w-full flex items-center justify-center px-8 py-6 bg-indigo-600 text-white rounded-2xl font-black text-sm uppercase tracking-[0.2em] transition-all hover:bg-indigo-700 disabled:opacity-50">
                <span v-if="!processingPurchase" class="flex items-center">
                  Confirm & Pay Online
                  <svg class="w-5 h-5 ml-3 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </span>
                <span v-else class="flex items-center">
                  <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                  Processing Checkout...
                </span>
              </button>
              
              <p class="text-center text-[10px] text-slate-400 font-bold uppercase tracking-widest">
                Payment processed securely via Flutterwave
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </PublicLayout>
</template>

<script setup>
import { router, Head, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'

const props = defineProps({
  event: Object,
  ticketTypes: {
    type: Array,
    default: () => []
  }
})

const selectedTicket = ref(null)
const processingPurchase = ref(false)

const form = ref({
  ticket_type_id: '',
  guest_name: '',
  guest_email: '',
  guest_phone: '',
  quantity: 1,
  payment_method: 'online',
  notes: '',
})

const selectTicket = (ticket) => {
  selectedTicket.value = ticket
  form.value.ticket_type_id = ticket.id
}

const submitPurchase = () => {
  if (!selectedTicket.value) return
  processingPurchase.value = true
  
  router.post(`/events/${props.event.id}/tickets/purchase`, form.value, {
    onSuccess: () => {
      processingPurchase.value = false
    },
    onError: () => {
      processingPurchase.value = false
    },
    onFinish: () => processingPurchase.value = false,
  })
}

const formatNumber = (num) => new Intl.NumberFormat('en-NG').format(num || 0)

const formattedStartDateTime = computed(() => {
  if (!props.event.start_datetime) return 'Date TBD'
  const date = new Date(props.event.start_datetime)
  return date.toLocaleString('en-NG', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
  })
})
</script>

<style scoped>
.animate-in {
  animation-duration: 0.5s;
  animation-fill-mode: both;
}
@keyframes fade-in {
  from { opacity: 0; }
  to { opacity: 1; }
}
@keyframes slide-in-from-top-4 {
  from { transform: translateY(-1rem); }
  to { transform: translateY(0); }
}
@keyframes slide-in-from-bottom-4 {
  from { transform: translateY(1rem); }
  to { transform: translateY(0); }
}
.fade-in { animation-name: fade-in; }
.slide-in-from-top-4 { animation-name: slide-in-from-top-4; }
.slide-in-from-bottom-4 { animation-name: slide-in-from-bottom-4; }
</style>