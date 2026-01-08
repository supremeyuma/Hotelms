<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { router, usePage, Head } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import {
  Plus,
  Minus,
  X,
} from 'lucide-vue-next'

/* ================= PROPS ================= */
const page = usePage()

const props = defineProps({
  categories: Array,
  type: String,
  accessToken: String,
})

/* ================= STATE ================= */
const activeCategory = ref(props.categories?.[0] || null)
const activeSubcategory = ref(null)
const cart = ref([])

const showPreview = ref(false)
const submitting = ref(false)

/* ================= FLASH / TOAST ================= */
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
      sessionStorage.removeItem('guest-cart')
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

/* ================= CART PERSISTENCE ================= */
onMounted(() => {
  const saved = sessionStorage.getItem('guest-cart')
  if (saved) {
    try {
      cart.value = JSON.parse(saved)
    } catch {
      sessionStorage.removeItem('guest-cart')
    }
  }
})

watch(
  cart,
  (val) => {
    sessionStorage.setItem('guest-cart', JSON.stringify(val))
  },
  { deep: true }
)

/* ================= ITEMS ================= */
const items = computed(() => {
  if (!activeCategory.value) return []
  if (activeSubcategory.value) return activeSubcategory.value.items

  let all = [...activeCategory.value.items]
  activeCategory.value.subcategories?.forEach(s =>
    all.push(...s.items)
  )
  return all
})

const total = computed(() =>
  cart.value.reduce((t, i) => t + i.price * i.quantity, 0)
)

/* ================= CART ACTIONS ================= */
function add(item) {
  const found = cart.value.find(i => i.id === item.id)
  if (found) {
    found.quantity++
  } else {
    cart.value.push({ ...item, quantity: 1 })
  }
}

function remove(item) {
  const found = cart.value.find(i => i.id === item.id)
  if (!found) return
  if (found.quantity > 1) found.quantity--
  else cart.value = cart.value.filter(i => i.id !== item.id)
}

/* ================= ORDER FLOW ================= */
function openPreview() {
  if (!cart.value.length) return
  showPreview.value = true
}

function confirmOrder() {
  if (submitting.value) return
  submitting.value = true

  router.post(
    `/guest/room/${props.accessToken}/orders`,
    {
      department: props.type,
      items: cart.value.map(i => ({
        name: i.name,
        price: i.price,
        quantity: i.quantity,
        note: i.note || null,
      })),
    },
    {
      onFinish: () => {
        submitting.value = false
        showPreview.value = false
        toast.value = 'Order placed successfully.'
      },
      onError: () => {
      submitting.value = false
      toastType.value = 'error'
      toast.value = 'Failed to place order. Please review your items.'
    },
    }
  )
}

function goToHistory() {
  router.visit(`/guest/room/${props.accessToken}/orders`)
}
</script>

<template>
  <GuestLayout>
    <Head :title="`${type} Menu`" />

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

    <!-- CONFIRM SUCCESS MODAL -->
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
    <div class="max-w-6xl mx-auto p-4 space-y-4">
      <h1 class="font-black text-lg capitalize">{{ type }} Menu</h1>

      <!-- CATEGORIES -->
      <div class="flex gap-2 overflow-x-auto">
        <button
          v-for="c in categories"
          :key="c.id"
          @click="activeCategory = c; activeSubcategory = null"
          class="px-4 py-2 rounded-full text-xs font-bold uppercase"
          :class="activeCategory?.id === c.id ? 'bg-black text-white' : 'bg-gray-200'"
        >
          {{ c.name }}
        </button>
      </div>

      <!-- ITEMS -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div
          v-for="item in items"
          :key="item.id"
          class="border rounded-xl p-3 bg-white"
        >
          <img
            v-if="item.images?.length"
            :src="`/storage/${item.images[0].path}`"
            class="h-40 w-full object-cover rounded mb-2"
          />
          <h3 class="font-bold text-sm">{{ item.name }}</h3>
          <p class="text-xs text-gray-500 mb-2">₦{{ item.price }}</p>

          <div class="flex items-center justify-between">
            <button
              @click="remove(item)"
              class="p-1 rounded bg-gray-100"
            >
              <Minus class="w-4 h-4" />
            </button>

            <span class="font-black text-sm">
              {{ cart.find(i => i.id === item.id)?.quantity || 0 }}
            </span>

            <button
              @click="add(item)"
              class="p-1 rounded bg-gray-100"
            >
              <Plus class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- CART BAR -->
    <div
      v-if="cart.length"
      class="fixed bottom-0 left-0 right-0 bg-white border-t p-4 z-40"
    >
      <div class="max-w-6xl mx-auto flex justify-between items-center">
        <div>
          <p class="font-black">₦{{ total }}</p>
          <p class="text-xs">{{ cart.length }} items</p>
        </div>

        <button
          @click="openPreview"
          class="bg-green-600 text-white px-6 py-3 rounded-xl font-black uppercase text-xs disabled:opacity-50"
        >
          Review Order
        </button>
      </div>
    </div>

    <!-- ORDER PREVIEW MODAL -->
    <div
      v-if="showPreview"
      class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center"
    >
      <div class="bg-white rounded-2xl p-6 w-full max-w-md space-y-4">
        <div class="flex justify-between items-center">
          <h2 class="font-black text-lg">Confirm Order</h2>
          <button @click="showPreview = false">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="space-y-3 max-h-64 overflow-y-auto">
          <div
            v-for="item in cart"
            :key="item.id"
            class="flex justify-between text-sm"
          >
            <div>
              <p class="font-bold">{{ item.name }}</p>
              <p class="text-xs text-gray-500">
                {{ item.quantity }} × ₦{{ item.price }}
              </p>
              <input
                v-model="item.note"
                placeholder="Item note (optional)"
                class="w-full border rounded p-1 text-xs"
              />
            </div>
            <p class="font-black">
              ₦{{ item.price * item.quantity }}
            </p>
          </div>
        </div>

        <div class="border-t pt-3 flex justify-between font-black">
          <span>Total</span>
          <span>₦{{ total }}</span>
        </div>

        <div class="flex gap-3">
          <button
            @click="showPreview = false"
            class="flex-1 py-3 bg-gray-100 rounded-xl font-black text-xs uppercase"
          >
            Cancel
          </button>
          <button
            @click="confirmOrder"
            :disabled="submitting"
            class="flex-1 py-3 bg-green-600 text-white rounded-xl font-black text-xs uppercase disabled:opacity-50"
          >
            Confirm
          </button>
        </div>
      </div>
    </div>
  </GuestLayout>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.4s ease;
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translate(-50%, -10px);
}
</style>
