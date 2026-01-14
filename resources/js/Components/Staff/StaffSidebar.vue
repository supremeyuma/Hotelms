<script setup>
import { computed } from 'vue'
import { usePage, Link, router } from '@inertiajs/vue3'
import StaffSidebarItem from './StaffSidebarItem.vue'

const page = usePage()
const user = page.props.auth.user

const role = computed(() => {
  if (Array.isArray(user.roles) && user.roles.length) {
    return user.roles[0].name ?? user.roles[0]
  }
  return user.role ?? 'staff'
})

function logout() {
  router.post(route('logout'))
}

const nav = computed(() => {
  switch (role.value) {

    /* =========================
     | FRONT DESK (CONTROL CENTER)
     |=========================*/
    case 'frontdesk':
      return [
        { label: 'Dashboard', route: 'frontdesk.dashboard', icon: 'home' },

        // Core operations
        { label: 'Bookings', route: 'frontdesk.bookings.index', icon: 'calendar' },
        { label: 'Rooms', route: 'frontdesk.rooms.index', icon: 'bed' },
        { label: 'Guest Requests', route: 'frontdesk.guest-requests.index', icon: 'bell' },
        { label: 'Laundry Requests', route: 'frontdesk.laundry.index', icon: 'shirt' },

        // Orders visibility (ALL DEPARTMENTS)
        { label: 'Kitchen Orders', route: 'staff.kitchen.orders.index', icon: 'clipboard' },
        { label: 'Kitchen History', route: 'staff.kitchen.orders.history', icon: 'clock' },

        { label: 'Bar Orders', route: 'staff.bar.orders.index', icon: 'clipboard' },
        { label: 'Bar History', route: 'staff.bar.orders.history', icon: 'clock' },

        { label: 'Laundry Dashboard', route: 'staff.laundry.dashboard', icon: 'shirt' },

        // Billing & reports
        
      ]

    /* =========================
     | CLEANING STAFF
     |=========================*/
    case 'clean':
      return [
        { label: 'Dashboard', route: 'clean.dashboard', icon: 'broom' },
      ]

    /* =========================
     | LAUNDRY STAFF
     |=========================*/
    case 'laundry':
      return [
        { label: 'Dashboard', route: 'staff.laundry.dashboard', icon: 'shirt' },
        { label: 'Laundry Items', route: 'staff.laundry-items.index', icon: 'tag' },
      ]

    /* =========================
     | KITCHEN STAFF
     |=========================*/
    case 'kitchen':
      return [
        { label: 'Dashboard', route: 'staff.kitchen.dashboard', icon: 'home' },
        { label: 'Orders Queue', route: 'staff.kitchen.orders.index', icon: 'clipboard' },
        { label: 'Orders History', route: 'staff.kitchen.orders.history', icon: 'clock' },
        { label: 'Kitchen Menu', route: 'staff.menu.kitchen', icon: 'menu' },
      ]

    /* =========================
     | BAR STAFF
     |=========================*/
    case 'bar':
      return [
        { label: 'Dashboard', route: 'staff.bar.dashboard', icon: 'home' },
        { label: 'Orders Queue', route: 'staff.bar.orders.index', icon: 'clipboard' },
        { label: 'Orders History', route: 'staff.bar.orders.history', icon: 'clock' },
        { label: 'Bar Menu', route: 'staff.menu.bar', icon: 'menu' },
      ]

    /* =========================
     | MANAGER / MD (FULL VISIBILITY)
     |=========================*/
    case 'manager':
    case 'md':
      return [
        { label: 'Admin Dashboard', route: 'admin.dashboard', icon: 'home' },

        // Operations
        { label: 'Bookings', route: 'admin.bookings.index', icon: 'calendar' },
        { label: 'Rooms', route: 'admin.rooms.index', icon: 'bed' },

        // Orders (ALL)
        { label: 'All Orders', route: 'admin.orders.index', icon: 'clipboard' },

        // Departments
        { label: 'Kitchen Orders', route: 'staff.kitchen.orders.index', icon: 'clipboard' },
        { label: 'Bar Orders', route: 'staff.bar.orders.index', icon: 'clipboard' },
        { label: 'Laundry', route: 'staff.laundry.dashboard', icon: 'shirt' },

        // Staff & inventory
        { label: 'Staff Management', route: 'admin.staff.index', icon: 'users' },
        { label: 'Inventory', route: 'admin.inventory.index', icon: 'boxes' },

        // Reports & audit
        { label: 'Reports', route: 'admin.reports.dashboard', icon: 'chart' },
        { label: 'Audit Logs', route: 'admin.audit.index', icon: 'shield' },

        // Website & events
        { label: 'Website Content', route: 'admin.website.content', icon: 'edit' },
        { label: 'Gallery', route: 'admin.website.gallery', icon: 'image' },
        { label: 'Events', route: 'admin.events.index', icon: 'calendar' },

        // Settings
        { label: 'Settings', route: 'admin.settings.index', icon: 'settings' },
      ]

    /* =========================
     | DEFAULT STAFF
     |=========================*/
    default:
      return [
        { label: 'Dashboard', route: 'staff.dashboard', icon: 'home' },
      ]
  }
})
</script>

<template>
  <aside class="w-64 bg-white border-r flex flex-col">
    <div class="h-16 flex items-center px-6 font-bold text-lg border-b">
      Moorelife Resort
    </div>

    <nav class="flex-1 p-4 space-y-2">
      <StaffSidebarItem
        v-for="item in nav"
        :key="item.label"
        :item="item"
      />
    </nav>

    <!-- USER FOOTER -->
    <div class="border-t p-4 space-y-3">
      <Link
        :href="route('staff.profile.show')"
        class="flex items-center gap-3 text-sm text-gray-700 hover:text-black"
      >
        <div class="w-9 h-9 rounded-full bg-gray-300 flex items-center justify-center">
          {{ user.name.charAt(0) }}
        </div>

        <div class="leading-tight">
          <p class="font-medium">{{ user.name }}</p>
          <p class="text-xs text-gray-500">Profile</p>
        </div>
      </Link>

      <button
        @click="logout"
        class="w-full text-left text-sm text-red-600 hover:text-red-800"
      >
        Sign Out
      </button>
    </div>
  </aside>
</template>
