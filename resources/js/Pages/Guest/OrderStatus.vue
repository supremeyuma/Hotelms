<script setup>
import { onMounted } from 'vue'

const props = defineProps({ order: Object })

onMounted(() => {
  window.Echo.channel(`orders.${props.order.service_area}`)
    .listen('.order.status.updated', e => {
      if (e.order.id === props.order.id) {
        props.order.status = e.order.status
      }
    })
})
</script>

<template>
  <div class="p-4">
    <h2>Order #{{ order.id }}</h2>
    <p>Status: {{ order.status }}</p>
  </div>
</template>
