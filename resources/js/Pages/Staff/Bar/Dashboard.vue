<script setup>
import { Link } from '@inertiajs/vue3'
import BarLayout from '@/Layouts/Staff/BarLayout.vue'
</script>

<template>
  <BarLayout>
    <div class="p-6">
      <h1 class="text-2xl font-bold mb-6">Bar Dashboard</h1>

      <div class="flex gap-4">
        <Link href="/staff/bar/orders" class="btn">View Orders</Link>
        <Link href="/staff/menu/manage?area=bar" class="btn">Manage Menu</Link>
      </div>
    </div>
  </BarLayout>
</template>
