<!-- resources/js/Layouts/PublicLayout.vue -->
<script setup>
import { computed } from 'vue'
import { usePage, Head, Link } from '@inertiajs/vue3'
import { ChevronDown, MapPin, Phone, Star, Mail } from 'lucide-vue-next'

const page = usePage()

const logo = computed(() => {
  const path = page.props.settings?.logo
  return path ? `/storage/${path}` : null
})

const phone = computed(() => page.props.settings?.hotel_phone ?? null)
const email = computed(() => page.props.settings?.contact_email ?? null)

</script>


<template>
  <div class="min-h-screen flex flex-col bg-white text-slate-800">
    <Head />

    <!-- HEADER -->
    <header class="sticky top-0 z-50 bg-white/90 backdrop-blur border-b">
      <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between gap-6">

        <!-- LOGO / BRAND -->
        <Link href="/" class="flex items-center gap-3">
          <div
            class="w-12 h-12 rounded-xl bg-slate-00 flex items-center justify-center overflow-hidden"
          >
            <img
              v-if="logo"
              :src="logo"
              alt="Hotel logo"
              class="w-full h-full object-contain"
            />
          </div>

          <span class="text-lg font-black tracking-wide">
            {{ content('site.name', 'Beach Resort') }}
          </span>
        </Link>

        <!-- NAV -->
        <nav class="hidden md:flex items-center gap-6 text-sm font-semibold">
          <Link href="/gallery">Gallery</Link>
          <Link href="/amenities">Amenities</Link>
          <Link href="/club-lounge">Club & Lounge</Link>
          <Link href="/booking" class="text-indigo-600">Book Now</Link>

          <!-- CALL BUTTON -->
          <a
            v-if="phone"
            :href="`tel:${phone}`"
            class="ml-4 px-4 py-2 rounded-full bg-emerald-600 text-white text-xs font-black tracking-wide hover:bg-emerald-700 transition"
          >
            Call
          </a>
        </nav>

        <!-- MOBILE CALL -->
        <a
          v-if="phone"
          :href="`tel:${phone}`"
          class="md:hidden px-4 py-2 rounded-full bg-emerald-600 text-white text-xs font-black"
        >
          Call
        </a>
      </div>
    </header>

    <!-- MAIN -->
    <main class="flex-1">
      <slot />
    </main>

    <!-- FOOTER -->
    <footer class="bg-slate-900 text-slate-200 mt-20">
      <div class="max-w-7xl mx-auto px-4 py-12 grid md:grid-cols-4 gap-10 text-sm">

        <div class="space-y-4">
                <h2 class="text-xl font-black uppercase tracking-tighter">{{ content('site.name') }}</h2>
                <p class="text-slate-400 text-sm leading-relaxed">{{ content('footer.about') }}</p>
            </div>

        <div>
          <h3 class="text-xs font-black uppercase tracking-widest text-slate-500">Contact</h3>
            <div class="flex items-start py-2 gap-1 text-slate-300">
              <Phone class="w-5 h-5 text-indigo-400 shrink-0" />
              <a class="text-slate-400 hover:text-indigo-400 transition-colors"
               v-if="phone" 
               :href="`tel:${phone}`">{{ phone }} </a>
            </div>
            <div class="flex items-start gap-1 text-slate-300">
              <Mail class="w-5 h-5 text-indigo-400 shrink-0" />
              
              <a 
                v-if="email" 
                :href="`mailto:${email}`" 
                class="text-slate-400 hover:text-indigo-400 transition-colors"
              >
                {{ email }}
              </a>
              
              <span v-else class="text-slate-500 italic">No email provided</span>
            </div>
        </div>

        <div>
          <h3 class="text-xs font-black uppercase tracking-widest text-slate-500">Location</h3>
          <div class="flex items-start py-2 gap-1 text-slate-300">
            <MapPin class="w-5 h-5 text-indigo-400 shrink-0" />
            <a
              :href="content('footer.map_url')"
              target="_blank"
              rel="noopener"
              class="text-indigo-400 hover:text-indigo-300 underline"
            >
              {{ content('footer.location') }}
            </a>
          </div>
        </div>


        <div>
          <h3 class="text-xs font-black uppercase tracking-widest text-slate-500">Legal</h3>
          <div class="flex items-start py-2 gap-1 text-slate-300">
            <Link
              href="/policies"
              class="text-indigo-400 hover:text-indigo-300 underline"
            >
              Hotel Policies
            </Link>
          </div>
        </div>
      </div>

      <div class="border-t border-slate-800 py-4 text-center text-xs text-slate-500 tracking-widest uppercase">
        © {{ new Date().getFullYear() }} {{ content('site.name') }}. All rights reserved.
      </div>
    </footer>
  </div>
</template>
