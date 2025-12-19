<!-- File: resources/js/Pages/Admin/Reports/Revenue.vue -->
<script setup>
defineProps({ rows:Object, filters:Object })
</script>

<template>
  <table class="w-full">
    <tr v-for="r in rows.data" :key="r.id">
      <td>{{ r.id }}</td>
      <td>{{ r.total_amount }}</td>
    </tr>
  </table>
</template>
