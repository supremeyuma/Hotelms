<script setup>
import { onMounted, computed } from 'vue'
import { 
  CheckCircle2, 
  Clock, 
  ChefHat, 
  Truck, 
  Utensils, 
  BellRing,
  CircleDot
} from 'lucide-vue-next'

const props = defineProps({ 
  order: Object 
})

// Listen for live updates from the Kitchen/Laundry/Bar
onMounted(() => {
  if (window.Echo) {
    window.Echo.channel(`orders.${props.order.service_area}`)
      .listen('.order.status.updated', e => {
        if (e.order.id === props.order.id) {
          // Note: In Vue 3, modifying props directly is discouraged.
          // Ideally, the parent should handle this, or this should be a local ref.
          props.order.status = e.order.status
        }
      })
  }
})

// Map statuses to visual steps
const statusMap = {
  'pending': { label: 'Received', icon: BellRing, color: 'text-amber-500', bg: 'bg-amber-50', step: 1 },
  'preparing': { label: 'Preparing', icon: ChefHat, color: 'text-indigo-600', bg: 'bg-indigo-50', step: 2 },
  'ready': { label: 'On the Way', icon: Truck, color: 'text-emerald-600', bg: 'bg-emerald-50', step: 3 },
  'completed': { label: 'Delivered', icon: CheckCircle2, color: 'text-slate-400', bg: 'bg-slate-50', step: 4 }
}

const currentStatus = computed(() => {
  return statusMap[props.order.status] || { label: props.order.status, icon: Clock, color: 'text-slate-500', bg: 'bg-slate-50', step: 1 }
})
</script>

<template>
  <div class="bg-white rounded-[2rem] border border-slate-100 shadow-sm overflow-hidden transition-all hover:shadow-md">
    <div class="p-6 space-y-6">
      
      <div class="flex justify-between items-start">
        <div class="flex items-center gap-3">
          <div :class="['p-3 rounded-2xl', currentStatus.bg, currentStatus.color]">
            <component :is="currentStatus.icon" class="w-6 h-6" />
          </div>
          <div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Request #{{ order.id }}</p>
            <h3 class="text-lg font-black text-slate-900 tracking-tight capitalize">
              {{ order.service_area }} Service
            </h3>
          </div>
        </div>
        
        <div :class="['flex items-center gap-2 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-tighter', currentStatus.bg, currentStatus.color]">
          <CircleDot v-if="order.status !== 'completed'" class="w-3 h-3 animate-pulse" />
          {{ currentStatus.label }}
        </div>
      </div>

      <div v-if="order.items" class="py-4 border-y border-slate-50">
        <p class="text-xs font-bold text-slate-500 italic">
          {{ order.items.map(i => i.name).join(', ') }}
        </p>
      </div>

      <div class="relative pt-2">
        <div class="flex mb-2 items-center justify-between text-[9px] font-black text-slate-300 uppercase tracking-[0.2em]">
          <span>Ordered</span>
          <span>Preparing</span>
          <span>Delivery</span>
        </div>
        <div class="overflow-hidden h-1.5 flex rounded-full bg-slate-100">
          <div 
            :class="['transition-all duration-1000 ease-out rounded-full', currentStatus.color.replace('text', 'bg')]"
            :style="{ width: (currentStatus.step / 4) * 100 + '%' }"
          ></div>
        </div>
      </div>
    </div>
    
    <div class="px-6 py-3 bg-slate-50/50 border-t border-slate-50 flex justify-between items-center">
      <span class="text-[9px] font-bold text-slate-400">Time: {{ new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }}</span>
      <button v-if="order.status === 'pending'" class="text-[9px] font-black text-rose-500 uppercase tracking-widest hover:underline">
        Cancel Request
      </button>
    </div>
  </div>
</template>

<style scoped>
/* Ensure smooth transition for the progress bar */
.transition-all {
  transition-property: all;
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
  transition-duration: 500ms;
}
</style>