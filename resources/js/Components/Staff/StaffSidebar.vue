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
  Settings,
  FileText,
  Calendar as CalendarIcon,
  TrendingUp,
  Lock,
  AlertTriangle
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
  'file-text': FileText,
  'calendar-icon': CalendarIcon,
  'trending-up': TrendingUp,
  lock: Lock,
  'alert-triangle': AlertTriangle,
}

const page = usePage()
const user = page.props.auth.user

function normalizeRole(value) {
  const normalized = String(value ?? '')
    .trim()
    .toLowerCase()
    .replace(/[\s_-]+/g, '')

  const roleAliases = {
    ceo: 'md',
    inventorymanager: 'inventory',
    cleaner: 'clean',
    cleaning: 'clean',
    housekeeping: 'clean',
    frontoffice: 'frontdesk',
    reception: 'frontdesk',
    receptionist: 'frontdesk',
    frontdeskofficer: 'frontdesk',
    laundrystaff: 'laundry',
    maintenancestaff: 'maintenance',
  }

  return roleAliases[normalized] ?? normalized
}

const role = computed(() => {
  if (Array.isArray(user.roles) && user.roles.length) {
    return normalizeRole(user.roles[0].name ?? user.roles[0])
  }
  return normalizeRole(user.role ?? 'staff')
})

function logout() {
  router.post(route('logout'))
}

/* ---------------- NAV ---------------- */
const nav = computed(() => {
  const items = (() => {
    switch (role.value) {
      case 'manager':
        return [
          { label: 'Operations Dashboard', route: 'admin.dashboard', icon: 'home', activeRoute: 'admin.dashboard' },
          { label: 'Feedback Queue', route: 'admin.feedback.index', icon: 'bell', activeRoute: 'admin.feedback.*' },
          { label: 'Submit Feedback', route: 'staff.feedback.create', icon: 'edit', activeRoute: 'staff.feedback.*' },
          { label: 'Staff Management', route: 'admin.staff.index', icon: 'users', activeRoute: 'admin.staff.*' },
          { label: 'My Threads', route: 'staff.threads.index', icon: 'clipboard', activeRoute: 'staff.threads.*' },
          { label: 'Bookings', route: 'admin.bookings.index', icon: 'calendar', activeRoute: 'admin.bookings.*' },
          { label: 'Discount Codes', route: 'admin.discount-codes.index', icon: 'tag', activeRoute: 'admin.discount-codes.*' },
          { label: 'Rooms', route: 'admin.rooms.index', icon: 'bed', activeRoute: 'admin.rooms.*' },
          { label: 'Room Types', route: 'admin.room-types.index', icon: 'layers', activeRoute: 'admin.room-types.*' },
          { label: 'Inventory', route: 'admin.inventory.index', icon: 'boxes', activeRoute: 'admin.inventory.*' },
          { label: 'Inventory Locations', route: 'admin.inventory-locations.index', icon: 'map-pin', activeRoute: 'admin.inventory-locations.*' },
          { label: 'Cleaning Templates', route: 'admin.cleaning-templates.index', icon: 'brush-cleaning', activeRoute: 'admin.cleaning-templates.*' },
          { label: 'Menu Recipes', route: 'admin.menu-recipes.index', icon: 'utensils', activeRoute: 'admin.menu-recipes.*' },
          { label: 'Maintenance', route: 'admin.maintenance.index', icon: 'wrench', activeRoute: 'admin.maintenance.*' },
          { label: 'Operations Reports', route: 'admin.reports.dashboard', icon: 'bar-chart', activeRoute: 'admin.reports.*' },
          { label: 'Staff Reports', route: 'admin.reports.staff', icon: 'user-check', activeRoute: 'admin.reports.staff*' },
          { label: 'Occupancy Reports', route: 'admin.reports.occupancy', icon: 'hotel', activeRoute: 'admin.reports.occupancy*' },
          { label: 'Inventory Reports', route: 'admin.reports.inventory', icon: 'archive', activeRoute: 'admin.reports.inventory*' },
          { label: 'Website Content', route: 'admin.website.content', icon: 'edit', activeRoute: 'admin.website.*' },
          { label: 'Gallery', route: 'admin.website.gallery', icon: 'image', activeRoute: 'admin.website.gallery*' },
          { label: 'Events', route: 'admin.events.index', icon: 'calendar', activeRoute: 'admin.events.*' },
          { label: 'Settings', route: 'admin.settings.index', icon: 'settings', activeRoute: 'admin.settings.*' },
        ]
      case 'md':
        return [
          { label: 'Executive Dashboard', route: 'admin.dashboard', icon: 'home', activeRoute: 'admin.dashboard' },
          { label: 'Feedback Queue', route: 'admin.feedback.index', icon: 'bell', activeRoute: 'admin.feedback.*' },
          { label: 'Submit Feedback', route: 'staff.feedback.create', icon: 'edit', activeRoute: 'staff.feedback.*' },
          { label: 'My Threads', route: 'staff.threads.index', icon: 'clipboard', activeRoute: 'staff.threads.*' },
          { label: 'Front Desk', route: 'frontdesk.dashboard', icon: 'bell', activeRoute: 'frontdesk.*' },
          { label: 'Housekeeping', route: 'clean.dashboard', icon: 'brush-cleaning', activeRoute: 'clean.*' },
          { label: 'Laundry', route: 'staff.laundry.dashboard', icon: 'shirt', activeRoute: 'staff.laundry.*' },
          { label: 'Kitchen', route: 'staff.kitchen.dashboard', icon: 'utensils', activeRoute: 'staff.kitchen.*' },
          { label: 'Bar', route: 'staff.bar.dashboard', icon: 'clipboard', activeRoute: 'staff.bar.*' },
          { label: 'Bookings', route: 'admin.bookings.index', icon: 'calendar', activeRoute: 'admin.bookings.*' },
          { label: 'Discount Codes', route: 'admin.discount-codes.index', icon: 'tag', activeRoute: 'admin.discount-codes.*' },
          { label: 'Rooms', route: 'admin.rooms.index', icon: 'bed', activeRoute: 'admin.rooms.*' },
          { label: 'Room Types', route: 'admin.room-types.index', icon: 'layers', activeRoute: 'admin.room-types.*' },
          { label: 'Staff Management', route: 'admin.staff.index', icon: 'users', activeRoute: 'admin.staff.*' },
          { label: 'HR Management', route: 'hr.staff.index', icon: 'user-check', activeRoute: 'hr.staff.*' },
          { label: 'Finance Dashboard', route: 'finance.dashboard', icon: 'dollar-sign', activeRoute: 'finance.dashboard' },
          { label: 'Revenue Reports', route: 'finance.reports.revenue', icon: 'trending-up', activeRoute: 'finance.reports.*' },
          { label: 'Profit & Loss', route: 'finance.reports.profit-loss', icon: 'file-text', activeRoute: 'finance.reports.profit-loss*' },
          { label: 'Balance Sheet', route: 'finance.reports.balance-sheet', icon: 'archive', activeRoute: 'finance.reports.balance-sheet*' },
          { label: 'Cleaning Templates', route: 'admin.cleaning-templates.index', icon: 'brush-cleaning', activeRoute: 'admin.cleaning-templates.*' },
          { label: 'Menu Recipes', route: 'admin.menu-recipes.index', icon: 'utensils', activeRoute: 'admin.menu-recipes.*' },
          { label: 'Maintenance', route: 'admin.maintenance.index', icon: 'wrench', activeRoute: 'admin.maintenance.*' },
          { label: 'Reports Dashboard', route: 'admin.reports.dashboard', icon: 'bar-chart', activeRoute: 'admin.reports.*' },
          { label: 'Staff Reports', route: 'admin.reports.staff', icon: 'user-check', activeRoute: 'admin.reports.staff*' },
          { label: 'Occupancy Reports', route: 'admin.reports.occupancy', icon: 'hotel', activeRoute: 'admin.reports.occupancy*' },
          { label: 'Inventory Reports', route: 'admin.reports.inventory', icon: 'archive', activeRoute: 'admin.reports.inventory*' },
          { label: 'Website Content', route: 'admin.website.content', icon: 'edit', activeRoute: 'admin.website.*' },
          { label: 'Gallery', route: 'admin.website.gallery', icon: 'image', activeRoute: 'admin.website.gallery*' },
          { label: 'Events', route: 'admin.events.index', icon: 'calendar', activeRoute: 'admin.events.*' },
          { label: 'Settings', route: 'admin.settings.index', icon: 'settings', activeRoute: 'admin.settings.*' },
        ]
      case 'hr':
        return [
          { label: 'HR Dashboard', route: 'hr.staff.index', icon: 'home', activeRoute: 'hr.staff.*' },
          { label: 'Submit Feedback', route: 'staff.feedback.create', icon: 'edit', activeRoute: 'staff.feedback.*' },
          { label: 'Staff Directory', route: 'hr.staff.index', icon: 'users', activeRoute: 'hr.staff.index' },
          { label: 'My Threads', route: 'staff.threads.index', icon: 'clipboard', activeRoute: 'staff.threads.*' },
          { label: 'Onboard Staff', route: 'hr.staff.create', icon: 'user-check', activeRoute: 'hr.staff.create' },
          { label: 'Staff Reports', route: 'admin.reports.staff', icon: 'bar-chart', activeRoute: 'admin.reports.staff*' },
        ]
      case 'inventory':
        return [
          { label: 'Admin Dashboard', route: 'admin.dashboard', icon: 'home', activeRoute: 'admin.dashboard' },
          { label: 'Submit Feedback', route: 'staff.feedback.create', icon: 'edit', activeRoute: 'staff.feedback.*' },
          { label: 'My Threads', route: 'staff.threads.index', icon: 'clipboard', activeRoute: 'staff.threads.*' },
          { label: 'Inventory', route: 'admin.inventory.index', icon: 'boxes', activeRoute: 'admin.inventory.*' },
          { label: 'Inventory Locations', route: 'admin.inventory-locations.index', icon: 'map-pin', activeRoute: 'admin.inventory-locations.*' },
          { label: 'Inventory Reports', route: 'admin.reports.inventory', icon: 'archive', activeRoute: 'admin.reports.inventory*' },
        ]
      case 'accountant':
        return [
          { label: 'Finance Dashboard', route: 'finance.dashboard', icon: 'home', activeRoute: 'finance.dashboard' },
          { label: 'Submit Feedback', route: 'staff.feedback.create', icon: 'edit', activeRoute: 'staff.feedback.*' },
          { label: 'My Threads', route: 'staff.threads.index', icon: 'clipboard', activeRoute: 'staff.threads.*' },
          { label: 'Revenue Reports', route: 'finance.reports.revenue', icon: 'dollar-sign', activeRoute: 'finance.reports.revenue*' },
          { label: 'Profit & Loss', route: 'finance.reports.profit-loss', icon: 'trending-up', activeRoute: 'finance.reports.profit-loss*' },
          { label: 'Balance Sheet', route: 'finance.reports.balance-sheet', icon: 'file-text', activeRoute: 'finance.reports.balance-sheet*' },
          { label: 'Daily Revenue', route: 'finance.reports.daily-revenue', icon: 'bar-chart', activeRoute: 'finance.reports.daily-revenue*' },
          { label: 'Outstanding Balances', route: 'finance.outstanding-balances.index', icon: 'alert-triangle', activeRoute: 'finance.outstanding-balances*' },
          { label: 'Accounting Periods', route: 'finance.accounting-periods.index', icon: 'lock', activeRoute: 'finance.accounting-periods.*' },
          { label: 'Audit Logs', route: 'finance.audit.index', icon: 'shield', activeRoute: 'finance.audit.*' },
        ]
      case 'frontdesk':
        return [
          { label: 'Front Desk Dashboard', route: 'frontdesk.dashboard', icon: 'home', activeRoute: 'frontdesk.dashboard' },
          { label: 'Submit Feedback', route: 'staff.feedback.create', icon: 'edit', activeRoute: 'staff.feedback.*' },
          { label: 'My Threads', route: 'staff.threads.index', icon: 'clipboard', activeRoute: 'staff.threads.*' },
          { label: 'Bookings', route: 'frontdesk.bookings.index', icon: 'calendar', activeRoute: 'frontdesk.bookings.*' },
          { label: 'Rooms', route: 'frontdesk.rooms.index', icon: 'bed', activeRoute: 'frontdesk.rooms.*' },
          { label: 'Guest Requests', route: 'frontdesk.guest-requests.index', icon: 'bell', activeRoute: 'frontdesk.guest-requests*' },
          { label: 'Laundry Requests', route: 'frontdesk.laundry.index', icon: 'shirt', activeRoute: 'frontdesk.laundry*' },
          { label: 'Event Check-In', route: 'staff.events.check-in.index', icon: 'user-check', activeRoute: 'staff.events.check-in.*' },
        ]
      case 'laundry':
        return [
          { label: 'Laundry Dashboard', route: 'staff.laundry.dashboard', icon: 'shirt', activeRoute: 'staff.laundry.*' },
          { label: 'Submit Feedback', route: 'staff.feedback.create', icon: 'edit', activeRoute: 'staff.feedback.*' },
          { label: 'My Threads', route: 'staff.threads.index', icon: 'clipboard', activeRoute: 'staff.threads.*' },
          { label: 'Laundry Items', route: 'staff.laundry-items.index', icon: 'tag', activeRoute: 'staff.laundry-items.*' },
        ]
      case 'maintenance':
        return [
          { label: 'Maintenance Dashboard', route: 'staff.maintenance.index', icon: 'wrench', activeRoute: 'staff.maintenance.*' },
          { label: 'Submit Feedback', route: 'staff.feedback.create', icon: 'edit', activeRoute: 'staff.feedback.*' },
          { label: 'My Threads', route: 'staff.threads.index', icon: 'clipboard', activeRoute: 'staff.threads.*' },
        ]
      case 'clean':
        return [
          { label: 'Cleaning Dashboard', route: 'clean.dashboard', icon: 'brush-cleaning', activeRoute: 'clean.*' },
          { label: 'Submit Feedback', route: 'staff.feedback.create', icon: 'edit', activeRoute: 'staff.feedback.*' },
          { label: 'My Threads', route: 'staff.threads.index', icon: 'clipboard', activeRoute: 'staff.threads.*' },
        ]
      case 'kitchen':
        return [
          { label: 'Kitchen Dashboard', route: 'staff.kitchen.dashboard', icon: 'utensils', activeRoute: 'staff.kitchen.dashboard' },
          { label: 'Submit Feedback', route: 'staff.feedback.create', icon: 'edit', activeRoute: 'staff.feedback.*' },
          { label: 'My Threads', route: 'staff.threads.index', icon: 'clipboard', activeRoute: 'staff.threads.*' },
          { label: 'Kitchen Orders', route: 'staff.kitchen.orders.index', icon: 'clipboard', activeRoute: 'staff.kitchen.orders.*' },
          { label: 'Order History', route: 'staff.kitchen.orders.history', icon: 'clock', activeRoute: 'staff.kitchen.orders.history' },
          { label: 'Kitchen Menu', route: 'staff.menu.kitchen', icon: 'menu', activeRoute: 'staff.menu.kitchen' },
        ]
      case 'bar':
        return [
          { label: 'Bar Dashboard', route: 'staff.bar.dashboard', icon: 'home', activeRoute: 'staff.bar.dashboard' },
          { label: 'Submit Feedback', route: 'staff.feedback.create', icon: 'edit', activeRoute: 'staff.feedback.*' },
          { label: 'My Threads', route: 'staff.threads.index', icon: 'clipboard', activeRoute: 'staff.threads.*' },
          { label: 'Bar Orders', route: 'staff.bar.orders.index', icon: 'clipboard', activeRoute: 'staff.bar.orders.*' },
          { label: 'Order History', route: 'staff.bar.orders.history', icon: 'clock', activeRoute: 'staff.bar.orders.history' },
          { label: 'Bar Menu', route: 'staff.menu.bar', icon: 'menu', activeRoute: 'staff.menu.bar' },
        ]
      default:
        return [
          { label: 'Dashboard', route: 'staff.dashboard', icon: 'home', activeRoute: 'staff.dashboard' },
          { label: 'Submit Feedback', route: 'staff.feedback.create', icon: 'edit', activeRoute: 'staff.feedback.*' },
          { label: 'My Threads', route: 'staff.threads.index', icon: 'clipboard', activeRoute: 'staff.threads.*' },
          { label: 'Orders Queue', route: 'staff.orders.queue', icon: 'clipboard', activeRoute: 'staff.orders.*' },
          { label: 'Quick Action', route: 'staff.quick-action.index', icon: 'clock', activeRoute: 'staff.quick-action.*' },
          { label: 'Event Check-In', route: 'staff.events.check-in.index', icon: 'user-check', activeRoute: 'staff.events.check-in.*' },
        ]
    }
  })()

  return items.map(item => ({
    ...item,
    iconComponent: ICONS[item.icon],
    active: route().current(item.activeRoute ?? item.route)
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
