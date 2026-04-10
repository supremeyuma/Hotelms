<template>
  <ManagerLayout>
    <Head title="Menu Recipes" />

    <div class="space-y-6">
      <section class="overflow-hidden rounded-3xl border border-slate-200 bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 text-white shadow-sm">
        <div class="grid gap-6 px-6 py-6 lg:grid-cols-[1.5fr_1fr] lg:px-8">
          <div class="space-y-4">
            <div class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-emerald-100">
              Manager workspace
            </div>

            <div class="space-y-2">
              <h1 class="text-3xl font-semibold tracking-tight">Menu recipe coverage</h1>
              <p class="max-w-2xl text-sm leading-6 text-slate-200">
                Manage the ingredients each menu item consumes, spot stock risks early, and keep kitchen costing data complete.
              </p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <button
                type="button"
                class="summary-card text-left"
                :class="statusFilter === 'all' ? 'summary-card-active' : ''"
                @click="statusFilter = 'all'"
              >
                <span class="summary-label">Menu items</span>
                <span class="summary-value">{{ summary.totalMenuItems }}</span>
                <span class="summary-note">All tracked food and drink items</span>
              </button>

              <button
                type="button"
                class="summary-card text-left"
                :class="statusFilter === 'missing' ? 'summary-card-active' : ''"
                @click="showMissingCoverage"
              >
                <span class="summary-label">Missing recipes</span>
                <span class="summary-value">{{ summary.missingRecipes }}</span>
                <span class="summary-note">Items that still need ingredients configured</span>
              </button>

              <button
                type="button"
                class="summary-card text-left"
                :class="statusFilter === 'at_risk' ? 'summary-card-active' : ''"
                @click="showAtRiskCoverage"
              >
                <span class="summary-label">At risk</span>
                <span class="summary-value">{{ summary.atRiskItems }}</span>
                <span class="summary-note">Configured items blocked by stock shortages</span>
              </button>

              <Link
                class="summary-card text-left"
                :href="route('admin.inventory.index')"
              >
                <span class="summary-label">Low-stock ingredients</span>
                <span class="summary-value">{{ summary.lowStockIngredients }}</span>
                <span class="summary-note">Open inventory to replenish ingredients</span>
              </Link>
            </div>
          </div>

          <div class="rounded-3xl border border-white/10 bg-white/5 p-5 backdrop-blur-sm">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Current focus</p>
                <h2 class="mt-2 text-xl font-semibold">
                  {{ selectedMenuItem ? selectedMenuItem.name : 'No menu item selected' }}
                </h2>
              </div>

              <span
                v-if="selectedMenuItem"
                class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide"
                :class="selectedSummary?.statusTone ?? 'bg-white/10 text-slate-100'"
              >
                {{ selectedSummary?.statusLabel }}
              </span>
            </div>

            <div v-if="selectedMenuItem" class="mt-4 space-y-3 text-sm text-slate-200">
              <div class="flex flex-wrap gap-2">
                <span class="rounded-full bg-white/10 px-3 py-1">
                  {{ selectedMenuItem.category_name ?? 'No category' }}
                </span>
                <span
                  v-if="selectedMenuItem.subcategory_name"
                  class="rounded-full bg-white/10 px-3 py-1"
                >
                  {{ selectedMenuItem.subcategory_name }}
                </span>
                <span class="rounded-full bg-white/10 px-3 py-1">
                  {{ selectedMenuItem.service_area ?? 'General service' }}
                </span>
                <span class="rounded-full bg-white/10 px-3 py-1">
                  Prep {{ selectedMenuItem.prep_time_minutes || 0 }} min
                </span>
              </div>

              <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl border border-white/10 bg-black/10 p-3">
                  <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Ingredients</p>
                  <p class="mt-2 text-2xl font-semibold text-white">{{ selectedSummary?.recipeCount ?? 0 }}</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-black/10 p-3">
                  <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Low stock</p>
                  <p class="mt-2 text-2xl font-semibold text-white">{{ selectedSummary?.lowStockCount ?? 0 }}</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-black/10 p-3">
                  <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Shortages</p>
                  <p class="mt-2 text-2xl font-semibold text-white">{{ selectedSummary?.atRiskCount ?? 0 }}</p>
                </div>
              </div>
            </div>

            <p v-else class="mt-4 text-sm text-slate-300">
              Select a menu item from the list below to build or review its recipe.
            </p>
          </div>
        </div>
      </section>

      <section class="grid gap-6 xl:grid-cols-[1.15fr_1.35fr]">
        <div class="space-y-6">
          <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-2 border-b border-slate-100 pb-4 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <h2 class="text-lg font-semibold text-slate-900">Recipe builder</h2>
                <p class="mt-1 text-sm text-slate-500">
                  Save one ingredient at a time for the selected menu item. Existing entries update safely if the same ingredient is used again.
                </p>
              </div>

              <div
                v-if="form.processing"
                class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700"
              >
                Saving recipe...
              </div>
            </div>

            <form class="mt-5 space-y-4" @submit.prevent="submit">
              <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700">Menu item</label>
                <select
                  v-model.number="form.menu_item_id"
                  class="field"
                  required
                >
                  <option disabled value="">Select a menu item</option>
                  <option v-for="item in sortedMenuItems" :key="item.id" :value="item.id">
                    {{ item.name }}{{ item.category_name ? ` - ${item.category_name}` : '' }}
                  </option>
                </select>
              </div>

              <div class="grid gap-4 md:grid-cols-[1.35fr_0.65fr]">
                <div class="space-y-2">
                  <label class="text-sm font-medium text-slate-700">Ingredient</label>
                  <select
                    v-model.number="form.inventory_item_id"
                    class="field"
                    required
                  >
                    <option disabled value="">Select an inventory item</option>
                    <option v-for="item in sortedInventoryItems" :key="item.id" :value="item.id">
                      {{ item.name }}{{ item.unit ? ` (${item.unit})` : '' }} - {{ formatQuantity(item.total_stock) }} in stock
                    </option>
                  </select>
                </div>

                <div class="space-y-2">
                  <label class="text-sm font-medium text-slate-700">Qty per order</label>
                  <input
                    v-model="form.quantity"
                    type="number"
                    min="0.01"
                    step="0.01"
                    class="field"
                    placeholder="0.00"
                    required
                  />
                </div>
              </div>

              <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                <div v-if="selectedIngredientPreview" class="flex flex-wrap items-center gap-2">
                  <span class="font-medium text-slate-900">{{ selectedIngredientPreview.name }}</span>
                  <span class="rounded-full bg-white px-3 py-1 text-xs text-slate-600">
                    SKU {{ selectedIngredientPreview.sku || 'N/A' }}
                  </span>
                  <span class="rounded-full bg-white px-3 py-1 text-xs text-slate-600">
                    {{ formatQuantity(selectedIngredientPreview.total_stock) }} {{ selectedIngredientPreview.unit || '' }} available
                  </span>
                  <span
                    v-if="selectedIngredientPreview.low_stock"
                    class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800"
                  >
                    Low stock
                  </span>
                </div>
                <p v-else>Select an ingredient to review current stock before saving.</p>
              </div>

              <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                <p class="text-sm text-slate-500">
                  {{ editingRecipeId ? 'Editing an existing recipe line.' : 'Create a new recipe line for this menu item.' }}
                </p>

                <div class="flex flex-wrap gap-2">
                  <button
                    v-if="editingRecipeId"
                    type="button"
                    class="btn-secondary"
                    @click="resetFormForSelectedMenuItem"
                  >
                    Cancel edit
                  </button>

                  <button
                    type="submit"
                    class="btn-primary"
                    :disabled="form.processing || !form.menu_item_id"
                  >
                    {{ form.processing ? 'Saving...' : editingRecipeId ? 'Update ingredient' : 'Save ingredient' }}
                  </button>
                </div>
              </div>
            </form>
          </div>

          <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-2 border-b border-slate-100 pb-4 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <h2 class="text-lg font-semibold text-slate-900">Selected item ingredients</h2>
                <p class="mt-1 text-sm text-slate-500">
                  Review shortages, open inventory records, or edit quantities without leaving this page.
                </p>
              </div>

              <span class="text-sm font-medium text-slate-500">
                {{ selectedItemRecipes.length }} ingredient{{ selectedItemRecipes.length === 1 ? '' : 's' }}
              </span>
            </div>

            <div v-if="selectedMenuItem" class="mt-5 space-y-3">
              <div
                v-for="recipe in selectedItemRecipes"
                :key="recipe.id"
                class="rounded-2xl border p-4 transition"
                :class="recipeIsShort(recipe) ? 'border-red-200 bg-red-50/70' : recipe.inventory_item.low_stock ? 'border-amber-200 bg-amber-50/70' : 'border-slate-200 bg-slate-50/60'"
              >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                  <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                      <h3 class="text-base font-semibold text-slate-900">{{ recipe.inventory_item.name }}</h3>
                      <span class="rounded-full bg-white px-3 py-1 text-xs text-slate-500">
                        {{ recipe.inventory_item.unit || 'unit not set' }}
                      </span>
                      <span
                        v-if="recipeIsShort(recipe)"
                        class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700"
                      >
                        Short by {{ formatQuantity(shortageAmount(recipe)) }}
                      </span>
                      <span
                        v-else-if="recipe.inventory_item.low_stock"
                        class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800"
                      >
                        Low stock
                      </span>
                    </div>

                    <div class="flex flex-wrap gap-4 text-sm text-slate-600">
                      <span>Qty per order: <strong class="text-slate-900">{{ formatQuantity(recipe.quantity) }}</strong></span>
                      <span>Available: <strong class="text-slate-900">{{ formatQuantity(recipe.inventory_item.total_stock) }}</strong></span>
                      <span>SKU: <strong class="text-slate-900">{{ recipe.inventory_item.sku || 'N/A' }}</strong></span>
                    </div>
                  </div>

                  <div class="flex flex-wrap gap-2">
                    <Link
                      class="btn-secondary"
                      :href="route('admin.inventory.show', recipe.inventory_item.id)"
                    >
                      View inventory
                    </Link>

                    <button
                      type="button"
                      class="btn-secondary"
                      @click="editRecipe(recipe)"
                    >
                      Edit
                    </button>

                    <button
                      type="button"
                      class="btn-danger"
                      :disabled="deletingRecipeId === recipe.id"
                      @click="destroy(recipe)"
                    >
                      {{ deletingRecipeId === recipe.id ? 'Removing...' : 'Remove' }}
                    </button>
                  </div>
                </div>
              </div>

              <div v-if="!selectedItemRecipes.length" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center">
                <p class="text-base font-medium text-slate-700">No ingredients configured yet.</p>
                <p class="mt-1 text-sm text-slate-500">
                  Start by choosing an ingredient and saving the quantity used per order.
                </p>
              </div>
            </div>

            <div v-else class="mt-5 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
              Select a menu item to view and manage its recipe ingredients.
            </div>
          </div>
        </div>

        <div class="space-y-6">
          <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 pb-4">
              <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                  <h2 class="text-lg font-semibold text-slate-900">Coverage view</h2>
                  <p class="mt-1 text-sm text-slate-500">
                    Search menu items, filter for gaps, and jump directly into recipe setup.
                  </p>
                </div>

                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600">
                  {{ visibleMenuItems.length }} shown
                </span>
              </div>

              <div class="grid gap-3 lg:grid-cols-[1fr_auto]">
                <input
                  v-model="search"
                  type="text"
                  class="field"
                  placeholder="Search menu items, categories, or ingredients..."
                />

                <div class="flex flex-wrap gap-2">
                  <button
                    type="button"
                    class="filter-chip"
                    :class="statusFilter === 'all' ? 'filter-chip-active' : ''"
                    @click="statusFilter = 'all'"
                  >
                    All
                  </button>
                  <button
                    type="button"
                    class="filter-chip"
                    :class="statusFilter === 'missing' ? 'filter-chip-active' : ''"
                    @click="statusFilter = 'missing'"
                  >
                    Missing
                  </button>
                  <button
                    type="button"
                    class="filter-chip"
                    :class="statusFilter === 'at_risk' ? 'filter-chip-active' : ''"
                    @click="statusFilter = 'at_risk'"
                  >
                    At risk
                  </button>
                  <button
                    type="button"
                    class="filter-chip"
                    :class="statusFilter === 'complete' ? 'filter-chip-active' : ''"
                    @click="statusFilter = 'complete'"
                  >
                    Healthy
                  </button>
                </div>
              </div>
            </div>

            <div class="mt-5 space-y-3">
              <button
                v-for="item in visibleMenuItems"
                :key="item.id"
                type="button"
                class="w-full rounded-2xl border p-4 text-left transition"
                :class="selectedMenuItemId === item.id ? 'border-slate-900 bg-slate-900 text-white shadow-sm' : 'border-slate-200 bg-slate-50/70 hover:border-slate-300 hover:bg-white'"
                @click="selectMenuItem(item.id)"
              >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                  <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                      <h3 class="text-base font-semibold">{{ item.name }}</h3>
                      <span
                        class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide"
                        :class="selectedMenuItemId === item.id ? 'bg-white/10 text-white' : item.summary.statusTone"
                      >
                        {{ item.summary.statusLabel }}
                      </span>
                      <span
                        v-if="!item.is_available"
                        class="rounded-full bg-slate-200 px-3 py-1 text-xs font-semibold text-slate-700"
                      >
                        Unavailable
                      </span>
                    </div>

                    <div class="flex flex-wrap gap-2 text-xs">
                      <span class="rounded-full px-3 py-1" :class="selectedMenuItemId === item.id ? 'bg-white/10 text-slate-100' : 'bg-white text-slate-600'">
                        {{ item.category_name ?? 'No category' }}
                      </span>
                      <span
                        v-if="item.subcategory_name"
                        class="rounded-full px-3 py-1"
                        :class="selectedMenuItemId === item.id ? 'bg-white/10 text-slate-100' : 'bg-white text-slate-600'"
                      >
                        {{ item.subcategory_name }}
                      </span>
                      <span class="rounded-full px-3 py-1" :class="selectedMenuItemId === item.id ? 'bg-white/10 text-slate-100' : 'bg-white text-slate-600'">
                        {{ item.service_area ?? 'General service' }}
                      </span>
                    </div>
                  </div>

                  <div class="grid min-w-[220px] grid-cols-3 gap-2 text-sm">
                    <div class="rounded-2xl px-3 py-2" :class="selectedMenuItemId === item.id ? 'bg-white/10 text-white' : 'bg-white text-slate-700'">
                      <p class="text-[11px] uppercase tracking-wide opacity-70">Ingredients</p>
                      <p class="mt-1 text-lg font-semibold">{{ item.summary.recipeCount }}</p>
                    </div>
                    <div class="rounded-2xl px-3 py-2" :class="selectedMenuItemId === item.id ? 'bg-white/10 text-white' : 'bg-white text-slate-700'">
                      <p class="text-[11px] uppercase tracking-wide opacity-70">Low stock</p>
                      <p class="mt-1 text-lg font-semibold">{{ item.summary.lowStockCount }}</p>
                    </div>
                    <div class="rounded-2xl px-3 py-2" :class="selectedMenuItemId === item.id ? 'bg-white/10 text-white' : 'bg-white text-slate-700'">
                      <p class="text-[11px] uppercase tracking-wide opacity-70">Shortages</p>
                      <p class="mt-1 text-lg font-semibold">{{ item.summary.atRiskCount }}</p>
                    </div>
                  </div>
                </div>
              </button>

              <div v-if="!visibleMenuItems.length" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center">
                <p class="text-base font-medium text-slate-700">No menu items match this filter.</p>
                <p class="mt-1 text-sm text-slate-500">Clear the search or choose a different coverage status.</p>
              </div>
            </div>
          </div>

          <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-2 border-b border-slate-100 pb-4 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h2 class="text-lg font-semibold text-slate-900">Operational recipe lines</h2>
                <p class="mt-1 text-sm text-slate-500">
                  A complete list of recipe ingredients with stock context for quick manager review.
                </p>
              </div>

              <button
                type="button"
                class="filter-chip"
                :class="stockSort !== null ? 'filter-chip-active' : ''"
                @click="toggleStockSort"
              >
                {{ stockSortLabel }}
              </button>
            </div>

            <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200">
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                  <thead class="bg-slate-50">
                    <tr>
                      <th class="px-4 py-3 text-left font-semibold text-slate-600">Menu item</th>
                      <th class="px-4 py-3 text-left font-semibold text-slate-600">Ingredient</th>
                      <th class="px-4 py-3 text-left font-semibold text-slate-600">Qty / order</th>
                      <th class="px-4 py-3 text-left font-semibold text-slate-600">Available</th>
                      <th class="px-4 py-3 text-right font-semibold text-slate-600">Actions</th>
                    </tr>
                  </thead>

                  <tbody class="divide-y divide-slate-200 bg-white">
                    <tr
                      v-for="recipe in visibleRecipeRows"
                      :key="recipe.id"
                      class="hover:bg-slate-50"
                    >
                      <td class="px-4 py-3">
                        <button
                          type="button"
                          class="font-semibold text-slate-900 hover:underline"
                          @click="editRecipe(recipe)"
                        >
                          {{ recipe.menu_item.name }}
                        </button>
                        <p class="mt-1 text-xs text-slate-500">
                          {{ recipe.menu_item.category_name ?? 'No category' }}
                        </p>
                      </td>

                      <td class="px-4 py-3">
                        <Link
                          class="font-medium text-slate-900 hover:underline"
                          :href="route('admin.inventory.show', recipe.inventory_item.id)"
                        >
                          {{ recipe.inventory_item.name }}
                        </Link>
                        <p class="mt-1 text-xs text-slate-500">
                          {{ recipe.inventory_item.unit || 'No unit' }}
                        </p>
                      </td>

                      <td class="px-4 py-3 font-semibold text-slate-700">{{ formatQuantity(recipe.quantity) }}</td>

                      <td class="px-4 py-3">
                        <div class="flex flex-wrap items-center gap-2">
                          <span
                            class="font-semibold"
                            :class="recipeIsShort(recipe) ? 'text-red-700' : recipe.inventory_item.low_stock ? 'text-amber-700' : 'text-emerald-700'"
                          >
                            {{ formatQuantity(recipe.inventory_item.total_stock) }}
                          </span>
                          <span
                            v-if="recipeIsShort(recipe)"
                            class="rounded-full bg-red-100 px-2 py-1 text-xs font-semibold text-red-700"
                          >
                            Short
                          </span>
                          <span
                            v-else-if="recipe.inventory_item.low_stock"
                            class="rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800"
                          >
                            Low
                          </span>
                        </div>
                      </td>

                      <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-2">
                          <button
                            type="button"
                            class="btn-secondary"
                            @click="editRecipe(recipe)"
                          >
                            Edit
                          </button>

                          <button
                            type="button"
                            class="btn-danger"
                            :disabled="deletingRecipeId === recipe.id"
                            @click="destroy(recipe)"
                          >
                            {{ deletingRecipeId === recipe.id ? 'Removing...' : 'Remove' }}
                          </button>
                        </div>
                      </td>
                    </tr>

                    <tr v-if="!visibleRecipeRows.length">
                      <td colspan="5" class="px-4 py-8 text-center text-slate-500">
                        No recipe lines match the current search and filter settings.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </ManagerLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'

const props = defineProps({
  recipes: {
    type: Array,
    default: () => [],
  },
  menuItems: {
    type: Array,
    default: () => [],
  },
  inventoryItems: {
    type: Array,
    default: () => [],
  },
})

const search = ref('')
const statusFilter = ref('all')
const stockSort = ref(null)
const deletingRecipeId = ref(null)
const editingRecipeId = ref(null)
const selectedMenuItemId = ref(
  props.menuItems.find(item => item.recipe_count === 0)?.id ?? props.menuItems[0]?.id ?? null
)

const form = useForm({
  menu_item_id: selectedMenuItemId.value ?? '',
  inventory_item_id: '',
  quantity: '',
})

const recipesByMenuItemId = computed(() => {
  return props.recipes.reduce((groups, recipe) => {
    if (!groups[recipe.menu_item.id]) {
      groups[recipe.menu_item.id] = []
    }

    groups[recipe.menu_item.id].push(recipe)
    return groups
  }, {})
})

const menuItemSummaries = computed(() => {
  return Object.fromEntries(
    props.menuItems.map(item => {
      const itemRecipes = [...(recipesByMenuItemId.value[item.id] ?? [])]
      const lowStockCount = itemRecipes.filter(recipe => recipe.inventory_item.low_stock).length
      const atRiskCount = itemRecipes.filter(recipe => recipeIsShort(recipe)).length
      const recipeCount = itemRecipes.length

      let status = 'complete'

      if (recipeCount === 0) {
        status = 'missing'
      } else if (atRiskCount > 0) {
        status = 'at_risk'
      }

      const toneMap = {
        complete: 'bg-emerald-100 text-emerald-800',
        missing: 'bg-slate-200 text-slate-700',
        at_risk: 'bg-red-100 text-red-700',
      }

      const labelMap = {
        complete: 'Healthy',
        missing: 'Missing recipe',
        at_risk: 'At risk',
      }

      return [item.id, {
        recipeCount,
        lowStockCount,
        atRiskCount,
        status,
        statusLabel: labelMap[status],
        statusTone: toneMap[status],
      }]
    })
  )
})

const sortedMenuItems = computed(() => {
  return [...props.menuItems].sort((a, b) => a.name.localeCompare(b.name))
})

const sortedInventoryItems = computed(() => {
  return [...props.inventoryItems].sort((a, b) => {
    if (a.low_stock !== b.low_stock) {
      return a.low_stock ? -1 : 1
    }

    return a.name.localeCompare(b.name)
  })
})

const selectedMenuItem = computed(() => {
  return props.menuItems.find(item => item.id === selectedMenuItemId.value) ?? null
})

const selectedSummary = computed(() => {
  if (!selectedMenuItem.value) {
    return null
  }

  return menuItemSummaries.value[selectedMenuItem.value.id]
})

const selectedItemRecipes = computed(() => {
  if (!selectedMenuItemId.value) {
    return []
  }

  return [...(recipesByMenuItemId.value[selectedMenuItemId.value] ?? [])].sort((a, b) => {
    if (recipeIsShort(a) !== recipeIsShort(b)) {
      return recipeIsShort(a) ? -1 : 1
    }

    if (a.inventory_item.low_stock !== b.inventory_item.low_stock) {
      return a.inventory_item.low_stock ? -1 : 1
    }

    return a.inventory_item.name.localeCompare(b.inventory_item.name)
  })
})

const selectedIngredientPreview = computed(() => {
  return props.inventoryItems.find(item => item.id === form.inventory_item_id) ?? null
})

const summary = computed(() => {
  const statuses = Object.values(menuItemSummaries.value)
  const lowStockIngredientIds = new Set(
    props.recipes
      .filter(recipe => recipe.inventory_item.low_stock)
      .map(recipe => recipe.inventory_item.id)
  )

  return {
    totalMenuItems: props.menuItems.length,
    missingRecipes: statuses.filter(item => item.status === 'missing').length,
    atRiskItems: statuses.filter(item => item.status === 'at_risk').length,
    lowStockIngredients: lowStockIngredientIds.size,
  }
})

const visibleMenuItems = computed(() => {
  const query = search.value.trim().toLowerCase()

  return sortedMenuItems.value
    .map(item => ({
      ...item,
      summary: menuItemSummaries.value[item.id],
      ingredients: (recipesByMenuItemId.value[item.id] ?? []).map(recipe => recipe.inventory_item.name.toLowerCase()),
    }))
    .filter(item => {
      const matchesStatus =
        statusFilter.value === 'all' ||
        item.summary.status === statusFilter.value

      if (!matchesStatus) {
        return false
      }

      if (!query) {
        return true
      }

      return [
        item.name,
        item.category_name,
        item.subcategory_name,
        ...item.ingredients,
      ]
        .filter(Boolean)
        .some(value => value.toLowerCase().includes(query))
    })
})

const visibleRecipeRows = computed(() => {
  let rows = [...props.recipes]

  if (search.value.trim()) {
    const query = search.value.trim().toLowerCase()
    rows = rows.filter(recipe => {
      return [
        recipe.menu_item.name,
        recipe.menu_item.category_name,
        recipe.inventory_item.name,
        recipe.inventory_item.sku,
      ]
        .filter(Boolean)
        .some(value => value.toLowerCase().includes(query))
    })
  }

  if (statusFilter.value === 'missing') {
    rows = []
  }

  if (statusFilter.value === 'at_risk') {
    rows = rows.filter(recipe => recipeIsShort(recipe))
  }

  if (statusFilter.value === 'complete') {
    rows = rows.filter(recipe => !recipeIsShort(recipe))
  }

  if (stockSort.value) {
    rows.sort((a, b) => {
      const difference = a.inventory_item.total_stock - b.inventory_item.total_stock
      return stockSort.value === 'asc' ? difference : -difference
    })
  } else {
    rows.sort((a, b) => {
      if (recipeIsShort(a) !== recipeIsShort(b)) {
        return recipeIsShort(a) ? -1 : 1
      }

      return a.menu_item.name.localeCompare(b.menu_item.name)
    })
  }

  return rows
})

const stockSortLabel = computed(() => {
  if (stockSort.value === 'desc') {
    return 'Stock: high to low'
  }

  if (stockSort.value === 'asc') {
    return 'Stock: low to high'
  }

  return 'Sort by stock'
})

watch(selectedMenuItemId, value => {
  form.menu_item_id = value ?? ''

  if (!editingRecipeId.value) {
    form.inventory_item_id = ''
    form.quantity = ''
  }
})

watch(
  () => form.menu_item_id,
  value => {
    if (value && value !== selectedMenuItemId.value) {
      selectedMenuItemId.value = value
    }
  }
)

function formatQuantity(value) {
  const amount = Number(value ?? 0)

  if (Number.isInteger(amount)) {
    return amount.toString()
  }

  return amount.toFixed(2).replace(/\.?0+$/, '')
}

function recipeIsShort(recipe) {
  return Number(recipe.inventory_item.total_stock) < Number(recipe.quantity)
}

function shortageAmount(recipe) {
  return Math.max(0, Number(recipe.quantity) - Number(recipe.inventory_item.total_stock))
}

function selectMenuItem(id) {
  selectedMenuItemId.value = id
}

function showMissingCoverage() {
  statusFilter.value = 'missing'
  const nextItem = visibleMenuItems.value[0]

  if (nextItem) {
    selectMenuItem(nextItem.id)
  }
}

function showAtRiskCoverage() {
  statusFilter.value = 'at_risk'
  const nextItem = visibleMenuItems.value[0]

  if (nextItem) {
    selectMenuItem(nextItem.id)
  }
}

function resetFormForSelectedMenuItem() {
  editingRecipeId.value = null
  form.inventory_item_id = ''
  form.quantity = ''
  form.clearErrors()
  form.menu_item_id = selectedMenuItemId.value ?? ''
}

function editRecipe(recipe) {
  editingRecipeId.value = recipe.id
  selectedMenuItemId.value = recipe.menu_item.id
  form.menu_item_id = recipe.menu_item.id
  form.inventory_item_id = recipe.inventory_item.id
  form.quantity = recipe.quantity
}

function submit() {
  form.post(route('admin.menu-recipes.store'), {
    preserveScroll: true,
    onSuccess: () => {
      editingRecipeId.value = null
      form.inventory_item_id = ''
      form.quantity = ''
      form.clearErrors()
    },
  })
}

function destroy(recipe) {
  if (!confirm(`Remove ${recipe.inventory_item.name} from ${recipe.menu_item.name}?`)) {
    return
  }

  deletingRecipeId.value = recipe.id

  router.delete(route('admin.menu-recipes.destroy', recipe.id), {
    preserveScroll: true,
    onSuccess: () => {
      if (editingRecipeId.value === recipe.id) {
        resetFormForSelectedMenuItem()
      }
    },
    onFinish: () => {
      deletingRecipeId.value = null
    },
  })
}

function toggleStockSort() {
  if (stockSort.value === null) {
    stockSort.value = 'desc'
    return
  }

  if (stockSort.value === 'desc') {
    stockSort.value = 'asc'
    return
  }

  stockSort.value = null
}
</script>

<style scoped>
.summary-card {
  @apply flex min-h-[128px] flex-col justify-between rounded-2xl border border-white/10 bg-white/5 p-4 transition hover:border-white/20 hover:bg-white/10;
}

.summary-card-active {
  @apply border-emerald-300/80 bg-emerald-400/15;
}

.summary-label {
  @apply text-xs font-semibold uppercase tracking-[0.18em] text-slate-300;
}

.summary-value {
  @apply mt-3 text-3xl font-semibold text-white;
}

.summary-note {
  @apply mt-2 text-sm text-slate-300;
}

.field {
  @apply w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-200;
}

.btn-primary {
  @apply inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60;
}

.btn-secondary {
  @apply inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60;
}

.btn-danger {
  @apply inline-flex items-center justify-center rounded-2xl border border-red-200 bg-white px-4 py-2.5 text-sm font-medium text-red-700 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60;
}

.filter-chip {
  @apply inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 transition hover:border-slate-300 hover:bg-slate-50;
}

.filter-chip-active {
  @apply border-slate-900 bg-slate-900 text-white hover:bg-slate-900;
}
</style>
