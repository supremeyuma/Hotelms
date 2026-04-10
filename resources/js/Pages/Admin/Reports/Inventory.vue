<script setup>
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import Pagination from '@/Components/Pagination.vue'

const props = defineProps({
  rows: Object,
  filters: Object,
  chart: Object,
  items: Array,
  locations: Array,
  types: Array,
})

const filterForm = computed(() => ({
  search: props.filters?.search ?? '',
  from: props.filters?.from ?? '',
  to: props.filters?.to ?? '',
  type: props.filters?.type ?? '',
  inventory_item_id: props.filters?.inventory_item_id ?? '',
  inventory_location_id: props.filters?.inventory_location_id ?? '',
}))

function applyFilters(event) {
  const form = new FormData(event.target)

  router.get(route('admin.reports.inventory'), {
    search: form.get('search') || undefined,
    from: form.get('from') || undefined,
    to: form.get('to') || undefined,
    type: form.get('type') || undefined,
    inventory_item_id: form.get('inventory_item_id') || undefined,
    inventory_location_id: form.get('inventory_location_id') || undefined,
  }, {
    preserveState: true,
    replace: true,
  })
}

function resetFilters() {
  router.get(route('admin.reports.inventory'), {}, {
    preserveState: true,
    replace: true,
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

function formatQuantity(value) {
  return Number(value ?? 0).toLocaleString(undefined, {
    minimumFractionDigits: Number(value ?? 0) % 1 === 0 ? 0 : 2,
    maximumFractionDigits: 2,
  })
}

function typeLabel(type) {
  return String(type).replaceAll('_', ' ')
}

const totalMovement = computed(() =>
  (props.rows.data ?? []).reduce((sum, row) => sum + Number(row.quantity ?? 0), 0)
)

const exportQuery = computed(() => {
  const params = new URLSearchParams()

  Object.entries(filterForm.value).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) {
      params.set(key, value)
    }
  })

  return params.toString()
})
</script>

<template>
  <ManagerLayout>
    <div class="space-y-6">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="space-y-2">
          <p class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Movement reporting</p>
          <h1 class="text-3xl font-semibold tracking-tight text-slate-900">Inventory report</h1>
          <p class="max-w-2xl text-sm text-slate-500">
            Review every stock movement with location, staff, reason, and reference context from the new ledger.
          </p>
        </div>

        <Link
          :href="`${route('admin.reports.inventory.export', 'xlsx')}${exportQuery ? `?${exportQuery}` : ''}`"
          class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-indigo-600"
        >
          Export XLSX
        </Link>
      </div>

      <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-black uppercase tracking-[0.22em] text-slate-400">Rows shown</p>
          <p class="mt-3 text-3xl font-semibold text-slate-900">{{ rows.data.length }}</p>
          <p class="mt-2 text-sm text-slate-500">Filtered movement entries on this page.</p>
        </div>

        <div class="rounded-[1.75rem] border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
          <p class="text-xs font-black uppercase tracking-[0.22em] text-emerald-700">Page quantity</p>
          <p class="mt-3 text-3xl font-semibold text-emerald-900">{{ formatQuantity(totalMovement) }}</p>
          <p class="mt-2 text-sm text-emerald-800/80">Combined quantity across visible rows.</p>
        </div>

        <div class="rounded-[1.75rem] border border-indigo-200 bg-indigo-50 p-5 shadow-sm">
          <p class="text-xs font-black uppercase tracking-[0.22em] text-indigo-700">Chart points</p>
          <p class="mt-3 text-3xl font-semibold text-indigo-900">{{ chart?.labels?.length ?? 0 }}</p>
          <p class="mt-2 text-sm text-indigo-800/80">Daily movement totals available for trend views.</p>
        </div>
      </div>

      <form class="grid gap-3 rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm lg:grid-cols-6" @submit.prevent="applyFilters">
        <input
          name="search"
          :value="filterForm.search"
          type="text"
          placeholder="Search item or SKU"
          class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm"
        />

        <input
          name="from"
          :value="filterForm.from"
          type="date"
          class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm"
        />

        <input
          name="to"
          :value="filterForm.to"
          type="date"
          class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm"
        />

        <select name="type" :value="filterForm.type" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
          <option value="">All movement types</option>
          <option v-for="type in types" :key="type" :value="type">{{ typeLabel(type) }}</option>
        </select>

        <select name="inventory_item_id" :value="filterForm.inventory_item_id" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
          <option value="">All items</option>
          <option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }} ({{ item.sku }})</option>
        </select>

        <select name="inventory_location_id" :value="filterForm.inventory_location_id" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
          <option value="">All locations</option>
          <option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option>
        </select>

        <div class="flex gap-2 lg:col-span-6 lg:justify-end">
          <button
            type="button"
            class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600"
            @click="resetFilters"
          >
            Reset
          </button>
          <button
            type="submit"
            class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-600"
          >
            Apply filters
          </button>
        </div>
      </form>

      <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-slate-500">
              <tr>
                <th class="px-4 py-4 text-left font-semibold">Item</th>
                <th class="px-4 py-4 text-left font-semibold">Type</th>
                <th class="px-4 py-4 text-left font-semibold">Quantity</th>
                <th class="px-4 py-4 text-left font-semibold">Location</th>
                <th class="px-4 py-4 text-left font-semibold">Staff</th>
                <th class="px-4 py-4 text-left font-semibold">Reason</th>
                <th class="px-4 py-4 text-left font-semibold">Date</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in rows.data"
                :key="row.id"
                class="border-t border-slate-100 align-top transition hover:bg-slate-50/70"
              >
                <td class="px-4 py-4">
                  <Link
                    class="font-semibold text-slate-900 transition hover:text-indigo-600"
                    :href="route('admin.inventory.show', row.inventory_item_id)"
                  >
                    {{ row.item?.name ?? 'Removed item' }}
                  </Link>
                  <p class="mt-1 text-xs text-slate-400">{{ row.item?.sku ?? 'No SKU' }}</p>
                </td>
                <td class="px-4 py-4">
                  <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-slate-700">
                    {{ typeLabel(row.type) }}
                  </span>
                </td>
                <td class="px-4 py-4 font-semibold text-slate-900">{{ formatQuantity(row.quantity) }}</td>
                <td class="px-4 py-4 text-slate-600">{{ row.location?.name ?? 'Unknown location' }}</td>
                <td class="px-4 py-4 text-slate-600">{{ row.staff?.name ?? 'System' }}</td>
                <td class="px-4 py-4 text-slate-600">{{ row.reason || 'No reason provided' }}</td>
                <td class="px-4 py-4 text-slate-500">{{ formatDate(row.created_at) }}</td>
              </tr>

              <tr v-if="rows.data.length === 0">
                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">
                  No inventory movements match the selected filters.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <Pagination :links="rows.links" />
    </div>
  </ManagerLayout>
</template>
