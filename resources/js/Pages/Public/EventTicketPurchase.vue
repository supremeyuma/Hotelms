<template>
  <div class="min-h-screen bg-gray-50 py-12">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
      <div class="bg-white rounded-lg shadow-md overflow-hidden">
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
                 :class="{ 'border-red-300 bg-red-50': !ticket.is_on_sale, 'border-green-300 bg-green-50': ticket.is_sold_out }">
              
              <!-- Ticket Header -->
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

              <!-- Ticket Status -->
              <div class="mb-4">
                <span v-if="ticket.is_on_sale" 
                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                  <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-8-8a1 1 0 111.414 0z" clip-rule="evenodd" />
                  </svg>
                  Available
                </span>
                <span v-else-if="ticket.is_sold_out" 
                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                  <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v2a1 1 0 001 1h4a1 1 0 001-1v-2a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                  </svg>
                  Sold Out
                </span>
                <span v-else 
                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                  <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 100-2 1 1 0 012 0zM9 9a1 1 0 000 2v2a1 1 0 001 1h4a1 1 0 001-1v-2a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                  </svg>
                  Not On Sale
                </span>
              </div>

              <!-- Ticket Details -->
              <div class="grid grid-cols-2 gap-4 text-sm text-gray-600">
                <div>
                  <span>Available:</span>
                  <span class="font-medium text-gray-900">{{ ticket.available_quantity }} tickets</span>
                </div>
                <div>
                  <span>Limit per person:</span>
                  <span class="font-medium text-gray-900">{{ ticket.max_per_person }} tickets</span>
                </div>
                <div v-if="ticket.sales_start">
                  <span>Sales start:</span>
                  <span class="font-medium text-gray-900">{{ formatDate(ticket.sales_start) }}</span>
                </div>
                <div v-if="ticket.sales_end">
                  <span>Sales end:</span>
                  <span class="font-medium text-gray-900">{{ formatDate(ticket.sales_end) }}</span>
                </div>
              </div>

              <!-- Purchase Button -->
              <button v-if="ticket.is_on_sale" 
                      @click="selectTicket(ticket)"
                      :disabled="processingPurchase"
                      class="w-full flex items-center justify-center px-4 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                <svg v-if="!processingPurchase" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-4h-4M14 3l-6 6 4-2M5 3v4h4" />
                </svg>
                <svg v-else class="w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V8C4 5.79 5.79 4 8h13C19.79 4 21 5.79 21 8v13a8 8 0 01-8 8H4z"></path>
                </svg>
                {{ processingPurchase ? 'Processing...' : 'Select Tickets' }}
              </button>
              
              <button v-else 
                      disabled
                      class="w-full flex items-center justify-center px-4 py-3 border border-gray-300 text-base font-medium rounded-md text-gray-400 bg-gray-100 cursor-not-allowed">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364l-8.485 8.485a5 5 0 01-7.07 0l-8.485-8.485a5 5 0 017.07 7.07l8.485 8.485a5 5 0 007.07-7.07l8.485-8.485A5 5 0 0015.754 6.828z" />
                </svg>
                {{ ticket.is_sold_out ? 'Sold Out' : 'Not Available' }}
              </button>
            </div>
          </div>

          <!-- Purchase Form -->
          <div v-if="selectedTicket" class="mt-8 p-6 bg-gray-50 rounded-lg">
            <h3 class="text-xl font-bold text-gray-900 mb-6">Complete Purchase</h3>
            
            <form @submit.prevent="submitPurchase" class="space-y-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                  <input v-model="form.guest_name" type="text" required
                         class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                  <input v-model="form.guest_email" type="email" required
                         class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                  <input v-model="form.guest_phone" type="tel"
                         class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Quantity *</label>
                  <input v-model="form.quantity" type="number" :min="1" :max="selectedTicket.max_per_person" required
                         class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                  <div class="text-xs text-gray-500 mt-1">
                    Max: {{ selectedTicket.max_per_person }} tickets per person
                  </div>
                </div>
              </div>

              <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method *</label>
                  <select v-model="form.payment_method" required
                          class="w-full px-3 py-2 border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="online">Pay Online (Flutterwave)</option>
                    <option value="cash">Pay at Venue</option>
                    <option value="points">Use Payment Points</option>
                  </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Additional Notes</label>
                <textarea v-model="form.notes" rows="3"
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea>
              </div>
            </form>

            <!-- Price Summary -->
            <div class="mt-6 p-4 bg-white rounded-lg border">
              <div class="space-y-2">
                <div class="flex justify-between">
                  <span class="text-gray-600">{{ selectedTicket.name }} (×{{ form.quantity }})</span>
                  <span class="font-bold text-gray-900">₦{{ formatNumber(selectedTicket.price * form.quantity) }}</span>
                </div>
                <div class="flex justify-between text-lg font-bold text-gray-900 pt-2 border-t">
                  <span>Total</span>
                  <span>₦{{ formatNumber(selectedTicket.price * form.quantity) }}</span>
                </div>
              </div>
            </div>

            <button type="submit" :disabled="processingPurchase"
                    class="w-full flex items-center justify-center px-4 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
              <svg v-if="!processingPurchase" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7m-7 4m0 4l8-4m-8 4v6m0 4h18" />
              </svg>
              <svg v-else class="w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V8C4 5.79 5.79 4 8h13C19.79 4 21 5.79 21 8v13a8 8 0 01-8 8H4z"></path>
              </svg>
              {{ processingPurchase ? 'Processing...' : 'Complete Purchase' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
  event: Object,
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
  })
}

const formatNumber = (num) => {
  return new Intl.NumberFormat('en-NG').format(num)
}

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString('en-GB', {
    day: 'numeric', 
    month: 'short', 
    year: 'numeric'
  })
}
</script>