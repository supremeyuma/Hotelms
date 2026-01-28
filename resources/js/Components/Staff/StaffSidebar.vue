<script setup>
import { computed } from 'vue'
import { usePage, Link, router } from '@inertiajs/vue3'
import StaffSidebarItem from './StaffSidebarItem.vue'

/* ---------------- ICONS ---------------- */
import {
  House,
  Calendar,
  BedDouble,
  Bell,
  Shirt,
  ClipboardList,
  Clock,
  Brush,
  Tag,
  Menu,
  Layers,
  Users,
  Boxes,
  MapPin,
  SprayCan,
  Utensils,
  Wrench,
  BarChart3,
  UserCheck,
  DollarSign,
  Hotel,
  Archive,
  ShieldCheck,
  FileEdit,
  Image as ImageIcon, // Aliased to avoid conflict with browser Image object
  Settings
} from 'lucide-vue-next'

// The keys here MUST match the 'icon' strings in the nav computed property below
const ICONS = {
  home: House,
  calendar: Calendar,
  bed: BedDouble,
  bell: Bell,
  shirt: Shirt,
  clipboard: ClipboardList,
  clock: Clock,
  'brush-cleaning': Brush,
  tag: Tag,
  menu: Menu,
  layers: Layers,
  users: Users,
  boxes: Boxes,
  'map-pin': MapPin,
  'spray-can': SprayCan,
  utensils: Utensils,
  wrench: Wrench,
  'bar-chart': BarChart3,
  'user-check': UserCheck,
  'dollar-sign': DollarSign,
  hotel: Hotel,
  archive: Archive,
  shield: ShieldCheck,
  edit: FileEdit,
  image: ImageIcon,
  settings: Settings,
}

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

/* ---------------- NAV ---------------- */
const nav = computed(() => {
  const items = (() => {
    switch (role.value) {
      case 'manager':
      case 'md':
        return [
          { label: 'Admin Dashboard', route: 'admin.dashboard', icon: 'home' },
          { label: 'Bookings', route: 'admin.bookings.index', icon: 'calendar' },
          { label: 'Rooms', route: 'admin.rooms.index', icon: 'bed' },
          { label: 'Room Types', route: 'admin.room-types.index', icon: 'layers' },
          { label: 'Staff Management', route: 'admin.staff.index', icon: 'users' },
          { label: 'Inventory', route: 'admin.inventory.index', icon: 'boxes' },
          { label: 'Inventory Locations', route: 'admin.inventory-locations.index', icon: 'map-pin' },
          { label: 'Cleaning Templates', route: 'admin.cleaning-templates.index', icon: 'brush-cleaning' },
          { label: 'Menu Recipes', route: 'admin.menu-recipes.index', icon: 'utensils' },
          { label: 'Maintenance', route: 'admin.maintenance.index', icon: 'wrench' },
          { label: 'Reports Dashboard', route: 'admin.reports.dashboard', icon: 'bar-chart' },
          { label: 'Staff Reports', route: 'admin.reports.staff', icon: 'user-check' },
          { label: 'Revenue Reports', route: 'admin.reports.revenue', icon: 'dollar-sign' },
          { label: 'Occupancy Reports', route: 'admin.reports.occupancy', icon: 'hotel' },
          { label: 'Inventory Reports', route: 'admin.reports.inventory', icon: 'archive' },
          { label: 'Audit Logs', route: 'admin.audit.index', icon: 'shield' },
          { label: 'Website Content', route: 'admin.website.content', icon: 'edit' },
          { label: 'Gallery', route: 'admin.website.gallery', icon: 'image' },
          { label: 'Events', route: 'admin.events.index', icon: 'calendar' },
          { label: 'Settings', route: 'admin.settings.index', icon: 'settings' },
        ]
      default:
        return [{ label: 'Dashboard', route: 'staff.dashboard', icon: 'home' }]
    }
  })()

  return items.map(item => ({
    ...item,
    iconComponent: ICONS[item.icon],
    active: route().current(item.route)
  }))
})
</script>

<template>
  <aside class="w-64 bg-white border-r flex flex-col h-screen sticky top-0">
    <div class="h-16 flex items-center px-6 font-bold text-lg border-b shrink-0">
      Moorelife Resort
    </div>

    <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
      <StaffSidebarItem
        v-for="item in nav"
        :key="item.label"
        :item="item"
      />
    </nav>

    <div class="border-t p-4 space-y-3 shrink-0">
      <Link
        :href="route('staff.profile.show')"
        class="flex items-center gap-3 text-sm text-gray-700 hover:text-black"
      >
        <div class="w-9 h-9 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold">
          {{ user.name?.charAt(0) || 'U' }}
        </div>

        <div class="leading-tight overflow-hidden">
          <p class="font-medium truncate w-32">{{ user.name }}</p>
          <p class="text-xs text-gray-500">Profile</p>
        </div>
      </Link>

      <button
        @click="logout"
        class="w-full text-left text-sm text-red-600 hover:text-red-800 transition-colors"
      >
        Sign Out
      </button>
    </div>
  </aside>
</template>