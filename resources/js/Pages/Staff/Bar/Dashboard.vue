<script setup>
import { Link } from '@inertiajs/vue3'
import BarLayout from '@/Layouts/Staff/BarLayout.vue'
import { 
  GlassWater, 
  ClipboardList, 
  Settings2, 
  Beer,
  ArrowRight
} from 'lucide-vue-next'
</script>

<template>
  <BarLayout>
    <div class="max-w-5xl mx-auto px-4 py-6 md:py-10">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
        <div class="flex items-center gap-5">
          <div class="p-4 bg-blue-600 rounded-2xl text-white shadow-lg shadow-blue-200">
            <Beer class="w-8 h-8" />
          </div>
          <div>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">
              Bar Dashboard
            </h1>
            <p class="text-slate-500 font-medium">
              Monitor drink tickets and beverage inventory
            </p>
          </div>
        </div>
        
        <div class="flex items-center gap-3 px-4 py-2 bg-white border border-slate-200 rounded-2xl shadow-sm">
          <span class="relative flex h-3 w-3">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-3 w-3 bg-blue-600"></span>
          </span>
          <span class="text-sm font-bold text-slate-700">Service Active</span>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <Link 
          href="/staff/bar/orders" 
          class="group relative overflow-hidden bg-white p-8 rounded-3xl border border-slate-200 shadow-sm hover:shadow-2xl hover:-translate-y-1 transition-all duration-300"
        >
          <div class="relative z-10 flex flex-col h-full">
            <div class="mb-6 inline-flex p-4 bg-blue-50 text-blue-600 rounded-2xl group-hover:bg-blue-600 group-hover:text-white transition-colors duration-300">
              <ClipboardList class="w-7 h-7" />
            </div>
            <h2 class="text-2xl font-extrabold text-slate-900 mb-2">View Orders</h2>
            <p class="text-slate-500 font-medium mb-8">Process drink orders, cocktails, and wine service.</p>
            
            <div class="mt-auto flex items-center gap-2 text-blue-600 font-bold">
              <span>Open Queue</span>
              <ArrowRight class="w-5 h-5 group-hover:translate-x-2 transition-transform" />
            </div>
          </div>
          <div class="absolute -bottom-10 -right-10 w-40 h-40 bg-blue-50/50 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
        </Link>

        <Link 
          href="/staff/menu" 
          class="group relative overflow-hidden bg-white p-8 rounded-3xl border border-slate-200 shadow-sm hover:shadow-2xl hover:-translate-y-1 transition-all duration-300"
        >
          <div class="relative z-10 flex flex-col h-full">
            <div class="mb-6 inline-flex p-4 bg-cyan-50 text-cyan-600 rounded-2xl group-hover:bg-cyan-600 group-hover:text-white transition-colors duration-300">
              <GlassWater class="w-7 h-7" />
            </div>
            <h2 class="text-2xl font-extrabold text-slate-900 mb-2">Manage Menu</h2>
            <p class="text-slate-500 font-medium mb-8">Update stock levels for beers, spirits, and seasonal drinks.</p>
            
            <div class="mt-auto flex items-center gap-2 text-cyan-600 font-bold">
              <span>Inventory Settings</span>
              <ArrowRight class="w-5 h-5 group-hover:translate-x-2 transition-transform" />
            </div>
          </div>
          <div class="absolute -bottom-10 -right-10 w-40 h-40 bg-cyan-50/50 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
        </Link>
      </div>

      <button class="fixed bottom-8 right-8 md:hidden w-16 h-16 bg-slate-900 text-white rounded-full shadow-2xl flex items-center justify-center hover:scale-110 active:scale-95 transition-all z-50">
        <Settings2 class="w-7 h-7" />
      </button>
    </div>
  </BarLayout>
</template>

<style scoped>
/* Scoped styles for high-end typography and smooth transitions */
h1, h2 {
  text-wrap: balance;
  -webkit-font-smoothing: antialiased;
}
</style>