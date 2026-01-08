<script setup>
import { ref } from 'vue'
import KitchenLayout from '@/Layouts/Staff/KitchenLayout.vue'
import { Clock, Hash, CheckCircle2, XCircle } from 'lucide-vue-next'

const props = defineProps({
  orders: Object, // paginated
})

const orders = ref(props.orders.data)
</script>

<template>
  <KitchenLayout>
    <div class="max-w-6xl mx-auto px-4 py-6">
      
      <div class="mb-8">
        <h1 class="text-2xl md:text-3xl font-black text-slate-900">
          Order History
        </h1>
        <p class="text-slate-500 text-sm mt-1">
          Completed and cancelled kitchen orders
        </p>
      </div>

      <div v-if="orders.length === 0" class="py-20 text-center bg-white rounded-3xl border border-slate-200">
        <p class="font-bold text-slate-500">No completed orders yet.</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div
          v-for="order in orders"
          :key="order.id"
          class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden"
        >
          <!-- HEADER -->
          <div class="p-5 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
            <div>
              <div class="flex items-center gap-1 text-xs font-black uppercase text-slate-400 tracking-widest">
                <Hash class="w-3 h-3" /> Order
              </div>
              <p class="text-xl font-black text-slate-900">
                #{{ order.id }}
              </p>
            </div>

            <div
              class="px-3 py-1 rounded-full text-[10px] font-black uppercase"
              :class="order.status === 'delivered'
                ? 'bg-emerald-100 text-emerald-700'
                : 'bg-rose-100 text-rose-700'"
            >
              {{ order.status }}
            </div>
          </div>

          <!-- ITEMS -->
          <div class="p-5 space-y-3">
            <div
              v-for="item in order.items"
              :key="item.id"
              class="flex justify-between items-center"
            >
              <span class="font-bold text-slate-700 text-sm">
                {{ item.item_name }} × {{ item.qty }}
              </span>
              <span class="text-xs font-bold text-slate-400">
                ₦{{ Number(item.price * item.qty).toLocaleString() }}
              </span>
            </div>
          </div>

          <!-- FOOTER -->
          <div class="px-5 py-4 bg-slate-900/5 flex justify-between items-center">
            <div class="flex items-center gap-1 text-xs text-slate-400">
              <Clock class="w-4 h-4" />
              {{ new Date(order.created_at).toLocaleString() }}
            </div>
            <span class="text-lg font-black text-slate-900">
              ₦{{ Number(order.total).toLocaleString() }}
            </span>
          </div>
        </div>
      </div>

      <!-- PAGINATION -->
      <div v-if="props.orders.links.length > 3" class="mt-10 flex justify-center">
        <div class="flex gap-2">
          <a
            v-for="link in props.orders.links"
            :key="link.label"
            v-html="link.label"
            :href="link.url || '#'"
            class="px-4 py-2 rounded-xl text-sm font-bold"
            :class="link.active
              ? 'bg-indigo-600 text-white'
              : 'bg-white border border-slate-200 text-slate-600'"
          />
        </div>
      </div>

    </div>
  </KitchenLayout>
</template>
