<template>
  <ManagerLayout>
    <div class="space-y-6">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="space-y-2">
          <p class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Inventory control</p>
          <h1 class="text-3xl font-semibold tracking-tight text-slate-900">Inventory ledger</h1>
          <p class="max-w-2xl text-sm text-slate-500">
            Review live stock by location, move items between stores, and reconcile physical counts without leaving the manager area.
          </p>
        </div>

        <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
          <input
            v-model="search"
            type="text"
            placeholder="Search by item or SKU"
            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100 sm:w-72"
          />

          <Link
            class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-indigo-600"
            :href="route('admin.inventory.create')"
          >
            New item
          </Link>
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-3">
        <Link
          :href="route('admin.inventory.index')"
          class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
        >
          <p class="text-xs font-black uppercase tracking-[0.22em] text-slate-400">Items tracked</p>
          <p class="mt-3 text-3xl font-semibold text-slate-900">{{ filteredItems.length }}</p>
          <p class="mt-2 text-sm text-slate-500">Active records visible in the current page results.</p>
        </Link>

        <Link
          :href="route('admin.inventory.index')"
          class="rounded-[1.75rem] border border-amber-200 bg-amber-50 p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
        >
          <p class="text-xs font-black uppercase tracking-[0.22em] text-amber-700">Low stock</p>
          <p class="mt-3 text-3xl font-semibold text-amber-900">{{ lowStockCount }}</p>
          <p class="mt-2 text-sm text-amber-800/80">Items at or below threshold and ready for replenishment.</p>
        </Link>

        <Link
          :href="route('admin.inventory-locations.index')"
          class="rounded-[1.75rem] border border-emerald-200 bg-emerald-50 p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
        >
          <p class="text-xs font-black uppercase tracking-[0.22em] text-emerald-700">Locations</p>
          <p class="mt-3 text-3xl font-semibold text-emerald-900">{{ locations.length }}</p>
          <p class="mt-2 text-sm text-emerald-800/80">Jump to location setup, types, and stock coverage.</p>
        </Link>
      </div>

      <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-slate-500">
              <tr>
                <th class="px-5 py-4 text-left font-semibold">Item</th>
                <th class="px-5 py-4 text-left font-semibold">SKU</th>
                <th class="px-5 py-4 text-left font-semibold">Total stock</th>
                <th class="px-5 py-4 text-left font-semibold">By location</th>
                <th class="px-5 py-4 text-right font-semibold">Actions</th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="item in filteredItems"
                :key="item.id"
                class="border-t border-slate-100 align-top transition hover:bg-slate-50/70"
              >
                <td class="px-5 py-4">
                  <div class="flex flex-col gap-2">
                    <Link
                      :href="route('admin.inventory.show', item.id)"
                      class="text-base font-semibold text-slate-900 transition hover:text-indigo-600"
                    >
                      {{ item.name }}
                    </Link>
                    <div class="flex flex-wrap items-center gap-2">
                      <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-slate-600">
                        {{ item.unit || 'No unit' }}
                      </span>
                      <span
                        v-if="item.low_stock"
                        class="rounded-full bg-red-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-red-700"
                      >
                        Low stock
                      </span>
                    </div>
                  </div>
                </td>

                <td class="px-5 py-4 font-medium text-slate-600">{{ item.sku }}</td>

                <td class="px-5 py-4">
                  <div class="font-semibold text-slate-900">{{ formatQuantity(item.total_stock) }}</div>
                  <p class="mt-1 text-xs text-slate-400">
                    Threshold {{ formatQuantity(item.low_stock_threshold) }}
                  </p>
                </td>

                <td class="px-5 py-4">
                  <div class="space-y-2">
                    <div
                      v-for="stock in item.stocks"
                      :key="stock.location_id"
                      class="flex items-center justify-between gap-4 rounded-2xl border border-slate-100 bg-slate-50 px-3 py-2"
                    >
                      <span class="text-slate-600">{{ stock.location }}</span>
                      <span class="font-semibold text-slate-900">{{ formatQuantity(stock.quantity) }}</span>
                    </div>
                  </div>
                </td>

                <td class="px-5 py-4">
                  <div class="flex flex-wrap justify-end gap-2">
                    <Link
                      class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                      :href="route('admin.inventory.edit', item.id)"
                    >
                      Edit
                    </Link>

                    <button
                      type="button"
                      class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                      @click="openAdd(item)"
                    >
                      Add
                    </button>

                    <button
                      type="button"
                      class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                      @click="openUse(item)"
                    >
                      Use
                    </button>

                    <button
                      type="button"
                      class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                      @click="openTransfer(item)"
                    >
                      Transfer
                    </button>

                    <button
                      type="button"
                      class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                      @click="openReconcile(item)"
                    >
                      Reconcile
                    </button>
                  </div>
                </td>
              </tr>

              <tr v-if="filteredItems.length === 0">
                <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-500">
                  No inventory items match the current search on this page.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <Pagination :links="items.links" />

      <Modal :show="showAddModal" @close="closeAdd">
        <template #title>Add Stock</template>
        <template #content>
          <form class="space-y-4" @submit.prevent="submitAdd">
            <p class="text-sm text-slate-500">
              Receive stock into <strong class="text-slate-900">{{ selectedItem?.name }}</strong>.
            </p>

            <select v-model="addStockForm.inventory_location_id" class="w-full rounded-xl border border-slate-200 px-3 py-2.5" required>
              <option value="" disabled>Select location</option>
              <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
            </select>

            <input
              v-model="addStockForm.quantity"
              type="number"
              min="0.01"
              step="0.01"
              class="w-full rounded-xl border border-slate-200 px-3 py-2.5"
              placeholder="Quantity received"
              required
            />

            <textarea
              v-model="addStockForm.reason"
              class="w-full rounded-xl border border-slate-200 px-3 py-2.5"
              placeholder="Reason, supplier note, or purchase reference"
            />

            <div class="flex justify-end gap-2">
              <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600" @click="closeAdd">
                Cancel
              </button>
              <PrimaryButton :disabled="addStockForm.processing">
                {{ addStockForm.processing ? 'Saving...' : 'Confirm receipt' }}
              </PrimaryButton>
            </div>
          </form>
        </template>
      </Modal>

      <Modal :show="showUseModal" @close="closeUse">
        <template #title>Consume Stock</template>
        <template #content>
          <form class="space-y-4" @submit.prevent="submitUse">
            <p class="text-sm text-slate-500">
              Record usage for <strong class="text-slate-900">{{ selectedItem?.name }}</strong>.
            </p>

            <select v-model="useStockForm.inventory_location_id" class="w-full rounded-xl border border-slate-200 px-3 py-2.5" required>
              <option value="" disabled>Select location</option>
              <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
            </select>

            <p v-if="useAvailableStock !== null" class="text-xs font-semibold text-slate-500">
              Available in selected location: {{ formatQuantity(useAvailableStock) }}
            </p>

            <input
              v-model="useStockForm.quantity"
              type="number"
              min="0.01"
              step="0.01"
              class="w-full rounded-xl border border-slate-200 px-3 py-2.5"
              placeholder="Quantity used"
              required
            />

            <p v-if="useStockForm.errors.quantity" class="text-sm text-red-600">{{ useStockForm.errors.quantity }}</p>

            <textarea
              v-model="useStockForm.reason"
              class="w-full rounded-xl border border-slate-200 px-3 py-2.5"
              placeholder="Usage reason"
            />

            <div class="flex justify-end gap-2">
              <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600" @click="closeUse">
                Cancel
              </button>
              <PrimaryButton :disabled="useStockForm.processing">
                {{ useStockForm.processing ? 'Saving...' : 'Confirm usage' }}
              </PrimaryButton>
            </div>
          </form>
        </template>
      </Modal>

      <Modal :show="showTransferModal" @close="closeTransfer">
        <template #title>Transfer Stock</template>
        <template #content>
          <form class="space-y-4" @submit.prevent="submitTransfer">
            <p class="text-sm text-slate-500">
              Move <strong class="text-slate-900">{{ selectedItem?.name }}</strong> between locations.
            </p>

            <div class="grid gap-3 md:grid-cols-2">
              <select v-model="transferForm.from_inventory_location_id" class="w-full rounded-xl border border-slate-200 px-3 py-2.5" required>
                <option value="" disabled>From location</option>
                <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
              </select>

              <select v-model="transferForm.to_inventory_location_id" class="w-full rounded-xl border border-slate-200 px-3 py-2.5" required>
                <option value="" disabled>To location</option>
                <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
              </select>
            </div>

            <p v-if="transferAvailableStock !== null" class="text-xs font-semibold text-slate-500">
              Available in source: {{ formatQuantity(transferAvailableStock) }}
            </p>

            <input
              v-model="transferForm.quantity"
              type="number"
              min="0.01"
              step="0.01"
              class="w-full rounded-xl border border-slate-200 px-3 py-2.5"
              placeholder="Quantity to transfer"
              required
            />

            <p v-if="transferForm.errors.transfer_quantity" class="text-sm text-red-600">{{ transferForm.errors.transfer_quantity }}</p>

            <textarea
              v-model="transferForm.reason"
              class="w-full rounded-xl border border-slate-200 px-3 py-2.5"
              placeholder="Why this transfer is being made"
              required
            />

            <div class="flex justify-end gap-2">
              <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600" @click="closeTransfer">
                Cancel
              </button>
              <PrimaryButton :disabled="transferForm.processing">
                {{ transferForm.processing ? 'Saving...' : 'Confirm transfer' }}
              </PrimaryButton>
            </div>
          </form>
        </template>
      </Modal>

      <Modal :show="showReconcileModal" @close="closeReconcile">
        <template #title>Reconcile Stock</template>
        <template #content>
          <form class="space-y-4" @submit.prevent="submitReconcile">
            <p class="text-sm text-slate-500">
              Set the counted quantity for <strong class="text-slate-900">{{ selectedItem?.name }}</strong>.
            </p>

            <select v-model="reconcileForm.inventory_location_id" class="w-full rounded-xl border border-slate-200 px-3 py-2.5" required>
              <option value="" disabled>Select location</option>
              <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
            </select>

            <p v-if="reconcileCurrentStock !== null" class="text-xs font-semibold text-slate-500">
              Current recorded quantity: {{ formatQuantity(reconcileCurrentStock) }}
            </p>

            <input
              v-model="reconcileForm.actual_quantity"
              type="number"
              min="0"
              step="0.01"
              class="w-full rounded-xl border border-slate-200 px-3 py-2.5"
              placeholder="Physical count"
              required
            />

            <textarea
              v-model="reconcileForm.reason"
              class="w-full rounded-xl border border-slate-200 px-3 py-2.5"
              placeholder="Count note, variance explanation, or reference"
              required
            />

            <div class="flex justify-end gap-2">
              <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600" @click="closeReconcile">
                Cancel
              </button>
              <PrimaryButton :disabled="reconcileForm.processing">
                {{ reconcileForm.processing ? 'Saving...' : 'Save count' }}
              </PrimaryButton>
            </div>
          </form>
        </template>
      </Modal>
    </div>
  </ManagerLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import Modal from '@/Components/Modal.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const props = defineProps({
  items: Object,
  locations: Array,
})

const search = ref('')
const selectedItem = ref(null)

const showAddModal = ref(false)
const showUseModal = ref(false)
const showTransferModal = ref(false)
const showReconcileModal = ref(false)

const addStockForm = useForm({
  inventory_location_id: '',
  quantity: '',
  reason: '',
})

const useStockForm = useForm({
  inventory_location_id: '',
  quantity: '',
  reason: '',
})

const transferForm = useForm({
  from_inventory_location_id: '',
  to_inventory_location_id: '',
  quantity: '',
  reason: '',
})

const reconcileForm = useForm({
  inventory_location_id: '',
  actual_quantity: '',
  reason: '',
})

const filteredItems = computed(() => {
  const q = search.value.trim().toLowerCase()

  if (!q) {
    return props.items.data
  }

  return props.items.data.filter(item =>
    item.name.toLowerCase().includes(q) ||
    item.sku.toLowerCase().includes(q)
  )
})

const lowStockCount = computed(() => filteredItems.value.filter(item => item.low_stock).length)

const useAvailableStock = computed(() => stockForLocation(useStockForm.inventory_location_id))
const transferAvailableStock = computed(() => stockForLocation(transferForm.from_inventory_location_id))
const reconcileCurrentStock = computed(() => stockForLocation(reconcileForm.inventory_location_id))

function formatQuantity(value) {
  return Number(value ?? 0).toLocaleString(undefined, {
    minimumFractionDigits: Number(value ?? 0) % 1 === 0 ? 0 : 2,
    maximumFractionDigits: 2,
  })
}

function stockForLocation(locationId) {
  if (!selectedItem.value || !locationId) {
    return null
  }

  const stock = selectedItem.value.stocks.find(entry => String(entry.location_id) === String(locationId))
  return stock ? Number(stock.quantity) : 0
}

function resetForms() {
  addStockForm.reset()
  useStockForm.reset()
  transferForm.reset()
  reconcileForm.reset()
  addStockForm.clearErrors()
  useStockForm.clearErrors()
  transferForm.clearErrors()
  reconcileForm.clearErrors()
}

function openAdd(item) {
  selectedItem.value = item
  resetForms()
  showAddModal.value = true
}

function closeAdd() {
  showAddModal.value = false
  selectedItem.value = null
}

function openUse(item) {
  selectedItem.value = item
  resetForms()
  showUseModal.value = true
}

function closeUse() {
  showUseModal.value = false
  selectedItem.value = null
}

function openTransfer(item) {
  selectedItem.value = item
  resetForms()
  showTransferModal.value = true
}

function closeTransfer() {
  showTransferModal.value = false
  selectedItem.value = null
}

function openReconcile(item) {
  selectedItem.value = item
  resetForms()
  showReconcileModal.value = true
}

function closeReconcile() {
  showReconcileModal.value = false
  selectedItem.value = null
}

function submitAdd() {
  addStockForm.post(route('admin.inventory.addStock', selectedItem.value.id), {
    preserveScroll: true,
    onSuccess: closeAdd,
  })
}

function submitUse() {
  useStockForm.post(route('admin.inventory.useItem', selectedItem.value.id), {
    preserveScroll: true,
    onSuccess: closeUse,
  })
}

function submitTransfer() {
  transferForm.post(route('admin.inventory.transfer', selectedItem.value.id), {
    preserveScroll: true,
    onSuccess: closeTransfer,
  })
}

function submitReconcile() {
  reconcileForm.post(route('admin.inventory.reconcile', selectedItem.value.id), {
    preserveScroll: true,
    onSuccess: closeReconcile,
  })
}
</script>
