<script setup>
import { ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
// Remove the vue-router import entirely
import StaffSidebar from '@/Components/Staff/StaffSidebar.vue'

const page = usePage();
const siteName = page.props.settings?.site_name || 'HotelMS';
const isMobileMenuOpen = ref(false)

// Function to handle clicks on the sidebar links (to close it on mobile)
const closeMobileMenu = () => {
  isMobileMenuOpen.value = false
}
</script>

<template>
  <div class="flex h-screen bg-slate-50 overflow-hidden font-sans">
    
    <div class="hidden lg:flex lg:flex-shrink-0 border-r border-slate-200">
      <StaffSidebar />
    </div>

    <div 
      v-if="isMobileMenuOpen" 
      class="fixed inset-0 z-50 flex lg:hidden" 
    >
      <div 
        @click="closeMobileMenu"
        class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity"
      ></div>

      <div class="relative flex w-full max-w-xs flex-1 flex-col bg-white shadow-xl animate-slide-in">
        <div class="absolute right-[-50px] top-2">
          <button @click="closeMobileMenu" class="text-white p-2">
            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        
        <StaffSidebar @click="closeMobileMenu" />
      </div>
    </div>

    <div class="flex flex-1 flex-col overflow-hidden">
      
      <header class="flex items-center justify-between bg-white px-5 py-3 border-b border-slate-200 lg:hidden shadow-sm">
        <div class="flex items-center gap-2">
          <div class="w-9 h-9 bg-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-200">
             <span class="text-white font-bold text-sm">H</span>
          </div>
          <span class="font-bold text-slate-800 tracking-tight text-lg">{{ siteName }}</span>
        </div>
        
        <button 
          @click="isMobileMenuOpen = true"
          class="p-2.5 rounded-xl bg-slate-50 text-slate-600 border border-slate-100 active:scale-95 transition-all"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
          </svg>
        </button>
      </header>

      <main class="flex-1 overflow-y-auto relative bg-slate-50">
        <div class="py-6 px-4 sm:px-8 max-w-[1600px] mx-auto">
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>

<style scoped>
.animate-slide-in {
  animation: slideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideIn {
  from { transform: translateX(-100%); }
  to { transform: translateX(0); }
}
</style>