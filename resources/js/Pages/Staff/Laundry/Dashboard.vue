<template>
  <LaundryLayout>
    <div class="max-w-5xl mx-auto px-4 py-6 md:py-10">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
        <div class="flex items-center gap-4">
          <div class="p-3 bg-blue-600 rounded-2xl text-white shadow-lg shadow-blue-200">
            <Waves class="w-8 h-8" />
          </div>
          <div>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Laundry Dashboard</h1>
            <p class="text-slate-500 font-medium text-sm">Track and manage guest laundry service</p>
          </div>
        </div>

        <Link
          :href="route('staff.laundry-items.index')"
          class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-white border border-slate-200 rounded-2xl font-bold text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition-all shadow-sm active:scale-95"
        >
          <Settings2 class="w-5 h-5" />
          Manage Items
        </Link>
      </div>

      <div v-if="orders.length === 0" class="flex flex-col items-center justify-center py-20 bg-white rounded-[2.5rem] border-2 border-dashed border-slate-200">
        <div class="p-4 bg-slate-50 rounded-full mb-4 text-slate-300">
          <Shirt class="w-12 h-12" />
        </div>
        <p class="text-slate-500 font-bold">No active laundry orders.</p>
      </div>

      <div class="space-y-4">
        <div
          v-for="order in orders"
          :key="order.id"
          class="group relative bg-white rounded-[2rem] border border-slate-200 shadow-sm hover:shadow-xl transition-all duration-300 overflow-hidden"
          @click="router.visit(route('staff.laundry.show', order.id))"
        >
          <div class="p-5 md:p-8">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 mb-6">
              <div class="flex items-start gap-4">
                <div class="w-14 h-14 bg-slate-900 rounded-2xl flex flex-col items-center justify-center text-white shrink-0">
                  <span class="text-[10px] font-black opacity-50 uppercase tracking-tighter">Room</span>
                  <span class="text-lg font-black leading-none">{{ order.room.name }}</span>
                </div>
                <div>
                  <div class="flex items-center gap-2 mb-1">
                    <span class="text-lg font-black text-slate-900 tracking-tight">{{ order.order_code }}</span>
                    <span :class="[getStatusBadgeClass(order.status)]">
                      {{ order.status.replace('_', ' ') }}
                    </span>
                  </div>
                  <p class="text-sm text-slate-400 font-medium flex items-center gap-1.5">
                    <Clock class="w-4 h-4" /> {{ formatDateTime(order.created_at) }}
                  </p>
                </div>
              </div>

              <div @click.stop class="flex items-center gap-2 bg-slate-50 p-2 rounded-2xl border border-slate-100">
                <select 
                  v-model="order.newStatus" 
                  class="bg-transparent border-none focus:ring-0 text-sm font-bold text-slate-700 pr-8"
                >
                  <option disabled value="">Change Status</option>
                  <option v-for="s in statuses" :key="s.value" :value="s.value">
                    {{ s.value.replace('_', ' ').toUpperCase() }}
                  </option>
                </select>
                <button 
                  @click="updateStatus(order)"
                  class="p-2.5 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 shadow-md shadow-indigo-100 transition-all active:scale-90"
                >
                  <RefreshCw class="w-4 h-4" />
                </button>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 pt-6 border-t border-slate-50">
              <div class="space-y-3">
                <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em]">Order Contents</h3>
                <ul class="space-y-2">
                  <li 
                    v-for="item in order.items" 
                    :key="item.id"
                    class="flex justify-between items-center text-slate-700 font-semibold bg-slate-50/50 p-3 rounded-xl border border-slate-100/50"
                  >
                    <span class="flex items-center gap-2">
                      <span class="w-6 h-6 rounded-md bg-white border border-slate-200 flex items-center justify-center text-[10px]">{{ item.quantity }}x</span>
                      {{ item.item.name }}
                    </span>
                    <span class="text-slate-400 font-mono text-sm">₦{{ item.subtotal }}</span>
                  </li>
                </ul>
              </div>

              <div class="space-y-3" v-if="order.images?.length">
                <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em]">Photo Documentation</h3>
                <div class="flex flex-wrap gap-2">
                  <div 
                    v-for="img in order.images" 
                    :key="img.id"
                    class="relative aspect-square w-20 rounded-xl overflow-hidden border border-slate-200 shadow-sm"
                  >
                    <img :src="`/storage/${img.path}`" alt="Laundry" class="h-full w-full object-cover" />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </LaundryLayout>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import LaundryLayout from '@/Layouts/Staff/LaundryLayout.vue'
import { 
  Waves, 
  Settings2, 
  Shirt, 
  Clock, 
  RefreshCw, 
  Image as ImageIcon 
} from 'lucide-vue-next'

const props = defineProps({
  orders: Array
});

const orders = ref([...props.orders]);

const statuses = [
  { value: 'requested' },
  { value: 'pickup_scheduled' },
  { value: 'picked_up' },
  { value: 'washing' },
  { value: 'ready' },
  { value: 'delivered' },
  { value: 'cancelled' },
];

function updateStatus(order) {
  if (!order.newStatus) return;
  router.post(route('staff.laundry.updateStatus', order.id), { 
    status: order.newStatus 
  }, {
    preserveScroll: true
  });
}

const getStatusBadgeClass = (status) => {
  const base = "px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border "
  switch (status) {
    case 'delivered': return base + "bg-emerald-50 text-emerald-600 border-emerald-100"
    case 'requested': return base + "bg-rose-50 text-rose-600 border-rose-100 animate-pulse"
    case 'washing': return base + "bg-blue-50 text-blue-600 border-blue-100"
    case 'ready': return base + "bg-indigo-50 text-indigo-600 border-indigo-100"
    default: return base + "bg-slate-50 text-slate-500 border-slate-200"
  }
}

onMounted(() => {
  if (!window.Echo) return;

  window.Echo.channel('laundry-orders')
    .listen('.LaundryOrderUpdated', (e) => {
      const updatedOrder = e.order;
      orders.value = orders.value.filter(o => o.id !== updatedOrder.id);
      orders.value.unshift(updatedOrder);
    });
});

onBeforeUnmount(() => {
  window.Echo?.leave('laundry-orders');
});

function formatDateTime(date) {
  return new Date(date).toLocaleString([], { 
    month: 'short', 
    day: 'numeric', 
    hour: '2-digit', 
    minute: '2-digit' 
  });
}
</script>

<style scoped>
/* Scoped custom styling for card interaction */
.group:hover {
  transform: translateY(-2px);
}
</style>