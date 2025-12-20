<template>
  <AuthenticatedLayout>
    <div>
      <h2 class="text-2xl mb-4">Welcome, {{ user?.name }}</h2>

      <div class="bg-white  rounded-2xl p-6 shadow-sm">
        <div class="text-sm text-gray-500">Department</div>
        <div class="text-xl font-bold text-gray-800 ">
          {{ department || 'N/A' }}
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white  p-4 rounded-xl shadow-sm">
          <div class="text-sm text-gray-500">Department Tasks</div>
          <div class="text-lg font-semibold">Coming soon</div>
        </div>

        <div class="bg-white p-4 rounded shadow">
          <h3 class="font-semibold">Pending Bookings</h3>
          <div class="text-3xl mt-2">{{ stats.pending_bookings }}</div>
        </div>

        <div class="bg-white p-4 rounded shadow">
          <div class="text-sm text-gray-500">Notifications</div>
          <div class="text-lg font-semibold">Coming soon</div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

defineProps({
  user: Object,
  department: String,
})


const props = usePage().props;
const user = props.auth.user;
const stats = props.stats ?? { open_orders: 0, pending_bookings: 0, low_stock: 0 };

console.log(user);
</script>
