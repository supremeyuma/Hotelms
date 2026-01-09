<script setup>
import Modal from '@/Components/Modal.vue'
import { Clock, Hash } from 'lucide-vue-next'

import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  show: Boolean,
  order: Object,
})

const emit = defineEmits(['close'])


const selectedCharge = ref(null)
const method = ref('cash')

function openPayModal(charge) {
  selectedCharge.value = charge
}

function markAsPaid() {
  router.post(
    `/staff/charges/${selectedCharge.value.id}/mark-paid`,
    { method: method.value },
    {
      onSuccess: () => {
        selectedCharge.value = null
        emit('close')

        router.reload({
          only: ['orders'],
          preserveScroll: true,
        })
      }

    }
  )
}


</script>


<template>
  <Modal :show="show" @close="emit('close')">
    <template #title>
      <div class="flex items-center gap-3">
        <div class="p-2 bg-indigo-50 rounded-lg">
          <Hash class="w-5 h-5 text-indigo-600" />
        </div>
        <div>
          <h2 class="text-xl font-black text-slate-900">Order #{{ order?.id }}</h2>
          <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
            {{ order?.service_area }}
          </p>
        </div>
      </div>
    </template>

    <template #content>
      <div v-if="order" class="space-y-6">

        <!-- STATUS -->
        <div class="flex justify-between items-center">
          <span
            class="px-3 py-1 rounded-full text-[10px] font-black uppercase"
            :class="order.status === 'delivered'
              ? 'bg-emerald-100 text-emerald-700'
              : order.status === 'cancelled'
                ? 'bg-rose-100 text-rose-700'
                : 'bg-amber-100 text-amber-700'"
          >
            {{ order.status }}
          </span>
                      <span
              v-if="order.charge"
              class="px-2 py-1 rounded-full text-[10px] font-black uppercase tracking-wide"
              :class="
                order.charge.status === 'paid'
                  ? 'bg-emerald-100 text-emerald-700'
                  : order.charge.payment_mode === 'pay_on_delivery'
                    ? 'bg-amber-100 text-amber-700'
                    : 'bg-rose-100 text-rose-700'
              "
            >
              <template v-if="order.charge.status === 'paid'">
                Paid
              </template>

              <template v-else-if="order.charge.payment_mode === 'pay_on_delivery'">
                Pay on Delivery
              </template>

              <template v-else>
                Awaiting Payment
              </template>
            </span>

          <div class="flex items-center gap-1 text-xs text-slate-400">
            <Clock class="w-4 h-4" />
            {{ new Date(order.created_at).toLocaleString() }}
          </div>
        </div>

        <!-- ITEMS -->
        <div class="space-y-3">
          <div
            v-for="item in order.items"
            :key="item.id"
            class="flex justify-between items-center p-4 bg-slate-50 rounded-xl border border-slate-100"
          >
            <div>
              <p class="font-bold text-slate-800 text-sm">
                {{ item.item_name }}
              </p>
              <p v-if="item.note" class="text-xs text-orange-600 mt-1">
                Note: {{ item.note }}
              </p>
            </div>

            <div class="text-right">
              <p class="text-xs font-bold text-slate-400">
                ×{{ item.qty }}
              </p>
              <p class="font-black text-slate-900">
                ₦{{ Number(item.price * item.qty).toLocaleString() }}
              </p>
            </div>
          </div>
        </div>

        <!-- TOTAL -->
        <div class="pt-4 border-t border-slate-200 flex justify-between items-center">
          <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">
            Total
          </span>
          <span class="text-2xl font-black text-slate-900 italic">
            ₦{{ Number(order.total).toLocaleString() }}
          </span>
        </div>


        <button
          v-if="
            order?.charge &&
            order.charge.status === 'unpaid' &&
            order.charge.payment_mode !== 'prepaid'
          "
          @click="openPayModal(order.charge)"
          class="px-3 py-1 bg-green-600 text-white text-xs font-bold rounded"
        >
          Mark as Paid
        </button>




      </div>

    </template>
  </Modal>

  <!-- MARK AS PAID MODAL -->
<div
  v-if="selectedCharge"
  class="fixed inset-0 bg-black/40 z-[60] flex items-center justify-center"
>
  <div class="bg-white rounded-2xl p-6 w-full max-w-sm space-y-4">
    <h2 class="font-black text-lg text-slate-900">
      Mark Charge as Paid
    </h2>

    <p class="text-sm text-slate-500">
      ₦{{ Number(selectedCharge.amount).toLocaleString() }}
    </p>

    <!-- PAYMENT METHOD -->
    <div class="space-y-2">
      <label class="flex items-center gap-2 text-sm">
        <input type="radio" v-model="method" value="cash" />
        Cash
      </label>

      <label class="flex items-center gap-2 text-sm">
        <input type="radio" v-model="method" value="pos" />
        POS
      </label>

      <label class="flex items-center gap-2 text-sm">
        <input type="radio" v-model="method" value="transfer" />
        Bank Transfer
      </label>
    </div>

    <!-- ACTIONS -->
    <div class="flex gap-3 pt-2">
      <button
        @click="markAsPaid"
        class="flex-1 bg-green-600 text-white py-3 rounded-xl font-black text-xs uppercase"
      >
        Confirm Payment
      </button>

      <button
        @click="selectedCharge = null"
        class="flex-1 bg-gray-100 py-3 rounded-xl font-black text-xs uppercase"
      >
        Cancel
      </button>
    </div>
  </div>
</div>

</template>
