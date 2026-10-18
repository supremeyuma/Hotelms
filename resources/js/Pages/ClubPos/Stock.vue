<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import ClubPosLayout from '@/Layouts/ClubPosLayout.vue'
import TopBar from '@/Components/ClubPos/TopBar.vue'
import OfflineBanner from '@/Components/ClubPos/OfflineBanner.vue'

defineOptions({ layout: ClubPosLayout })

const props = defineProps({
  staff: { type: Object, default: null },
  currentSession: { type: Object, default: null },
  items: { type: Array, default: () => [] },
})

const lowOnly = ref(false)
const search = ref('')
const modal = ref(null)
const submitting = ref(false)
const error = ref('')

const rows = computed(() => {
  let list = props.items
  if (lowOnly.value) list = list.filter(i => i.low_stock)
  const q = search.value.trim().toLowerCase()
  if (q) list = list.filter(i => (i.menu_item?.name || '').toLowerCase().includes(q))
  return list
})

const summary = computed(() => {
  const total = props.items.length
  const low = props.items.filter(i => i.low_stock).length
  const bottles = props.items.reduce((s, i) => s + (i.stock?.full_bottles ?? 0), 0)
  const pours = props.items.reduce((s, i) => {
    const st = i.stock
    return s + (st ? (st.full_bottles * st.pours_per_bottle) + st.opened_bottles : 0)
  }, 0)
  return { total, low, bottles, pours }
})

function poursFor(item) {
  const st = item.stock
  return st ? (st.full_bottles * st.pours_per_bottle) + st.opened_bottles : 0
}

function openAdd(item) {
  error.value = ''
  modal.value = { mode: 'add', item, bottles: 1, actual_bottles: 0, notes: '' }
}

function openStocktake(item) {
  error.value = ''
  modal.value = { mode: 'stocktake', item, bottles: 1, actual_bottles: item.stock?.full_bottles ?? 0, notes: '' }
}

function closeModal() {
  if (submitting.value) return
  modal.value = null
  error.value = ''
}

async function submitModal() {
  if (!modal.value || submitting.value) return
  submitting.value = true
  error.value = ''

  const item = modal.value.item
  const isAdd = modal.value.mode === 'add'
  const url = isAdd
    ? route('club.pos.stock.add', { menuItem: item.menu_item.id })
    : route('club.pos.stock.stocktake', { menuItem: item.menu_item.id })

  const body = isAdd
    ? { bottles: Number(modal.value.bottles), notes: modal.value.notes || null }
    : { actual_bottles: Number(modal.value.actual_bottles), notes: modal.value.notes || null }

  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
      },
      body: JSON.stringify(body),
    })
    if (!res.ok) {
      const data = await res.json().catch(() => ({}))
      error.value = data.message || data.error || 'Could not save stock change.'
      return
    }
    modal.value = null
    router.reload({ only: ['items'], preserveScroll: true })
  } catch (e) {
    error.value = 'Could not save stock change.'
  } finally {
    submitting.value = false
  }
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
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-2xl font-bold">Bar Stock</h1>
            <p class="text-sm text-slate-400">Track bottles, low-stock items and replenishment.</p>
          </div>
          <input v-model="search" type="text" placeholder="Search items…"
            class="px-4 py-2 rounded-lg bg-slate-800 border border-slate-700 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-slate-500 w-64" />
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div class="rounded-xl border border-slate-700 bg-slate-800 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-400">Tracked Items</p>
            <p class="text-2xl font-bold mt-1 tabular-nums">{{ summary.total }}</p>
          </div>
          <button type="button" @click="lowOnly = !lowOnly"
            class="text-left rounded-xl border p-4 transition-colors"
            :class="lowOnly ? 'border-amber-500 bg-amber-500/10' : 'border-slate-700 bg-slate-800 hover:border-slate-600'">
            <p class="text-xs uppercase tracking-wide text-amber-400">Low Stock</p>
            <p class="text-2xl font-bold mt-1 tabular-nums text-amber-400">{{ summary.low }}</p>
            <p class="text-xs text-slate-500 mt-1">{{ lowOnly ? 'Showing low stock only' : 'Click to filter' }}</p>
          </button>
          <div class="rounded-xl border border-slate-700 bg-slate-800 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-400">Full Bottles</p>
            <p class="text-2xl font-bold mt-1 tabular-nums">{{ summary.bottles }}</p>
          </div>
          <div class="rounded-xl border border-slate-700 bg-slate-800 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-400">Est. Pours</p>
            <p class="text-2xl font-bold mt-1 tabular-nums">{{ summary.pours }}</p>
          </div>
        </div>

        <div class="rounded-xl border border-slate-700 bg-slate-800 overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-slate-900/60 text-slate-400 text-left">
              <tr>
                <th class="px-4 py-3 font-medium">Item</th>
                <th class="px-4 py-3 font-medium text-right">Full</th>
                <th class="px-4 py-3 font-medium text-right">Opened</th>
                <th class="px-4 py-3 font-medium text-right">Pours/Bottle</th>
                <th class="px-4 py-3 font-medium text-right">Est. Pours</th>
                <th class="px-4 py-3 font-medium text-center">Status</th>
                <th class="px-4 py-3 font-medium text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/60">
              <tr v-for="item in rows" :key="item.menu_item.id" class="hover:bg-slate-700/30">
                <td class="px-4 py-3">
                  <p class="font-medium text-white">{{ item.menu_item.name }}</p>
                  <p class="text-xs text-slate-500">Ksh {{ Number(item.menu_item.price).toLocaleString() }}</p>
                </td>
                <td class="px-4 py-3 text-right tabular-nums">{{ item.stock?.full_bottles ?? 0 }}</td>
                <td class="px-4 py-3 text-right tabular-nums">{{ item.stock?.opened_bottles ?? 0 }}</td>
                <td class="px-4 py-3 text-right tabular-nums">{{ item.stock?.pours_per_bottle ?? '—' }}</td>
                <td class="px-4 py-3 text-right tabular-nums">{{ poursFor(item) }}</td>
                <td class="px-4 py-3 text-center">
                  <span v-if="item.low_stock"
                    class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-500/15 text-amber-400 border border-amber-500/30">
                    Low
                  </span>
                  <span v-else
                    class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                    OK
                  </span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex justify-end gap-2">
                    <button type="button" @click="openAdd(item)"
                      class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-medium transition-colors">
                      Add Stock
                    </button>
                    <button type="button" @click="openStocktake(item)"
                      class="px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-200 text-xs font-medium transition-colors">
                      Stocktake
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!rows.length">
                <td colspan="7" class="px-4 py-10 text-center text-slate-500">
                  {{ lowOnly ? 'No low-stock items.' : 'No bar items found.' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="modal" class="fixed inset-0 z-[100] bg-black/70 backdrop-blur-sm flex items-center justify-center p-4"
        @keydown.esc="closeModal">
        <div role="dialog" aria-modal="true" class="w-full max-w-md rounded-2xl bg-slate-800 border border-slate-700 shadow-2xl p-6">
          <h2 class="text-lg font-bold text-white">
            {{ modal.mode === 'add' ? 'Add Stock' : 'Stocktake' }}
          </h2>
          <p class="text-sm text-slate-400 mt-1">{{ modal.item.menu_item.name }}</p>

          <div class="mt-4 space-y-3">
            <label v-if="modal.mode === 'add'" class="block">
              <span class="text-xs uppercase tracking-wide text-slate-400">Bottles received</span>
              <input v-model.number="modal.bottles" type="number" min="1" max="9999"
                class="mt-1 w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-slate-500" />
            </label>
            <label v-else class="block">
              <span class="text-xs uppercase tracking-wide text-slate-400">Actual full bottles counted</span>
              <input v-model.number="modal.actual_bottles" type="number" min="0" max="9999"
                class="mt-1 w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-slate-500" />
            </label>
            <label class="block">
              <span class="text-xs uppercase tracking-wide text-slate-400">Notes (optional)</span>
              <input v-model="modal.notes" type="text" maxlength="500"
                class="mt-1 w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-white focus:outline-none focus:border-slate-500" />
            </label>
            <p v-if="error" class="text-sm text-red-400">{{ error }}</p>
          </div>

          <div class="flex justify-end gap-2 mt-6">
            <button type="button" @click="closeModal" :disabled="submitting"
              class="px-4 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-200 text-sm font-medium disabled:opacity-50">
              Cancel
            </button>
            <button type="button" @click="submitModal" :disabled="submitting"
              class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-bold disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
              <svg v-if="submitting" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
              </svg>
              {{ submitting ? 'Saving…' : 'Save' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
