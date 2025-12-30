<template>
  <GuestLayout>
    <div class="space-y-6 p-6">
      <!-- Room Header -->
      <div class="flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-bold">Room {{ room.number }}</h1>
          <p class="text-sm text-gray-500">Booking: {{ booking.guest_name }}</p>
          <p class="text-sm text-gray-500">Stay: {{ formatDate(booking.check_in) }} → {{ formatDate(booking.check_out) }}</p>
        </div>
        <div class="text-right">
          <OutstandingBill :accessToken="accessToken" />
          <button @click="showBillHistory = true" class="text-sm text-blue-600 underline">View Bill History</button>
        </div>
      </div>

      <!-- Service Buttons -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <button @click="requestService('cleaning')" class="service-btn bg-green-500 hover:bg-green-600">Cleaning</button>
        <button @click="requestService('kitchen')" class="service-btn bg-yellow-500 hover:bg-yellow-600">Kitchen</button>
        <button @click="requestService('bar')" class="service-btn bg-blue-500 hover:bg-blue-600">Bar</button>
        <button @click="openLaundryModal" class="service-btn bg-purple-500 hover:bg-purple-600 text-center">Laundry</button>

        </div>

      <!-- Action Buttons -->
      <div class="flex flex-wrap gap-4 mt-4">
        <button @click="showMaintenanceModal = true" class="btn-secondary">Report Maintenance Issue</button>
        <button @click="showExtendStayModal = true" class="btn-primary">Extend Stay</button>
      </div>

      <!-- Checkout -->
      <div class="mt-6 flex justify-end space-x-4">
        <button @click="checkout" :disabled="outstandingBill > 0" class="btn-danger">Checkout</button>
        <button @click="payBill" class="btn-secondary">Pay Bill</button>
      </div>

      <!-- Modals -->
      <Modal :show="showBillHistory" @close="showBillHistory = false">
        <template #title>Bill History</template>
        <template #content>
          <ul class="space-y-2">
            <li v-for="charge in billHistory" :key="charge.id">
              {{ charge.description }} — ₦{{ charge.amount }} — {{ formatDateTime(charge.created_at) }}
            </li>
          </ul>
        </template>
      </Modal>

      <Modal :show="showMaintenanceModal" @close="closeMaintenanceModal">
        <template #title>Report Maintenance Issue</template>
        <template #content>
          <form @submit.prevent="submitMaintenance" class="space-y-4">
            <div>
              <label class="block text-sm font-medium">Issue Type</label>
              <select v-model="maintenance.type" class="input-field">
                <option value="plumbing">Plumbing</option>
                <option value="electrical">Electrical</option>
                <option value="furniture">Furniture</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium">Description</label>
              <textarea v-model="maintenance.description" class="input-field" rows="3"></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium">Photo (optional)</label>
              <input type="file" @change="handleFileUpload" />
            </div>
            <div class="flex justify-end gap-3">
              <button type="button" @click="closeMaintenanceModal" class="btn-secondary">Cancel</button>
              <button type="submit" class="btn-primary">Submit Issue</button>
            </div>
          </form>
        </template>
      </Modal>

      <Modal :show="showExtendStayModal" @close="closeExtendStayModal">
        <template #title>Extend Stay</template>
        <template #content>
          <form @submit.prevent="submitExtendStay" class="space-y-4">
            <div>
              <label class="block text-sm font-medium">New Checkout Date</label>
              <input type="date" v-model="extensionDate" class="input-field" required />
            </div>
            <div class="flex justify-end gap-3">
              <button type="button" @click="closeExtendStayModal" class="btn-secondary">Cancel</button>
              <button type="submit" class="btn-primary">Confirm Extension</button>
            </div>
          </form>
        </template>
      </Modal>

      <LaundryModal
        v-if="showLaundryModal"
        :room="room"
        :booking="booking"
        :items="laundryItems"
        :accessToken="accessToken"
        :show="showLaundryModal"
        @close="showLaundryModal = false"
      />

    </div>
  </GuestLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import axios from 'axios'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import Modal from '@/Components/Modal.vue'
import OutstandingBill from '@/Pages/Guest/OutstandingBill.vue'

//Laundry Modal
import LaundryModal from '@/Pages/Guest/LaundryModal.vue'


const showLaundryModal = ref(false)
const laundryItems = ref([]) // This will hold the items fetched from the backend

//End Laundry Modal



const props = defineProps({
  room: Object,
  booking: Object,
  outstandingBill: Number,
  accessToken: String,
  laundryItems: Array,
})


console.log(props.booking)

//console.log(props.accessToken)

/* ---------------- UI STATE ---------------- */
const showBillHistory = ref(false)
const showMaintenanceModal = ref(false)
const showExtendStayModal = ref(false)
const extensionDate = ref('')

/* ---------------- EXTEND STAY ---------------- */
function closeExtendStayModal() {
  showExtendStayModal.value = false
  extensionDate.value = ''
}
function submitExtendStay() {
  router.post(`/guest/room/${props.accessToken}/extend-stay`, { new_checkout: extensionDate.value }, { onSuccess: closeExtendStayModal })
}

/* ---------------- MAINTENANCE ---------------- */
const maintenance = reactive({ type: 'plumbing', description: '', file: null })
function handleFileUpload(e) { maintenance.file = e.target.files[0] }
function closeMaintenanceModal() {
  showMaintenanceModal.value = false
  maintenance.type = 'plumbing'
  maintenance.description = ''
  maintenance.file = null
}
function submitMaintenance() {
  const formData = new FormData()
  formData.append('type', maintenance.type)
  formData.append('description', maintenance.description)
  if (maintenance.file) formData.append('file', maintenance.file)
  router.post(`/guest/room/${props.accessToken}/maintenance`, formData, { onSuccess: closeMaintenanceModal })
}

//LAUNDRY
function openLaundryModal() {
  // Use the items passed from the backend
  laundryItems.value = props.laundryItems || []
  showLaundryModal.value = true
}

//BILL HISTORY
// 1. Define the reactive variable that was missing
const billHistory = ref([]) 

// 2. Fetch the data from your new controller method
async function fetchBillHistory() {
  try {
    const response = await axios.get(`/guest/room/${props.accessToken}/bill-history`)
    // Your controller returns { history: [...] }, so we access .history
    billHistory.value = response.data.history
  } catch (error) {
    console.error("Failed to load bill history:", error)
  }
}

// 3. Trigger the fetch when the page loads
onMounted(() => {
  fetchBillHistory()
})

/* ---------------- ACTIONS ---------------- */
//function requestService(type) { router.post(`/guest/room/${props.accessToken}/service-request`, { type }) }
function checkout() { router.post(`/guest/room/${props.accessToken}/checkout`) }
function payBill() { router.post(`/guest/room/${props.accessToken}/payment`) }

function formatDate(date) { return new Date(date).toLocaleDateString() }
function formatDateTime(date) { return new Date(date).toLocaleString() }
</script>

<style scoped>
.service-btn { padding: 1rem; color: white; font-weight: bold; border-radius: 0.5rem; }
.input-field { width: 100%; border: 1px solid #ddd; padding: 0.5rem; border-radius: 0.375rem; }
.btn-primary { background-color: #2563eb; color: white; padding: 0.5rem 1rem; border-radius: 0.375rem; }
.btn-secondary { background-color: #9ca3af; color: white; padding: 0.5rem 1rem; border-radius: 0.375rem; }
.btn-danger { background-color: #dc2626; color: white; padding: 0.5rem 1rem; border-radius: 0.375rem; }
</style>
