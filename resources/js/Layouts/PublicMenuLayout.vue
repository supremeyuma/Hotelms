<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

defineProps({
  title: {
    type: String,
    default: null,
  },
})

const page = usePage()
const siteName = computed(() => page.props.settings?.site_name || 'HotelMS')
const logo = computed(() => {
  const path = page.props.settings?.logo
  return path ? `/storage/${path}` : null
})
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <Head :title="title" />

    <header class="sticky top-0 z-40 border-b border-gray-200 bg-white/95 backdrop-blur">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2.5 sm:py-3">
        <div class="flex items-center justify-between gap-3">
          <Link href="/" class="shrink-0 flex items-center">
            <img
              v-if="logo"
              :src="logo"
              :alt="siteName"
              class="h-9 w-auto object-contain sm:h-10"
            />
            <ApplicationLogo
              v-else
              class="h-9 w-9 text-slate-900 sm:h-10 sm:w-10"
            />
          </Link>

          <nav class="flex items-center gap-2 text-sm font-semibold text-gray-600 sm:gap-4">
            <Link href="/" class="rounded-full px-3 py-1.5 transition hover:bg-gray-100 hover:text-gray-900">
              Home
            </Link>
            <Link href="/contact" class="rounded-full px-3 py-1.5 transition hover:bg-gray-100 hover:text-gray-900">
              Contact
            </Link>
          </nav>
        </div>
      </div>
    </header>

    <main>
      <slot />
    </main>

    <footer class="border-t border-gray-200 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 text-center text-xs font-medium text-gray-500">
        &copy; {{ new Date().getFullYear() }} {{ siteName }}
      </div>
    </footer>
  </div>
</template>
