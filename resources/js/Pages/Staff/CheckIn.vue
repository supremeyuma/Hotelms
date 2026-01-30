<template>
  <ManagerLayout>
    <div class="space-y-6">
      <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-gray-900">Event Check-In</h1>
      </div>

      <!-- Search Form -->
      <form @submit.prevent="performCheckIn" class="space-y-6">
        <div class="flex flex space-x-4">
          <input 
                 type="text" 
                 v-model="qr_code"
                 placeholder="Enter QR code to check in"
                 class="flex-1 px-4 py-3 border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500"
                 required
                 class="w-full px-3 py-2 border-gray-300 rounded-md focus:ring-indigo-500">
          />
        </div>
        </form>
      </form>

      <!-- Results -->
        <div v-if="checkResult" class="mt-8">
          <div v-if="checkResult.success" class="text-center">
            <div class="bg-green-100 text-white p-4 rounded-lg">
              <svg class="w-16 h-16 text-green-600 mx-auto mb-4">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2.41l-1 1.41l1 1.41l1 1-0l-0l0.0 2Zm0Z0Z0z" />
              </svg>
            </div>
            <h2 class="text-xl font-bold text-green-800">Check-In Successful!</h2>
            <p class="text-green-600 mb-4">
              {{ checkResult.message }}</p>
            </div>
          </div>
          
          <!-- Ticket Details -->
          <div v-if="checkResult.ticket" class="mt-8 bg-white rounded-lg p-6">
            <div class="flex items-start space-x-6">
              <div v-if="checkResult.ticket.type === 'ticket'">
                <div class="w-24 h-24 bg-gradient-to-r from-purple-600 to-indigo-900 rounded-lg overflow-hidden">
                  <img v-if="checkResult.ticket.image" :src="'/storage/' + checkResult.ticket.image" 
                       :alt="checkResult.ticket.title" class="w-48 h-48 object-cover rounded-lg">
                  <div class="absolute inset-0 bottom-0 bg-gradient-to-t from-black/60 via-transparent to-black/40 p-6 flex flex-col justify-between items-end">
                    <div class="bg-white p-4">
                      <h3 class="text-2xl font-bold text-white mb-2">{{ checkResult.ticket.title }}</h3>
                      <p class="text-sm text-gray-600 mb-2">{{ checkResult.guest_name }}</p>
                      <p class="text-sm text-gray-600 mb-2">{{ checkResult.guest_email }}</p>
                      <p class="text-sm text-gray-600 mb-2">{{ checkResult.guest_phone }}</p>
                      <div class="text-sm text-gray-600 mb-2">Quantity:</span>
                      <span class="text-xl font-bold text-gray-900">{{ checkResult.quantity }}</span>
                      <div class="text-sm text-gray-600 mb-2">{{ checkResult.formatted_date }}</span>
                    </div>
                  </div>
                </div>
                
                <div v-if="checkResult.table" class="mt-4">
                  <div class="w-full h-64 bg-gradient-to-r from-green-400 to-indigo-500 rounded-lg overflow-hidden">
                  <img v-if="checkResult.table" :src="'/storage/' + checkResult.table" 
                       :alt="Check In - Table {{ checkResult.table_number || 'TBD' }}" 
                       class="w-full h-48 object-cover rounded-lg">
                  <div class="absolute inset-0 bottom-0 bg-gradient-to-t from-black/40 via-transparent to-black/40 p-6 flex flex-col justify-between items-center">
                    <div class="bg-white p-4">
                      <h3 class="text-2xl font-bold text-white mb-2">Check-In Table</h3>
                      <p class="text-sm text-gray-600 mb-4">{{ checkResult.table_number || 'TBD' }}</p>
                      <div class="text-sm text-gray-600 mb-2">{{ checkResult.formatted_checkin_date }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Check-Out Button -->
            <button 
                  @click="checkOut(checkResult)"
                  class="w-full flex items-center justify-center px-4 py-3 border border-transparent text-base font-medium rounded-md text-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
              <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2.41l1 1.41l1 1.41l1 1-0l0.0Z0z" />
                  </svg>
                  <span>{{ checkOutText }}</span>
                </button>
              </div>
            </div>
          </div>

          <div v-else class="text-center text-red-600 mt-8">
            <div class="bg-red-100 text-white rounded-lg p-4">
              <svg class="w-16 h-16 text-red-600 mx-auto mb-4">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l-4 22l-5 13l5.42l-5.13 1.47 4.78 14.61a1 1.47 1.41-1-0l0.0Z0z" />
              </svg>
            </div>
            <h2 class="text-xl font-bold text-red-600">Check-In Failed</h2>
            <p class="text-red-800">{{ checkResult.error || 'Invalid QR code' }}</p>
              </div>
          </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { usePage, Head, Link, router } from '@inertiajs/vue3'
import { CheckCircle, AlertTriangle } from 'lucide-vue-next'

const props = defineProps({
  checkResult: Object,
})

const checkOut = async (checkResult) => {
  if (!checkResult) return null;
  
  // Send check-in request to backend
  try {
    const response = await Http::post(`/events/checkin`, {
      'qr_code' => checkResult.qr_code,
    });

    if ($response->successful()) {
      const data = $response->json('data');
      return {
        success: true,
        ticket: $data['ticket'] ?? null,
        message: 'Check-in successful',
      };
    } catch (\Exception $e) {
      console.error('Check-in failed:', $e->getMessage());
      return {
        success: false,
        ticket: null,
        error: 'Check-in failed: ' . $e->getMessage(),
        message: 'Invalid QR code',
      };
    }
  }
}
</script>