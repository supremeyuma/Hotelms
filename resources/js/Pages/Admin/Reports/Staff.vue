<!-- File: resources/js/Pages/Admin/Reports/Staff.vue -->
<script setup>
defineProps({ rows:Object })
</script>

<template>
  <table class="w-full">
    <tr v-for="s in rows.data" :key="s.id">
      <td>{{ s.name }}</td>
      <td>{{ s.orders_count }}</td>
    </tr>
  </table>
</template>
