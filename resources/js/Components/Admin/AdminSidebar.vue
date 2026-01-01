<script setup>
import { Link } from '@inertiajs/vue3'
import Icon from '@/Components/Icon.vue'
</script>

<template>
  <aside class="w-64 bg-white border-r flex flex-col">
    <div class="h-16 flex items-center px-6 font-bold text-lg border-b">
      Moorelife Resort
    </div>

    <nav class="flex-1 p-4 space-y-2">
      <Link :href="route('admin.dashboard')" class="nav-item">
        <Icon name="home" /> Dashboard
      </Link>

      <Link :href="route('admin.rooms.index')" class="nav-item">
        <Icon name="bed" /> Rooms
      </Link>

      <Link :href="route('admin.room-types.index')" class="nav-item">
        <Icon name="layers" /> Room Types
      </Link>

      <Link :href="route('admin.bookings.index')" class="nav-item">
        <Icon name="calendar" /> Bookings
      </Link>

      <Link :href="route('admin.orders.index')" class="nav-item">
        <Icon name="clipboard" /> Orders
      </Link>

      <Link :href="route('admin.inventory.index')" class="nav-item">
        <Icon name="box" /> Inventory
      </Link>

      <Link :href="route('admin.maintenance.index')" class="nav-item">
        <Icon name="tool" /> Maintenance
      </Link>

      <Link :href="route('admin.audit.index')" class="nav-item">
        <Icon name="shield" /> Audit Logs
      </Link>

      <Link :href="route('admin.reports.dashboard')" class="nav-item">
        <Icon name="chart" /> Reports
      </Link>

      <Link :href="route('admin.settings.index')" class="nav-item">
        <Icon name="settings" /> Settings
      </Link>

      <!-- Switch to staff -->
      <Link href="/staff" class="nav-item text-blue-600">
        <Icon name="repeat" /> Switch to Staff
      </Link>
    </nav>
  </aside>
</template>

<style scoped>
.nav-item {
  @apply flex items-center gap-3 px-3 py-2 rounded-lg text-sm
         text-gray-700 hover:bg-gray-100;
}
</style>
