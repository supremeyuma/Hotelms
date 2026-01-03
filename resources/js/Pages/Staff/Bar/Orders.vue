<script setup>
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import BarLayout from '@/Layouts/Staff/BarLayout.vue'
import { 
  Beer, 
  Clock, 
  CheckCircle2, 
  Check, 
  Hash, 
  MessageSquare,
  Wine
} from 'lucide-vue-next'

const props = defineProps({ orders: Array })
const orders = ref(props.orders)

onMounted(() => {
  if (window.Echo) {
    window.Echo.channel('orders.bar')
      .listen('.order.status.updated', e => {
        const index = orders.value.findIndex(o => o.id === e.order.id)
        if (index !== -1) orders.value[index] = e.order
        else orders.value.unshift(e.order)
      })
  }
})

function setStatus(order, status) {
  router.patch(`/staff/bar/orders/${order.id}`, { status }, {
    preserveScroll: true,
    only: ['orders']
  })
}

const getStatusStyles = (status) => {
  const map = {
    'pending': 'bg-amber-100 text-amber-700 ring-amber-600/20',
    'preparing': 'bg-blue-100 text-blue-700 ring-blue-600/20',
    'ready': 'bg-emerald-100 text-emerald-700 ring-emerald-600/20',
    'delivered': 'bg-slate-100 text-slate-600 ring-slate-600/10'
  }
  return map[status] || 'bg-gray-100 text-gray-600 ring-gray-600/10'
}
</script>

<template>
  <BarLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
        <div>
          <div class="flex items-center gap-2 text-blue-600 font-bold text-sm uppercase tracking-widest mb-1">
            <Wine class="w-4 h-4" /> Beverage Service
          </div>
          <h1 class="text-3xl font-black text-slate-900 tracking-tight">Active Drink Tickets</h1>
        </div>
        <div class="flex items-center gap-3 bg-white p-2 rounded-2xl border border-slate-200 shadow-sm">
          <div class="px-4 py-1 bg-slate-100 rounded-lg text-sm font-bold text-slate-600">
            Total: {{ orders.length }}
          </div>
        </div>
      </div>

      <div v-if="orders.length === 0" class="flex flex-col items-center justify-center py-24 bg-white rounded-[2.5rem] border-2 border-dashed border-slate-200">
        <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mb-4">
          <Beer class="w-10 h-10 text-slate-300" />
        </div>
        <h2 class="text-xl font-bold text-slate-900">The bar is clear</h2>
        <p class="text-slate-500">New drink orders will appear here in real-time.</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        <div 
          v-for="order in orders" 
          :key="order.id" 
          class="group bg-white rounded-[2rem] border border-slate-200 shadow-sm hover:shadow-xl transition-all duration-300 overflow-hidden flex flex-col"
        >
          <div class="p-6 pb-4 flex justify-between items-start border-b border-slate-50">
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 bg-slate-900 rounded-2xl flex flex-col items-center justify-center text-white">
                <Hash class="w-3 h-3 opacity-50" />
                <span class="text-lg font-bold leading-none">{{ order.id }}</span>
              </div>
              <div>
                <span :class="[getStatusStyles(order.status), 'inline-flex items-center rounded-md px-2 py-1 text-xs font-bold ring-1 ring-inset uppercase tracking-wider mb-1']">
                  {{ order.status }}
                </span>
                <p class="text-xs text-slate-400 font-medium">Updated just now</p>
              </div>
            </div>
          </div>

          <div class="p-6 flex-1 bg-gradient-to-b from-white to-slate-50/30">
            <ul class="space-y-4">
              <li v-for="item in order.items" :key="item.id" class="flex items-center justify-between">
                <div class="flex flex-col">
                  <span class="text-slate-900 font-bold text-lg leading-tight">{{ item.menu_item.name }}</span>
                  <div v-if="item.notes" class="flex items-center gap-1.5 mt-1 text-blue-600 bg-blue-50 w-fit px-2 py-0.5 rounded-md">
                    <MessageSquare class="w-3 h-3" />
                    <span class="text-xs font-bold">{{ item.notes }}</span>
                  </div>
                </div>
                <div class="flex items-center justify-center w-10 h-10 bg-white border-2 border-slate-100 rounded-xl shadow-sm text-slate-900 font-black">
                  {{ item.quantity }}
                </div>
              </li>
            </ul>
          </div>

          <div class="p-4 bg-white border-t border-slate-100 grid grid-cols-3 gap-3">
            <button 
              @click="setStatus(order, 'preparing')"
              class="flex flex-col items-center gap-1.5 py-3 rounded-2xl transition-all font-bold text-[10px] uppercase tracking-tighter shadow-sm border"
              :class="order.status === 'preparing' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-100 hover:bg-slate-50'"
            >
              <Clock class="w-5 h-5" />
              Prep
            </button>
            
            <button 
              @click="setStatus(order, 'ready')"
              class="flex flex-col items-center gap-1.5 py-3 rounded-2xl transition-all font-bold text-[10px] uppercase tracking-tighter shadow-sm border"
              :class="order.status === 'ready' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-600 border-slate-100 hover:bg-slate-50'"
            >
              <CheckCircle2 class="w-5 h-5" />
              Ready
            </button>

            <button 
              @click="setStatus(order, 'delivered')"
              class="flex flex-col items-center gap-1.5 py-3 rounded-2xl transition-all font-bold text-[10px] uppercase tracking-tighter shadow-sm border bg-white text-slate-600 border-slate-100 hover:bg-slate-50"
            >
              <Check class="w-5 h-5" />
              Done
            </button>
          </div>
        </div>
      </div>
    </div>
  </BarLayout>
</template>

<style scoped>
/* Scoped layout polish */
.grid {
  display: grid;
}
</style>