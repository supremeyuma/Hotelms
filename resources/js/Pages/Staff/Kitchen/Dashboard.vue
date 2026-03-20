<script setup>
import { Link } from '@inertiajs/vue3'
import KitchenLayout from '@/Layouts/Staff/KitchenLayout.vue'
import { 
  UtensilsCrossed, 
  ClipboardList, 
  Settings2, 
  ChefHat 
} from 'lucide-vue-next'
</script>

<template>
  <KitchenLayout>
    <div class="max-w-5xl mx-auto px-4 py-6 md:py-10">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div class="flex items-center gap-4">
          <div class="p-3 bg-orange-100 rounded-2xl text-orange-600">
            <ChefHat class="w-8 h-8" />
          </div>
          <div>
            <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">
              Kitchen Dashboard
            </h1>
            <p class="text-slate-500 text-sm md:text-base font-medium">
              Real-time station management and order flow
            </p>
          </div>
        </div>
        
        <div class="flex items-center self-start md:self-center px-3 py-1.5 bg-green-50 border border-green-100 rounded-full">
          <span class="flex h-2 w-2 rounded-full bg-green-500 animate-pulse mr-2"></span>
          <span class="text-xs font-bold text-green-700 uppercase tracking-wider">Kitchen Open</span>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 md:gap-6">
        <Link 
          href="/staff/kitchen/orders" 
          class="group relative overflow-hidden bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-xl hover:border-indigo-100 transition-all duration-300"
        >
          <div class="flex items-start justify-between">
            <div class="space-y-4">
              <div class="inline-flex p-3 bg-indigo-50 text-indigo-600 rounded-2xl group-hover:scale-110 transition-transform duration-300">
                <ClipboardList class="w-6 h-6" />
              </div>
              <div>
                <h2 class="text-xl font-bold text-slate-900">View Orders</h2>
                <p class="text-slate-500 text-sm mt-1">Track incoming tickets and preparation times.</p>
              </div>
            </div>
            <div class="text-indigo-200 group-hover:text-indigo-500 transition-colors">
              <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
              </svg>
            </div>
          </div>
          <div class="absolute -bottom-6 -right-6 w-24 h-24 bg-indigo-50/50 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
        </Link>

        <Link 
          href="/staff/kitchen/menu" 
          class="group relative overflow-hidden bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-xl hover:border-orange-100 transition-all duration-300"
        >
          <div class="flex items-start justify-between">
            <div class="space-y-4">
              <div class="inline-flex p-3 bg-orange-50 text-orange-600 rounded-2xl group-hover:scale-110 transition-transform duration-300">
                <UtensilsCrossed class="w-6 h-6" />
              </div>
              <div>
                <h2 class="text-xl font-bold text-slate-900">Manage Menu</h2>
                <p class="text-slate-500 text-sm mt-1">Toggle item availability and edit descriptions.</p>
              </div>
            </div>
            <div class="text-orange-200 group-hover:text-orange-500 transition-colors">
              <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
              </svg>
            </div>
          </div>
          <div class="absolute -bottom-6 -right-6 w-24 h-24 bg-orange-50/50 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
        </Link>
      </div>

      <button class="fixed bottom-6 right-6 md:hidden w-14 h-14 bg-slate-900 text-white rounded-full shadow-2xl flex items-center justify-center active:scale-90 transition-transform">
        <Settings2 class="w-6 h-6" />
      </button>
    </div>
  </KitchenLayout>
</template>

<style scoped>
/* Responsive text balancing */
h1 {
  text-wrap: balance;
}
</style>