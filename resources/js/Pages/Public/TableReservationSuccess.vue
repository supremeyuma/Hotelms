<template>
  <div class="min-h-screen bg-gray-50 py-12">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
      <div class="text-center">
        <!-- Success Icon -->
        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-8">
          <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2l-2-2m0 6v-6M12 8v6m0-6h6m-6-6h6m2 10a8 8 0 01-16 0v-8a8 8 0 0116 0z" />
          </svg>
        </div>
        
        <!-- Success Message -->
        <div class="mb-8">
          <h1 class="text-3xl font-bold text-gray-900">Table Reservation Successful!</h1>
          <p class="mt-4 text-lg text-gray-600">
            Your table has been reserved and confirmed. A confirmation email will be sent to your registered email address.
          </p>
        </div>

        <!-- Reservation Details -->
        <div v-if="reservation" class="bg-white rounded-lg shadow-md overflow-hidden">
          <div class="p-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Reservation Details</h2>
            
            <div class="space-y-4">
              <div class="flex justify-between border-b pb-4">
                <span class="text-gray-600">Event:</span>
                <span class="font-medium text-gray-900">{{ reservation.event.title }}</span>
              </div>
              
              <div class="flex justify-between border-b pb-4">
                <span class="text-gray-600">Guest Name:</span>
                <span class="font-medium text-gray-900">{{ reservation.guest_name }}</span>
              </div>
              
              <div class="flex justify-between border-b pb-4">
                <span class="text-gray-600">Email:</span>
                <span class="font-medium text-gray-900">{{ reservation.guest_email }}</span>
              </div>
              
              <div class="flex justify-between border-b pb-4">
                <span class="text-gray-600">Phone:</span>
                <span class="font-medium text-gray-900">{{ reservation.guest_phone || 'Not provided' }}</span>
              </div>
              
              <div class="flex justify-between border-b pb-4">
                <span class="text-gray-600">Table Number:</span>
                <span class="font-medium text-gray-900">{{ reservation.table_number || 'TBD' }}</span>
              </div>
              
              <div class="flex justify-between border-b pb-4">
                <span class="text-gray-600">Number of Guests:</span>
                <span class="font-medium text-gray-900">{{ reservation.number_of_guests }}</span>
              </div>
              
              <div class="flex justify-between border-b pb-4">
                <span class="text-gray-600">Total Paid:</span>
                <span class="font-medium text-gray-900">{{ reservation.formatted_amount }}</span>
              </div>
              
              <div class="flex justify-between border-b pb-4">
                <span class="text-gray-600">Reservation Date:</span>
                <span class="font-medium text-gray-900">{{ reservation.formatted_reservation_date }}</span>
              </div>
              
              <div class="flex justify-between pb-4">
                <span class="text-gray-600">Status:</span>
                <span class="font-medium" :class="reservation.payment_status === 'paid' ? 'text-green-600' : 'text-yellow-600'">
                  {{ reservation.payment_status.toUpperCase() }}
                </span>
              </div>
            </div>

            <!-- QR Code -->
            <div v-if="reservation.qr_code" class="text-center mt-8">
              <div class="text-sm text-gray-600 mb-4">Your Reservation QR Code</div>
              <div class="bg-white p-4 inline-block rounded-lg shadow-md border">
                <div class="w-48 h-48 bg-gray-100 flex items-center justify-center">
                  <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h18a1 1 0 001 1v16a1 1 0 001-1H3a1 1 0 00-1-1V3a1 1 0 00-1-1zM7 8a1 1 0 110 2h2a1 1 0 110 2v10a1 1 0 11-2 2H7a1 1 0 11-2 2v-10z" />
                  </svg>
                </div>
                <div class="absolute inset-0 flex items-center justify-center">
                  <div class="text-2xl font-bold text-gray-900 bg-white px-3 py-1 rounded shadow-lg">
                    {{ reservation.qr_code }}
                  </div>
                </div>
              </div>
              <p class="text-xs text-gray-500 mt-4">Bring this QR code to the event for check-in</p>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="mt-8 flex flex-col sm:flex-row gap-4 justify-center">
          <a :href="`/events/${reservation.event.id}`" 
             class="flex items-center justify-center px-4 py-3 border border-gray-300 text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l5-5m0 0a2 2 0 110 2m0 4a2 2 0 110-2m0-4H4a2 2 0 00-2-2v6a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 002-2h2m-4l-5 5a2 2 0 112-2v6a2 2 0 011-2H4a2 2 0 00-2-2v-2z" />
            </svg>
            Back to Event
          </a>
          
          <a :href="`mailto:${reservation.guest_email}`" 
             class="flex items-center justify-center px-4 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a12 12 0 111.61 5.39L12 12l-2.89-8.26A12 12 0 010.39 16.61L11.61 8l-4.22 2.23zM16.5 5.5a1.5 1.5 0 1003 0v6a1.5 1.5 0 100 3h-3a1.5 1.5 0 100-3 0V7a1.5 1.5 0 100-3 0z" />
            </svg>
            Email Confirmation
          </a>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  reservation: Object,
})
</script>