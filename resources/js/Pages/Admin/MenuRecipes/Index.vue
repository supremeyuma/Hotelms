<template>
  <AuthenticatedLayout>
    <div class="space-y-6">

      <!-- Header + Search -->
      <div class="flex justify-between items-center gap-4">
        <h1 class="text-2xl font-semibold">Menu Inventory Recipes</h1>

        <input
          v-model="search"
          type="text"
          placeholder="Search menu or ingredient..."
          class="border rounded px-3 py-2 text-sm w-64"
        />
      </div>

      <!-- Create / Update -->
      <form
        @submit.prevent="submit"
        class="grid grid-cols-4 gap-3 bg-white p-4 rounded shadow"
      >
        <select v-model="form.menu_item_id" class="border p-2 rounded" required>
          <option value="" disabled>Select Menu Item</option>
          <option v-for="m in menuItems" :key="m.id" :value="m.id">
            {{ m.name }}
          </option>
        </select>

        <select v-model="form.inventory_item_id" class="border p-2 rounded" required>
          <option value="" disabled>Select Inventory Item</option>
          <option v-for="i in inventoryItems" :key="i.id" :value="i.id">
            {{ i.name }} ({{ i.unit }})
          </option>
        </select>

        <input
          type="number"
          step="0.01"
          min="0.01"
          v-model="form.quantity"
          class="border p-2 rounded"
          placeholder="Qty per order"
          required
        />

        <PrimaryButton :disabled="form.processing">
          Save
        </PrimaryButton>
      </form>

      <!-- GROUPED TABLE -->
      <div
        v-for="(group, menuName) in groupedRecipes"
        :key="menuName"
        class="bg-white rounded shadow"
      >
        <div class="px-4 py-2 font-semibold border-b bg-slate-50">
          {{ menuName }}
        </div>

        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-100">
              <th class="p-2">Ingredient</th>
              <th class="p-2">Qty / Order</th>
              <th class="p-2">
                <button
                    type="button"
                    class="flex items-center gap-1 font-semibold hover:underline"
                    @click="toggleStockSort"
                >
                    Available
                    <span v-if="stockSort === 'asc'">↑</span>
                    <span v-else-if="stockSort === 'desc'">↓</span>
                </button>
                </th>

              <th class="p-2 text-right">Action</th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="r in group"
              :key="r.id"
              class="border-t"
            >
              <td class="p-2">
                {{ r.inventory_item.name }}
                <span class="text-xs text-gray-500">
                  ({{ r.inventory_item.unit }})
                </span>
              </td>

              <td class="p-2 font-semibold">
                {{ r.quantity }}
              </td>

              <td class="p-2">
                <span
                  :class="r.inventory_item.total_stock < r.quantity
                    ? 'text-red-600 font-semibold'
                    : 'text-green-700 font-semibold'"
                >
                  {{ r.inventory_item.total_stock }}
                </span>

                <span
                  v-if="r.inventory_item.total_stock < r.quantity"
                  class="ml-2 text-xs text-red-600"
                >
                  Insufficient stock
                </span>
              </td>

              <td class="p-2 text-right">
                <button class="btn-danger" @click="destroy(r.id)">
                  Remove
                </button>
              </td>
            </tr>

            <tr v-if="!group.length">
              <td colspan="4" class="p-4 text-center text-gray-500">
                No recipes defined
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const props = defineProps({
  recipes: Array,
  menuItems: Array,
  inventoryItems: Array,
})

const search = ref('')

const form = useForm({
  menu_item_id: '',
  inventory_item_id: '',
  quantity: ''
})

const stockSort = ref(null) 
// null | 'asc' | 'desc'


/* ---------------- COMPUTED ---------------- */

const filteredRecipes = computed(() => {
  if (!search.value) return props.recipes

  const q = search.value.toLowerCase()

  return props.recipes.filter(r =>
    r.menu_item.name.toLowerCase().includes(q) ||
    r.inventory_item.name.toLowerCase().includes(q)
  )
})

const groupedRecipes = computed(() => {
  const groups = {}

  filteredRecipes.value.forEach(recipe => {
    const key = recipe.menu_item.name
    if (!groups[key]) groups[key] = []
    groups[key].push(recipe)
  })

  if (stockSort.value) {
    Object.keys(groups).forEach(key => {
      groups[key].sort((a, b) => {
        const diff =
          a.inventory_item.total_stock -
          b.inventory_item.total_stock

        return stockSort.value === 'asc' ? diff : -diff
      })
    })
  }

  return groups
})


/* ---------------- ACTIONS ---------------- */

function submit() {
  form.post(route('admin.menu-recipes.store'), {
    onSuccess: () => form.reset()
  })
}

function destroy(id) {
  if (!confirm('Remove this recipe?')) return
  router.delete(route('admin.menu-recipes.destroy', id))
}

function toggleStockSort() {
  if (stockSort.value === null) {
    stockSort.value = 'desc' // default: highest first
  } else if (stockSort.value === 'desc') {
    stockSort.value = 'asc'
  } else {
    stockSort.value = null
  }
}

</script>
