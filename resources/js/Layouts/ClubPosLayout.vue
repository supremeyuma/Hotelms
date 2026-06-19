<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const user = computed(() => page.props.auth?.user)
</script>

<template>
  <div class="h-screen w-screen flex flex-col bg-slate-900 text-white overflow-hidden select-none touch-none">
    <slot />
  </div>
</template>
