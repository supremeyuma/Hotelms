<script setup>
import { ref, computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import ClubPosLayout from '@/Layouts/ClubPosLayout.vue'
import TopBar from '@/Components/ClubPos/TopBar.vue'

defineOptions({ layout: ClubPosLayout })

const props = defineProps({
  staff: { type: Object, default: null },
  currentSession: { type: Object, default: null },
  summary: { type: Object, default: () => ({}) },
  category_breakdown: { type: Array, default: () => [] },
  payment_method_breakdown: { type: Array, default: () => [] },
  top_items: { type: Array, default: () => [] },
  hourly_breakdown: { type: Array, default: () => [] },
  dockets: { type: Array, default: () => [] },
})

const dateFrom = ref(props.summary.date_from)
const dateTo = ref(props.summary.date_to)
const loading = ref(false)

function applyFilters() {
  if (loading.value) return
  loading.value = true
  router.get(route('club.pos.reports.sales'), {
    date_from: dateFrom.value,
    date_to: dateTo.value,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
    onFinish: () => { loading.value = false },
  })
}

function money(v) {
  return 'Ksh ' + Number(v || 0).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const maxCategory = computed(() => Math.max(1, ...props.category_breakdown.map(r => Number(r.total))))
const maxHour = computed(() => Math.max(1, ...props.hourly_breakdown.map(r => Number(r.total))))
const paymentTotal = computed(() => props.payment_method_breakdown.reduce((s, r) => s + Number(r.total), 0))

const paymentLabels = {
  cash: 'Cash',
  card: 'Card',
  room_charge: 'Room Charge',
  mobile_money: 'Mobile Money',
  voucher: 'Voucher',
}

function formatTime(v) {
  if (!v) return '—'
  return new Date(v).toLocaleTimeString('en-KE', { hour: '2-digit', minute: '2-digit' })
}

function handleLock() {
  router.visit(route('club.pos.dashboard'))
}
</script>

<template>
  <div class="h-full flex flex-col">
    <TopBar :staff="staff" :session="currentSession" :device="null"
      @lock="handleLock" @switch-staff="handleLock" />

    <div class="flex-1 overflow-y-auto p-6">
      <div class="max-w-6xl mx-auto space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
          <div>
            <h1 class="text-2xl font-bold">Sales Report</h1>
            <p class="text-sm text-slate-400">{{ summary.date_from }} → {{ summary.date_to }}</p>
          </div>
          <div class="flex items-end gap-3">
            <label class="block">
              <span class="text-xs uppercase tracking-wide text-slate-400">From</span>
              <input v-model="dateFrom" type="date"
                class="mt-1 px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-sm text-white focus:outline-none focus:border-slate-500" />
            </label>
            <label class="block">
              <span class="text-xs uppercase tracking-wide text-slate-400">To</span>
              <input v-model="dateTo" type="date"
                class="mt-1 px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-sm text-white focus:outline-none focus:border-slate-500" />
            </label>
            <button type="button" @click="applyFilters" :disabled="loading"
              class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-bold disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
              <svg v-if="loading" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
              </svg>
              {{ loading ? 'Loading…' : 'Apply' }}
            </button>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
            <p class="text-xs uppercase tracking-wide text-slate-400">Total Sales</p>
            <p class="text-3xl font-bold mt-1 tabular-nums text-emerald-400">{{ money(summary.total_sales) }}</p>
          </div>
          <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
            <p class="text-xs uppercase tracking-wide text-slate-400">Dockets Closed</p>
            <p class="text-3xl font-bold mt-1 tabular-nums">{{ summary.total_dockets }}</p>
          </div>
          <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
            <p class="text-xs uppercase tracking-wide text-slate-400">Average / Docket</p>
            <p class="text-3xl font-bold mt-1 tabular-nums">{{ money(summary.average_per_docket) }}</p>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
            <h2 class="font-semibold text-white mb-4">Sales by Category</h2>
            <div v-if="category_breakdown.length" class="space-y-3">
              <div v-for="row in category_breakdown" :key="row.category">
                <div class="flex justify-between text-sm mb-1">
                  <span class="text-slate-300">{{ row.category }}</span>
                  <span class="tabular-nums text-slate-400">{{ money(row.total) }}</span>
                </div>
                <div class="h-2 rounded-full bg-slate-700 overflow-hidden">
                  <div class="h-full bg-emerald-500" :style="{ width: (Number(row.total) / maxCategory * 100) + '%' }" />
                </div>
              </div>
            </div>
            <p v-else class="text-sm text-slate-500">No sales in this period.</p>
          </div>

          <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
            <h2 class="font-semibold text-white mb-4">Payment Methods</h2>
            <div v-if="payment_method_breakdown.length" class="space-y-3">
              <div v-for="row in payment_method_breakdown" :key="row.payment_method"
                class="flex items-center justify-between text-sm">
                <span class="text-slate-300">{{ paymentLabels[row.payment_method] || row.payment_method }}</span>
                <span class="text-slate-400">
                  <span class="tabular-nums">{{ money(row.total) }}</span>
                  <span class="text-xs text-slate-500 ml-2">
                    {{ paymentTotal ? Math.round(Number(row.total) / paymentTotal * 100) : 0 }}%
                  </span>
                </span>
              </div>
            </div>
            <p v-else class="text-sm text-slate-500">No payments in this period.</p>
          </div>

          <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
            <h2 class="font-semibold text-white mb-4">Top Items</h2>
            <table v-if="top_items.length" class="w-full text-sm">
              <tbody class="divide-y divide-slate-700/60">
                <tr v-for="row in top_items" :key="row.item_name">
                  <td class="py-2 text-slate-300">{{ row.item_name }}</td>
                  <td class="py-2 text-right text-slate-500 tabular-nums">x{{ row.total_qty }}</td>
                  <td class="py-2 text-right text-slate-300 tabular-nums">{{ money(row.total) }}</td>
                </tr>
              </tbody>
            </table>
            <p v-else class="text-sm text-slate-500">No items sold.</p>
          </div>

          <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
            <h2 class="font-semibold text-white mb-4">Sales by Hour</h2>
            <div v-if="hourly_breakdown.length" class="space-y-2">
              <div v-for="row in hourly_breakdown" :key="row.hour" class="flex items-center gap-3">
                <span class="w-12 text-xs text-slate-400 tabular-nums">{{ String(row.hour).padStart(2, '0') }}:00</span>
                <div class="flex-1 h-2 rounded-full bg-slate-700 overflow-hidden">
                  <div class="h-full bg-indigo-500" :style="{ width: (Number(row.total) / maxHour * 100) + '%' }" />
                </div>
                <span class="w-24 text-right text-xs text-slate-400 tabular-nums">{{ money(row.total) }}</span>
              </div>
            </div>
            <p v-else class="text-sm text-slate-500">No sales in this period.</p>
          </div>
        </div>

        <div class="rounded-xl border border-slate-700 bg-slate-800 overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-700 flex items-center justify-between">
            <h2 class="font-semibold text-white">Closed Dockets ({{ dockets.length }})</h2>
          </div>
          <table class="w-full text-sm">
            <thead class="bg-slate-900/60 text-slate-400 text-left">
              <tr>
                <th class="px-5 py-3 font-medium">Docket</th>
                <th class="px-5 py-3 font-medium">Closed</th>
                <th class="px-5 py-3 font-medium">Staff</th>
                <th class="px-5 py-3 font-medium text-right">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/60">
              <tr v-for="d in dockets" :key="d.id" class="hover:bg-slate-700/30">
                <td class="px-5 py-3 text-white">#{{ d.docket_number }}</td>
                <td class="px-5 py-3 text-slate-400">{{ formatTime(d.closed_at) }}</td>
                <td class="px-5 py-3 text-slate-400">{{ d.staff?.name || '—' }}</td>
                <td class="px-5 py-3 text-right text-emerald-400 tabular-nums">{{ money(d.total) }}</td>
              </tr>
              <tr v-if="!dockets.length">
                <td colspan="4" class="px-5 py-10 text-center text-slate-500">No closed dockets in this period.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="text-right">
          <Link :href="route('club.pos.reports.trends')" class="text-sm text-indigo-400 hover:text-indigo-300">
            View trends →
          </Link>
        </div>
      </div>
    </div>
  </div>
</template>
