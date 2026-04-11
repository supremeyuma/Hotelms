<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import KitchenLayout from '@/Layouts/Staff/KitchenLayout.vue'
import OrderDetailsModal from '@/Components/Orders/OrderDetailsModal.vue'
import CreateRoomOrderModal from '@/Components/Orders/CreateRoomOrderModal.vue'
import { CheckCircle2, ChefHat, Clock, Hash, Plus, Truck } from 'lucide-vue-next'

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

  window.Echo.channel('orders.kitchen')
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
  router.patch(route('staff.kitchen.orders.updateStatus', order.id), { status }, {
    preserveScroll: true,
  })
}

function openOrder(order) {
  selectedOrder.value = order
  showDetails.value = true
}

function paymentUpdateUrl(order) {
  return route('staff.kitchen.orders.updatePayment', order.id)
}

function getStatusClass(status) {
  const base = 'rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wider '

  switch (status) {
    case 'pending':
      return `${base}bg-amber-100 text-amber-700`
    case 'preparing':
      return `${base}bg-indigo-100 text-indigo-700`
    case 'ready':
      return `${base}bg-emerald-100 text-emerald-700`
    default:
      return `${base}bg-slate-100 text-slate-700`
  }
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
  <KitchenLayout>
    <div class="mx-auto max-w-7xl px-4 py-6">
      <div class="mb-8 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
          <h1 class="flex items-center gap-3 text-2xl font-black text-slate-900 md:text-3xl">
            Active Kitchen Orders
            <span class="rounded-full bg-indigo-600 px-3 py-1 text-sm text-white">{{ activeCount }}</span>
          </h1>
          <p class="mt-1 text-sm font-medium text-slate-500">Create room orders, track preparation, and keep payment records current.</p>
        </div>

        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-2xl bg-slate-900 px-5 py-3 text-sm font-black text-white shadow-xl shadow-slate-200 transition hover:bg-indigo-600"
          @click="showCreateModal = true"
        >
          <Plus class="h-4 w-4" />
          New Room Order
        </button>
      </div>

      <div v-if="!orders.length" class="flex flex-col items-center justify-center rounded-3xl border-2 border-dashed border-slate-200 bg-white py-20">
        <div class="mb-4 rounded-full bg-slate-50 p-4">
          <ChefHat class="h-12 w-12 text-slate-300" />
        </div>
        <h3 class="text-lg font-bold text-slate-900">No active orders</h3>
        <p class="text-slate-500">New room orders will appear here automatically.</p>
      </div>

      <div v-else class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
        <div
          v-for="order in orders"
          :key="order.id"
          class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition-all hover:shadow-md"
          @click="openOrder(order)"
        >
          <div class="flex items-start justify-between gap-4 border-b border-slate-100 bg-slate-50/60 p-5">
            <div>
              <div class="mb-1 flex items-center gap-1 text-[10px] font-black uppercase tracking-widest text-slate-400">
                <Hash class="h-3 w-3" /> Order
              </div>
              <div class="text-xl font-black text-slate-900">#{{ order.id }}</div>
              <div class="mt-1 text-sm font-semibold text-slate-500">{{ order.room?.name || `Room ${order.room_id}` }}</div>
            </div>

            <div class="flex flex-col items-end gap-2">
              <span :class="getStatusClass(order.status)">
                {{ order.status }}
              </span>
              <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wide" :class="paymentBadgeClass(order)">
                {{ paymentLabel(order) }}
              </span>
            </div>
          </div>

          <div class="space-y-3 p-5">
            <div
              v-for="item in order.items"
              :key="item.id"
              class="flex items-start justify-between gap-4"
            >
              <div>
                <p class="text-sm font-bold text-slate-900">{{ item.item_name }}</p>
                <p v-if="item.note" class="mt-1 text-xs text-orange-600">{{ item.note }}</p>
              </div>
              <div class="rounded-lg bg-slate-100 px-2.5 py-1 text-sm font-black text-slate-900">
                x{{ item.qty }}
              </div>
            </div>
          </div>

          <div class="grid grid-cols-3 gap-2 bg-slate-50/80 p-4">
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
              class="flex flex-col items-center justify-center gap-1 rounded-xl border p-2 transition-all"
              :class="order.status === 'ready'
                ? 'border-emerald-600 bg-emerald-600 text-white'
                : 'border-slate-200 bg-white text-slate-600 hover:border-emerald-400'"
              :disabled="order.status === 'ready'"
              @click.stop="setStatus(order, 'ready')"
            >
              <CheckCircle2 class="h-5 w-5" />
              <span class="text-[10px] font-bold uppercase">Ready</span>
            </button>

            <button
              type="button"
              class="flex flex-col items-center justify-center gap-1 rounded-xl border border-slate-200 bg-white p-2 text-slate-600 transition-all hover:border-blue-400"
              @click.stop="setStatus(order, 'delivered')"
            >
              <Truck class="h-5 w-5" />
              <span class="text-[10px] font-bold uppercase">Done</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </KitchenLayout>

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
    area="kitchen"
    :rooms="rooms"
    :menu-items="menuItems"
    :submit-url="route('staff.kitchen.orders.store')"
    @close="showCreateModal = false"
  />
</template>
