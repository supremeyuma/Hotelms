<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import {
  Camera, X, Ticket, Users, Clock,
  QrCode, Check, CheckCircle, AlertTriangle
} from 'lucide-vue-next'
import axios from 'axios'

defineProps({
  activeEvents: {
    type: Array,
    default: () => [],
  },
})

/* ================= STATE ================= */
const isScanning = ref(false)
const cameraActive = ref(false)
const isLoading = ref(false)

const scanInput = ref('')
const scanResult = ref(null)

const todayStats = ref({
  total_tickets_checked_in: 0,
  total_tables_checked_in: 0,
  pending_tickets: 0,
  pending_tables: 0,
})

/* ================= LIFECYCLE ================= */
onMounted(loadTodayStats)
onUnmounted(() => {
  stopCamera()
})

/* ================= DASHBOARD ================= */
async function loadTodayStats() {
  try {
    const { data } = await axios.get(route('events.check-in.stats'))
    todayStats.value = data
  } catch {
    // fail silently in production UI
  }
}

/* ================= CAMERA ================= */
function stopCamera() {
  cameraActive.value = false
}

/* ================= SCANNING ================= */
function startScanning() {
  isScanning.value = true
  scanResult.value = null
  scanInput.value = ''

  setTimeout(() => {
    cameraActive.value = true
  }, 700)
}

function stopScanning() {
  isScanning.value = false
  scanResult.value = null
  scanInput.value = ''
  stopCamera()
}

async function validateQRCode() {
  if (!scanInput.value) return

  isLoading.value = true
  try {
    const { data } = await axios.post(
      route('events.check-in.validate'),
      { qr_code: scanInput.value }
    )
    scanResult.value = data
  } catch {
    scanResult.value = {
      success: false,
      type: 'error',
      message: 'Failed to validate QR code',
    }
  } finally {
    isLoading.value = false
  }
}

async function confirmCheckIn() {
  if (!scanResult.value?.success) return

  isLoading.value = true
  try {
    const { data } = await axios.post(
      route('events.check-in.process'),
      { qr_code: scanInput.value }
    )

    scanResult.value = data
    await loadTodayStats()

    setTimeout(stopScanning, 3000)
  } catch {
    scanResult.value = {
      success: false,
      type: 'error',
      message: 'Failed to check in',
    }
  } finally {
    isLoading.value = false
  }
}

/* ================= HELPERS ================= */
const formatDateTime = d =>
  new Date(d).toLocaleString('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })

const formatCurrency = a =>
  new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
  }).format(a)
</script>

<template>
  <ManagerLayout>
    <Head title="Event Check-In" />

    <div class="min-h-screen bg-gray-50 py-8">
      <div class="max-w-5xl mx-auto px-4">

        <!-- HEADER -->
        <div class="bg-white rounded-2xl border p-6 mb-8 flex justify-between items-center">
          <div>
            <h1 class="text-3xl font-bold text-gray-900">Event Check-In</h1>
            <p class="text-gray-500">Tickets & table reservations</p>
          </div>

          <button
            v-if="!isScanning"
            @click="startScanning"
            class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 text-white rounded-xl"
          >
            <Camera class="w-5 h-5" />
            Start Scanning
          </button>

          <button
            v-else
            @click="stopScanning"
            class="inline-flex items-center gap-2 px-6 py-3 bg-red-600 text-white rounded-xl"
          >
            <X class="w-5 h-5" />
            Stop
          </button>
        </div>

        <!-- DASHBOARD -->
        <div v-if="!isScanning">
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <Stat title="Tickets Checked In" :value="todayStats.total_tickets_checked_in">
              <Ticket class="w-8 h-8 text-indigo-600" />
            </Stat>

            <Stat title="Tables Checked In" :value="todayStats.total_tables_checked_in">
              <Users class="w-8 h-8 text-green-600" />
            </Stat>

            <Stat title="Pending Tickets" :value="todayStats.pending_tickets" warning>
              <Clock class="w-8 h-8 text-orange-600" />
            </Stat>

            <Stat title="Pending Tables" :value="todayStats.pending_tables" warning>
              <Clock class="w-8 h-8 text-orange-600" />
            </Stat>
          </div>

          <div class="bg-white rounded-2xl border p-6">
            <h2 class="text-xl font-bold mb-4">Active Events Today</h2>

            <div v-if="activeEvents.length" class="space-y-3">
              <div
                v-for="event in activeEvents"
                :key="event.id"
                class="flex justify-between items-center p-4 bg-gray-50 rounded-xl"
              >
                <div>
                  <p class="font-semibold">{{ event.title }}</p>
                  <p class="text-sm text-gray-600">
                    {{ event.venue }} • {{ formatDateTime(event.start_datetime) }}
                  </p>
                </div>
                <span class="text-green-600 flex items-center gap-2">
                  <span class="w-2 h-2 bg-green-600 rounded-full animate-pulse"></span>
                  Active
                </span>
              </div>
            </div>

            <p v-else class="text-gray-500 text-center py-6">
              No active events
            </p>
          </div>
        </div>

        <!-- SCANNER -->
        <div v-if="isScanning" class="bg-white rounded-2xl border overflow-hidden">
          <div class="relative bg-black aspect-video flex items-center justify-center">
            <div v-if="!cameraActive" class="text-white">Initializing camera…</div>
            <QrCode v-else class="w-20 h-20 text-white" />

            <div class="absolute bottom-4 left-4 right-4 bg-black/70 p-4 rounded-xl">
              <input
                v-model="scanInput"
                class="w-full px-3 py-2 rounded-lg bg-white/10 text-white"
                placeholder="Scan or paste QR code"
              />
              <button
                @click="validateQRCode"
                :disabled="isLoading"
                class="mt-3 w-full bg-white text-black py-2 rounded-lg"
              >
                Validate
              </button>
            </div>
          </div>

          <div v-if="scanResult" class="p-6 border-t">
            <div v-if="!scanResult.success" class="text-center">
              <AlertTriangle class="w-10 h-10 mx-auto text-yellow-600" />
              <p class="mt-4 font-semibold">{{ scanResult.message }}</p>
            </div>

            <div v-else-if="scanResult.type === 'ready'" class="flex justify-between items-center">
              <div>
                <p class="font-bold">{{ scanResult.data.event_title }}</p>
                <p class="text-sm text-gray-500">{{ scanResult.data.guest_name }}</p>
              </div>
              <button
                @click="confirmCheckIn"
                class="bg-green-600 text-white px-6 py-3 rounded-xl flex gap-2"
              >
                <Check /> Check In
              </button>
            </div>

            <div v-else-if="scanResult.type === 'success'" class="text-center">
              <CheckCircle class="w-12 h-12 mx-auto text-green-600" />
              <p class="mt-4 font-bold">{{ scanResult.message }}</p>
            </div>
          </div>
        </div>

      </div>
    </div>
  </ManagerLayout>
</template>

<script>
export default {
  components: {
    Stat: {
      props: ['title', 'value', 'warning'],
      template: `
        <div class="bg-white rounded-xl p-4 border">
          <div class="flex justify-between items-center">
            <div>
              <p class="text-sm text-gray-500">{{ title }}</p>
              <p :class="['text-2xl font-bold', warning ? 'text-orange-600' : 'text-gray-900']">
                {{ value }}
              </p>
            </div>
            <slot />
          </div>
        </div>
      `,
    },
  },
}
</script>
