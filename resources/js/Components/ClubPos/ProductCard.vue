<script setup>
const props = defineProps({
  item: { type: Object, required: true },
  stock: { type: Object, default: null },
})

const emit = defineEmits(['add'])
</script>

<template>
  <button @click="emit('add', item)"
    class="relative flex flex-col items-center justify-center p-4 rounded-xl bg-slate-800 border border-slate-700 hover:border-indigo-500/50 hover:bg-slate-750 transition-all active:scale-[0.97] min-h-[80px] text-center group">
    <span class="text-base font-semibold text-white leading-tight">{{ item.name }}</span>
    <span v-if="item.description" class="text-xs text-slate-400 mt-1 line-clamp-1">{{ item.description }}</span>
    <span class="text-lg font-bold text-emerald-400 mt-1">Ksh {{ Number(item.price).toLocaleString() }}</span>
    <span v-if="stock && stock.full_bottles <= stock.low_stock_threshold"
      class="absolute top-2 right-2 w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse" />
  </button>
</template>
