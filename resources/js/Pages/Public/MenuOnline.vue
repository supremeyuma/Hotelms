<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import PublicMenuLayout from '@/Layouts/PublicMenuLayout.vue'
import {
  Plus,
  Minus,
  X,
  ShoppingCart,
  CheckCircle,
} from 'lucide-vue-next'

const page = usePage()

const props = defineProps({
  categories: Array,
  type: String,
})

const activeCategory = ref(props.categories?.[0] || null)
const activeSubcategory = ref(null)
const cart = ref([])
const showPreview = ref(false)
const submitting = ref(false)
const toast = ref(null)
const toastType = ref('success')
const showConfirm = ref(false)

onMounted(() => {
  const saved = sessionStorage.getItem('public-online-cart')
  if (saved) {
    try {
      cart.value = JSON.parse(saved)
    } catch {
      sessionStorage.removeItem('public-online-cart')
    }
  }
})

watch(
  cart,
  val => sessionStorage.setItem('public-online-cart', JSON.stringify(val)),
  { deep: true }
)

watch(
  () => page.props.flash,
  flash => {
    if (flash?.success) {
      toastType.value = 'success'
      toast.value = flash.success
      showConfirm.value = true
      cart.value = []
      sessionStorage.removeItem('public-online-cart')
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

function removeFromCart(itemId) {
  cart.value = cart.value.filter(i => i.id !== itemId)
}

function openPreview() {
  if (!cart.value.length) return
  showPreview.value = true
}

function confirmOrder() {
  if (submitting.value) return
  submitting.value = true

  router.post(
    route('public.orders.store'),
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
      },
      onError: () => {
        submitting.value = false
        toastType.value = 'error'
        toast.value = 'Failed to place order. Please try again.'
      },
    }
  )
}

function goHome() {
  router.visit(route('home'))
}
</script>

<template>
  <PublicMenuLayout title="Order Online">
    <div class="sticky top-0 z-30 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-0 sm:py-2 flex items-center justify-between gap-3">
        <button
          @click="goHome"
          class="shrink-0 text-slate-600 hover:text-slate-900 font-semibold text-sm transition"
        >
          Back Home
        </button>

        <div class="min-w-0 flex-1 text-center">
          <h1 class="text-lg sm:text-xl font-black text-slate-900">Menu</h1>
          <p class="text-xs sm:text-xs font-semibold uppercase tracking-wide text-slate-500">
            Order Online
          </p>
        </div>

        <div class="w-16 sm:w-20 shrink-0"></div>
      </div>
    </div>

    <div class="sticky top-[40px] sm:top-[72px] z-30 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-0 sm:py-2">
        <div class="flex items-center justify-center gap-1.5 sm:gap-2">
          <button
            @click="router.visit(route('menu.online.show', { type: 'kitchen' }))"
            class="px-3 py-1 sm:px-4 sm:py-1.5 rounded-full text-[11px] sm:text-xs font-bold transition"
            :class="type === 'kitchen'
              ? 'bg-black text-white'
              : 'bg-slate-200 text-slate-700 hover:bg-slate-300'"
          >
            Kitchen
          </button>
          <button
            @click="router.visit(route('menu.online.show', { type: 'bar' }))"
            class="px-3 py-1 sm:px-4 sm:py-1.5 rounded-full text-[11px] sm:text-xs font-bold transition"
            :class="type === 'bar'
              ? 'bg-black text-white'
              : 'bg-slate-200 text-slate-700 hover:bg-slate-300'"
          >
            Bar
          </button>
        </div>
      </div>
    </div>

    <transition name="toast">
      <div
        v-if="toast"
        class="fixed top-24 left-1/2 -translate-x-1/2 z-50 px-6 py-4 rounded-lg text-white font-semibold shadow-lg"
        :class="toastType === 'success' ? 'bg-green-600' : 'bg-red-600'"
      >
        {{ toast }}
      </div>
    </transition>

    <div
      v-if="showConfirm"
      class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center p-4"
    >
      <div class="bg-white rounded-2xl p-8 max-w-sm w-full text-center space-y-6">
        <div class="flex justify-center">
          <CheckCircle class="w-16 h-16 text-green-600" />
        </div>
        <div>
          <h2 class="font-black text-xl text-slate-900">Order Confirmed!</h2>
          <p class="text-slate-600 text-sm mt-2">
            Your order has been received and will be prepared.
          </p>
        </div>
        <button
          @click="goHome"
          class="w-full bg-black text-white py-3 rounded-lg font-semibold uppercase text-sm transition hover:bg-slate-800"
        >
          Back to Home
        </button>
      </div>
    </div>

    <div class="bg-slate-50 min-h-screen">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
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
                  : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200'
              "
            >
              {{ c.name }}
            </button>
          </div>
        </div>

        <div v-if="activeCategory?.subcategories?.length" class="mb-8">
          <div class="flex gap-2 overflow-x-auto">
            <button
              v-for="sub in activeCategory.subcategories"
              :key="sub.id"
              @click="activeSubcategory = sub"
              class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition"
              :class="
                activeSubcategory?.id === sub.id
                  ? 'bg-slate-300 text-slate-900'
                  : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200'
              "
            >
              {{ sub.name }}
            </button>
          </div>
        </div>

        <div class="grid lg:grid-cols-4 gap-8">
          <div class="lg:col-span-3">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
              <div
                v-for="item in items"
                :key="item.id"
                class="bg-white rounded-xl overflow-hidden shadow-sm hover:shadow-md transition group"
              >
                <div class="relative overflow-hidden bg-slate-100 h-28 sm:h-32">
                  <img
                    v-if="item.images?.length"
                    :src="`/storage/${item.images[0].path}`"
                    :alt="item.name"
                    class="h-full w-full object-cover group-hover:scale-110 transition"
                  />
                  <div v-else class="h-full flex items-center justify-center text-slate-400">
                    <span class="text-xs">No image</span>
                  </div>
                </div>

                <div class="p-3 space-y-2">
                  <div>
                    <h3 class="font-bold text-xs sm:text-sm text-slate-900 leading-snug">{{ item.name }}</h3>
                    <p v-if="item.description" class="text-[11px] sm:text-xs text-slate-500 mt-0.5 line-clamp-2 leading-snug">
                      {{ item.description }}
                    </p>
                  </div>

                  <div class="flex items-center justify-between pt-1.5 border-t border-slate-100">
                    <span class="font-bold text-sm sm:text-base text-slate-900">₦{{ item.price }}</span>

                    <div class="flex items-center gap-1 bg-slate-100 rounded-lg p-0.5">
                      <button
                        aria-label="Decrease quantity"
                        @click="remove(item)"
                        class="p-1 hover:bg-slate-200 rounded transition"
                      >
                        <Minus class="w-3.5 h-3.5 text-slate-600" />
                      </button>
                      <span class="w-5 text-center text-xs sm:text-sm font-semibold">
                        {{ cart.find(i => i.id === item.id)?.quantity || 0 }}
                      </span>
                      <button
                        aria-label="Increase quantity"
                        @click="add(item)"
                        class="p-1 hover:bg-slate-200 rounded transition"
                      >
                        <Plus class="w-3.5 h-3.5 text-slate-600" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="lg:col-span-1">
            <div
              class="sticky top-24 bg-white rounded-xl shadow-lg p-6 space-y-4 border border-slate-100"
            >
              <div class="flex items-center gap-2">
                <ShoppingCart class="w-5 h-5 text-black" />
                <h2 class="font-black text-lg text-slate-900">Your Cart</h2>
              </div>

              <div
                v-if="!cart.length"
                class="text-center py-8 text-slate-500"
              >
                <ShoppingCart class="w-12 h-12 mx-auto opacity-20 mb-3" />
                <p class="text-sm">No items yet</p>
              </div>

              <div v-else class="space-y-3 max-h-96 overflow-y-auto">
                <div
                  v-for="item in cart"
                  :key="item.id"
                  class="border border-slate-200 rounded-lg p-3 space-y-2"
                >
                  <div class="flex justify-between items-start gap-2">
                    <div class="flex-1">
                      <p class="font-semibold text-sm text-slate-900">{{ item.name }}</p>
                      <p class="text-xs text-slate-500">
                        {{ item.quantity }} × ₦{{ item.price }}
                      </p>
                    </div>
                    <button
                      aria-label="Remove from cart"
                      @click="removeFromCart(item.id)"
                      class="text-slate-400 hover:text-red-600 transition"
                    >
                      <X class="w-4 h-4" />
                    </button>
                  </div>
                  <p class="font-bold text-sm text-right text-slate-900">
                    ₦{{ item.price * item.quantity }}
                  </p>
                </div>
              </div>

              <div v-if="cart.length" class="border-t border-slate-200 pt-4 space-y-2">
                <div class="flex justify-between text-sm">
                  <span class="text-slate-600">Subtotal</span>
                  <span class="font-semibold text-slate-900">₦{{ total }}</span>
                </div>
                <div class="bg-slate-50 p-3 rounded-lg">
                  <div class="flex justify-between items-center">
                    <span class="font-bold text-slate-900">Total</span>
                    <span class="font-black text-lg text-slate-900">₦{{ total }}</span>
                  </div>
                </div>
              </div>

              <button
                v-if="cart.length"
                @click="openPreview"
                class="w-full bg-black text-white py-3 rounded-lg font-bold uppercase text-sm transition hover:bg-slate-800 flex items-center justify-center gap-2"
              >
                <ShoppingCart class="w-4 h-4" />
                Proceed to Payment
              </button>
              <button
                v-else
                disabled
                class="w-full bg-slate-300 text-slate-500 py-3 rounded-lg font-bold uppercase text-sm cursor-not-allowed"
              >
                Add items to start
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div
      v-if="showPreview"
      class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
    >
      <div class="bg-white rounded-2xl p-8 w-full max-w-md space-y-6">
        <div class="flex justify-between items-center">
          <h2 class="font-black text-xl text-slate-900">Confirm Order</h2>
          <button @click="showPreview = false" aria-label="Close" class="text-slate-400 hover:text-slate-600">
            <X class="w-6 h-6" />
          </button>
        </div>

        <div class="space-y-3 max-h-64 overflow-y-auto">
          <div v-for="item in cart" :key="item.id" class="flex justify-between text-sm">
            <div class="flex-1">
              <p class="font-semibold text-slate-900">{{ item.name }}</p>
              <p class="text-xs text-slate-500">
                {{ item.quantity }} × ₦{{ item.price }}
              </p>
            </div>
            <p class="font-bold text-slate-900">₦{{ item.price * item.quantity }}</p>
          </div>
        </div>

        <div class="bg-slate-50 p-4 rounded-lg">
          <div class="flex justify-between items-center">
            <span class="font-bold text-slate-900">Order Total</span>
            <span class="font-black text-xl text-slate-900">₦{{ total }}</span>
          </div>
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <p class="text-sm text-blue-900">
            <span class="font-semibold">Payment Method:</span> Online Payment (Prepaid)
          </p>
        </div>

        <div class="flex gap-3">
          <button
            @click="showPreview = false"
            class="flex-1 py-3 bg-slate-100 text-slate-900 rounded-lg font-bold text-sm uppercase transition hover:bg-slate-200"
          >
            Cancel
          </button>
          <button
            @click="confirmOrder"
            :disabled="submitting"
            class="flex-1 py-3 bg-black text-white rounded-lg font-bold text-sm uppercase transition hover:bg-slate-800 disabled:opacity-50"
          >
            {{ submitting ? 'Processing...' : 'Pay Now' }}
          </button>
        </div>
      </div>
    </div>
  </PublicMenuLayout>
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

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  line-clamp: 2;
  overflow: hidden;
}
</style>
