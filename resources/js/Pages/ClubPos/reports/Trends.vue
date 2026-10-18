<script setup>
import { ref, computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import ClubPosLayout from '@/Layouts/ClubPosLayout.vue'
import TopBar from '@/Components/ClubPos/TopBar.vue'
import OfflineBanner from '@/Components/ClubPos/OfflineBanner.vue'

defineOptions({ layout: ClubPosLayout })

const props = defineProps({
  staff: { type: Object, default: null },
  currentSession: { type: Object, default: null },
  daily_trend: { type: Array, default: () => [] },
  top_items: { type: Array, default: () => [] },
  staff_performance: { type: Array, default: () => [] },
  payment_method_trend: { type: Array, default: () => [] },
  period_days: { type: Number, default: 30 },
})

const days = ref(props.period_days)
const loading = ref(false)
const filterError = ref(null)

function applyRange() {
  if (loading.value) return
  loading.value = true
  filterError.value = null
  router.get(route('club.pos.reports.trends'), { days: days.value }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
    onError: () => {
      filterError.value = 'Could not load the report. Try again.'
    },
    onFinish: () => { loading.value = false },
  })
}

function money(v) {
  return 'Ksh ' + Number(v || 0).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function shortMoney(v) {
  const n = Number(v || 0)
  if (n >= 1000) return 'Ksh ' + (n / 1000).toFixed(1) + 'k'
  return 'Ksh ' + n.toLocaleString()
}

const maxDaily = computed(() => Math.max(1, ...props.daily_trend.map(r => Number(r.total_sales))))
const maxStaff = computed(() => Math.max(1, ...props.staff_performance.map(r => Number(r.total_sales))))
const paymentTotal = computed(() => props.payment_method_trend.reduce((s, r) => s + Number(r.total), 0))

const paymentLabels = {
  cash: 'Cash',
  card: 'Card',
  room_charge: 'Room Charge',
  mobile_money: 'Mobile Money',
  voucher: 'Voucher',
}

function handleLock() {
  router.visit(route('club.pos.dashboard'))
}
</script>

<template>
  <div class="h-full flex flex-col">
    <OfflineBanner />
    <TopBar :staff="staff" :session="currentSession" :device="null"
      @lock="handleLock" @switch-staff="handleLock" />

    <div class="flex-1 overflow-y-auto p-6">
      <div class="max-w-6xl mx-auto space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
          <div>
            <h1 class="text-2xl font-bold">Trends</h1>
            <p class="text-sm text-slate-400">Last {{ period_days }} days</p>
          </div>
          <div class="flex items-end gap-3">
            <label class="block">
              <span class="text-xs uppercase tracking-wide text-slate-400">Period (days)</span>
              <select v-model.number="days"
                class="mt-1 px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-sm text-white focus:outline-none focus:border-slate-500">
                <option :value="7">7 days</option>
                <option :value="30">30 days</option>
                <option :value="90">90 days</option>
                <option :value="365">365 days</option>
              </select>
            </label>
            <button type="button" @click="applyRange" :disabled="loading"
              class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-bold disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
              <svg v-if="loading" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
              </svg>
              {{ loading ? 'Loading…' : 'Apply' }}
            </button>
            <Link :href="route('club.pos.reports.sales')" class="text-sm text-indigo-400 hover:text-indigo-300 pb-2">
              ← Sales
            </Link>
          </div>
        </div>

        <p v-if="filterError" role="alert" class="text-sm text-red-400 -mt-2">
          {{ filterError }}
        </p>

        <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
          <h2 class="font-semibold text-white mb-4">Daily Sales</h2>
          <div v-if="daily_trend.length" class="flex items-end gap-1 h-48 overflow-x-auto">
            <div v-for="row in daily_trend" :key="row.date" class="flex-1 min-w-[14px] flex flex-col items-center gap-1"
              :title="`${row.date}: ${money(row.total_sales)} (${row.docket_count})`">
              <div class="w-full bg-emerald-500/80 hover:bg-emerald-400 rounded-t transition-colors"
                :style="{ height: Math.max(2, Number(row.total_sales) / maxDaily * 160) + 'px' }" />
              <span class="text-[10px] text-slate-500 rotate-45 origin-left whitespace-nowrap">{{ row.date?.slice(5) }}</span>
            </div>
          </div>
          <p v-else class="text-sm text-slate-500">No sales in this period.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
            <h2 class="font-semibold text-white mb-4">Top Items</h2>
            <table v-if="top_items.length" class="w-full text-sm">
              <tbody class="divide-y divide-slate-700/60">
                <tr v-for="row in top_items" :key="row.item_name">
                  <td class="py-2 text-slate-300">{{ row.item_name }}</td>
                  <td class="py-2 text-right text-slate-500 tabular-nums">x{{ row.total_qty }}</td>
                  <td class="py-2 text-right text-slate-300 tabular-nums">{{ shortMoney(row.total) }}</td>
                </tr>
              </tbody>
            </table>
            <p v-else class="text-sm text-slate-500">No items sold.</p>
          </div>

          <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
            <h2 class="font-semibold text-white mb-4">Staff Performance</h2>
            <div v-if="staff_performance.length" class="space-y-3">
              <div v-for="row in staff_performance" :key="row.staff_name">
                <div class="flex justify-between text-sm mb-1">
                  <span class="text-slate-300">{{ row.staff_name }}</span>
                  <span class="text-slate-400 tabular-nums">{{ shortMoney(row.total_sales) }}</span>
                </div>
                <div class="h-2 rounded-full bg-slate-700 overflow-hidden">
                  <div class="h-full bg-indigo-500" :style="{ width: (Number(row.total_sales) / maxStaff * 100) + '%' }" />
                </div>
                <p class="text-xs text-slate-500 mt-0.5">{{ row.docket_count }} dockets</p>
              </div>
            </div>
            <p v-else class="text-sm text-slate-500">No staff sales.</p>
          </div>

          <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
            <h2 class="font-semibold text-white mb-4">Payment Mix</h2>
            <div v-if="payment_method_trend.length" class="space-y-3">
              <div v-for="row in payment_method_trend" :key="row.payment_method"
                class="flex items-center justify-between text-sm">
                <span class="text-slate-300">{{ paymentLabels[row.payment_method] || row.payment_method }}</span>
                <span class="text-slate-400">
                  <span class="tabular-nums">{{ shortMoney(row.total) }}</span>
                  <span class="text-xs text-slate-500 ml-2">
                    {{ paymentTotal ? Math.round(Number(row.total) / paymentTotal * 100) : 0 }}%
                  </span>
                </span>
              </div>
            </div>
            <p v-else class="text-sm text-slate-500">No payments in this period.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
