<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import BaseStaffLayout from '@/Layouts/Staff/BaseStaffLayout.vue'
import OrderDetailsModal from '@/Components/Orders/OrderDetailsModal.vue'
import Pagination from '@/Components/Pagination.vue'
import { Hash, Inbox } from 'lucide-vue-next'

const props = defineProps({
  orders: Object,
})

const showDetails = ref(false)
const selectedOrder = ref(null)

function openOrder(order) {
  selectedOrder.value = order
  showDetails.value = true
}

function setStatus(order, status) {
  router.patch(route('staff.orders.updateStatus', order.id), { status }, {
    preserveScroll: true,
  })
}

function departmentLabel(department) {
  return String(department || '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function departmentClass(department) {
  const base = 'rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wider '
  switch (department) {
    case 'kitchen':
      return `${base}bg-orange-100 text-orange-700`
    case 'bar':
      return `${base}bg-sky-100 text-sky-700`
    case 'laundry':
      return `${base}bg-teal-100 text-teal-700`
    case 'housekeeping':
      return `${base}bg-violet-100 text-violet-700`
    case 'maintenance':
      return `${base}bg-rose-100 text-rose-700`
    default:
      return `${base}bg-slate-100 text-slate-700`
  }
}

function getStatusClass(status) {
  const base = 'rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wider '
  switch (status) {
    case 'pending':
      return `${base}bg-amber-100 text-amber-700`
    case 'preparing':
    case 'in_progress':
      return `${base}bg-indigo-100 text-indigo-700`
    case 'ready':
      return `${base}bg-emerald-100 text-emerald-700`
    default:
      return `${base}bg-slate-100 text-slate-700`
  }
}
</script>

<template>
  <BaseStaffLayout>
    <div class="mx-auto max-w-7xl">
      <div class="mb-8">
        <h1 class="flex items-center gap-3 text-2xl font-black text-slate-900 md:text-3xl">
          Orders Queue
          <span class="text-base font-bold text-slate-400">{{ orders?.total ?? 0 }}</span>
        </h1>
        <p class="mt-1 text-sm font-medium text-slate-500">
          Active orders across every department. Review details and advance status as work is completed.
        </p>
      </div>

      <div v-if="!orders?.data?.length" class="flex flex-col items-center justify-center rounded-3xl border-2 border-dashed border-slate-200 bg-white py-20">
        <div class="mb-4 rounded-full bg-slate-50 p-4">
          <Inbox class="h-12 w-12 text-slate-300" />
        </div>
        <h3 class="text-lg font-bold text-slate-900">No active orders</h3>
        <p class="text-slate-500">Orders across all departments will appear here.</p>
      </div>

      <div v-else class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
        <div
          v-for="order in orders.data"
          :key="order.id"
          class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition-all hover:shadow-md"
        >
          <button
            type="button"
            class="block w-full text-left"
            @click="openOrder(order)"
          >
            <div class="flex items-start justify-between gap-4 border-b border-slate-100 bg-slate-50/60 p-5">
              <div>
                <div class="mb-1 flex items-center gap-1 text-xs uppercase font-semibold tracking-wide text-slate-400">
                  <Hash class="h-3 w-3" /> Order
                </div>
                <div class="text-xl font-black text-slate-900">#{{ order.id }}</div>
                <div class="mt-1 text-sm font-semibold text-slate-500">
                  {{ order.room?.name || (order.booking?.room?.name) || `Room ${order.room_id || '—'}` }}
                </div>
              </div>

              <div class="flex flex-col items-end gap-2">
                <span :class="departmentClass(order.department)">{{ departmentLabel(order.department) }}</span>
                <span :class="getStatusClass(order.status)">{{ order.status }}</span>
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
          </button>

          <div class="flex flex-wrap items-center justify-end gap-2 bg-slate-50/80 p-4">
            <button
              type="button"
              class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold uppercase tracking-wider text-slate-600 transition hover:border-indigo-400"
              :disabled="order.status === 'pending'"
              @click="setStatus(order, 'pending')"
            >
              Pending
            </button>
            <button
              type="button"
              class="rounded-xl border border-indigo-200 bg-white px-4 py-2 text-xs font-bold uppercase tracking-wider text-indigo-700 transition hover:border-indigo-400"
              :disabled="order.status === 'in_progress' || order.status === 'preparing'"
              @click="setStatus(order, 'in_progress')"
            >
              In Progress
            </button>
            <button
              type="button"
              class="rounded-xl border border-emerald-200 bg-white px-4 py-2 text-xs font-bold uppercase tracking-wider text-emerald-700 transition hover:border-emerald-400"
              :disabled="order.status === 'ready'"
              @click="setStatus(order, 'ready')"
            >
              Ready
            </button>
          </div>
        </div>
      </div>

      <div v-if="orders?.data?.length" class="mt-8 flex justify-center">
        <Pagination :links="orders.links" />
      </div>
    </div>

    <OrderDetailsModal
      :show="showDetails"
      :order="selectedOrder"
      @close="showDetails = false"
    />
  </BaseStaffLayout>
</template>
