<template>
  <ManagerLayout>
    <Head title="Event Check-In | MooreLife Resort" />
    <div class="space-y-6">
      <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-gray-900">Event Check-In</h1>
      </div>

      <!-- Search Form -->
      <form @submit.prevent="performCheckIn" class="space-y-6">
        <div class="flex space-x-4">
          <input 
                 type="text" 
                 v-model="qr_code"
                 placeholder="Enter QR code to check in"
                 class="flex-1 px-4 py-3 border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500"
                 required>
          </div>
        </form>

      <!-- Results -->
      <div v-if="checkResult" class="mt-8">
        <div v-if="checkResult.success" class="text-center">
          <div class="bg-green-100 text-white p-4 rounded-lg">
            <div class="w-16 h-16 text-green-600 mx-auto mb-4">
              <CheckCircle class="w-full h-full" />
            </div>
          </div>
          <h2 class="text-xl font-bold text-green-800">Check-In Successful!</h2>
          
          <div v-if="checkResult.ticket" class="mt-4 bg-white rounded-lg shadow-md p-6">
            <div class="text-sm text-gray-600 mb-2">Ticket Details</div>
            <div class="text-lg font-bold text-gray-900">{{ checkResult.ticket.title }}</div>
            <div class="text-sm text-gray-600">{{ checkResult.guest_name }}</div>
            <div class="text-sm text-gray-600">{{ checkResult.guest_email }}</div>
            <div class="text-sm text-gray-600 mb-2">Quantity:</div>
            <span class="text-xl font-bold text-gray-900">{{ checkResult.quantity }}</span>
            <div class="text-sm text-gray-600">{{ checkResult.formatted_date }}</div>
          </div>
        </div>

        <div v-else class="text-center">
          <div class="bg-red-100 text-white p-4 rounded-lg">
            <div class="w-16 h-16 text-red-600 mx-auto mb-4">
              <AlertTriangle class="w-full h-full" />
            </div>
          </div>
          <h2 class="text-xl font-bold text-red-600">Check-In Failed</h2>
          <p class="text-red-800">{{ checkResult.error || 'Invalid QR code' }}</p>
        </div>
      </div>
    </div>
  </ManagerLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import { CheckCircle, AlertTriangle } from 'lucide-vue-next'

const qr_code = ref('')
const checkResult = ref(null)

const performCheckIn = async () => {
  if (!qr_code.value) return
  
  try {
    const response = await router.post('/staff/events/validate', {
      qr_code: qr_code.value
    })
    
    checkResult.value = response.props.flash?.checkResult || {
      success: false,
      error: 'No response from server'
    }
    
  } catch (error) {
    checkResult.value = {
      success: false,
      error: 'Check-in failed: ' + (error.message || 'Unknown error')
    }
  }
}
</script>