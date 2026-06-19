<script setup>
import { ref } from 'vue'
import ClubPosLayout from '@/Layouts/ClubPosLayout.vue'

defineOptions({ layout: ClubPosLayout })

const activeTab = ref('sales')
const salesData = ref(null)
const trendsData = ref(null)
const loading = ref(false)

const tabs = [
  { id: 'sales', label: 'Sales Report' },
  { id: 'trends', label: 'Trends' },
  { id: 'shifts', label: 'Shift History' },
]

async function fetchSales() {
  loading.value = true
  try {
    const res = await fetch(route('club.pos.reports.sales'))
    salesData.value = await res.json()
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function fetchTrends() {
  loading.value = true
  try {
    const res = await fetch(route('club.pos.reports.trends', { days: 30 }))
    trendsData.value = await res.json()
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function switchTab(tab) {
  activeTab.value = tab
  if (tab === 'sales' && !salesData.value) fetchSales()
  if (tab === 'trends' && !trendsData.value) fetchTrends()
}

const methodLabels = {
  cash: 'Cash',
  card: 'Card',
  room_charge: 'Room Charge',
  mobile_money: 'Mobile Money',
  voucher: 'Voucher',
}
</script>

<template>
  <div class="h-full flex flex-col p-6 overflow-y-auto">
    <h1 class="text-2xl font-bold text-white mb-6 shrink-0">Club Reports</h1>

    <div class="flex gap-2 mb-6 shrink-0 overflow-x-auto">
      <button v-for="tab in tabs" :key="tab.id"
        @click="switchTab(tab.id)"
        class="px-5 py-2.5 rounded-lg text-sm font-medium transition-all whitespace-nowrap min-h-[44px]"
        :class="activeTab === tab.id ? 'bg-indigo-600 text-white' : 'bg-slate-700 text-slate-300 hover:bg-slate-600'">
        {{ tab.label }}
      </button>
    </div>

    <div v-if="loading" class="flex-1 flex items-center justify-center">
      <div class="w-8 h-8 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin" />
    </div>

    <div v-else-if="activeTab === 'sales' && salesData" class="space-y-6">
      <div class="grid grid-cols-3 gap-4">
        <div class="p-4 rounded-xl bg-slate-800 border border-slate-700">
          <p class="text-sm text-slate-400">Total Sales</p>
          <p class="text-2xl font-bold text-emerald-400 tabular-nums">Ksh {{ Number(salesData.summary.total_sales).toLocaleString() }}</p>
        </div>
        <div class="p-4 rounded-xl bg-slate-800 border border-slate-700">
          <p class="text-sm text-slate-400">Dockets</p>
          <p class="text-2xl font-bold text-white">{{ salesData.summary.total_dockets }}</p>
        </div>
        <div class="p-4 rounded-xl bg-slate-800 border border-slate-700">
          <p class="text-sm text-slate-400">Avg / Docket</p>
          <p class="text-2xl font-bold text-indigo-400 tabular-nums">Ksh {{ Number(salesData.summary.average_per_docket).toLocaleString() }}</p>
        </div>
      </div>

      <div v-if="salesData.top_items?.length" class="p-4 rounded-xl bg-slate-800 border border-slate-700">
        <h3 class="text-base font-semibold text-white mb-3">Top Items</h3>
        <div v-for="(item, i) in salesData.top_items" :key="i"
          class="flex items-center justify-between py-2 border-b border-slate-700 last:border-0">
          <span class="text-sm text-slate-300">{{ item.item_name }}</span>
          <div class="text-right">
            <span class="text-sm font-bold text-white tabular-nums">x{{ item.total_qty }}</span>
            <span class="text-sm text-emerald-400 ml-3 tabular-nums">Ksh {{ Number(item.total).toLocaleString() }}</span>
          </div>
        </div>
      </div>

      <div v-if="salesData.payment_method_breakdown?.length" class="p-4 rounded-xl bg-slate-800 border border-slate-700">
        <h3 class="text-base font-semibold text-white mb-3">Payment Methods</h3>
        <div v-for="pm in salesData.payment_method_breakdown" :key="pm.payment_method"
          class="flex items-center justify-between py-2 border-b border-slate-700 last:border-0">
          <span class="text-sm text-slate-300">{{ methodLabels[pm.payment_method] || pm.payment_method }}</span>
          <div class="text-right">
            <span class="text-sm text-slate-400 tabular-nums">{{ pm.count }} txns</span>
            <span class="text-sm font-bold text-white ml-3 tabular-nums">Ksh {{ Number(pm.total).toLocaleString() }}</span>
          </div>
        </div>
      </div>
    </div>

    <div v-else-if="activeTab === 'trends' && trendsData" class="space-y-6">
      <div class="p-4 rounded-xl bg-slate-800 border border-slate-700">
        <h3 class="text-base font-semibold text-white mb-3">Daily Trend ({{ trendsData.period_days }} days)</h3>
        <div v-for="day in trendsData.daily_trend" :key="day.date"
          class="flex items-center justify-between py-2 border-b border-slate-700 last:border-0">
          <span class="text-sm text-slate-300">{{ day.date }}</span>
          <div class="text-right">
            <span class="text-sm text-slate-400 tabular-nums">{{ day.docket_count }} dockets</span>
            <span class="text-sm font-bold text-emerald-400 ml-3 tabular-nums">Ksh {{ Number(day.total_sales).toLocaleString() }}</span>
          </div>
        </div>
      </div>

      <div v-if="trendsData.top_items?.length" class="p-4 rounded-xl bg-slate-800 border border-slate-700">
        <h3 class="text-base font-semibold text-white mb-3">Top Items ({{ trendsData.period_days }} days)</h3>
        <div v-for="(item, i) in trendsData.top_items" :key="i"
          class="flex items-center justify-between py-2 border-b border-slate-700 last:border-0">
          <span class="text-sm text-slate-300">{{ item.item_name }}</span>
          <div class="text-right">
            <span class="text-sm font-bold text-white tabular-nums">x{{ item.total_qty }}</span>
            <span class="text-sm text-emerald-400 ml-3 tabular-nums">Ksh {{ Number(item.total).toLocaleString() }}</span>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="flex-1 flex items-center justify-center text-slate-500">
      <p>Select a report tab to load data</p>
    </div>
  </div>
</template>
