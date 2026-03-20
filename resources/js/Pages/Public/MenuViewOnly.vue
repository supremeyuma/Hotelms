<script setup>
import { ref, computed } from 'vue'
import { router, Head } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { ArrowLeft, Star } from 'lucide-vue-next'

/* ================= PROPS ================= */
const props = defineProps({
  categories: Array,
  type: String,
})

/* ================= STATE ================= */
const activeCategory = ref(props.categories?.[0] || null)
const activeSubcategory = ref(null)
const selectedItem = ref(null)

/* ================= COMPUTED ================= */
const items = computed(() => {
  if (!activeCategory.value) return []
  if (activeSubcategory.value) return activeSubcategory.value.items

  let all = [...activeCategory.value.items]
  activeCategory.value.subcategories?.forEach(s => all.push(...s.items))
  return all
})

/* ================= ACTIONS ================= */
function goHome() {
  router.visit(route('home'))
}

function openDetails(item) {
  selectedItem.value = item
}

function closeDetails() {
  selectedItem.value = null
}
</script>

<template>
  <GuestLayout>
    <Head title="Menu" />

    <!-- HEADER -->
    <div class="sticky top-0 z-30 bg-white border-b border-gray-200">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
        <button
          @click="goHome"
          class="flex items-center gap-2 text-sm font-bold text-gray-600 hover:text-gray-900 transition"
        >
          <ArrowLeft class="w-4 h-4" />
          Back
        </button>

        <div class="text-center">
          <h1 class="text-2xl font-black text-gray-900">Menu</h1>
          <div class="flex items-center justify-center gap-2 mt-2">
            <button
              @click="router.visit(route('menu.view.show', { type: 'kitchen' }))"
              class="px-4 py-1.5 rounded-full text-xs font-bold transition"
              :class="type === 'kitchen' 
                ? 'bg-black text-white' 
                : 'bg-gray-200 text-gray-700 hover:bg-gray-300'"
            >
              🍽️ Kitchen
            </button>
            <button
              @click="router.visit(route('menu.view.show', { type: 'bar' }))"
              class="px-4 py-1.5 rounded-full text-xs font-bold transition"
              :class="type === 'bar' 
                ? 'bg-black text-white' 
                : 'bg-gray-200 text-gray-700 hover:bg-gray-300'"
            >
              🍹 Bar
            </button>
          </div>
        </div>

        <div class="w-8"></div>
      </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="bg-white min-h-screen">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- CATEGORIES TABS -->
        <div class="mb-8">
          <div class="flex gap-2 overflow-x-auto pb-4">
            <button
              v-for="c in categories"
              :key="c.id"
              @click="activeCategory = c; activeSubcategory = null"
              class="px-6 py-3 rounded-full font-semibold text-sm whitespace-nowrap transition"
              :class="
                activeCategory?.id === c.id
                  ? 'bg-black text-white shadow-lg'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200 border border-gray-300'
              "
            >
              {{ c.name }}
            </button>
          </div>
        </div>

        <!-- SUBCATEGORIES -->
        <div v-if="activeCategory?.subcategories?.length" class="mb-8">
          <p class="text-xs font-semibold text-gray-500 uppercase mb-3">Collections</p>
          <div class="flex gap-2 overflow-x-auto">
            <button
              @click="activeSubcategory = null"
              :class="
                !activeSubcategory
                  ? 'ring-2 ring-black'
                  : 'hover:bg-gray-50'
              "
              class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition bg-gray-50 border border-gray-200"
            >
              All Items
            </button>
            <button
              v-for="sub in activeCategory.subcategories"
              :key="sub.id"
              @click="activeSubcategory = sub"
              class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition bg-gray-50 border border-gray-200"
              :class="
                activeSubcategory?.id === sub.id
                  ? 'ring-2 ring-black text-gray-900'
                  : 'text-gray-600 hover:bg-gray-100'
              "
            >
              {{ sub.name }}
            </button>
          </div>
        </div>

        <!-- ITEMS GRID -->
        <div>
          <div v-if="!items.length" class="text-center py-12">
            <p class="text-gray-500 text-lg">No items available in this category</p>
          </div>

          <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <div
              v-for="item in items"
              :key="item.id"
              @click="openDetails(item)"
              class="group cursor-pointer bg-white rounded-2xl overflow-hidden border border-gray-200 hover:border-black hover:shadow-xl transition-all duration-300"
            >
              <!-- IMAGE -->
              <div class="relative overflow-hidden bg-gray-100 h-48">
                <img
                  v-if="item.images?.length"
                  :src="`/storage/${item.images[0].path}`"
                  :alt="item.name"
                  class="h-full w-full object-cover group-hover:scale-125 transition-transform duration-300"
                />
                <div v-else class="h-full flex items-center justify-center text-gray-400">
                  <span class="text-xs">No image</span>
                </div>
              </div>

              <!-- CONTENT -->
              <div class="p-4 space-y-2">
                <h3 class="font-bold text-sm text-gray-900 line-clamp-2">
                  {{ item.name }}
                </h3>
                <p v-if="item.description" class="text-xs text-gray-500 line-clamp-2">
                  {{ item.description }}
                </p>
                <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                  <span class="font-black text-base text-gray-900">₦{{ item.price }}</span>
                  <div class="flex gap-1">
                    <Star class="w-4 h-4 text-amber-400 fill-amber-400" />
                    <span class="text-xs font-semibold text-gray-700">Popular</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ITEM DETAILS MODAL -->
    <div
      v-if="selectedItem"
      class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
    >
      <div class="bg-white rounded-2xl overflow-hidden w-full max-w-md max-h-[90vh] overflow-y-auto">
        <!-- IMAGE -->
        <div class="relative bg-gray-100 h-64 overflow-hidden">
          <img
            v-if="selectedItem.images?.length"
            :src="`/storage/${selectedItem.images[0].path}`"
            :alt="selectedItem.name"
            class="h-full w-full object-cover"
          />
          <div v-else class="h-full flex items-center justify-center text-gray-400">
            <span>No image</span>
          </div>

          <!-- CLOSE BUTTON -->
          <button
            @click="closeDetails"
            class="absolute top-4 right-4 bg-white rounded-full p-2 shadow-lg hover:bg-gray-100 transition"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- CONTENT -->
        <div class="p-6 space-y-4">
          <div>
            <h2 class="text-2xl font-black text-gray-900">{{ selectedItem.name }}</h2>
            <p class="text-gray-600 text-sm mt-2 leading-relaxed">
              {{ selectedItem.description || 'A delicious item from our menu.' }}
            </p>
          </div>

          <!-- PRICE & INFO -->
          <div class="bg-gray-50 p-4 rounded-xl space-y-3">
            <div class="flex justify-between items-center">
              <span class="text-gray-600 font-semibold">Price</span>
              <span class="text-2xl font-black text-gray-900">₦{{ selectedItem.price }}</span>
            </div>

            <div v-if="selectedItem.prep_time_minutes" class="flex justify-between items-center text-sm">
              <span class="text-gray-600 font-semibold">Prep Time</span>
              <span class="text-gray-900 font-semibold">
                {{ selectedItem.prep_time_minutes }} min
              </span>
            </div>
          </div>

          <!-- CTA -->
          <div class="flex gap-3 pt-4">
            <button
              @click="closeDetails"
              class="flex-1 py-3 bg-gray-100 text-gray-900 rounded-lg font-bold text-sm uppercase transition hover:bg-gray-200"
            >
              Close
            </button>
            <a
              href="#"
              class="flex-1 py-3 bg-black text-white rounded-lg font-bold text-sm uppercase transition hover:bg-gray-800 text-center"
            >
              Place Order
            </a>
          </div>
        </div>
      </div>
    </div>
  </GuestLayout>
</template>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  line-clamp: 2;
  overflow: hidden;
}
</style>
