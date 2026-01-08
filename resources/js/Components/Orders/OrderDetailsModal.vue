<script setup>
import Modal from '@/Components/Modal.vue'
import { Clock, Hash } from 'lucide-vue-next'

const props = defineProps({
  show: Boolean,
  order: Object,
})

const emit = defineEmits(['close'])

//console.log(props.order)
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

      </div>
    </template>
  </Modal>
</template>
