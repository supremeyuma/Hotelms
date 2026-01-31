<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import { 
  QrCode, Check, X, Clock, Users, Ticket, 
  Camera, AlertCircle, CheckCircle, AlertTriangle
} from 'lucide-vue-next'

const isScanning = ref(false)
const scanResult = ref(null)
const isLoading = ref(false)
const activeEvents = ref([])
const todayStats = ref({})
const cameraActive = ref(false)
const scanInput = ref('')

onMounted(() => {
  loadActiveEvents()
  loadTodayStats()
})

onUnmounted(() => {
  stopCamera()
})

const loadActiveEvents = () => {
  // This would be loaded from props in a real implementation
  activeEvents.value = [
    {
      id: 1,
      title: 'Jazz Night Live',
      start_datetime: '2026-01-30T20:00:00',
      end_datetime: '2026-01-31T02:00:00',
      venue: 'Rooftop Bar'
    }
  ]
}

const loadTodayStats = () => {
  // This would be loaded from API in a real implementation
  todayStats.value = {
    total_tickets_checked_in: 45,
    total_tables_checked_in: 12,
    pending_tickets: 23,
    pending_tables: 8
  }
}

const startScanning = () => {
  isScanning.value = true
  scanResult.value = null
  initializeScanner()
}

const stopScanning = () => {
  isScanning.value = false
  scanResult.value = null
  stopCamera()
}

const initializeScanner = () => {
  // In a real implementation, this would initialize the camera and QR scanner
  // For now, we'll simulate with manual input
  setTimeout(() => {
    cameraActive.value = true
  }, 1000)
}

const stopCamera = () => {
  cameraActive.value = false
}

const onScanSuccess = (decodedText) => {
  if (!decodedText) return
  
  isLoading.value = true
  scanInput.value = decodedText
  
  // Simulate API call to validate QR code
  setTimeout(() => {
    validateQRCode(decodedText)
  }, 1000)
}

const validateQRCode = (qrCode) => {
  // Mock validation - in real implementation, this would call the API
  if (qrCode === 'ET_DEMO123') {
    // Valid ticket
    scanResult.value = {
      success: true,
      type: 'ticket',
      data: {
        id: 1,
        event_title: 'Jazz Night Live',
        ticket_type: 'VIP',
        guest_name: 'John Doe',
        guest_email: 'john@example.com',
        quantity: 2,
        unit_price: 15000,
        total_amount: 30000,
        event_venue: 'Rooftop Bar',
        event_datetime: '2026-01-30T20:00:00'
      }
    }
  } else if (qrCode === 'TR_DEMO456') {
    // Valid table reservation
    scanResult.value = {
      success: true,
      type: 'table',
      data: {
        id: 1,
        event_title: 'Jazz Night Live',
        guest_name: 'Jane Smith',
        guest_email: 'jane@example.com',
        table_number: 'T12',
        guest_count: 4,
        total_amount: 25000,
        special_requests: 'Window seat preferred',
        event_venue: 'Rooftop Bar',
        event_datetime: '2026-01-30T20:00:00'
      }
    }
  } else if (qrCode === 'ET_USED123') {
    // Already used ticket
    scanResult.value = {
      success: false,
      type: 'warning',
      message: 'Ticket already used',
      data: {
        checked_in_at: '2026-01-30 19:30:00',
        checked_in_by: 'Staff Member'
      }
    }
  } else {
    // Invalid QR code
    scanResult.value = {
      success: false,
      type: 'error',
      message: 'Invalid QR code'
    }
  }
  
  isLoading.value = false
}

const confirmCheckIn = () => {
  if (!scanResult.value?.success) return
  
  isLoading.value = true
  
  // Simulate API call to check in
  setTimeout(() => {
    scanResult.value = {
      success: true,
      type: 'success',
      message: scanResult.value.type === 'ticket' ? 
        'Ticket checked in successfully' : 
        'Table reservation checked in successfully',
      data: {
        checked_in_at: new Date().toLocaleString(),
        checked_in_by: 'Current Staff'
      }
    }
    
    isLoading.value = false
    loadTodayStats()
    
    // Auto reset after 3 seconds
    setTimeout(() => {
      stopScanning()
    }, 3000)
  }, 1000)
}

const formatDateTime = (dateTime) => {
  return new Date(dateTime).toLocaleString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN'
  }).format(amount)
}
</script>

<template>
  <ManagerLayout>
    <Head title="Event Check-In" />

    <div class="min-h-screen bg-gray-50 py-8">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-8">
          <div class="flex items-center justify-between">
            <div>
              <h1 class="text-3xl font-bold text-gray-900">Event Check-In</h1>
              <p class="text-gray-500 mt-1">Scan QR codes for tickets and table reservations</p>
            </div>
            <button
              v-if="!isScanning"
              @click="startScanning"
              class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700 transition-colors"
            >
              <Camera class="w-5 h-5" />
              Start Scanning
            </button>
            <button
              v-else
              @click="stopScanning"
              class="inline-flex items-center gap-2 px-6 py-3 bg-red-600 text-white font-medium rounded-xl hover:bg-red-700 transition-colors"
            >
              <X class="w-5 h-5" />
              Stop Scanning
            </button>
          </div>
        </div>

        <!-- Today's Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
          <div class="bg-white rounded-xl p-4 border border-gray-200">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-500">Tickets Checked In</p>
                <p class="text-2xl font-bold text-gray-900">{{ todayStats.total_tickets_checked_in }}</p>
              </div>
              <Ticket class="w-8 h-8 text-indigo-600" />
            </div>
          </div>
          <div class="bg-white rounded-xl p-4 border border-gray-200">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-500">Tables Checked In</p>
                <p class="text-2xl font-bold text-gray-900">{{ todayStats.total_tables_checked_in }}</p>
              </div>
              <Users class="w-8 h-8 text-green-600" />
            </div>
          </div>
          <div class="bg-white rounded-xl p-4 border border-gray-200">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-500">Pending Tickets</p>
                <p class="text-2xl font-bold text-orange-600">{{ todayStats.pending_tickets }}</p>
              </div>
              <Clock class="w-8 h-8 text-orange-600" />
            </div>
          </div>
          <div class="bg-white rounded-xl p-4 border border-gray-200">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-500">Pending Tables</p>
                <p class="text-2xl font-bold text-orange-600">{{ todayStats.pending_tables }}</p>
              </div>
              <Clock class="w-8 h-8 text-orange-600" />
            </div>
          </div>
        </div>

        <!-- Scanner Section -->
        <div v-if="isScanning" class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-8">
          <!-- Camera View -->
          <div class="relative bg-black aspect-video">
            <div v-if="!cameraActive" class="absolute inset-0 flex items-center justify-center">
              <div class="text-center">
                <div class="animate-spin rounded-full h-12 w-12 border-4 border-white border-t-transparent mx-auto mb-4"></div>
                <p class="text-white">Initializing camera...</p>
              </div>
            </div>
            
            <div v-else class="absolute inset-0 flex items-center justify-center">
              <div class="relative">
                <div class="absolute inset-0 bg-white/20 animate-pulse"></div>
                <div class="relative border-4 border-white rounded-2xl p-8">
                  <div class="text-center text-white">
                    <QrCode class="w-16 h-16 mx-auto mb-4" />
                    <p class="text-lg font-medium">Position QR code in frame</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Manual Input for Testing -->
            <div class="absolute bottom-4 left-4 right-4">
              <div class="bg-black/80 backdrop-blur-sm rounded-xl p-4">
                <label class="text-white text-sm font-medium mb-2 block">Manual Test QR Code:</label>
                <div class="flex gap-2">
                  <input
                    v-model="scanInput"
                    type="text"
                    placeholder="ET_DEMO123 (ticket), TR_DEMO456 (table), ET_USED123 (used)"
                    class="flex-1 px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-white/50"
                  />
                  <button
                    @click="onScanSuccess(scanInput)"
                    :disabled="!scanInput || isLoading"
                    class="px-4 py-2 bg-white text-black font-medium rounded-lg hover:bg-gray-100 transition-colors disabled:opacity-50"
                  >
                    Validate
                  </button>
                </div>
                <div class="text-xs text-white/60 mt-2">
                  Testing codes: ET_DEMO123, TR_DEMO456, ET_USED123
                </div>
              </div>
            </div>
          </div>

          <!-- Loading Overlay -->
          <div v-if="isLoading" class="absolute inset-0 bg-black/50 flex items-center justify-center z-10">
            <div class="bg-white rounded-xl p-6 flex flex-col items-center">
              <div class="animate-spin rounded-full h-8 w-8 border-2 border-gray-800 border-t-transparent mb-3"></div>
              <p class="text-gray-800 font-medium">Validating QR code...</p>
            </div>
          </div>
        </div>

        <!-- Result Section -->
        <div v-if="scanResult" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-8">
          <!-- Error/Warning Result -->
          <div v-if="!scanResult.success" class="text-center py-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full mb-4"
                 :class="{
                   'bg-red-100': scanResult.type === 'error',
                   'bg-yellow-100': scanResult.type === 'warning'
                 }">
              <component :is="scanResult.type === 'error' ? X : AlertTriangle" 
                        :class="scanResult.type === 'error' ? 'text-red-600' : 'text-yellow-600'"
                        class="w-8 h-8" />
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">{{ scanResult.message }}</h3>
            
            <!-- Additional info for used tickets -->
            <div v-if="scanResult.data" class="text-left bg-gray-50 rounded-xl p-4 mt-4 max-w-sm mx-auto">
              <p class="text-sm text-gray-600 mb-1">Checked in at:</p>
              <p class="font-medium text-gray-900">{{ scanResult.data.checked_in_at }}</p>
              <p class="text-sm text-gray-600 mb-1 mt-2">Checked in by:</p>
              <p class="font-medium text-gray-900">{{ scanResult.data.checked_in_by }}</p>
            </div>

            <button
              @click="startScanning"
              class="mt-6 inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700 transition-colors"
            >
              <Camera class="w-5 h-5" />
              Scan Another
            </button>
          </div>

          <!-- Success Result -->
          <div v-else-if="scanResult.type === 'success'" class="text-center py-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 mb-4">
              <CheckCircle class="w-8 h-8 text-green-600" />
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">{{ scanResult.message }}</h3>
            <p class="text-gray-600">Checked in at: {{ scanResult.data.checked_in_at }}</p>
            <p class="text-gray-600">Checked in by: {{ scanResult.data.checked_in_by }}</p>

            <button
              @click="startScanning"
              class="mt-6 inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700 transition-colors"
            >
              <Camera class="w-5 h-5" />
              Scan Another
            </button>
          </div>

          <!-- Valid QR Code (Ready to check in) -->
          <div v-else>
            <div class="flex items-center justify-between mb-6">
              <div class="flex items-center gap-3">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-green-100">
                  <Check class="w-6 h-6 text-green-600" />
                </div>
                <div>
                  <h3 class="text-xl font-bold text-gray-900">{{ scanResult.message }}</h3>
                  <p class="text-gray-500">{{ scanResult.type === 'ticket' ? 'Valid ticket' : 'Valid table reservation' }}</p>
                </div>
              </div>
              
              <button
                @click="confirmCheckIn"
                :disabled="isLoading"
                class="inline-flex items-center gap-2 px-6 py-3 bg-green-600 text-white font-medium rounded-xl hover:bg-green-700 transition-colors disabled:opacity-50"
              >
                <Check class="w-5 h-5" />
                Check In
              </button>
            </div>

            <!-- Details Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="space-y-4">
                <div>
                  <p class="text-sm text-gray-500 mb-1">Event</p>
                  <p class="font-semibold text-gray-900">{{ scanResult.data.event_title }}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-500 mb-1">{{ scanResult.type === 'ticket' ? 'Ticket Type' : 'Table Number' }}</p>
                  <p class="font-semibold text-gray-900">
                    {{ scanResult.type === 'ticket' ? scanResult.data.ticket_type : scanResult.data.table_number }}
                  </p>
                </div>
                <div>
                  <p class="text-sm text-gray-500 mb-1">Guest Name</p>
                  <p class="font-semibold text-gray-900">{{ scanResult.data.guest_name }}</p>
                  <p class="text-sm text-gray-600">{{ scanResult.data.guest_email }}</p>
                </div>
              </div>
              
              <div class="space-y-4">
                <div>
                  <p class="text-sm text-gray-500 mb-1">Venue</p>
                  <p class="font-semibold text-gray-900">{{ scanResult.data.event_venue }}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-500 mb-1">Event Date & Time</p>
                  <p class="font-semibold text-gray-900">{{ formatDateTime(scanResult.data.event_datetime) }}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-500 mb-1">{{ scanResult.type === 'ticket' ? 'Quantity / Total' : 'Guests / Total' }}</p>
                  <p class="font-semibold text-gray-900">
                    {{ scanResult.type === 'ticket' ? scanResult.data.quantity : scanResult.data.guest_count }} 
                    • {{ formatCurrency(scanResult.data.total_amount) }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Special Requests (Table Only) -->
            <div v-if="scanResult.type === 'table' && scanResult.data.special_requests" class="mt-6">
              <p class="text-sm text-gray-500 mb-2">Special Requests</p>
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                <p class="text-blue-800">{{ scanResult.data.special_requests }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Active Events -->
        <div v-if="!isScanning" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
          <h2 class="text-xl font-bold text-gray-900 mb-4">Active Events Today</h2>
          <div class="space-y-3">
            <div v-for="event in activeEvents" :key="event.id" class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
              <div>
                <h3 class="font-semibold text-gray-900">{{ event.title }}</h3>
                <p class="text-sm text-gray-600">{{ event.venue }} • {{ formatDateTime(event.start_datetime) }}</p>
              </div>
              <div class="flex items-center gap-2 text-sm text-green-600">
                <div class="w-2 h-2 bg-green-600 rounded-full animate-pulse"></div>
                Active
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </ManagerLayout>
</template>