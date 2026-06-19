<script setup>
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import ClubPosLayout from '@/Layouts/ClubPosLayout.vue'

defineOptions({ layout: ClubPosLayout })

const items = ref([])
const summary = ref(null)
const loading = ref(true)
const adding = ref({})

onMounted(fetchStock)

async function fetchStock() {
  loading.value = true
  try {
    const res = await fetch(route('club.pos.stock.index'))
    const data = await res.json()
    items.value = data
  } catch (e) {
    console.error('Failed to load stock', e)
  } finally {
    loading.value = false
  }
}

function addBottles(itemId) {
  const qty = prompt('Number of full bottles to add:')
  if (!qty || isNaN(qty) || Number(qty) < 1) return
  adding.value[itemId] = true
  router.post(route('club.pos.stock.add', { menuItem: itemId }), {
    bottles: Number(qty),
    notes: 'Manual addition',
  }, {
    preserveScroll: true,
    onSuccess: () => fetchStock(),
    onFinish: () => { adding.value[itemId] = false },
  })
}

function doStocktake(itemId) {
  const qty = prompt('Actual bottle count:')
  if (qty === null || isNaN(qty) || Number(qty) < 0) return
  adding.value[itemId] = true
  router.post(route('club.pos.stock.stocktake', { menuItem: itemId }), {
    actual_bottles: Number(qty),
  }, {
    preserveScroll: true,
    onSuccess: () => fetchStock(),
    onFinish: () => { adding.value[itemId] = false },
  })
}
</script>

<template>
  <div class="h-full flex flex-col p-6">
    <div class="flex items-center justify-between mb-6 shrink-0">
      <h1 class="text-2xl font-bold text-white">Drink Stock</h1>
      <button @click="fetchStock" :disabled="loading"
        class="px-4 py-2 rounded-lg bg-slate-700 text-slate-300 hover:bg-slate-600 transition-all text-sm font-medium disabled:opacity-50 min-h-[44px]">
        {{ loading ? 'Loading...' : 'Refresh' }}
      </button>
    </div>

    <div v-if="loading" class="flex-1 flex items-center justify-center">
      <div class="w-8 h-8 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin" />
    </div>

    <div v-else class="flex-1 overflow-y-auto">
      <div class="grid gap-3">
        <div v-for="entry in items" :key="entry.menu_item?.id"
          class="flex items-center gap-4 p-4 rounded-xl bg-slate-800 border"
          :class="entry.low_stock ? 'border-amber-500/30' : 'border-slate-700'">
          <div class="flex-1 min-w-0">
            <p class="text-base font-semibold text-white">{{ entry.menu_item?.name }}</p>
            <p class="text-sm text-slate-400">{{ entry.menu_item?.description || '' }}</p>
          </div>

          <div class="text-right shrink-0">
            <p class="text-lg font-bold tabular-nums"
              :class="entry.low_stock ? 'text-amber-400' : 'text-emerald-400'">
              {{ entry.stock?.full_bottles ?? 0 }}
            </p>
            <p class="text-xs text-slate-500">bottles</p>
          </div>

          <div class="text-right shrink-0">
            <p class="text-lg font-bold text-slate-300 tabular-nums">{{ entry.stock?.opened_bottles ?? 0 }}</p>
            <p class="text-xs text-slate-500">opened</p>
          </div>

          <div class="flex gap-2 shrink-0">
            <button @click="addBottles(entry.menu_item.id)" :disabled="adding[entry.menu_item.id]"
              class="px-4 py-2 rounded-lg bg-emerald-600/20 text-emerald-400 hover:bg-emerald-600/30 border border-emerald-600/30 text-sm font-medium transition-all active:scale-95 min-h-[44px] disabled:opacity-40">
              + Add
            </button>
            <button @click="doStocktake(entry.menu_item.id)" :disabled="adding[entry.menu_item.id]"
              class="px-4 py-2 rounded-lg bg-slate-700 text-slate-300 hover:bg-slate-600 text-sm font-medium transition-all active:scale-95 min-h-[44px] disabled:opacity-40">
              Stocktake
            </button>
          </div>
        </div>

        <p v-if="items.length === 0" class="text-center text-slate-500 py-12">
          No stock items configured yet.
        </p>
      </div>
    </div>
  </div>
</template>
