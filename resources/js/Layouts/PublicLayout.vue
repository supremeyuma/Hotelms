<script setup>
import { computed, onMounted, ref } from 'vue'
import { usePage, Head, Link } from '@inertiajs/vue3'
import { MapPin, Phone, Mail, Instagram, Facebook } from 'lucide-vue-next'

const page = usePage()
const isScrolled = ref(false)

const logo = computed(() => {
  const path = page.props.settings?.logo
  return path ? `/storage/${path}` : null
})

const phone = computed(() => page.props.settings?.hotel_phone ?? null)
const email = computed(() => page.props.settings?.contact_email ?? null)

onMounted(() => {
  window.addEventListener('scroll', () => {
    isScrolled.value = window.scrollY > 50
  })
})
</script>

<template>
  <div class="min-h-screen flex flex-col bg-white text-slate-900 selection:bg-indigo-100 selection:text-indigo-900">
    <Head />

    <header 
      class="fixed top-0 w-full z-[60] transition-all duration-500"
      :class="[
        isScrolled 
          ? 'py-4 bg-white/80 backdrop-blur-lg border-b border-slate-100 shadow-sm' 
          : 'py-8 bg-transparent'
      ]"
    >
      <div class="max-w-screen-2xl mx-auto px-6 md:px-12 flex items-center justify-between">

        <Link href="/" class="group flex items-center gap-4">
          <div class="relative w-10 h-10 overflow-hidden  border-slate-200 transition-transform group-hover:scale-110">
            <img
              v-if="logo"
              :src="logo"
              alt="Logo"
              class="w-full h-full object-cover"
            />
            <div v-else class="w-full h-full bg-slate-950 flex items-center justify-center text-[10px] text-white font-bold">ML</div>
          </div>

          <span 
            class="text-sm font-black uppercase tracking-[0.3em] transition-colors"
            :class="isScrolled ? 'text-slate-900' : 'text-white'"
          >
            {{ content('site.name', 'Beach Resort') }}
          </span>
        </Link>

        <nav class="hidden md:flex items-center gap-10">
          <Link 
            v-for="link in [
              { label: 'Gallery', href: '/gallery' },
              { label: 'Amenities', href: '/amenities' },
              { label: 'Club & Lounge', href: '/club-lounge' }
            ]" 
            :key="link.label"
            :href="link.href"
            class="nav-link"
            :class="isScrolled ? 'text-slate-600 hover:text-indigo-600' : 'text-white/80 hover:text-white'"
          >
            {{ link.label }}
          </Link>
        </nav>

        <div class="flex items-center gap-6">
          <Link 
            href="/booking" 
            class="hidden sm:block px-8 py-3 rounded-full text-[10px] font-black uppercase tracking-widest transition-all"
            :class="isScrolled 
              ? 'bg-slate-950 text-white hover:bg-indigo-600 shadow-lg' 
              : 'bg-white text-slate-950 hover:bg-indigo-50'
            "
          >
            Book Now
          </Link>

          <a
            v-if="phone"
            :href="`tel:${phone}`"
            class="md:hidden w-10 h-10 flex items-center justify-center rounded-full bg-emerald-600 backdrop-blur-md border border-white/90 text-white"
          >
            <Phone class="w-4 h-4" />
          </a>
        </div>
      </div>
    </header>

    <main class="flex-1">
      <slot />
    </main>

    <footer class="bg-slate-950 text-white pt-32 pb-12 overflow-hidden relative">
      <div class="absolute top-0 right-0 w-1/2 h-full bg-indigo-500/5 blur-[120px] rounded-full -translate-y-1/2" />

      <div class="max-w-screen-2xl mx-auto px-6 md:px-12 relative z-10">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-16 lg:gap-8 mb-24">
          
          <div class="lg:col-span-5 space-y-8">
            <h2 class="text-4xl font-black uppercase tracking-tighter leading-none">
              {{ content('site.name') }}
            </h2>
            <p class="text-slate-400 text-lg font-light leading-relaxed max-w-md">
              {{ content('footer.about') }}
            </p>
            <div class="flex gap-4">
              <a href="#" class="social-icon-btn"><Instagram class="w-4 h-4" /></a>
              <a href="#" class="social-icon-btn"><Facebook class="w-4 h-4" /></a>
            </div>
          </div>

          <div class="lg:col-span-7 grid grid-cols-1 sm:grid-cols-3 gap-12 sm:gap-4">
            <div class="space-y-6">
              <h3 class="footer-label">Contact</h3>
              <div class="space-y-4">
                <a v-if="phone" :href="`tel:${phone}`" class="footer-link group">
                  <span class="block text-[10px] text-slate-500 font-bold mb-1">Phone</span>
                  {{ phone }}
                </a>
                <a v-if="email" :href="`mailto:${email}`" class="footer-link group">
                  <span class="block text-[10px] text-slate-500 font-bold mb-1">Email</span>
                  {{ email }}
                </a>
              </div>
            </div>

            <div class="space-y-6">
              <h3 class="footer-label">Location</h3>
              <div class="space-y-4">
                <a :href="content('footer.map_url')" target="_blank" class="footer-link group">
                  <span class="block text-[10px] text-slate-500 font-bold mb-1">Address</span>
                  {{ content('footer.location') }}
                </a>
              </div>
            </div>

            <div class="space-y-6">
              <h3 class="footer-label">Legal</h3>
              <div class="space-y-4">
                <Link href="/policies" class="footer-link group">
                  Hotel Policies
                </Link>
                <Link href="/terms" class="footer-link group">
                  Terms of Service
                </Link>
              </div>
            </div>
          </div>
        </div>

        <div class="pt-12 border-t border-white/10 flex flex-col md:flex-row justify-between items-center gap-6 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">
          <span>© {{ new Date().getFullYear() }} {{ content('site.name') }}</span>
          <span class="hidden md:block h-1 w-1 bg-slate-700 rounded-full"></span>
          <span>Crafted for Excellence</span>
        </div>
      </div>
    </footer>
  </div>
</template>

<style scoped>
.nav-link {
  @apply relative text-[10px] font-black uppercase tracking-[0.2em] transition-all duration-300;
}

.nav-link::after {
  content: '';
  @apply absolute -bottom-1 left-0 w-0 h-[1px] bg-current transition-all duration-300;
}

.nav-link:hover::after {
  @apply w-full;
}

.footer-label {
  @apply text-[10px] font-black uppercase tracking-[0.3em] text-indigo-400;
}

.footer-link {
  @apply block text-sm font-light text-slate-300 hover:text-white transition-colors duration-300;
}

.social-icon-btn {
  @apply w-10 h-10 rounded-full border border-white/10 flex items-center justify-center hover:bg-white hover:text-slate-950 transition-all duration-300;
}
</style>