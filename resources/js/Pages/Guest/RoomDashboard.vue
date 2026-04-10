<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue'
import { router, Head } from '@inertiajs/vue3'
import axios from 'axios'
import Echo from 'laravel-echo'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import Modal from '@/Components/Modal.vue'
import OutstandingBill from '@/Pages/Guest/OutstandingBill.vue'
import LaundryModal from '@/Pages/Guest/LaundryModal.vue'
import { 
  Sparkles, Utensils, Wine, WashingMachine, Wrench, 
  CalendarPlus, LogOut, CreditCard, History, 
  ChevronRight, Clock, Receipt, Plus, Minus, Camera, X
} from 'lucide-vue-next'

const props = defineProps({
  room: Object,
  booking: Object,
  outstandingBill: Number,
  accessToken: String,
  laundryItems: Array,
  cleaningStatus: String,
  orders: Array,
  showOrders: Boolean,
})


/* ---------------- UI STATE ---------------- */

const orders = ref([...props.orders])

const showLaundryModal = ref(false)
const showBillHistory = ref(false)
const showMaintenanceModal = ref(false)
const showExtendStayModal = ref(false)
const extensionDate = ref('')
const billHistory = ref([]) 

const showOrdersHistory = ref(false)
const currentCleaningStatus = ref(props.cleaningStatus)
const isSubmittingMaintenance = ref(false)
//const orders = ref([])

//console.log(props.orders);

const maintenance = reactive({ type: 'plumbing', description: '', file: null })
const cleaningRequested = computed(() => currentCleaningStatus.value === 'cleaner_requested')

/* ---------------- DATA FETCHING ---------------- */
async function fetchBillHistory() {
  try {
    const response = await axios.get(`/guest/room/${props.accessToken}/bill-history`)
    billHistory.value = response.data.history
  } catch (error) {
    console.error("Failed to load bill history:", error)
  }
}

onMounted(() => {
  fetchBillHistory()
})
onMounted(() => {
  window.Echo.channel('orders')
    .listen('OrderCreated', e => orders.value.unshift(e.order))
    .listen('OrderStatusUpdated', e => {
      const i = orders.value.find(o => o.id === e.order.id)
      if (i) Object.assign(i, e.order)
    })
})

onMounted(() => {
  if (props.showOrders) {
    showOrdersHistory.value = true
  }
})

watch(
  () => props.showOrders,
  (val) => {
    if (val) showOrdersHistory.value = true
  },
  { immediate: true }
)

watch(
  () => props.cleaningStatus,
  (status) => {
    currentCleaningStatus.value = status
  }
)


/* ---------------- ACTIONS ---------------- */
function openMenu(type) {
  router.visit(`/guest/room/${props.accessToken}/menu/${type}`)
}

function requestService(type, notes = '') {
  if (type === 'cleaning' && cleaningRequested.value) {
    return
  }

  router.post(
    `/guest/room/${props.accessToken}/service-request`,
    { type, notes },
    {
      preserveScroll: true,
      onSuccess: () => {
        if (type === 'cleaning') {
          currentCleaningStatus.value = 'cleaner_requested'
        }
      },
    }
  )
}

// Maintenance Logic
function handleFileUpload(e) { maintenance.file = e.target.files[0] }
function closeMaintenanceModal() {
  showMaintenanceModal.value = false
  maintenance.type = 'plumbing'
  maintenance.description = ''
  maintenance.file = null
}
function submitMaintenance() {
  if (isSubmittingMaintenance.value) {
    return
  }

  const formData = new FormData()
  formData.append('type', maintenance.type)
  formData.append('description', maintenance.description)
  if (maintenance.file) formData.append('file', maintenance.file)

  isSubmittingMaintenance.value = true

  router.post(`/guest/room/${props.accessToken}/maintenance`, formData, {
    preserveScroll: true,
    onSuccess: closeMaintenanceModal,
    onFinish: () => {
      isSubmittingMaintenance.value = false
    },
  })
}

// Extend Stay Logic
function closeExtendStayModal() {
  showExtendStayModal.value = false
  extensionDate.value = ''
}
function submitExtendStay() {
  router.post(`/guest/room/${props.accessToken}/extend-stay`, 
    { new_checkout: extensionDate.value }, 
    { onSuccess: closeExtendStayModal }
  )
}

function checkout() { router.post(`/guest/room/${props.accessToken}/checkout`) }
async function payBill() {
  try {
    const response = await axios.post(
      `/guest/room/${props.accessToken}/payment`,
      { amount: props.outstandingBill }
    )

    // OPTIONAL: refresh page data
    router.reload({ preserveScroll: true })
  } catch (error) {
    console.error(error.response?.data || error)
  }
}


function cancelOrder(order) {
  if (!order.can_be_cancelled) return

  if (!confirm('Are you sure you want to cancel this order?')) return

  router.post(
    `/guest/room/${props.accessToken}/orders/${order.id}/cancel`,
    {},
    {
      preserveScroll: true,
      onSuccess: () => {
        // Optimistic UI update
        order.status = 'cancelled'
        order.can_be_cancelled = false
      },
    }
  )
}



function formatDate(date) { 
  return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) 
}
</script>

<template>
  <GuestLayout>
    <Head title="Your Stay" />
    
    <div class="max-w-xl mx-auto space-y-8 pb-32">
      
      <div class="bg-slate-900 text-white p-8 rounded-b-[3rem] shadow-2xl -mt-6 -mx-6">
        <div class="flex justify-between items-start mb-6">
          <div>
            <p class="text-indigo-400 text-[10px] font-black uppercase tracking-[0.2em] mb-1">Current Residence</p>
            <h1 class="text-4xl font-black tracking-tight">Room {{ room.name || room.name }}</h1>
          </div>
          <div class="bg-white/10 p-3 rounded-2xl backdrop-blur-md">
            <Sparkles class="w-6 h-6 text-indigo-300" />
          </div>
        </div>

        <div class="space-y-1 opacity-90">
          <p class="text-lg font-bold">{{ booking.guest_name }}</p>
          <div class="flex items-center gap-2 text-xs font-medium text-slate-400">
            <Clock class="w-3.5 h-3.5" />
            {{ formatDate(booking.check_in) }} — {{ formatDate(booking.check_out) }}
          </div>
        </div>
      </div>

      <div class="bg-white rounded-[2rem] p-6 shadow-sm border border-slate-100 flex items-center justify-between">
        <div class="space-y-1">
          <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Outstanding Bill</p>
          <OutstandingBill :accessToken="accessToken" />
        </div>
        <button @click="showOrdersHistory = true" class="flex items-center gap-2 px-4 py-2 bg-slate-50 rounded-xl text-[10px] font-black uppercase text-slate-600 hover:bg-slate-100 transition-all">
          <Receipt class="w-3.5 h-3.5" /> Orders
        </button>
        <button @click="showBillHistory = true" class="flex items-center gap-2 px-4 py-2 bg-slate-50 rounded-xl text-[10px] font-black uppercase text-slate-600 hover:bg-slate-100 transition-all">
          <History class="w-3.5 h-3.5" /> History
        </button>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <button @click="openMenu('kitchen')" class="group flex flex-col items-center justify-center p-6 bg-amber-50 rounded-[2rem] border border-amber-100 transition-all active:scale-95">
          <div class="p-3 bg-white rounded-2xl text-amber-600 shadow-sm mb-3 group-hover:scale-110 transition-transform">
            <Utensils class="w-6 h-6" />
          </div>
          <span class="text-xs font-black uppercase tracking-widest text-amber-700">Kitchen</span>
        </button>

        <button @click="openMenu('bar')" class="group flex flex-col items-center justify-center p-6 bg-indigo-50 rounded-[2rem] border border-indigo-100 transition-all active:scale-95">
          <div class="p-3 bg-white rounded-2xl text-indigo-600 shadow-sm mb-3 group-hover:scale-110 transition-transform">
            <Wine class="w-6 h-6" />
          </div>
          <span class="text-xs font-black uppercase tracking-widest text-indigo-700">The Bar</span>
        </button>

        <button
          @click="requestService('cleaning')"
          :disabled="cleaningRequested"
          class="group flex flex-col items-center justify-center p-6 rounded-[2rem] border transition-all active:scale-95"
          :class="cleaningRequested ? 'bg-slate-100 border-slate-200 cursor-not-allowed' : 'bg-emerald-50 border-emerald-100 hover:bg-emerald-100'"
        >
          <div class="p-3 bg-white rounded-2xl shadow-sm mb-3 transition-transform" :class="cleaningRequested ? 'text-slate-400' : 'text-emerald-600 group-hover:scale-110'">
            <Sparkles class="w-6 h-6" />
          </div>
          <span class="text-xs font-black uppercase tracking-widest" :class="cleaningRequested ? 'text-slate-500' : 'text-emerald-700'">
            {{ cleaningRequested ? 'Requested' : 'Cleaning' }}
          </span>
        </button>

        <button @click="showLaundryModal = true" class="group flex flex-col items-center justify-center p-6 bg-purple-50 rounded-[2rem] border border-purple-100 transition-all active:scale-95">
          <div class="p-3 bg-white rounded-2xl text-purple-600 shadow-sm mb-3 group-hover:scale-110 transition-transform">
            <WashingMachine class="w-6 h-6" />
          </div>
          <span class="text-xs font-black uppercase tracking-widest text-purple-700">Laundry</span>
        </button>
      </div>

      <div class="space-y-3">
        <button @click="showMaintenanceModal = true" class="w-full flex items-center justify-between p-5 bg-white border border-slate-100 rounded-2xl group active:scale-[0.98] transition-all">
          <div class="flex items-center gap-4">
            <div class="p-2 bg-rose-50 text-rose-500 rounded-lg"><Wrench class="w-5 h-5" /></div>
            <span class="text-sm font-bold text-slate-700">Report Maintenance Issue</span>
          </div>
          <ChevronRight class="w-4 h-4 text-slate-300 group-hover:text-rose-500 transition-colors" />
        </button>

        <button @click="showExtendStayModal = true" class="w-full flex items-center justify-between p-5 bg-white border border-slate-100 rounded-2xl group active:scale-[0.98] transition-all">
          <div class="flex items-center gap-4">
            <div class="p-2 bg-indigo-50 text-indigo-600 rounded-lg"><CalendarPlus class="w-5 h-5" /></div>
            <span class="text-sm font-bold text-slate-700">Extend Your Stay</span>
          </div>
          <ChevronRight class="w-4 h-4 text-slate-300 group-hover:text-indigo-500 transition-colors" />
        </button>
      </div>

      <div class="fixed bottom-0 left-0 right-0 p-6 bg-white/80 backdrop-blur-lg border-t border-slate-100 flex gap-4 z-50">
        <button
          @click="payBill"
          :disabled="outstandingBill <= 0"
          class="flex-1 py-4 rounded-2xl font-black text-sm uppercase tracking-widest shadow-xl transition-all
                active:scale-95
                disabled:bg-slate-200 disabled:text-slate-400 disabled:shadow-none disabled:cursor-not-allowed
                bg-slate-900 text-white shadow-slate-200"
        >
          <div class="flex items-center justify-center gap-2">
            <CreditCard class="w-4 h-4" />
            {{ outstandingBill > 0 ? 'Pay Bill' : 'Paid' }}
          </div>
        </button>

        <button @click="checkout" :disabled="outstandingBill > 0" class="flex-1 py-4 bg-rose-600 disabled:bg-slate-200 text-white rounded-2xl font-black text-sm uppercase tracking-widest shadow-xl shadow-rose-100 active:scale-95 transition-all">
          <div class="flex items-center justify-center gap-2">
            <LogOut class="w-4 h-4" /> Checkout
          </div>
        </button>
      </div>

      <Modal :show="showBillHistory" @close="showBillHistory = false">
        <template #title>
          <div class="flex items-center gap-2">
            <History class="w-5 h-5 text-indigo-600" /> 
            <span>Statement of Account</span>
          </div>
        </template>
        <template #content>
          <div class="space-y-4">
            <div v-if="billHistory && billHistory.length > 0" class="space-y-3 max-h-[50vh] overflow-y-auto pr-2">
              <div v-for="charge in billHistory" :key="charge.id" class="flex justify-between items-center p-4 bg-slate-50 rounded-2xl border border-slate-100">
                <div class="space-y-0.5">
                  <p class="font-bold text-slate-800 text-sm">{{ charge.description }}</p>
                  <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tighter">{{ new Date(charge.created_at).toLocaleString() }}</p>
                </div>
                <p class="font-black text-slate-900">₦{{ Number(charge.amount).toLocaleString() }}</p>
              </div>
            </div>
            <div v-else class="py-10 text-center text-slate-400 italic">No transactions found.</div>
          </div>
        </template>
      </Modal>

      <Modal :show="showOrdersHistory" @close="showOrdersHistory = false">
        <template #title>
          <div class="flex items-center gap-2">
            <div class="p-2 bg-indigo-50 rounded-lg">
              <History class="w-5 h-5 text-indigo-600" />
            </div>
            <div>
              <h2 class="text-xl font-black text-slate-900 tracking-tight">Order History</h2>
              <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Kitchen & Bar</p>
            </div>
          </div>
        </template>

        <template #content>
          <div class="space-y-6 py-2">
            <div v-if="orders && orders.length > 0" class="space-y-4 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar">
              <div 
                v-for="order in orders" 
                :key="order.id" 
                class="bg-slate-50 border border-slate-100 rounded-[2rem] overflow-hidden transition-all hover:bg-white hover:shadow-md group"
              >
                <div class="px-5 py-4 flex justify-between items-center border-b border-white/50">
                  <div>
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em]">Code - {{order.service_area}}</p>
                    <p class="font-black text-slate-900">{{ order.order_code }}</p>
                  </div>
                  <button
                    v-if="order.can_be_cancelled"
                    @click="cancelOrder(order)"
                    class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-tight
                          bg-red-100 text-red-700 hover:bg-red-200 active:scale-95 transition"
                  >
                    Cancel Order
                  </button>
                  
                  <div :class="[
                  'px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-tighter',
                  order.status === 'delivered'
                    ? 'bg-emerald-100 text-emerald-700'
                    : order.status === 'cancelled'
                    ? 'bg-rose-100 text-rose-700'
                    : 'bg-amber-100 text-amber-700'
                ]">
                  {{ order.status }}
                </div>

                </div>

                <div class="px-5 py-4 space-y-2">
                  <div v-for="item in order.items" :key="item.id" class="flex justify-between items-center">
                    <div class="flex items-center gap-2">
                      <span class="w-5 h-5 flex items-center justify-center bg-white rounded-md text-[10px] font-black text-slate-400 border border-slate-100">
                        {{ item.qty }}
                      </span>
                      <span class="text-sm font-bold text-slate-600">{{ item.item_name }}</span>
                    </div>
                    <span class="text-xs font-bold text-slate-400">₦{{ Number(item.price * item.qty).toLocaleString() }}</span>
                  </div>
                </div>

                <div class="px-5 py-3 bg-slate-900/5 flex justify-between items-center">
                  <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Amount</span>
                  <span class="text-lg font-black text-slate-900 italic">
                    ₦{{ Number(order.total).toLocaleString() }}
                  </span>
                </div>
              </div>
            </div>

            <div v-else class="py-12 text-center">
              <div class="inline-flex p-5 bg-slate-50 rounded-full text-slate-200 mb-4">
                <Utensils class="w-10 h-10" />
              </div>
              <p class="text-slate-500 font-bold italic">You haven't placed any orders yet.</p>
            </div>
          </div>
        </template>
      </Modal>

      <Modal :show="showMaintenanceModal" @close="closeMaintenanceModal">
        <template #title>
          <div class="flex items-center gap-2">
            <Wrench class="w-5 h-5 text-rose-500" /> 
            <span>Maintenance Report</span>
          </div>
        </template>
        <template #content>
          <form @submit.prevent="submitMaintenance" class="space-y-5">
            <div>
              <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Issue Category</label>
              <select v-model="maintenance.type" class="w-full bg-slate-50 border-slate-100 rounded-xl font-bold">
                <option value="plumbing">Plumbing</option>
                <option value="electrical">Electrical</option>
                <option value="furniture">Furniture</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div>
              <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Description</label>
              <textarea v-model="maintenance.description" rows="3" class="w-full bg-slate-50 border-slate-100 rounded-xl" placeholder="Describe the issue..."></textarea>
            </div>
            <div>
              <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Attach Photo</label>
              <input type="file" @change="handleFileUpload" class="text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-black file:bg-rose-50 file:text-rose-700 hover:file:bg-rose-100" />
            </div>
            <div class="flex gap-3">
              <button type="button" @click="closeMaintenanceModal" :disabled="isSubmittingMaintenance" class="flex-1 py-3 bg-slate-100 text-slate-500 rounded-xl font-black text-[10px] uppercase disabled:opacity-60 disabled:cursor-not-allowed">Cancel</button>
              <button type="submit" :disabled="isSubmittingMaintenance" class="flex-1 py-3 bg-rose-600 text-white rounded-xl font-black text-[10px] uppercase disabled:bg-rose-300 disabled:cursor-not-allowed">
                {{ isSubmittingMaintenance ? 'Submitting...' : 'Submit Report' }}
              </button>
            </div>
          </form>
        </template>
      </Modal>

      <Modal :show="showExtendStayModal" @close="closeExtendStayModal">
        <template #title>
          <div class="flex items-center gap-2">
            <CalendarPlus class="w-5 h-5 text-indigo-600" /> 
            <span>Extend Your Stay</span>
          </div>
        </template>
        <template #content>
          <form @submit.prevent="submitExtendStay" class="space-y-5">
            <div>
              <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">New Checkout Date</label>
              <input type="date" v-model="extensionDate" required class="w-full bg-slate-50 border-slate-100 rounded-xl font-bold" />
            </div>
            <div class="flex gap-3">
              <button type="button" @click="closeExtendStayModal" class="flex-1 py-3 bg-slate-100 text-slate-500 rounded-xl font-black text-[10px] uppercase">Cancel</button>
              <button type="submit" class="flex-1 py-3 bg-indigo-600 text-white rounded-xl font-black text-[10px] uppercase">Confirm</button>
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
