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
} from 'lucide-vue-next';

const props = defineProps({
  name: {
    type: String,
    required: true,
  },
});

const icons = {
  bed: BedDouble,
  'bed-outline': Bed,
  'user-plus': UserPlus,
  'user-minus': UserMinus,
  alert: AlertCircle,
  requests: ClipboardList,
  billing: CreditCard,
};

const iconComponent = icons[props.name] ?? AlertCircle;
</script>
