<script setup>
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import KitchenLayout from '@/Layouts/Staff/KitchenLayout.vue'
import { 
  Clock, 
  CheckCircle2, 
  ChefHat, 
  Truck, 
  Hash, 
  AlertCircle 
} from 'lucide-vue-next'

const props = defineProps({ orders: Array })
const orders = ref(props.orders)

onMounted(() => {
  if (window.Echo) {
    window.Echo.channel('orders.kitchen')
      .listen('.order.status.updated', e => {
        const index = orders.value.findIndex(o => o.id === e.order.id)
        if (index !== -1) orders.value[index] = e.order
        else orders.value.unshift(e.order)
      })
  }
})

function setStatus(order, status) {
  router.patch(`/staff/kitchen/orders/${order.id}`, { status }, {
    preserveScroll: true
  })
}

const getStatusClass = (status) => {
  const base = "px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider "
  switch (status) {
    case 'pending': return base + "bg-amber-100 text-amber-700 border border-amber-200"
    case 'preparing': return base + "bg-indigo-100 text-indigo-700 border border-indigo-200"
    case 'ready': return base + "bg-emerald-100 text-emerald-700 border border-emerald-200"
    default: return base + "bg-slate-100 text-slate-700 border border-slate-200"
  }
}
</script>

<template>
  <KitchenLayout>
    <div class="max-w-6xl mx-auto px-4 py-6">
      <div class="flex items-center justify-between mb-8">
        <div>
          <h1 class="text-2xl md:text-3xl font-black text-slate-900 flex items-center gap-3">
            Active Tickets
            <span class="bg-indigo-600 text-white text-sm py-1 px-3 rounded-full">{{ orders.length }}</span>
          </h1>
          <p class="text-slate-500 font-medium text-sm mt-1">Manage live orders and preparation status</p>
        </div>
      </div>

      <div v-if="orders.length === 0" class="flex flex-col items-center justify-center py-20 bg-white rounded-3xl border-2 border-dashed border-slate-200">
        <div class="p-4 bg-slate-50 rounded-full mb-4">
          <ChefHat class="w-12 h-12 text-slate-300" />
        </div>
        <h3 class="text-lg font-bold text-slate-900">No active orders</h3>
        <p class="text-slate-500">New orders will appear here automatically.</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div 
          v-for="order in orders" 
          :key="order.id" 
          class="bg-white rounded-3xl border border-slate-200 shadow-sm flex flex-col overflow-hidden transition-all hover:shadow-md"
        >
          <div class="p-5 border-b border-slate-100 flex justify-between items-start bg-slate-50/50">
            <div>
              <div class="flex items-center gap-1 text-slate-400 font-bold text-xs uppercase tracking-widest mb-1">
                <Hash class="w-3 h-3" /> Order ID
              </div>
              <div class="text-xl font-black text-slate-900">#{{ order.id }}</div>
            </div>
            <span :class="getStatusClass(order.status)">
              {{ order.status }}
            </span>
          </div>

          <div class="p-5 flex-1">
            <ul class="space-y-4">
              <li 
                v-for="item in order.items" 
                :key="item.id"
                class="flex items-start justify-between gap-4"
              >
                <div class="flex flex-col">
                  <span class="font-bold text-slate-800 text-sm md:text-base leading-tight">
                    {{ item.menu_item.name }}
                  </span>
                  <span v-if="item.notes" class="text-xs text-orange-600 font-medium mt-1 flex items-center gap-1">
                    <AlertCircle class="w-3 h-3" /> {{ item.notes }}
                  </span>
                </div>
                <div class="bg-slate-100 text-slate-900 font-black px-2.5 py-1 rounded-lg text-sm">
                  ×{{ item.quantity }}
                </div>
              </li>
            </ul>
          </div>

          <div class="p-4 bg-slate-50/80 grid grid-cols-3 gap-2">
            <button 
              @click="setStatus(order,'preparing')"
              :disabled="order.status === 'preparing'"
              class="flex flex-col items-center justify-center gap-1 p-2 rounded-xl border transition-all"
              :class="order.status === 'preparing' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-200 hover:border-indigo-400'"
            >
              <Clock class="w-5 h-5" />
              <span class="text-[10px] font-bold uppercase">Prep</span>
            </button>

            <button 
              @click="setStatus(order,'ready')"
              :disabled="order.status === 'ready'"
              class="flex flex-col items-center justify-center gap-1 p-2 rounded-xl border transition-all"
              :class="order.status === 'ready' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-600 border-slate-200 hover:border-emerald-400'"
            >
              <CheckCircle2 class="w-5 h-5" />
              <span class="text-[10px] font-bold uppercase">Ready</span>
            </button>

            <button 
              @click="setStatus(order,'delivered')"
              class="flex flex-col items-center justify-center gap-1 p-2 rounded-xl bg-white border border-slate-200 text-slate-600 hover:border-blue-400 transition-all"
            >
              <Truck class="w-5 h-5" />
              <span class="text-[10px] font-bold uppercase">Done</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </KitchenLayout>
</template>

<style scoped>
.grid {
  display: grid;
}
/* Ensure smooth transitions for status changes */
.bg-white {
  transition: background-color 0.2s, border-color 0.2s;
}
</style>