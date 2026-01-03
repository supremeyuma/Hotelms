<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { 
  Bed, 
  UserPlus, 
  UserMinus, 
  List, 
  CheckCircle2, 
  AlertCircle 
} from 'lucide-vue-next'

const props = defineProps({
  title: { type: String, required: true },
  value: { type: [Number, String], required: true },
  label: { type: String, default: '' }, // Supporting the secondary text
  href: { type: String, default: null },
  variant: { type: String, default: 'indigo' }, // indigo, emerald, amber, rose
})

const Wrapper = computed(() => (props.href ? Link : 'div'))

// Map variants to specific Tailwind classes
const theme = computed(() => {
  const themes = {
    indigo: {
      bg: 'hover:border-indigo-200',
      iconBg: 'bg-indigo-50 text-indigo-600',
      valueColor: 'text-slate-900',
      glow: 'shadow-indigo-100/50',
      icon: Bed
    },
    emerald: {
      bg: 'hover:border-emerald-200',
      iconBg: 'bg-emerald-50 text-emerald-600',
      valueColor: 'text-slate-900',
      glow: 'shadow-emerald-100/50',
      icon: CheckCircle2
    },
    amber: {
      bg: 'hover:border-amber-200',
      iconBg: 'bg-amber-50 text-amber-600',
      valueColor: 'text-slate-900',
      glow: 'shadow-amber-100/50',
      icon: UserPlus
    },
    rose: {
      bg: 'hover:border-rose-200',
      iconBg: 'bg-rose-50 text-rose-600',
      valueColor: 'text-slate-900',
      glow: 'shadow-rose-100/50',
      icon: UserMinus
    }
  }
  return themes[props.variant] || themes.indigo
})
</script>

<template>
  <component
    :is="Wrapper"
    :href="href"
    class="relative overflow-hidden group p-6 rounded-[2rem] bg-white border border-slate-100 shadow-sm transition-all duration-300 hover:shadow-xl hover:-translate-y-1"
    :class="[theme.bg, theme.glow]"
  >
    <div class="flex items-start justify-between relative z-10">
      <div class="space-y-1">
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">
          {{ title }}
        </p>
        
        <div class="flex items-baseline gap-1">
          <p class="text-4xl font-black tracking-tight" :class="theme.valueColor">
            {{ value }}
          </p>
          <span v-if="label" class="text-xs font-bold text-slate-400 lowercase">
            {{ label }}
          </span>
        </div>
      </div>

      <div 
        class="p-3 rounded-2xl transition-transform duration-500 group-hover:rotate-12"
        :class="theme.iconBg"
      >
        <component :is="theme.icon" class="w-6 h-6" stroke-width="2.5" />
      </div>
    </div>

    <div 
      class="absolute -bottom-8 -right-8 w-24 h-24 rounded-full opacity-0 group-hover:opacity-10 transition-opacity duration-500"
      :class="theme.iconBg"
    ></div>
  </component>
</template>

<style scoped>
/* Ensure smooth font rendering for heavy weights */
p {
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
</style>