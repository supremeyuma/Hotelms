<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { router, usePage, Head } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import {
  Plus,
  Minus,
  X,
  ArrowLeft,
  ClipboardList,
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
const paymentMode = ref('pay_on_delivery')

/* ================= UI ================= */
const toast = ref(null)
const toastType = ref('success')
const showConfirm = ref(false)

/* ================= FLASH ================= */
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

/* ================= CART ================= */
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
  (val) => sessionStorage.setItem('guest-cart', JSON.stringify(val)),
  { deep: true }
)

/* ================= COMPUTED ================= */
const items = computed(() => {
  if (!activeCategory.value) return []
  if (activeSubcategory.value) return activeSubcategory.value.items

  let all = [...activeCategory.value.items]
  activeCategory.value.subcategories?.forEach(s => all.push(...s.items))
  return all
})

const total = computed(() =>
  cart.value.reduce((t, i) => t + i.price * i.quantity, 0)
)

/* ================= CART ACTIONS ================= */
function add(item) {
  const found = cart.value.find(i => i.id === item.id)
  found ? found.quantity++ : cart.value.push({ ...item, quantity: 1 })
}

function remove(item) {
  const found = cart.value.find(i => i.id === item.id)
  if (!found) return
  found.quantity > 1
    ? found.quantity--
    : (cart.value = cart.value.filter(i => i.id !== item.id))
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
      payment_mode: paymentMode.value,
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
        cart.value = []
        sessionStorage.removeItem('guest-cart')
      },
      onError: () => {
        submitting.value = false
        toastType.value = 'error'
        toast.value = 'Failed to place order. Please try again.'
      },
    }
  )
}

function goBack() {
  router.visit(route('guest.room.dashboard', props.accessToken))
}

function openHistory() {
  router.visit(route('guest.room.dashboard', props.accessToken), {
    data: { showOrders: true },
    preserveState: false,
  })
}
</script>

<template>
  <GuestLayout>
    <Head :title="`${type} Menu`" />

    <!-- HEADER -->
    <div class="sticky top-0 z-30 bg-white border-b border-gray-200">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
        <button
          @click="goBack"
          class="flex items-center gap-2 text-sm font-bold text-gray-600 hover:text-gray-900 transition"
        >
          <ArrowLeft class="w-4 h-4" />
          Back
        </button>

        <div class="text-center">
          <h1 class="text-2xl font-black text-gray-900">{{ type }} Menu</h1>
          <div class="flex items-center justify-center gap-2 mt-2">
            <button
              @click="router.visit(route('guest.room.dashboard', props.accessToken) + '/menu/kitchen')"
              class="px-4 py-1.5 rounded-full text-xs font-bold transition"
              :class="type === 'kitchen' 
                ? 'bg-black text-white' 
                : 'bg-gray-200 text-gray-700 hover:bg-gray-300'"
            >
              🍽️ Kitchen
            </button>
            <button
              @click="router.visit(route('guest.room.dashboard', props.accessToken) + '/menu/bar')"
              class="px-4 py-1.5 rounded-full text-xs font-bold transition"
              :class="type === 'bar' 
                ? 'bg-black text-white' 
                : 'bg-gray-200 text-gray-700 hover:bg-gray-300'"
            >
              🍹 Bar
            </button>
          </div>
        </div>

        <button
          @click="openHistory"
          class="flex items-center gap-2 text-sm font-bold text-gray-600 hover:text-gray-900 transition"
        >
          <ClipboardList class="w-4 h-4" />
          <span class="hidden sm:inline">Orders</span>
        </button>
      </div>
    </div>

    <!-- TOAST -->
    <transition name="toast">
      <div
        v-if="toast"
        class="fixed top-24 left-1/2 -translate-x-1/2 z-50 px-6 py-4 rounded-lg text-white font-semibold shadow-lg"
        :class="toastType === 'success' ? 'bg-green-600' : 'bg-red-600'"
      >
        {{ toast }}
      </div>
    </transition>

    <!-- SUCCESS MODAL -->
    <div
      v-if="showConfirm"
      class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center p-4"
    >
      <div class="bg-white rounded-2xl p-8 max-w-sm w-full text-center space-y-6">
        <div class="flex justify-center">
          <svg class="w-16 h-16 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div>
          <h2 class="font-black text-xl text-gray-900">Order Confirmed!</h2>
          <p class="text-gray-600 text-sm mt-2">
            Your order has been sent to the {{ type }}. Check the status in Orders.
          </p>
        </div>
        <button
          @click="openHistory"
          class="w-full bg-black text-white py-3 rounded-lg font-semibold uppercase text-sm transition hover:bg-gray-800 flex items-center justify-center gap-2"
        >
          <ClipboardList class="w-4 h-4" />
          View Orders
        </button>
        <button
          @click="goBack"
          class="w-full bg-gray-100 text-gray-900 py-3 rounded-lg font-semibold uppercase text-sm transition hover:bg-gray-200"
        >
          Back to Room
        </button>
      </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="bg-gray-50 min-h-[calc(100vh-200px)]">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- CATEGORIES TABS -->
        <div class="mb-8">
          <div class="flex gap-2 overflow-x-auto pb-2">
            <button
              v-for="c in categories"
              :key="c.id"
              @click="activeCategory = c; activeSubcategory = null"
              class="px-6 py-3 rounded-full font-semibold text-sm whitespace-nowrap transition"
              :class="
                activeCategory?.id === c.id
                  ? 'bg-black text-white shadow-lg'
                  : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200'
              "
            >
              {{ c.name }}
            </button>
          </div>
        </div>

        <!-- SUBCATEGORIES -->
        <div v-if="activeCategory?.subcategories?.length" class="mb-8">
          <div class="flex gap-2 overflow-x-auto">
            <button
              v-for="sub in activeCategory.subcategories"
              :key="sub.id"
              @click="activeSubcategory = sub"
              class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition"
              :class="
                activeSubcategory?.id === sub.id
                  ? 'bg-gray-300 text-gray-900 font-semibold'
                  : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-200'
              "
            >
              {{ sub.name }}
            </button>
          </div>
        </div>

        <!-- ITEMS GRID + CART -->
        <div class="grid lg:grid-cols-4 gap-8">
          <!-- ITEMS SECTION -->
          <div class="lg:col-span-3">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
              <div
                v-for="item in items"
                :key="item.id"
                class="bg-white rounded-xl overflow-hidden shadow-sm hover:shadow-md transition group"
              >
                <!-- IMAGE -->
                <div class="relative overflow-hidden bg-gray-100 h-40">
                  <img
                    v-if="item.images?.length"
                    :src="`/storage/${item.images[0].path}`"
                    :alt="item.name"
                    class="h-full w-full object-cover group-hover:scale-110 transition"
                  />
                  <div v-else class="h-full flex items-center justify-center text-gray-400">
                    <span class="text-xs">No image</span>
                  </div>
                </div>

                <!-- CONTENT -->
                <div class="p-4 space-y-3">
                  <div>
                    <h3 class="font-bold text-sm text-gray-900">{{ item.name }}</h3>
                    <p v-if="item.description" class="text-xs text-gray-500 mt-1 line-clamp-2">
                      {{ item.description }}
                    </p>
                  </div>

                  <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                    <span class="font-bold text-base text-gray-900">₦{{ item.price }}</span>

                    <!-- QUANTITY SELECTOR -->
                    <div class="flex items-center gap-1 bg-gray-100 rounded-lg p-1">
                      <button
                        @click="remove(item)"
                        class="p-1 hover:bg-gray-200 rounded transition"
                      >
                        <Minus class="w-4 h-4 text-gray-600" />
                      </button>
                      <span class="w-6 text-center text-sm font-semibold">
                        {{ cart.find(i => i.id === item.id)?.quantity || 0 }}
                      </span>
                      <button
                        @click="add(item)"
                        class="p-1 hover:bg-gray-200 rounded transition"
                      >
                        <Plus class="w-4 h-4 text-gray-600" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- CART SIDEBAR -->
          <div class="lg:col-span-1">
            <div
              class="sticky top-24 bg-white rounded-xl shadow-lg p-6 space-y-4 border border-gray-100"
            >
              <h2 class="font-black text-lg text-gray-900">Your Cart</h2>

              <div
                v-if="!cart.length"
                class="text-center py-8 text-gray-500"
              >
                <Plus class="w-12 h-12 mx-auto opacity-20 mb-3" />
                <p class="text-sm">No items yet</p>
              </div>

              <!-- CART ITEMS -->
              <div v-else class="space-y-3 max-h-96 overflow-y-auto">
                <div
                  v-for="item in cart"
                  :key="item.id"
                  class="border border-gray-200 rounded-lg p-3 space-y-2"
                >
                  <div class="flex justify-between items-start gap-2">
                    <div class="flex-1">
                      <p class="font-semibold text-sm text-gray-900">{{ item.name }}</p>
                      <p class="text-xs text-gray-500">
                        {{ item.quantity }} × ₦{{ item.price }}
                      </p>
                    </div>
                    <button
                      @click="cart = cart.filter(i => i.id !== item.id)"
                      class="text-gray-400 hover:text-red-600 transition"
                    >
                      <X class="w-4 h-4" />
                    </button>
                  </div>
                  <p class="font-bold text-sm text-right text-gray-900">
                    ₦{{ item.price * item.quantity }}
                  </p>
                </div>
              </div>

              <!-- TOTALS -->
              <div v-if="cart.length" class="border-t border-gray-200 pt-4 space-y-2">
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600">Subtotal</span>
                  <span class="font-semibold text-gray-900">₦{{ total }}</span>
                </div>
                <div class="bg-gray-50 p-3 rounded-lg">
                  <div class="flex justify-between items-center">
                    <span class="font-bold text-gray-900">Total</span>
                    <span class="font-black text-lg text-gray-900">₦{{ total }}</span>
                  </div>
                </div>
              </div>

              <!-- CTA -->
              <button
                v-if="cart.length"
                @click="openPreview"
                class="w-full bg-black text-white py-3 rounded-lg font-bold uppercase text-sm transition hover:bg-gray-800"
              >
                Review Order
              </button>
              <button
                v-else
                disabled
                class="w-full bg-gray-300 text-gray-500 py-3 rounded-lg font-bold uppercase text-sm cursor-not-allowed"
              >
                Add items to start
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- PREVIEW/PAYMENT MODAL -->
    <div
      v-if="showPreview"
      class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
    >
      <div class="bg-white rounded-2xl p-8 w-full max-w-md space-y-6">
        <div class="flex justify-between items-center">
          <h2 class="font-black text-xl text-gray-900">Confirm Order</h2>
          <button @click="showPreview = false" class="text-gray-400 hover:text-gray-600">
            <X class="w-6 h-6" />
          </button>
        </div>

        <!-- ORDER SUMMARY -->
        <div class="space-y-3 max-h-64 overflow-y-auto">
          <div v-for="item in cart" :key="item.id" class="flex justify-between text-sm">
            <div class="flex-1">
              <p class="font-semibold text-gray-900">{{ item.name }}</p>
              <p class="text-xs text-gray-500">
                {{ item.quantity }} × ₦{{ item.price }}
              </p>
              <input
                v-model="item.note"
                placeholder="Item note (optional)"
                class="w-full border border-gray-300 rounded p-2 text-xs mt-2 focus:outline-none focus:ring-2 focus:ring-black"
              />
            </div>
            <p class="font-bold text-gray-900">₦{{ item.price * item.quantity }}</p>
          </div>
        </div>

        <!-- TOTAL -->
        <div class="bg-gray-50 p-4 rounded-lg">
          <div class="flex justify-between items-center">
            <span class="font-bold text-gray-900">Order Total</span>
            <span class="font-black text-xl text-gray-900">₦{{ total }}</span>
          </div>
        </div>

        <!-- PAYMENT METHOD SELECTION -->
        <div class="space-y-3 border-t border-gray-200 pt-4">
          <p class="font-bold text-sm text-gray-900">Payment Method</p>
          <label class="flex items-center gap-3 p-3 border-2 rounded-lg cursor-pointer transition"
            :class="paymentMode === 'pay_on_delivery' ? 'border-black bg-black/5' : 'border-gray-200 hover:border-gray-300'">
            <input type="radio" value="pay_on_delivery" v-model="paymentMode" class="w-4 h-4" />
            <div>
              <p class="font-semibold text-sm text-gray-900">Pay on Delivery</p>
              <p class="text-xs text-gray-500">Added to your room bill</p>
            </div>
          </label>
          <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-medium text-amber-800">
            Online payment for in-room menu orders is being finalized. Orders currently go through your room bill.
          </div>
        </div>

        <!-- ACTIONS -->
        <div class="flex gap-3 pt-4">
          <button
            @click="showPreview = false"
            class="flex-1 py-3 bg-gray-100 text-gray-900 rounded-lg font-bold text-sm uppercase transition hover:bg-gray-200"
          >
            Cancel
          </button>
          <button
            @click="confirmOrder"
            :disabled="submitting"
            class="flex-1 py-3 bg-black text-white rounded-lg font-bold text-sm uppercase transition hover:bg-gray-800 disabled:opacity-50"
          >
            {{ submitting ? 'Processing...' : 'Confirm' }}
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
