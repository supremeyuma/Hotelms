<script setup>
import { Link } from '@inertiajs/vue3'
import KitchenLayout from '@/Layouts/Staff/KitchenLayout.vue'
</script>

<template>
  <KitchenLayout>
    <div class="p-6">
      <h1 class="text-2xl font-bold mb-6">Kitchen Dashboard</h1>

      <div class="flex gap-4">
        <Link href="/staff/kitchen/orders" class="btn">View Orders</Link>
        <Link href="/staff/menu/manage?area=kitchen" class="btn">Manage Menu</Link>
      </div>
    </div>
  </KitchenLayout>
</template>
