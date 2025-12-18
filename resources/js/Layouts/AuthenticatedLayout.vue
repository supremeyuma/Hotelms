<template>
  <div class="min-h-screen bg-slate-50">
    <!-- Sidebar -->
    <aside class="w-64 fixed inset-y-0 left-0 bg-white border-r p-4">
      <h2 class="font-bold mb-6">{{ portalTitle }}</h2>
      <nav class="space-y-2">
        <template v-for="link in sidebarLinks" :key="link.name">
          <Link :href="link.href" class="block">{{ link.name }}</Link>
        </template>
      </nav>
    </aside>

    <!-- Main content -->
    <div class="ml-64">
      <header class="bg-white border-b p-4 flex justify-between">
        <div>{{ page.props.auth.user.name  }}</div>
        <div>
          <Link href="/logout">Logout</Link>
        </div>
      </header>

      <main class="p-6">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { usePage, Link } from '@inertiajs/vue3';
const page = usePage();

// Determine role
const userRole = page.props.auth.user.roles?.[0]?.name || 'Guest';

// Sidebar configuration for each role
const sidebars = {
  Staff: [
    { name: 'Dashboard', href: '/staff/dashboard' },
    { name: 'Orders Queue', href: '/staff/orders' },
    { name: 'Quick Action', href: '/staff/quick-action' },
    { name: 'My Profile', href: '/staff/profile' },
  ],
  Manager: [
    { name: 'Dashboard', href: '/admin/dashboard' },
    { name: 'Bookings', href: '/admin/bookings' },
    { name: 'Rooms', href: '/admin/rooms' },
    { name: 'Staff Management', href: '/admin/staff' },
    { name: 'Inventory', href: '/admin/inventory' },
    { name: 'Reports', href: '/admin/reports' },
    { name: 'My Profile', href: '/admin/profile' },
    { name: 'Settings', href: '/admin/settings' },
  ],
  MD: [
    { name: 'Dashboard', href: '/admin/dashboard' },
    { name: 'All Bookings', href: '/admin/bookings' },
    { name: 'All Rooms', href: '/admin/rooms' },
    { name: 'Staff Management', href: '/admin/staff' },
    { name: 'Inventory', href: '/admin/inventory' },
    { name: 'Maintenance', href: '/admin/maintenance' },
    { name: 'Reports', href: '/admin/reports' },
    { name: 'Settings', href: '/admin/settings' },
    { name: 'My Profile', href: '/admin/profile' },
  ],
};

// Compute the sidebar and title dynamically
const sidebarLinks = sidebars[userRole] || [];
const portalTitle = userRole.charAt(0).toUpperCase() + userRole.slice(1) + ' Portal';
</script>
