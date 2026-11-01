<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { ArrowLeft, ShoppingCart } from 'lucide-vue-next'

const props = defineProps({
  orders: Object,
  status: { type: String, default: '' },
})

const filter = ref(props.status)

watch(filter, (value) => {
  router.get(route('admin.licences.orders'), value ? { status: value } : {}, { preserveState: true, replace: true })
})

function money(amount, code) {
  return new Intl.NumberFormat(code === 'USD' ? 'en-US' : 'en-NG', {
    style: 'currency',
    currency: code ?? 'NGN',
    maximumFractionDigits: 0,
  }).format(Number(amount ?? 0))
}

function date(value) {
  return value ? new Date(value).toLocaleString() : '—'
}

function statusClass(value) {
  return {
    'bg-emerald-100 text-emerald-700': value === 'paid',
    'bg-amber-100 text-amber-700': value === 'pending',
    'bg-rose-100 text-rose-700': value === 'failed',
  }
}
</script>

<template>
  <ManagerLayout>
    <Head title="Licence orders" />

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
      <Link :href="route('admin.licences.index')" class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 hover:text-slate-900">
        <ArrowLeft class="h-4 w-4" />
        Licence desk
      </Link>

      <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 p-6 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900 text-white">
              <ShoppingCart class="h-5 w-5" />
            </span>
            <div>
              <h1 class="text-2xl font-black tracking-tight text-slate-900">Licence orders</h1>
              <p class="text-sm text-slate-500">Every checkout, including ones the buyer abandoned.</p>
            </div>
          </div>

          <select v-model="filter" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
            <option value="">All statuses</option>
            <option value="pending">Pending</option>
            <option value="paid">Paid</option>
            <option value="failed">Failed</option>
          </select>
        </div>

        <div v-if="orders.data.length" class="overflow-x-auto">
          <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-[11px] font-black uppercase tracking-[0.14em] text-slate-400">
              <tr>
                <th class="px-5 py-3">Reference</th>
                <th class="px-5 py-3">Property</th>
                <th class="px-5 py-3">Amount</th>
                <th class="px-5 py-3">Provider</th>
                <th class="px-5 py-3">Status</th>
                <th class="px-5 py-3">Created</th>
                <th class="px-5 py-3">Licence</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="order in orders.data" :key="order.id" class="hover:bg-slate-50/70">
                <td class="px-5 py-4 font-mono text-xs text-slate-700">{{ order.reference }}</td>
                <td class="px-5 py-4">
                  <p class="font-bold text-slate-900">{{ order.property_name }}</p>
                  <p class="text-xs text-slate-500">{{ order.rooms }} rooms · {{ order.customer_email }}</p>
                </td>
                <td class="px-5 py-4 font-bold text-slate-900">{{ money(order.amount, order.currency) }}</td>
                <td class="px-5 py-4 capitalize text-slate-600">{{ order.provider || '—' }}</td>
                <td class="px-5 py-4">
                  <span class="rounded-full px-3 py-1 text-[11px] font-black uppercase tracking-[0.12em]" :class="statusClass(order.status)">
                    {{ order.status }}
                  </span>
                </td>
                <td class="px-5 py-4 text-slate-600">{{ date(order.created_at) }}</td>
                <td class="px-5 py-4">
                  <Link
                    v-if="order.licence_id"
                    :href="route('admin.licences.show', order.licence_id)"
                    class="text-xs font-black uppercase tracking-[0.14em] text-slate-500 hover:text-slate-900"
                  >
                    #{{ order.licence_id }}
                  </Link>
                  <span v-else class="text-xs text-slate-400">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="px-6 py-16 text-center">
          <ShoppingCart class="mx-auto h-10 w-10 text-slate-300" />
          <h2 class="mt-4 text-lg font-black text-slate-900">No orders yet</h2>
          <p class="mt-2 text-sm text-slate-500">Checkouts appear here as soon as a buyer starts one.</p>
        </div>

        <div v-if="orders.links?.length > 3" class="border-t border-slate-100 p-4">
          <Pagination :links="orders.links" />
        </div>
      </section>
    </div>
  </ManagerLayout>
</template>
