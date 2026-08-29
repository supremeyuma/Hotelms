<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Clock, Hash } from 'lucide-vue-next'

const props = defineProps({
  orders: {
    type: [Object, Array],
    default: () => [],
  },
})

const orderList = Array.isArray(props.orders) ? props.orders : (props.orders?.data ?? [])

function statusLabel(status) {
  return String(status || '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function statusClass(status) {
  switch (status) {
    case 'delivered':
    case 'completed':
      return 'bg-emerald-100 text-emerald-700'
    case 'cancelled':
      return 'bg-rose-100 text-rose-700'
    default:
      return 'bg-amber-100 text-amber-700'
  }
}
</script>

<template>
  <GuestLayout>
    <div class="max-w-4xl mx-auto px-4 py-8">
      <div class="mb-8">
        <h1 class="text-3xl font-black text-slate-900">Order History</h1>
        <p class="text-slate-500 text-sm mt-1">All the room-service and laundry requests you have made during your stay.</p>
      </div>

      <div v-if="orderList.length === 0" class="py-20 text-center bg-white rounded-2xl border border-slate-200">
        <p class="font-bold text-slate-500">You have no orders yet.</p>
        <p class="mt-2 text-sm text-slate-500">Use the room dashboard to place your first order.</p>
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div
          v-for="order in orderList"
          :key="order.id"
          class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden"
        >
          <div class="p-5 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
            <div>
              <div class="flex items-center gap-1 text-xs font-black uppercase text-slate-500 tracking-widest">
                <Hash class="w-3 h-3" /> Order
              </div>
              <p class="text-xl font-black text-slate-900">#{{ order.id }}</p>
              <p class="text-sm font-semibold text-slate-500 capitalize">{{ statusLabel(order.service_area) }} service</p>
            </div>

            <div
              class="px-3 py-1 rounded-full text-[10px] font-black uppercase"
              :class="statusClass(order.status)"
            >
              {{ statusLabel(order.status) }}
            </div>
          </div>

          <div v-if="order.items?.length" class="px-5 py-4 space-y-2 border-b border-slate-100">
            <p
              v-for="item in order.items"
              :key="item.id"
              class="text-sm font-medium text-slate-700"
            >
              {{ item.item_name || item.name }} <span class="font-black text-slate-900">x{{ item.qty || item.quantity }}</span>
            </p>
          </div>

          <div class="px-5 py-4 bg-slate-50 flex justify-between items-center">
            <div class="flex items-center gap-1 text-xs text-slate-500">
              <Clock class="w-4 h-4" />
              {{ new Date(order.created_at).toLocaleString() }}
            </div>
            <span class="text-lg font-black text-slate-900">
              ₦{{ Number(order.total).toLocaleString() }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </GuestLayout>
</template>
