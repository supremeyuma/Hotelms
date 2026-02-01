<template>
  <PublicLayout>
    <div class="py-12">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
      <div class="bg-white rounded-lg shadow-md overflow-hidden">

        <div v-if="Object.keys($page.props.errors).length > 0" class="mb-4 p-4 bg-red-50 border-l-4 border-red-500 text-red-700">
          <ul>
              <li v-for="(error, key) in $page.props.errors" :key="key">{{ error }}</li>
          </ul>
      </div>
        <!-- Event Header -->
        <div class="relative">
          <img v-if="event.image" :src="'/storage/' + event.image" 
               :alt="event.title" class="w-full h-48 object-cover">
          <div v-else class="w-full h-48 bg-gradient-to-br from-purple-400 to-indigo-400"></div>
          <div class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-50 text-white p-4">
            <h2 class="text-xl font-bold">{{ event.title }}</h2>
            <div class="text-sm mt-1">{{ event.formatted_date }} • {{ event.start_time }}</div>
          </div>
        </div>

        <!-- Ticket Selection -->
        <div class="p-8">
          <h3 class="text-2xl font-bold text-gray-900 mb-6">Select Tickets</h3>
          
          <div v-if="ticketTypes.length === 0" class="text-center py-8">
            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m3 3m6-3a9 9 0 00-9-9v9m0-6v3m0-6h6m-9 6v6m0-6h9" />
            </svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No Tickets Available</h3>
            <p class="text-gray-600">Tickets are not currently available for this event.</p>
          </div>

          <div v-else class="space-y-4">
            <div v-for="ticket in ticketTypes" :key="ticket.id" 
                class="border rounded-lg p-6 hover:border-indigo-500 transition-colors"
                :class="{ 'border-red-300 bg-red-50': ticket.quantity_available <= 0 }">
              
              <div class="flex justify-between items-start mb-4">
                <div>
                  <h4 class="text-lg font-bold text-gray-900">{{ ticket.name }}</h4>
                  <p v-if="ticket.description" class="text-sm text-gray-600 mt-1">{{ ticket.description }}</p>
                </div>
                <div class="text-right">
                  <div class="text-2xl font-bold text-gray-900">₦{{ formatNumber(ticket.price) }}</div>
                  <div class="text-sm text-gray-600">per ticket</div>
                </div>
              </div>

              <div class="mb-4">
                <span v-if="ticket.quantity_available > 0" 
                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                  <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-8-8a1 1 0 111.414 0z" clip-rule="evenodd" />
                  </svg>
                  Available
                </span>
                <span v-else 
                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                  Sold Out
                </span>
              </div>

              <div class="grid grid-cols-2 gap-4 text-sm text-gray-600">
                <div>
                  <span>Remaining:</span>
                  <span class="font-medium text-gray-900">{{ ticket.quantity_available }}</span>
                </div>
                <div>
                  <span>Limit:</span>
                  <span class="font-medium text-gray-900">{{ ticket.max_per_person }} per person</span>
                </div>
              </div>

              <button v-if="ticket.quantity_available > 0" 
                      @click="selectTicket(ticket)"
                      :disabled="processingPurchase"
                      class="mt-4 w-full flex items-center justify-center px-4 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50">
                {{ selectedTicket?.id === ticket.id ? 'Selected' : 'Select Ticket' }}
              </button>
              
              <button v-else 
                      disabled
                      class="mt-4 w-full px-4 py-3 border border-gray-300 text-base font-medium rounded-md text-gray-400 bg-gray-100 cursor-not-allowed">
                Sold Out
              </button>
            </div>
          </div>

          <div v-if="selectedTicket" class="mt-8 p-6 bg-gray-50 rounded-lg border-2 border-indigo-100">
            <h3 class="text-xl font-bold text-gray-900 mb-6">Complete Purchase: {{ selectedTicket.name }}</h3>
            
            <form @submit.prevent="submitPurchase" class="space-y-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                  <input v-model="form.guest_name" type="text" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                  <input v-model="form.guest_email" type="email" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Quantity *</label>
                  <input v-model="form.quantity" type="number" :min="1" :max="selectedTicket.max_per_person" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method *</label>
                  <div
                    class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-700"
                  >
                    Pay Online (Flutterwave)
                  </div>
                </div>
              </div>

              <div class="p-4 bg-white rounded-lg border">
                <div class="flex justify-between text-lg font-bold text-gray-900">
                  <span>Total Price</span>
                  <span>₦{{ formatNumber(selectedTicket.price * form.quantity) }}</span>
                </div>
              </div>

              <button type="submit" :disabled="processingPurchase"
                      class="w-full flex items-center justify-center px-4 py-3 border border-transparent text-base font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none disabled:opacity-50">
                <span v-if="processingPurchase">Processing...</span>
                <span v-else>Confirm & Pay ₦{{ formatNumber(selectedTicket.price * form.quantity) }}</span>
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
    </div>
  </PublicLayout>
</template>

<script setup>
import { router, Head } from '@inertiajs/vue3'
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

// Helper to handle the "is_on_sale" column removal we discussed
const isOnSale = (ticket) => {
  // If the column is gone, we check active status and availability
  return ticket.is_active && ticket.quantity_available > 0
}

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
    onError: (errors) => {
      processingPurchase.value = false
      console.error("Validation Errors:", errors) // Check your browser console!
    },
    onFinish: () => processingPurchase.value = false,
  })
}

const formatNumber = (num) => new Intl.NumberFormat('en-NG').format(num || 0)

const formatDate = (dateString) => {
  if (!dateString) return 'TBD'
  return new Date(dateString).toLocaleDateString('en-GB', {
    day: 'numeric', month: 'short', year: 'numeric'
  })
}

// Format the display date for the header
const displayDate = computed(() => {
  const dateSource = props.event.start_datetime || props.event.event_date
  return formatDate(dateSource)
})
</script>