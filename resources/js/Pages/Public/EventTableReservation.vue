<template>
  <div class="min-h-screen bg-gray-50 py-12">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
      <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <!-- Event Header -->
        <div class="relative">
          <img v-if="event.image" :src="'/storage/' + event.image" 
               :alt="event.title" class="w-full h-48 object-cover">
          <div v-else class="w-full h-48 bg-gradient-to-br from-green-400 to-blue-400"></div>
          <div class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-50 text-white p-4">
            <h2 class="text-2xl font-bold">{{ event.title }}</h2>
            <div class="text-sm">{{ event.formatted_date }}</div>
            <div class="text-lg font-semibold">₦{{ event.table_price }}/table</div>
          </div>
        </div>

        <!-- Table Reservation Form -->
        <div class="p-8">
          <h3 class="text-2xl font-bold text-gray-900 mb-6">Reserve Your Table</h3>
          
          <div v-if="!event.has_table_reservations" class="text-center py-8">
            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364l-8.485-8.485A8.987 8.987 0 00-12.727L3.636 3.636a8.987 8.987 0 00-12.727l4.243 4.243A8.987 8.987 0 0012.727L21.364 18.364a8.987 8.987 0 000-12.727L15.75 6.636a8.987 8.987 0 000-12.727l6.615 6.615A8.987 8.987 0 00-12.728l6.615-6.615A8.987 8.987 0 000-12.727l-6.615 6.615zm0-9a9 9 0 000-18v18a9 9 0 000-18z" />
            </svg>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">Table Reservations Not Available</h3>
            <p class="text-gray-600">This event does not support table reservations. Please check back later for ticket availability.</p>
          </div>

          <div v-else>
            <form @submit.prevent="submitReservation" class="space-y-6">
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
                  <label class="block text-sm font-medium text-gray-700 mb-2">Number of Guests *</label>
                  <input v-model="form.number_of_guests" type="number" :min="1" :max="10" required
                         class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Preferred Table Number</label>
                  <input v-model="form.table_number" type="text" placeholder="e.g., Table 1"
                         class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Special Requests</label>
                <textarea v-model="form.special_requests" rows="3" placeholder="Any special dietary or seating requirements?"
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method *</label>
                  <select v-model="form.payment_method" required
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="online">Pay Online</option>
                    <option value="cash">Pay at Venue</option>
                  </select>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Additional Notes</label>
                  <textarea v-model="form.notes" rows="2" placeholder="Any additional information?"
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                </div>
              </div>

              <!-- Price Summary -->
              <div class="bg-gray-50 p-4 rounded-lg">
                <h4 class="text-lg font-semibold text-gray-900 mb-4">Reservation Summary</h4>
                <div class="space-y-2">
                  <div class="flex justify-between">
                    <span class="text-gray-600">Table Price:</span>
                    <span class="font-medium text-gray-900">₦{{ formatNumber(event.table_price) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Number of Guests:</span>
                    <span class="font-medium text-gray-900">{{ form.number_of_guests }}</span>
                  </div>
                  <div class="flex justify-between text-lg font-bold text-gray-900 pt-2 border-t">
                    <span>Total:</span>
                    <span>₦{{ formatNumber(event.table_price * form.number_of_guests) }}</span>
                  </div>
                </div>
              </div>

              <button type="submit" :disabled="processing"
                      class="w-full flex items-center justify-center px-4 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                <svg v-if="!processing" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7m-7 4V3m7 4v4m0-4h18m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <svg v-else class="w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V8C4 5.79 5.79 4 8h13C19.79 4 21 5.79 21 8v13a8 8 0 01-8 8H4z"></path>
                </svg>
                {{ processing ? 'Processing...' : 'Reserve Table' }}
              </button>
            </form>
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

const processing = ref(false)

const form = ref({
  guest_name: '',
  guest_email: '',
  guest_phone: '',
  number_of_guests: 1,
  table_number: '',
  special_requests: '',
  payment_method: 'online',
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
  })
}

const formatNumber = (num) => {
  return new Intl.NumberFormat('en-NG').format(num)
}
</script>