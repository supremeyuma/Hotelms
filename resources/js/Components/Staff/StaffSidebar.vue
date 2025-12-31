<script setup>
import { computed } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import StaffSidebarItem from './StaffSidebarItem.vue'

const page = usePage()
const user = page.props.auth.user

const role = computed(() => {
  if (Array.isArray(user.roles) && user.roles.length) {
    return user.roles[0].name ?? user.roles[0]
  }

  return user.role ?? 'staff'
})


const nav = computed(() => {
  switch (role.value) {
    case 'frontdesk':
      return [
        { label: 'Dashboard', route: 'frontdesk.dashboard', icon: 'home' },
        { label: 'Bookings', route: 'frontdesk.bookings.index', icon: 'calendar' },
        { label: 'Rooms', route: 'frontdesk.rooms.index', icon: 'bed' },
        { label: 'Guest Requests', route: 'frontdesk.guest-requests.index', icon: 'bell' },
        { label: 'Laundry Requests', route: 'frontdesk.laundry.index', icon: 'shirt' },
      ]

    case 'laundry':
      return [
        { label: 'Dashboard', route: 'staff.laundry.dashboard', icon: 'home' },
        { label: 'Orders', route: 'staff.laundry.dashboard', icon: 'clipboard' },
        { label: 'Laundry Items', route: 'staff.laundry-items.index', icon: 'tag' },
      ]

    case 'manager':
    case 'md':
      return [
        { label: 'Dashboard', route: 'staff.dashboard', icon: 'home' },
        { label: 'Reports', route: 'frontdesk.reports.bookings', icon: 'chart' },
        { label: 'Staff', route: 'staff.profile.show', icon: 'users' },
      ]

    default:
      return [
        { label: 'Dashboard', route: 'staff.dashboard', icon: 'home' },
      ]
  }
})

console.log(nav.value)
</script>

<template>
  <aside class="w-64 bg-white border-r flex flex-col">
    <!-- Brand -->
    <div class="h-16 flex items-center px-6 font-bold text-lg border-b">
      Moorelife Resort
    </div>

    <!-- Nav -->
    <nav class="flex-1 p-4 space-y-2">
      <StaffSidebarItem
        v-for="item in nav"
        :key="item.route"
        :item="item"
      />
    </nav>

    <!-- Profile (BOTTOM PINNED) -->
    <div class="border-t p-4">
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
    </div>
  </aside>
</template>
