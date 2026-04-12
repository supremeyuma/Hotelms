<script setup>
import FrontDeskLayout from '@/Layouts/Staff/FrontDeskLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { 
  WashingMachine, 
  Clock, 
  Camera, 
  History, 
  MapPin, 
  Tag,
  ChevronDown,
  User,
  PackageCheck,
  Loader
} from 'lucide-vue-next'
import { ref } from 'vue'

const props = defineProps({
  requests: Array
})

const expandedHistory = ref({})
const showStatusModal = ref(false)
const selectedOrder = ref(null)
const selectedStatus = ref('')
const isLoading = ref(false)

const toggleHistory = (id) => {
  expandedHistory.value[id] = !expandedHistory.value[id]
}

const getStatusTheme = (status) => {
  const themes = {
    'pending': 'bg-amber-100 text-amber-700 border-amber-200',
    'processing': 'bg-indigo-100 text-indigo-700 border-indigo-200',
    'ready': 'bg-emerald-100 text-emerald-700 border-emerald-200',
    'delivered': 'bg-slate-100 text-slate-500 border-slate-200'
  }
  return themes[status] || 'bg-slate-100 text-slate-600'
}

const getAvailableStatuses = (currentStatus) => {
  const transitions = {
    'pending': ['processing', 'cancelled'],
    'processing': ['ready', 'pending', 'cancelled'],
    'ready': ['delivered', 'processing', 'cancelled'],
    'delivered': ['cancelled'],
    'cancelled': []
  }
  return transitions[currentStatus] || []
}

function formatDate(dateString) {
  return new Date(dateString).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

const handlePrintTag = (order) => {
  window.open(route('frontdesk.laundry.print', { order: order.id }), '_blank')
}

const openStatusModal = (order) => {
  selectedOrder.value = order
  selectedStatus.value = ''
  showStatusModal.value = true
}

const closeStatusModal = () => {
  showStatusModal.value = false
  selectedOrder.value = null
  selectedStatus.value = ''
}

const submitStatusUpdate = () => {
  if (!selectedOrder.value || !selectedStatus.value) {
    return
  }

  isLoading.value = true

  router.post(
    route('frontdesk.laundry.updateStatus', { order: selectedOrder.value.id }),
    { status: selectedStatus.value },
    {
      onFinish: () => {
        isLoading.value = false
        closeStatusModal()
      }
    }
  )
}
</script>

<template>
  <FrontDeskLayout>
    <Head title="Laundry Operations" />
</script>

<template>
  <FrontDeskLayout>
    <Head title="Laundry Operations" />

    <div class="p-8 max-w-6xl mx-auto space-y-8">
      <div class="flex items-center justify-between mb-10">
        <div>
          <h1 class="text-4xl font-black text-slate-900 tracking-tight flex items-center gap-4">
            Laundry Service
            <span class="bg-indigo-600 text-white text-xs px-4 py-1.5 rounded-full font-black uppercase tracking-widest">
              {{ requests.length }} Active Tasks
            </span>
          </h1>
          <p class="text-slate-500 font-medium mt-1">Manage guest garment care and delivery status.</p>
        </div>
        <div class="p-4 bg-white rounded-3xl shadow-sm border border-slate-100 flex items-center gap-3">
            <WashingMachine class="w-6 h-6 text-indigo-600 animate-spin-slow" />
            <span class="text-sm font-black text-slate-700 uppercase tracking-tight">System Live</span>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6">
        <div v-for="req in requests" :key="req.id" 
             class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden transition-all hover:shadow-md">
          
          <div class="p-8">
            <div class="flex flex-col lg:flex-row justify-between gap-8">
              
              <div class="flex-1 space-y-4">
                <div class="flex items-center gap-3">
                  <div class="px-4 py-1.5 rounded-xl border-2 font-black text-xs uppercase tracking-widest" :class="getStatusTheme(req.status)">
                    {{ req.status }}
                  </div>
                  <span class="text-slate-300 font-bold">#{{ req.requestable.order_code }}</span>
                </div>

                <h2 class="text-2xl font-black text-slate-900">
                  Room {{ req.requestable.room.name }} 
                  <span class="text-slate-400 font-medium mx-2">—</span>
                  {{ req.requestable.booking?.guest_name || 'Guest' }}
                </h2>

                <div class="flex flex-wrap gap-4">
                  <div class="flex items-center gap-2 text-slate-500 font-bold text-sm">
                    <Clock class="w-4 h-4" /> Posted {{ formatDate(req.created_at) }}
                  </div>
                  <div class="flex items-center gap-2 text-slate-500 font-bold text-sm">
                    <Tag class="w-4 h-4" /> ₦{{ req.requestable.total_price?.toLocaleString() || '0.00' }}
                  </div>
                </div>
              </div>

              <div class="lg:w-80 bg-slate-50 rounded-3xl p-6">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                  <PackageCheck class="w-3 h-3" /> Inventory List
                </p>
                <ul class="space-y-3">
                  <li v-for="item in req.requestable.items" :key="item.id" class="flex justify-between items-center text-sm">
                    <span class="font-bold text-slate-700">
                        <span class="text-indigo-600">{{ item.quantity }}x</span> {{ item.item.name }}
                    </span>
                    <span class="text-slate-400 font-black text-[10px]">₦{{ item.subtotal }}</span>
                  </li>
                </ul>
              </div>
            </div>

            <div v-if="req.requestable.images?.length" class="mt-8 pt-8 border-t border-slate-50">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                  <Camera class="w-3 h-3" /> Condition Verification
                </p>
                <div class="flex flex-wrap gap-3">
                    <div v-for="img in req.requestable.images" :key="img.id" class="relative group">
                        <img :src="`/storage/${img.path}`" 
                             class="h-24 w-24 object-cover rounded-2xl shadow-sm border-2 border-white transition-transform group-hover:scale-105" />
                    </div>
                </div>
            </div>

            <div class="mt-8 pt-4">
                <button @click="toggleHistory(req.id)" class="flex items-center gap-2 text-slate-400 font-black text-[10px] uppercase tracking-widest hover:text-indigo-600 transition-colors">
                    <History class="w-3.5 h-3.5" />
                    {{ expandedHistory[req.id] ? 'Hide' : 'View' }} Audit Trail
                    <ChevronDown class="w-3 h-3 transition-transform" :class="{'rotate-180': expandedHistory[req.id]}" />
                </button>

                <div v-if="expandedHistory[req.id]" class="mt-6 space-y-4 border-l-2 border-slate-100 ml-1.5 pl-6 py-2">
                    <div v-for="h in req.requestable.status_histories" :key="h.id" class="relative">
                        <div class="absolute -left-[31px] top-1.5 w-2 h-2 rounded-full bg-slate-200"></div>
                        <p class="text-xs font-bold text-slate-600">
                            {{ h.from_status || 'Initiated' }} 
                            <span class="text-slate-300 font-black mx-1">→</span> 
                            <span class="text-indigo-600">{{ h.to_status }}</span>
                        </p>
                        <p class="text-[10px] text-slate-400 font-bold mt-0.5 uppercase tracking-tighter">
                            By {{ h.changer?.name || 'Guest' }} <span class="mx-1">•</span> {{ formatDate(h.created_at) }}
                        </p>
                    </div>
                </div>
            </div>
          </div>
          
          <div class="bg-slate-50 px-8 py-4 flex justify-end gap-3 border-t border-slate-100">
              <button 
                @click="handlePrintTag(req.requestable)"
                class="px-6 py-2 bg-white border border-slate-200 text-slate-600 font-black text-xs rounded-xl uppercase tracking-widest hover:bg-slate-100 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="isLoading">
                Print Tag
              </button>
              <button 
                @click="openStatusModal(req.requestable)"
                class="px-6 py-2 bg-slate-900 text-white font-black text-xs rounded-xl uppercase tracking-widest hover:bg-indigo-600 transition-all shadow-lg shadow-slate-200 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                :disabled="isLoading">
                <Loader v-if="isLoading" class="w-3 h-3 animate-spin" />
                Update Status
              </button>
          </div>
        </div>
      </div>

      <!-- Status Update Modal -->
      <div v-if="showStatusModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full">
          <div class="p-8">
            <h2 class="text-2xl font-black text-slate-900 mb-2">Update Status</h2>
            <p class="text-slate-500 font-medium mb-6">Order {{ selectedOrder?.order_code }} - Room {{ selectedOrder?.room?.name }}</p>
            
            <div class="mb-6">
              <label class="block text-xs font-black text-slate-600 uppercase tracking-widest mb-3">Select New Status</label>
              <div class="space-y-2">
                <button 
                  v-for="status in getAvailableStatuses(selectedOrder?.status)"
                  :key="status"
                  @click="selectedStatus = status"
                  class="w-full text-left p-3 rounded-xl border-2 transition-all"
                  :class="selectedStatus === status 
                    ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-black'
                    : 'border-slate-200 hover:border-slate-300 text-slate-700 font-bold'">
                  {{ status.charAt(0).toUpperCase() + status.slice(1) }}
                </button>
              </div>
              <p v-if="getAvailableStatuses(selectedOrder?.status).length === 0" class="text-sm text-slate-500 mt-3">
                No status transitions available for this order.
              </p>
            </div>

            <div class="flex gap-3">
              <button 
                @click="closeStatusModal"
                class="flex-1 px-4 py-2 bg-slate-100 text-slate-700 font-black text-xs rounded-xl uppercase tracking-widest hover:bg-slate-200 transition-all disabled:opacity-50"
                :disabled="isLoading">
                Cancel
              </button>
              <button 
                @click="submitStatusUpdate"
                class="flex-1 px-4 py-2 bg-indigo-600 text-white font-black text-xs rounded-xl uppercase tracking-widest hover:bg-indigo-700 transition-all disabled:opacity-50 flex items-center justify-center gap-2"
                :disabled="isLoading || !selectedStatus">
                <Loader v-if="isLoading" class="w-3 h-3 animate-spin" />
                {{ isLoading ? 'Updating...' : 'Update' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </FrontDeskLayout>
</template>

<style scoped>
.animate-spin-slow {
  animation: spin 8s linear infinite;
}
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>