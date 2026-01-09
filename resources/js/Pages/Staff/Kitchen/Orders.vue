<script setup>
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import KitchenLayout from '@/Layouts/Staff/KitchenLayout.vue'
import OrderDetailsModal from '@/Components/Orders/OrderDetailsModal.vue'
import { 
  Clock, 
  CheckCircle2, 
  ChefHat, 
  Truck, 
  Hash, 
  AlertCircle 
} from 'lucide-vue-next'

const props = defineProps({ orders: Array })
const orders = ref(props.orders)

const selectedOrder = ref(null)
const showModal = ref(false)

console.log(props.orders)

onMounted(() => {
  if (window.Echo) {
    window.Echo.channel('orders.kitchen')
      .listen('.order.status.updated', e => {
        const index = orders.value.findIndex(o => o.id === e.order.id)
        if (index !== -1) orders.value[index] = e.order
        else orders.value.unshift(e.order)
      })
  }
})

function setStatus(order, status) {
  router.patch(`/staff/kitchen/orders/${order.id}`, { status }, {
    preserveScroll: true
  })
}

function openOrder(order) {
  selectedOrder.value = order
  showModal.value = true
  console.log(order)
}

const getStatusClass = (status) => {
  const base = "px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider "
  switch (status) {
    case 'pending': return base + "bg-amber-100 text-amber-700 border border-amber-200"
    case 'preparing': return base + "bg-indigo-100 text-indigo-700 border border-indigo-200"
    case 'ready': return base + "bg-emerald-100 text-emerald-700 border border-emerald-200"
    default: return base + "bg-slate-100 text-slate-700 border border-slate-200"
  }
}

function canStartPreparing(order) {
  // No charge → allow (safety fallback)
  if (!order.charge) return true

  // Block prepaid orders until paid
  if (
    order.charge.payment_mode === 'prepaid' &&
    order.charge.status === 'unpaid'
  ) {
    return false
  }

  return true
}

</script>

<template>
  <KitchenLayout>
    <div class="max-w-6xl mx-auto px-4 py-6">
      <div class="flex items-center justify-between mb-8">
        <div>
          <h1 class="text-2xl md:text-3xl font-black text-slate-900 flex items-center gap-3">
            Active Tickets
            <span class="bg-indigo-600 text-white text-sm py-1 px-3 rounded-full">{{ orders.length }}</span>
          </h1>
          <p class="text-slate-500 font-medium text-sm mt-1">Manage live orders and preparation status</p>
        </div>
      </div>

      <div v-if="orders.length === 0" class="flex flex-col items-center justify-center py-20 bg-white rounded-3xl border-2 border-dashed border-slate-200">
        <div class="p-4 bg-slate-50 rounded-full mb-4">
          <ChefHat class="w-12 h-12 text-slate-300" />
        </div>
        <h3 class="text-lg font-bold text-slate-900">No active orders</h3>
        <p class="text-slate-500">New orders will appear here automatically.</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div 
          v-for="order in orders" 
          @click="openOrder(order)"
          :key="order.id" 
          class="bg-white rounded-3xl border border-slate-200 shadow-sm flex flex-col overflow-hidden transition-all hover:shadow-md"
        >
          <div class="p-5 border-b border-slate-100 flex justify-between items-start bg-slate-50/50">
            <div>
              <div class="flex items-center gap-1 text-slate-400 font-bold text-xs uppercase tracking-widest mb-1">
                <Hash class="w-3 h-3" /> Order ID
              </div>
              <div class="text-xl font-black text-slate-900">#{{ order.id }}</div>
            </div>
            <div>
              <div class="flex items-center gap-1 text-slate-400 font-bold text-xs uppercase tracking-widest mb-1">
                <Hash class="w-3 h-3" /> ROOM
              </div>
              <div class="text-xl font-black text-slate-900">ROOM - {{ order.room_id }}</div>
            </div>
            <span :class="getStatusClass(order.status)">
              {{ order.status }}
            </span>
            <!-- PAYMENT BADGE -->
            <span
              v-if="order.charge"
              class="px-2 py-1 rounded-full text-[10px] font-black uppercase tracking-wide"
              :class="
                order.charge.status === 'paid'
                  ? 'bg-emerald-100 text-emerald-700'
                  : order.charge.payment_mode === 'pay_on_delivery'
                    ? 'bg-amber-100 text-amber-700'
                    : 'bg-rose-100 text-rose-700'
              "
            >
              <template v-if="order.charge.status === 'paid'">
                Paid
              </template>

              <template v-else-if="order.charge.payment_mode === 'pay_on_delivery'">
                Pay on Delivery
              </template>

              <template v-else>
                Awaiting Payment
              </template>
            </span>

          </div>

          <div class="p-5 flex-1">
            <ul class="space-y-4">
              <li 
                v-for="item in order.items" 
                :key="item.id"
                class="flex items-start justify-between gap-4"
              >
                <div class="flex flex-col">
                  <span class="font-bold text-slate-800 text-sm md:text-base leading-tight">
                    {{ item.item_name }}
                  </span>
                  <span v-if="item.notes" class="text-xs text-orange-600 font-medium mt-1 flex items-center gap-1">
                    <AlertCircle class="w-3 h-3" /> {{ item.notes }}
                  </span>
                </div>
                <div class="bg-slate-100 text-slate-900 font-black px-2.5 py-1 rounded-lg text-sm">
                  ×{{ item.qty }}
                </div>
              </li>
            </ul>
          </div>

          <div class="p-4 bg-slate-50/80 grid grid-cols-3 gap-2">
            
            <button 
              @click.stop="setStatus(order,'preparing')"
              :disabled="order.status === 'preparing' || !canStartPreparing(order)"
              class="flex flex-col items-center justify-center gap-1 p-2 rounded-xl border transition-all"
              :class="
                order.status === 'preparing'
                  ? 'bg-indigo-600 text-white border-indigo-600'
                  : !canStartPreparing(order)
                    ? 'bg-slate-100 text-slate-400 border-slate-200 cursor-not-allowed'
                    : 'bg-white text-slate-600 border-slate-200 hover:border-indigo-400'
              "
            >
              <Clock class="w-5 h-5" />
              <span class="text-[10px] font-bold uppercase">Prep</span>
            </button>
            <div
              v-if="!canStartPreparing(order)"
              class="absolute -top-8 left-1/2 -translate-x-1/2 bg-black text-white text-[10px] px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition"
            >
              Awaiting payment
            </div>
          


            <button 
              @click="setStatus(order,'ready')"
              :disabled="order.status === 'ready'"
              class="flex flex-col items-center justify-center gap-1 p-2 rounded-xl border transition-all"
              :class="order.status === 'ready' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-600 border-slate-200 hover:border-emerald-400'"
            >
              <CheckCircle2 class="w-5 h-5" />
              <span class="text-[10px] font-bold uppercase">Ready</span>
            </button>

            <button 
              @click="setStatus(order,'delivered')"
              class="flex flex-col items-center justify-center gap-1 p-2 rounded-xl bg-white border border-slate-200 text-slate-600 hover:border-blue-400 transition-all"
            >
              <Truck class="w-5 h-5" />
              <span class="text-[10px] font-bold uppercase">Done</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </KitchenLayout>

  <!-- ORDER DETAILS MODAL -->
      <OrderDetailsModal
        :show="showModal"
        :order="selectedOrder"
        @close="showModal = false"
      />
</template>

<style scoped>
.grid {
  display: grid;
}
/* Ensure smooth transitions for status changes */
.bg-white {
  transition: background-color 0.2s, border-color 0.2s;
}
</style>