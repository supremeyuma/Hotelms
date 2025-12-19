<!-- File: resources/js/Pages/Admin/Reports/Inventory.vue -->
<script setup>
defineProps({ rows:Object })
</script>

<template>
  <table class="w-full">
    <tr v-for="i in rows.data" :key="i.id">
      <td>{{ i.inventory_item.name }}</td>
      <td>{{ i.change }}</td>
    </tr>
  </table>
</template>
