<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white shadow">
      <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
        <h1 class="text-xl font-semibold">HotelMS</h1>
        <nav>
          <Link href="/" class="px-3">Home</Link>
          <Link href="/rooms" class="px-3">Rooms</Link>
          <Link href="/contact" class="px-3">Contact</Link>
        </nav>
      </div>
    </header>

    <main class="max-w-7xl mx-auto p-4">
      <slot />
    </main>

    <footer class="text-center p-6 text-sm text-gray-500">© {{ new Date().getFullYear() }} HotelMS</footer>
  </div>
</template>


