<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const siteName = page.props.settings?.site_name || 'HotelMS';
</script>

<template>
  <div class="min-h-screen bg-slate-50 dark:bg-slate-950">
    <header class="bg-white shadow dark:bg-slate-900 dark:border-b dark:border-slate-700">
      <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
        <h1 class="text-xl font-semibold">{{ siteName }}</h1>
        <nav>
          <Link href="/" class="px-3">Home</Link>
          <Link href="/rooms" class="px-3">Rooms</Link>
          <Link href="/contact" class="px-3">Contact</Link>
          <Link :href="route('feedback.create')" class="px-3">Feedback</Link>
        </nav>
      </div>
    </header>

    <main class="max-w-7xl mx-auto p-4">
      <slot />
    </main>

    <footer class="text-center p-6 text-sm text-slate-500 dark:text-slate-400">© {{ new Date().getFullYear() }} {{ siteName }}</footer>
  </div>
</template>

