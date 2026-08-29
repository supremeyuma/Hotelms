<script setup>
import { ref } from 'vue'
import BarLayout from '@/Layouts/Staff/BarLayout.vue'
import OrderDetailsModal from '@/Components/Orders/OrderDetailsModal.vue'
import Pagination from '@/Components/Pagination.vue'
import { Clock, Hash } from 'lucide-vue-next'

const props = defineProps({
  orders: Object,
})

const orders = ref(props.orders.data)
const selectedOrder = ref(null)
const showModal = ref(false)

function openOrder(order) {
  selectedOrder.value = order
  showModal.value = true
}
</script>

<template>
  <BarLayout>
    <div class="max-w-6xl mx-auto px-4 py-6">

      <div class="mb-8">
        <h1 class="text-3xl font-black text-slate-900">Order History</h1>
        <p class="text-slate-500 text-sm mt-1">
          Completed and cancelled bar orders
        </p>
      </div>

      <div v-if="orders.length === 0" class="py-20 text-center bg-white rounded-3xl border border-slate-200">
        <p class="font-bold text-slate-500">No completed orders yet.</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <button
          v-for="order in orders"
          :key="order.id"
          @click="openOrder(order)"
          class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden text-left hover:shadow-md transition-all"
        >
          <!-- HEADER -->
          <div class="p-5 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
            <div>
              <div class="flex items-center gap-1 text-xs font-black uppercase text-slate-400 tracking-widest">
                <Hash class="w-3 h-3" /> Order
              </div>
              <p class="text-xl font-black text-slate-900">
                #{{ order.id }}
              </p>
            </div>

            <div
              class="px-3 py-1 rounded-full text-[10px] font-black uppercase"
              :class="order.status === 'delivered'
                ? 'bg-emerald-100 text-emerald-700'
                : 'bg-rose-100 text-rose-700'"
            >
              {{ order.status }}
            </div>
          </div>

          <!-- FOOTER -->
          <div class="px-5 py-4 bg-slate-900/5 flex justify-between items-center">
            <div class="flex items-center gap-1 text-xs text-slate-400">
              <Clock class="w-4 h-4" />
              {{ new Date(order.created_at).toLocaleString() }}
            </div>
            <span class="text-lg font-black text-slate-900">
              ₦{{ Number(order.total).toLocaleString() }}
            </span>
          </div>
        </button>
      </div>

      <!-- PAGINATION -->
      <div v-if="props.orders.links.length > 3" class="mt-10 flex justify-center">
        <Pagination :links="props.orders.links" />
      </div>

      <!-- ORDER DETAILS MODAL -->
      <OrderDetailsModal
        :show="showModal"
        :order="selectedOrder"
        @close="showModal = false"
      />

    </div>
  </BarLayout>
</template>
