<template>
  <ManagerLayout>
    <div class="space-y-6">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="space-y-2">
          <p class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Inventory detail</p>
          <h1 class="text-3xl font-semibold tracking-tight text-slate-900">{{ item.name }}</h1>
          <p class="text-sm text-slate-500">
            Review stock by location and inspect every movement recorded against this item.
          </p>
        </div>

        <span
          v-if="item.low_stock"
          class="inline-flex items-center rounded-full bg-red-100 px-3 py-1 text-sm font-semibold text-red-700"
        >
          Low stock
        </span>
      </div>

      <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-black uppercase tracking-[0.22em] text-slate-400">SKU</p>
          <p class="mt-3 text-xl font-semibold text-slate-900">{{ item.sku }}</p>
        </div>
        <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-black uppercase tracking-[0.22em] text-slate-400">Total stock</p>
          <p class="mt-3 text-xl font-semibold text-slate-900">{{ formatQuantity(item.total_stock) }}</p>
        </div>
        <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-black uppercase tracking-[0.22em] text-slate-400">Unit</p>
          <p class="mt-3 text-xl font-semibold text-slate-900">{{ item.unit ?? 'Not set' }}</p>
        </div>
        <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-black uppercase tracking-[0.22em] text-slate-400">Threshold</p>
          <p class="mt-3 text-xl font-semibold text-slate-900">{{ formatQuantity(item.low_stock_threshold) }}</p>
        </div>
      </div>

      <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Stock by location</h2>

        <div
          v-for="stock in item.stocks"
          :key="stock.location_id"
          class="mt-3 flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm"
        >
          <span class="text-slate-600">{{ stock.location }}</span>
          <span class="font-semibold text-slate-900">{{ formatQuantity(stock.quantity) }}</span>
        </div>
      </div>

      <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-slate-500">
            <tr>
              <th class="px-4 py-4 text-left font-semibold">Date</th>
              <th class="px-4 py-4 text-left font-semibold">Staff</th>
              <th class="px-4 py-4 text-left font-semibold">Type</th>
              <th class="px-4 py-4 text-left font-semibold">Qty</th>
              <th class="px-4 py-4 text-left font-semibold">Location</th>
              <th class="px-4 py-4 text-left font-semibold">Reason</th>
              <th class="px-4 py-4 text-left font-semibold">Movement detail</th>
            </tr>
            </thead>

            <tbody>
              <tr
                v-for="move in item.movements"
                :key="move.id"
                class="border-t border-slate-100 align-top"
              >
                <td class="px-4 py-4 text-slate-500">{{ formatDate(move.created_at) }}</td>
                <td class="px-4 py-4 text-slate-600">{{ move.staff?.name ?? 'System' }}</td>
                <td class="px-4 py-4">
                  <span
                    class="rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide"
                    :class="badgeClass(move.type)"
                  >
                    {{ move.type.replaceAll('_', ' ') }}
                  </span>
                </td>
                <td class="px-4 py-4 font-semibold text-slate-900">{{ formatQuantity(move.quantity) }}</td>
                <td class="px-4 py-4 text-slate-600">{{ move.location }}</td>
                <td class="px-4 py-4 text-slate-600">{{ move.reason ?? 'No reason provided' }}</td>
                <td class="px-4 py-4 text-xs text-slate-500">
                  <div v-if="move.meta?.before !== undefined || move.meta?.after !== undefined" class="space-y-1">
                    <p>Before: <strong class="text-slate-700">{{ formatQuantity(move.meta?.before) }}</strong></p>
                    <p>After: <strong class="text-slate-700">{{ formatQuantity(move.meta?.after) }}</strong></p>
                    <p v-if="move.meta?.difference !== undefined">Difference: <strong class="text-slate-700">{{ formatQuantity(move.meta?.difference) }}</strong></p>
                  </div>
                  <span v-else>Ledger movement recorded</span>
                </td>
            </tr>

            <tr v-if="!item.movements.length">
              <td colspan="7" class="px-4 py-10 text-center text-slate-500">
                No inventory movements yet
              </td>
            </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </ManagerLayout>
</template>

<script setup>
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'

defineProps({
  item: Object
})

function formatQuantity(value) {
  return Number(value ?? 0).toLocaleString(undefined, {
    minimumFractionDigits: Number(value ?? 0) % 1 === 0 ? 0 : 2,
    maximumFractionDigits: 2,
  })
}

function formatDate(value) {
  return new Date(value).toLocaleString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

function badgeClass(type) {
  if (type === 'out' || type === 'transfer_out') {
    return 'bg-red-100 text-red-700'
  }

  if (type === 'adjustment') {
    return 'bg-amber-100 text-amber-700'
  }

  return 'bg-emerald-100 text-emerald-700'
}
</script>
