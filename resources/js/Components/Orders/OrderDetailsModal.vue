<script setup>
import Modal from '@/Components/Modal.vue'
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { Clock, Hash } from 'lucide-vue-next'

const props = defineProps({
  show: Boolean,
  order: Object,
  paymentOptions: { type: Array, default: () => [] },
  paymentStatuses: { type: Array, default: () => [] },
  paymentUpdateUrl: { type: String, default: '' },
})

const emit = defineEmits(['close'])

const paymentMethod = ref('pending_selection')
const paymentStatus = ref('pending')
const savingPayment = ref(false)

watch(
  () => props.order,
  (order) => {
    paymentMethod.value = order?.payment_method || 'pending_selection'
    paymentStatus.value = order?.payment_status || 'pending'
  },
  { immediate: true }
)

const roomName = computed(() => props.order?.room?.name || `Room ${props.order?.room_id ?? '--'}`)

const canUpdatePayment = computed(() => Boolean(props.order?.id && props.paymentUpdateUrl))

function formatPaymentMethod(value) {
  return String(value || 'pending_selection')
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function savePayment() {
  if (!canUpdatePayment.value || savingPayment.value) return

  savingPayment.value = true

  router.patch(props.paymentUpdateUrl, {
    payment_method: paymentMethod.value,
    payment_status: paymentStatus.value,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      emit('close')
    },
    onFinish: () => {
      savingPayment.value = false
    },
  })
}
</script>

<template>
  <Modal :show="show" @close="emit('close')">
    <template #title>
      <div class="flex items-center gap-3">
        <div class="rounded-lg bg-indigo-50 p-2">
          <Hash class="h-5 w-5 text-indigo-600" />
        </div>
        <div>
          <h2 class="text-xl font-black text-slate-900">Order #{{ order?.id }}</h2>
          <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">
            {{ order?.service_area }} · {{ roomName }}
          </p>
        </div>
      </div>
    </template>

    <template #content>
      <div v-if="order" class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="flex flex-wrap items-center gap-2">
            <span
              class="rounded-full px-3 py-1 text-[10px] font-black uppercase"
              :class="order.status === 'delivered'
                ? 'bg-emerald-100 text-emerald-700'
                : order.status === 'cancelled'
                  ? 'bg-rose-100 text-rose-700'
                  : 'bg-amber-100 text-amber-700'"
            >
              {{ order.status }}
            </span>

            <span
              class="rounded-full px-3 py-1 text-[10px] font-black uppercase"
              :class="order.payment_status === 'paid'
                ? 'bg-emerald-100 text-emerald-700'
                : order.payment_status === 'failed'
                  ? 'bg-rose-100 text-rose-700'
                  : 'bg-slate-100 text-slate-700'"
            >
              Payment {{ order.payment_status }}
            </span>
          </div>

          <div class="flex items-center gap-1 text-xs text-slate-400">
            <Clock class="h-4 w-4" />
            {{ new Date(order.created_at).toLocaleString() }}
          </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
          <div class="grid gap-3 md:grid-cols-3">
            <div>
              <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Room</p>
              <p class="mt-1 text-sm font-bold text-slate-900">{{ roomName }}</p>
            </div>
            <div>
              <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Booking</p>
              <p class="mt-1 text-sm font-bold text-slate-900">{{ order.booking?.booking_code || 'Unavailable' }}</p>
            </div>
            <div>
              <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Guest</p>
              <p class="mt-1 text-sm font-bold text-slate-900">{{ order.booking?.guest_name || 'Walk-in guest' }}</p>
            </div>
          </div>
        </div>

        <div class="space-y-3">
          <div
            v-for="item in order.items"
            :key="item.id"
            class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 p-4"
          >
            <div>
              <p class="text-sm font-bold text-slate-800">{{ item.item_name }}</p>
              <p v-if="item.note" class="mt-1 text-xs text-orange-600">Note: {{ item.note }}</p>
            </div>

            <div class="text-right">
              <p class="text-xs font-bold text-slate-400">x{{ item.qty }}</p>
              <p class="font-black text-slate-900">NGN {{ Number(item.price * item.qty).toLocaleString() }}</p>
            </div>
          </div>
        </div>

        <div v-if="order.notes" class="rounded-2xl border border-slate-200 bg-white p-4">
          <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Room note</p>
          <p class="mt-2 text-sm text-slate-700">{{ order.notes }}</p>
        </div>

        <div class="flex items-center justify-between border-t border-slate-200 pt-4">
          <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Total</span>
          <span class="text-2xl font-black text-slate-900">NGN {{ Number(order.total).toLocaleString() }}</span>
        </div>

        <div v-if="canUpdatePayment" class="rounded-2xl border border-slate-200 bg-white p-5 space-y-4">
          <div>
            <p class="text-sm font-black text-slate-900">Payment handling</p>
            <p class="mt-1 text-xs text-slate-500">Kitchen, bar, and front desk can keep the payment record current here.</p>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <label class="space-y-2 text-sm font-semibold text-slate-700">
              <span>Payment status</span>
              <select
                v-model="paymentStatus"
                class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
              >
                <option v-for="status in paymentStatuses" :key="status" :value="status">
                  {{ formatPaymentMethod(status) }}
                </option>
              </select>
            </label>

            <label class="space-y-2 text-sm font-semibold text-slate-700">
              <span>Payment method</span>
              <select
                v-model="paymentMethod"
                class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
              >
                <option v-for="method in paymentOptions" :key="method" :value="method">
                  {{ formatPaymentMethod(method) }}
                </option>
              </select>
            </label>
          </div>

          <button
            type="button"
            class="rounded-xl bg-slate-900 px-5 py-3 text-xs font-black uppercase tracking-widest text-white disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="savingPayment"
            @click="savePayment"
          >
            {{ savingPayment ? 'Saving...' : 'Update Payment' }}
          </button>
        </div>
      </div>
    </template>
  </Modal>
</template>
