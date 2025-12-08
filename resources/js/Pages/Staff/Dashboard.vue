<template>
  <AuthenticatedLayout>
    <div>
      <h2 class="text-2xl mb-4">Welcome, {{ user?.name }}</h2>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white p-4 rounded shadow">
          <h3 class="font-semibold">Open Orders</h3>
          <div class="text-3xl mt-2">{{ stats.open_orders }}</div>
        </div>

        <div class="bg-white p-4 rounded shadow">
          <h3 class="font-semibold">Pending Bookings</h3>
          <div class="text-3xl mt-2">{{ stats.pending_bookings }}</div>
        </div>

        <div class="bg-white p-4 rounded shadow">
          <h3 class="font-semibold">Low Inventory</h3>
          <div class="text-3xl mt-2">{{ stats.low_stock }}</div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref } from 'vue';

const props = usePage().props;
const user = props.auth.user;
const stats = props.stats ?? { open_orders: 0, pending_bookings: 0, low_stock: 0 };
</script>
