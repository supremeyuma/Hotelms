<template>
  <AuthenticatedLayout>
    <div class="space-y-6">

      <!-- Header + Search -->
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <h1 class="text-2xl font-semibold">Inventory</h1>

        <div class="flex gap-2 w-full md:w-auto">
          <input
            v-model="search"
            type="text"
            placeholder="Search by name or SKU..."
            class="w-full md:w-64 border rounded px-3 py-2 text-sm"
          />

          <Link class="btn-primary whitespace-nowrap" :href="route('admin.inventory.create')">
            New Item
          </Link>
        </div>
      </div>

      <!-- Table -->
      <div class="card overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b">
              <th class="text-left p-3">Item</th>
              <th class="text-left p-3">SKU</th>
              <th class="text-left p-3">Total</th>
              <th class="text-left p-3">By Location</th>
              <th class="text-right p-3">Actions</th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="item in filteredItems"
              :key="item.id"
              class="border-b hover:bg-slate-50"
            >
              <td class="p-3 font-semibold">
                <Link
                  :href="route('admin.inventory.show', item.id)"
                  class="hover:underline"
                >
                  {{ item.name }}
                </Link>

                <span
                  v-if="item.low_stock"
                  class="ml-2 px-2 py-1 text-xs bg-red-100 text-red-600 rounded-full"
                >
                  Low Stock
                </span>
              </td>

              <td class="p-3">{{ item.sku }}</td>

              <td class="p-3 font-bold">{{ item.total_stock }}</td>

              <td class="p-3 space-y-1 text-xs">
                <div
                  v-for="stock in item.stocks"
                  :key="stock.location_id"
                  class="flex justify-between"
                >
                  <span>{{ stock.location }}</span>
                  <span class="font-semibold">{{ stock.quantity }}</span>
                </div>
              </td>

              <td class="p-3 text-right space-x-2 whitespace-nowrap">
                <Link
                  class="btn-secondary"
                  :href="route('admin.inventory.edit', item.id)"
                >
                  Edit
                </Link>

                <button
                  type="button"
                  class="btn-secondary"
                  @click.stop="openAdd(item)"
                >
                  Add
                </button>

                <button
                  type="button"
                  class="btn-secondary"
                  @click.stop="openUse(item)"
                >
                  Use
                </button>
              </td>
            </tr>

            <tr v-if="filteredItems.length === 0">
              <td colspan="5" class="p-6 text-center text-slate-500">
                No inventory items found
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination :links="items.links" />

      <!-- ADD STOCK MODAL -->
      <Modal :show="showAddModal" @close="closeAdd">
        <template #title>Add Stock</template>

        <template #content>
          <form @submit.prevent="submitAdd" class="space-y-4">
            <select
              v-model="addStockForm.inventory_location_id"
              class="w-full border rounded px-3 py-2"
              required
            >
              <option value="" disabled>Select Location</option>
              <option v-for="loc in locations" :key="loc.id" :value="loc.id">
                {{ loc.name }}
              </option>
            </select>

            <input
              type="number"
              min="1"
              v-model="addStockForm.quantity"
              class="w-full border rounded px-3 py-2"
              placeholder="Quantity"
              required
            />

            <textarea
              v-model="addStockForm.reason"
              class="w-full border rounded px-3 py-2"
              placeholder="Reason (Purchase, Transfer, Adjustment)"
            />

            <div class="flex justify-end gap-2">
              <button type="button" class="btn-secondary" @click="closeAdd">
                Cancel
              </button>
              <PrimaryButton :disabled="addStockForm.processing">
                Confirm
              </PrimaryButton>
            </div>
          </form>
        </template>
      </Modal>

      <!-- USE STOCK MODAL -->
      <Modal :show="showUseModal" @close="closeUse">
        <template #title>Use Inventory</template>

        <template #content>
          <form @submit.prevent="submitUse" class="space-y-4">
            <select
              v-model="useStockForm.inventory_location_id"
              class="w-full border rounded px-3 py-2"
              required
            >
              <option value="" disabled>Select Location</option>
              <option v-for="loc in locations" :key="loc.id" :value="loc.id">
                {{ loc.name }}
              </option>
            </select>

            <!-- Available Stock -->
            <p
              v-if="availableStock !== null"
              class="text-xs text-slate-500"
            >
              Available in selected location:
              <span class="font-semibold">{{ availableStock }}</span>
            </p>

            <!-- Quantity -->
            <input
              type="number"
              min="1"
              v-model="useStockForm.quantity"
              class="w-full border rounded px-3 py-2"
              placeholder="Quantity"
              required
            />

            <!-- Error -->
            <p
              v-if="useStockForm.errors.quantity"
              class="text-sm text-red-600"
            >
              {{ useStockForm.errors.quantity }}
            </p>

            <textarea
              v-model="useStockForm.reason"
              class="w-full border rounded px-3 py-2"
              placeholder="Reason (optional)"
            />

            <div class="flex justify-end gap-2">
              <button type="button" class="btn-secondary" @click="closeUse">
                Cancel
              </button>
              <PrimaryButton :disabled="useStockForm.processing">
                Confirm
              </PrimaryButton>
            </div>
          </form>
        </template>
      </Modal>

    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import Modal from '@/Components/Modal.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const props = defineProps({
  items: Object,
  locations: Array
})


/* ---------------- STATE ---------------- */

const search = ref('')
const selectedItem = ref(null)

const showAddModal = ref(false)
const showUseModal = ref(false)

/* ---------------- FORMS ---------------- */

const addStockForm = useForm({
  inventory_location_id: '',
  quantity: '',
  reason: ''
})

const useStockForm = useForm({
  inventory_location_id: '',
  quantity: '',
  reason: ''
})

/* ---------------- COMPUTED ---------------- */

const filteredItems = computed(() => {
  if (!search.value) return props.items.data

  const q = search.value.toLowerCase()

  return props.items.data.filter(item =>
    item.name.toLowerCase().includes(q) ||
    item.sku.toLowerCase().includes(q)
  )
})

const availableStock = computed(() => {
  if (!selectedItem.value || !useStockForm.inventory_location_id) return null

  const stock = selectedItem.value.stocks.find(
    s => s.location_id === useStockForm.inventory_location_id
  )

  return stock ? stock.quantity : 0
})


/* ---------------- ACTIONS ---------------- */

function openAdd(item) {
  selectedItem.value = item
  addStockForm.reset()
  showAddModal.value = true
}

function closeAdd() {
  showAddModal.value = false
  selectedItem.value = null
}

function openUse(item) {
  selectedItem.value = item
  useStockForm.reset()
  showUseModal.value = true
}

function closeUse() {
  showUseModal.value = false
  selectedItem.value = null
}

function submitAdd() {
  const url = route('admin.inventory.addStock', selectedItem.value.id);
  console.log('Submitting to:', url); // Check if this matches the route:list exactly

  addStockForm.post(url, { 
    onSuccess: closeAdd 
  });
}

function submitUse() {
  useStockForm.post(
    route('admin.inventory.useItem', selectedItem.value.id),
    { onSuccess: closeUse }
  )
}
</script>
