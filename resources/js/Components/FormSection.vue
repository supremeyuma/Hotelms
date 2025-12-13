<template>
  <section class="bg-white shadow rounded-lg p-6 mb-6">
    <header v-if="$slots.header" class="mb-4">
      <slot name="header"></slot>
    </header>
    <div>
      <slot></slot>
    </div>
  </section>
</template>

<script setup>
</script>

<style scoped>
</style>
