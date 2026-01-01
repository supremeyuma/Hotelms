<!-- resources/js/Components/Icon.vue -->
<template>
  <component
    :is="iconComponent"
    class="inline-block"
    v-bind="$attrs"
  />
</template>

<script setup>
import {
  Bed,
  BedDouble,
  UserPlus,
  UserMinus,
  AlertCircle,
  ClipboardList,
  CreditCard,

  Home,
  Calendar,
  Bell,
  Shirt,
  Clipboard,
  Tag,
  BarChart,
  Users,
} from 'lucide-vue-next'

const props = defineProps({
  name: {
    type: String,
    required: true,
  },
})

const icons = {
  // Existing / shared
  bed: BedDouble,
  'bed-outline': Bed,
  'user-plus': UserPlus,
  'user-minus': UserMinus,
  alert: AlertCircle,
  requests: ClipboardList,
  billing: CreditCard,

  // Sidebar / navigation
  home: Home,
  calendar: Calendar,
  bell: Bell,
  shirt: Shirt,
  clipboard: Clipboard,
  tag: Tag,
  chart: BarChart,
  users: Users,
}

const iconComponent = icons[props.name] ?? AlertCircle
</script>
