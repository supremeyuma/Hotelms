<template>
<div class="bg-white p-4 rounded shadow text-center">
<div class="text-sm text-gray-500">{{ label }}</div>
<div class="text-xl font-bold">{{ value }}</div>
</div>
</template>


<script setup>
defineProps({ label:String, value:[String,Number] })
</script>