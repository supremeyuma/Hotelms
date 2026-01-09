<!-- resources/js/Layouts/PublicLayout.vue -->
<script setup>
import { Head, Link } from '@inertiajs/vue3'
</script>

<template>
  <div class="min-h-screen flex flex-col bg-white text-slate-800">
    <Head />

    <header class="sticky top-0 z-50 bg-white/90 backdrop-blur border-b">
      <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
        <Link href="/" class="text-xl font-black tracking-wide">
          {{ content('site.name', 'Beach Resort') }}
        </Link>

        <nav class="hidden md:flex gap-6 text-sm font-semibold">
          <Link href="/gallery">Gallery</Link>
          <Link href="/amenities">Amenities</Link>
          <Link href="/club-lounge">Club & Lounge</Link>
          <Link href="/policies">Policies</Link>
          <Link href="/booking" class="text-indigo-600">Book Now</Link>
        </nav>
      </div>
    </header>

    <main class="flex-1">
      <slot />
    </main>

    <footer class="bg-slate-900 text-slate-200 mt-20">
      <div class="max-w-7xl mx-auto px-4 py-10 grid md:grid-cols-3 gap-8 text-sm">
        <div>
          <h3 class="font-bold mb-2">About</h3>
          <p>{{ content('footer.about') }}</p>
        </div>
        <div>
          <h3 class="font-bold mb-2">Contact</h3>
          <p>{{ content('footer.contact') }}</p>
        </div>
        <div>
          <h3 class="font-bold mb-2">Location</h3>
          <p>{{ content('footer.location') }}</p>
        </div>
      </div>
    </footer>
  </div>
</template>
