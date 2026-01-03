<script setup>
import { ref } from 'vue'
import BaseStaffLayout from './BaseStaffLayout.vue'
import { 
  ChefHat, 
  Utensils, 
  Settings, 
  Bell, 
  Menu as MenuIcon, 
  X 
} from 'lucide-vue-next'

const isMobileMenuOpen = ref(false)

const kitchenNavigation = [
  { name: 'Live Orders', href: '/staff/kitchen/orders', icon: Utensils },
  { name: 'Menu Editor', href: '/staff/menu', icon: ChefHat },
  { name: 'Station Settings', href: '/staff/settings', icon: Settings },
]
</script>

<template>
  <BaseStaffLayout>
    <div class="flex h-screen bg-slate-50 overflow-hidden">
      <aside class="hidden lg:flex w-72 flex-col bg-white border-r border-slate-200">
        <div class="p-6">
          <div class="flex items-center gap-3 px-2 mb-8">
            <div class="p-2 bg-orange-100 text-orange-600 rounded-xl">
              <ChefHat class="w-6 h-6" />
            </div>
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Kitchen Ops</h2>
          </div>

          <nav class="space-y-1">
            <a 
              v-for="item in kitchenNavigation" 
              :key="item.name" 
              :href="item.href"
              class="flex items-center gap-3 px-4 py-3 rounded-2xl text-slate-600 hover:bg-slate-50 hover:text-orange-600 transition-all font-semibold"
            >
              <component :is="item.icon" class="w-5 h-5" />
              {{ item.name }}
            </a>
          </nav>
        </div>

        <div class="mt-auto p-6">
          <div class="bg-orange-50 rounded-2xl p-4 border border-orange-100">
            <div class="flex items-center gap-2 text-orange-700 font-bold text-sm mb-1">
              <Bell class="w-4 h-4" /> System Alerts
            </div>
            <p class="text-xs text-orange-600/80 leading-relaxed font-medium">
              3 items are currently marked as "Out of Stock" in the main menu.
            </p>
          </div>
        </div>
      </aside>

      <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
        <header class="lg:hidden flex items-center justify-between px-6 py-4 bg-white border-b border-slate-200">
          <div class="flex items-center gap-3">
            <ChefHat class="w-6 h-6 text-orange-600" />
            <span class="font-bold text-slate-900">Kitchen Ops</span>
          </div>
          <button 
            @click="isMobileMenuOpen = !isMobileMenuOpen"
            class="p-2 rounded-xl bg-slate-50 text-slate-600 border border-slate-100"
          >
            <component :is="isMobileMenuOpen ? X : MenuIcon" class="w-6 h-6" />
          </button>
        </header>

        <transition 
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 -translate-y-4"
          enter-to-class="opacity-100 translate-y-0"
          leave-active-class="transition duration-150 ease-in"
          leave-from-class="opacity-100 translate-y-0"
          leave-to-class="opacity-0 -translate-y-4"
        >
          <nav v-if="isMobileMenuOpen" class="lg:hidden bg-white border-b border-slate-200 px-4 py-4 space-y-1 shadow-xl relative z-40">
            <a 
              v-for="item in kitchenNavigation" 
              :key="item.name" 
              :href="item.href"
              @click="isMobileMenuOpen = false"
              class="flex items-center gap-3 px-4 py-4 rounded-xl text-slate-600 font-bold active:bg-slate-50"
            >
              <component :is="item.icon" class="w-6 h-6" />
              {{ item.name }}
            </a>
          </nav>
        </transition>

        <main class="flex-1 overflow-y-auto bg-slate-50">
          <div class="h-full">
            <slot />
          </div>
        </main>
      </div>
    </div>
  </BaseStaffLayout>
</template>

<style scoped>
/* Smooth scrolling for kitchen ticket lists */
main {
  scroll-behavior: smooth;
  -webkit-overflow-scrolling: touch;
}
</style>