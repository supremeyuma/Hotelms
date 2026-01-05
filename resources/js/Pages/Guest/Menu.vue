<script setup>
import { ref, computed, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'

const page = usePage()

const props = defineProps({
  categories: Array,
  type: String,
  accessToken: String,
})

const activeCategory = ref(props.categories[0] || null)
const activeSubcategory = ref(null)
const cart = ref([])

/* ---------------- FLASH ---------------- */
const toast = ref(null)
const toastType = ref('success')
const showConfirm = ref(false)

watch(
  () => page.props.flash,
  (flash) => {
    if (flash?.success) {
      toastType.value = 'success'
      toast.value = flash.success
      showConfirm.value = true
      cart.value = []
    }

    if (flash?.error) {
      toastType.value = 'error'
      toast.value = flash.error
    }

    if (toast.value) {
      setTimeout(() => (toast.value = null), 3500)
    }
  },
  { deep: true, immediate: true }
)

/* ---------------- ITEMS ---------------- */
const items = computed(() => {
  if (!activeCategory.value) return []
  if (activeSubcategory.value) return activeSubcategory.value.items
  let all = [...activeCategory.value.items]
  activeCategory.value.subcategories.forEach(s => all.push(...s.items))
  return all
})

const total = computed(() =>
  cart.value.reduce((t, i) => t + i.price * i.quantity, 0)
)

function add(item) {
  const found = cart.value.find(i => i.id === item.id)
  found ? found.quantity++ : cart.value.push({ ...item, quantity: 1 })
}

function placeOrder() {
  router.post(`/guest/room/${props.accessToken}/orders`, {
    department: props.type,
    items: cart.value.map(i => ({
      name: i.name,
      price: i.price,
      quantity: i.quantity,
    })),
  })
}

function goToHistory() {
  router.visit(`/guest/room/${props.accessToken}/orders`)
}
</script>

<template>
  <GuestLayout>

    <!-- TOAST -->
    <transition name="toast">
      <div
        v-if="toast"
        class="fixed top-4 left-1/2 -translate-x-1/2 z-50 px-6 py-3 rounded-xl text-white font-bold shadow-lg"
        :class="toastType === 'success' ? 'bg-green-600' : 'bg-red-600'"
      >
        {{ toast }}
      </div>
    </transition>

    <!-- CONFIRM MODAL -->
    <div v-if="showConfirm" class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center">
      <div class="bg-white rounded-2xl p-6 max-w-sm w-full text-center space-y-4">
        <h2 class="font-black text-lg">Order Confirmed 🎉</h2>
        <p class="text-sm text-gray-500">
          Your order has been sent to the {{ type }}.
        </p>
        <button
          @click="goToHistory"
          class="w-full bg-black text-white py-3 rounded-xl font-black uppercase text-xs"
        >
          View Order History
        </button>
        <button
          @click="showConfirm = false"
          class="text-xs text-gray-400 underline"
        >
          Close
        </button>
      </div>
    </div>

    <!-- MENU -->
    <div class="max-w-6xl mx-auto p-4">
      <div class="flex gap-2 overflow-x-auto mb-4">
        <button
          v-for="c in categories"
          :key="c.id"
          @click="activeCategory = c; activeSubcategory = null"
          class="px-4 py-2 rounded-full text-xs font-bold uppercase"
          :class="activeCategory.id === c.id ? 'bg-black text-white' : 'bg-gray-200'"
        >
          {{ c.name }}
        </button>
      </div>

      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div
          v-for="item in items"
          :key="item.id"
          class="border rounded-xl p-2 bg-white"
        >
          <img
            v-if="item.images?.length"
            :src="`/storage/${item.images[0].path}`"
            class="h-40 w-full object-cover rounded mb-2"
          />
          <h3 class="font-bold text-sm">{{ item.name }}</h3>
          <p class="text-xs text-gray-500">₦{{ item.price }}</p>
          <button
            @click="add(item)"
            class="mt-2 w-full bg-black text-white py-1 rounded text-xs font-bold"
          >
            Add
          </button>
        </div>
      </div>
    </div>

    <!-- CART -->
    <div v-if="cart.length" class="fixed bottom-0 left-0 right-0 bg-white border-t p-4">
      <div class="max-w-6xl mx-auto flex justify-between items-center">
        <div>
          <p class="font-bold">₦{{ total }}</p>
          <p class="text-xs">{{ cart.length }} items</p>
        </div>
        <button
          @click="placeOrder"
          class="bg-green-600 text-white px-6 py-3 rounded-xl font-black uppercase text-xs"
        >
          Place Order
        </button>
      </div>
    </div>

  </GuestLayout>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all .4s ease;
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translate(-50%, -10px);
}
</style>
