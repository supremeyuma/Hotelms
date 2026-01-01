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
     | FRONT DESK
     |=========================*/
    case 'frontdesk':
      return [
        { label: 'Dashboard', route: 'frontdesk.dashboard', icon: 'home' },
        { label: 'Bookings', route: 'frontdesk.bookings.index', icon: 'calendar' },
        { label: 'Rooms', route: 'frontdesk.rooms.index', icon: 'bed' },
        { label: 'Guest Requests', route: 'frontdesk.guest-requests.index', icon: 'bell' },
        { label: 'Laundry Requests', route: 'frontdesk.laundry.index', icon: 'shirt' },
      ]

    /* =========================
     | KITCHEN STAFF
     |=========================*/
    case 'kitchen':
      return [
        { label: 'Kitchen Dashboard', route: 'staff.kitchen.dashboard', icon: 'home' },
        { label: 'Orders Queue', route: 'staff.kitchen.orders.index', icon: 'clipboard' },
        {
          label: 'Menu Management',
          href: '/staff/menu?area=kitchen',
          icon: 'menu'
        },
      ]

    /* =========================
     | BAR STAFF
     |=========================*/
    case 'bar':
      return [
        { label: 'Bar Dashboard', route: 'staff.bar.dashboard', icon: 'home' },
        { label: 'Orders Queue', route: 'staff.bar.orders.index', icon: 'clipboard' },
        {
          label: 'Menu Management',
          href: '/staff/menu?area=bar',
          icon: 'menu'
        },
      ]

    /* =========================
     | MANAGER / MD
     |=========================*/
    case 'manager':
    case 'md':
      return [
        { label: 'Dashboard', route: 'staff.dashboard', icon: 'home' },
        { label: 'Kitchen Orders', route: 'staff.kitchen.orders.index', icon: 'clipboard' },
        { label: 'Bar Orders', route: 'staff.bar.orders.index', icon: 'clipboard' },
        {
          label: 'Kitchen Menu',
          href: '/staff/menu?area=kitchen',
          icon: 'menu'
        },
        {
          label: 'Bar Menu',
          href: '/staff/menu?area=bar',
          icon: 'menu'
        },
        { label: 'Reports', route: 'frontdesk.reports.bookings', icon: 'chart' },
        { label: 'Staff', route: 'staff.profile.show', icon: 'users' },
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

      <!-- SIGN OUT -->
      <button
        @click="logout"
        class="w-full text-left text-sm text-red-600 hover:text-red-800"
      >
        Sign Out
      </button>
    </div>
  </aside>
</template>
