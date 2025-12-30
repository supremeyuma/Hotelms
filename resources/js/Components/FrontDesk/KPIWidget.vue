<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import Icon from '@/Components/Icon.vue'

const props = defineProps({
  title: { type: String, required: true },
  value: { type: [Number, String], required: true },
  href: { type: String, default: null },
  icon: { type: String, default: null },
})

//console.log(props)
const Wrapper = computed(() => (props.href ? Link : 'div'))
</script>

<template>
  <component
    :is="Wrapper"
    :href="href"
    class="block p-4 rounded-lg shadow hover:shadow-lg transition bg-white"
  >
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm text-gray-500">{{ title }}</p>
        <p class="text-2xl font-bold">{{ value }}</p>
      </div>

      <Icon
        v-if="icon"
        :name="icon"
        class="w-8 h-8 text-gray-400"
      />
    </div>
  </component>
</template>
