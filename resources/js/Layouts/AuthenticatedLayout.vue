<template>
  <div class="min-h-screen bg-slate-50">
    <aside class="w-64 fixed inset-y-0 left-0 bg-white border-r p-4">
      <h2 class="font-bold mb-6">Staff Portal</h2>
      <nav class="space-y-2">
        <inertia-link href="/staff/dashboard" class="block">Dashboard</inertia-link>
        <inertia-link href="/staff/orders" class="block">Orders Queue</inertia-link>
        <inertia-link href="/staff/quick-action" class="block">Quick Action</inertia-link>
        <inertia-link href="/staff/profile" class="block">My Profile</inertia-link>
      </nav>
    </aside>

    <div class="ml-64">
      <header class="bg-white border-b p-4 flex justify-between">
        <div>{{ page.props.auth.user.name ?? 'Guest' }}</div>
        <div>
          <inertia-link href="/logout">Logout</inertia-link>
        </div>
      </header>

      <main class="p-6">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { usePage } from '@inertiajs/inertia-vue3';
const page = usePage();
</script>
