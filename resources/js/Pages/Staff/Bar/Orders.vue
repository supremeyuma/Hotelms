<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import BarLayout from '@/Layouts/Staff/BarLayout.vue'
import OrderDetailsModal from '@/Components/Orders/OrderDetailsModal.vue'
import CreateRoomOrderModal from '@/Components/Orders/CreateRoomOrderModal.vue'
import { Beer, CheckCircle2, Clock, Hash, Plus, Wine } from 'lucide-vue-next'

const props = defineProps({
  orders: Array,
  rooms: Array,
  menuItems: Array,
  paymentOptions: Array,
  paymentStatuses: Array,
})

const orders = ref(props.orders)
const selectedOrder = ref(null)
const showDetails = ref(false)
const showCreateModal = ref(false)

onMounted(() => {
  if (!window.Echo) return

  window.Echo.channel('orders.bar')
    .listen('.order.status.updated', (event) => {
      const index = orders.value.findIndex((order) => order.id === event.order.id)

      if (index === -1) {
        orders.value.unshift(event.order)
        return
      }

      orders.value[index] = event.order
    })
})

watch(
  () => props.orders,
  (value) => {
    orders.value = value

    if (selectedOrder.value) {
      selectedOrder.value = value.find((order) => order.id === selectedOrder.value.id) || selectedOrder.value
    }
  }
)

const activeCount = computed(() => orders.value.length)

function setStatus(order, status) {
  router.patch(route('staff.bar.orders.updateStatus', order.id), { status }, {
    preserveScroll: true,
  })
}

function openOrder(order) {
  selectedOrder.value = order
  showDetails.value = true
}

function paymentUpdateUrl(order) {
  return route('staff.bar.orders.updatePayment', order.id)
}

function statusBadge(status) {
  const map = {
    pending: 'bg-amber-100 text-amber-700',
    preparing: 'bg-blue-100 text-blue-700',
    ready: 'bg-emerald-100 text-emerald-700',
    delivered: 'bg-slate-100 text-slate-700',
  }

  return `rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wider ${map[status] || map.delivered}`
}

function paymentBadgeClass(order) {
  if (order.payment_status === 'paid') return 'bg-emerald-100 text-emerald-700'
  if (order.payment_status === 'failed') return 'bg-rose-100 text-rose-700'
  return 'bg-slate-100 text-slate-700'
}

function paymentLabel(order) {
  const method = String(order.payment_method || 'pending_selection').replace(/_/g, ' ')
  return `${order.payment_status} · ${method}`
}

function canStartPreparing(order) {
  if (!order.charge) return true

  return !(
    order.charge.payment_mode === 'prepaid' &&
    order.charge.status === 'unpaid'
  )
}
</script>

<template>
  <BarLayout>
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
      <div class="mb-8 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
          <div class="mb-1 flex items-center gap-2 text-sm font-bold uppercase tracking-widest text-blue-600">
            <Wine class="h-4 w-4" /> Beverage Service
          </div>
          <h1 class="text-3xl font-black tracking-tight text-slate-900">Active Drink Tickets</h1>
          <p class="mt-1 text-sm font-medium text-slate-500">Create bar orders for occupied rooms and keep service and payment updates live.</p>
        </div>

        <div class="flex items-center gap-3">
          <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700">
            Total: {{ activeCount }}
          </div>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-2xl bg-slate-900 px-5 py-3 text-sm font-black text-white shadow-xl shadow-slate-200 transition hover:bg-blue-600"
            @click="showCreateModal = true"
          >
            <Plus class="h-4 w-4" />
            New Room Order
          </button>
        </div>
      </div>

      <div v-if="!orders.length" class="flex flex-col items-center justify-center rounded-[2.5rem] border-2 border-dashed border-slate-200 bg-white py-24">
        <div class="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-slate-50">
          <Beer class="h-10 w-10 text-slate-300" />
        </div>
        <h2 class="text-xl font-bold text-slate-900">The bar is clear</h2>
        <p class="text-slate-500">New room drink orders will appear here in real-time.</p>
      </div>

      <div v-else class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
        <div
          v-for="order in orders"
          :key="order.id"
          class="group flex flex-col overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm transition-all duration-300 hover:shadow-xl"
          @click="openOrder(order)"
        >
          <div class="flex items-start justify-between gap-4 border-b border-slate-50 p-6">
            <div class="flex items-center gap-3">
              <div class="flex h-12 w-12 flex-col items-center justify-center rounded-2xl bg-slate-900 text-white">
                <Hash class="h-3 w-3 opacity-50" />
                <span class="text-lg font-bold leading-none">{{ order.id }}</span>
              </div>
              <div>
                <span :class="statusBadge(order.status)">
                  {{ order.status }}
                </span>
                <p class="mt-2 text-sm font-semibold text-slate-500">{{ order.room?.name || `Room ${order.room_id}` }}</p>
              </div>
            </div>

            <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wide" :class="paymentBadgeClass(order)">
              {{ paymentLabel(order) }}
            </span>
          </div>

          <div class="flex-1 bg-gradient-to-b from-white to-slate-50/30 p-6">
            <ul class="space-y-4">
              <li v-for="item in order.items" :key="item.id" class="flex items-start justify-between gap-4">
                <div>
                  <p class="text-lg font-bold leading-tight text-slate-900">{{ item.item_name }}</p>
                  <p v-if="item.note" class="mt-1 text-xs font-semibold text-blue-600">{{ item.note }}</p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl border-2 border-slate-100 bg-white font-black text-slate-900 shadow-sm">
                  {{ item.qty }}
                </div>
              </li>
            </ul>
          </div>

          <div class="grid grid-cols-3 gap-3 border-t border-slate-100 bg-white p-4">
            <button
              type="button"
              class="flex flex-col items-center justify-center gap-1 rounded-xl border p-2 transition-all"
              :class="order.status === 'preparing'
                ? 'border-indigo-600 bg-indigo-600 text-white'
                : !canStartPreparing(order)
                  ? 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400'
                  : 'border-slate-200 bg-white text-slate-600 hover:border-indigo-400'"
              :disabled="order.status === 'preparing' || !canStartPreparing(order)"
              @click.stop="setStatus(order, 'preparing')"
            >
              <Clock class="h-5 w-5" />
              <span class="text-[10px] font-bold uppercase">Prep</span>
            </button>

            <button
              type="button"
              class="flex flex-col items-center gap-1.5 rounded-2xl border py-3 text-[10px] font-bold uppercase tracking-tight shadow-sm transition-all"
              :class="order.status === 'ready'
                ? 'border-emerald-600 bg-emerald-600 text-white'
                : 'border-slate-100 bg-white text-slate-600 hover:bg-slate-50'"
              @click.stop="setStatus(order, 'ready')"
            >
              <CheckCircle2 class="h-5 w-5" />
              Ready
            </button>

            <button
              type="button"
              class="flex flex-col items-center gap-1.5 rounded-2xl border border-slate-100 bg-white py-3 text-[10px] font-bold uppercase tracking-tight text-slate-600 shadow-sm transition-all hover:bg-slate-50"
              @click.stop="setStatus(order, 'delivered')"
            >
              <CheckCircle2 class="h-5 w-5" />
              Done
            </button>
          </div>
        </div>
      </div>
    </div>
  </BarLayout>

  <OrderDetailsModal
    :show="showDetails"
    :order="selectedOrder"
    :payment-options="paymentOptions"
    :payment-statuses="paymentStatuses"
    :payment-update-url="selectedOrder ? paymentUpdateUrl(selectedOrder) : ''"
    @close="showDetails = false"
  />

  <CreateRoomOrderModal
    :show="showCreateModal"
    area="bar"
    :rooms="rooms"
    :menu-items="menuItems"
    :submit-url="route('staff.bar.orders.store')"
    @close="showCreateModal = false"
  />
</template>
