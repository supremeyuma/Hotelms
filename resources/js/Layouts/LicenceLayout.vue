<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { CheckCircle2 } from 'lucide-vue-next'

defineProps({
  product: {
    type: String,
    default: 'MooreLife',
  },
})
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-900">
    <Head />

    <header class="border-b border-slate-200 bg-white">
      <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-5 sm:px-6 lg:px-8">
        <Link :href="route('licence.pricing')" class="flex items-center gap-3">
          <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-900 text-sm font-black text-white">
            M
          </span>
          <span class="text-lg font-black tracking-tight">{{ product }}</span>
        </Link>

        <nav class="flex items-center gap-6 text-sm font-bold text-slate-600">
          <Link :href="route('licence.pricing')" class="hover:text-slate-900">Pricing</Link>
          <Link
            :href="route('licence.checkout.create')"
            class="rounded-xl bg-slate-900 px-4 py-2 text-white transition hover:bg-slate-800"
          >
            Buy a licence
          </Link>
        </nav>
      </div>
    </header>

    <main>
      <slot />
    </main>

    <footer class="border-t border-slate-200 bg-white">
      <div class="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-8 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
        <p class="flex items-center gap-2">
          <CheckCircle2 class="h-4 w-4 text-emerald-500" />
          Licences are activated offline after a single online exchange.
        </p>
        <p>&copy; {{ new Date().getFullYear() }} {{ product }}. All rights reserved.</p>
      </div>
    </footer>
  </div>
</template>
