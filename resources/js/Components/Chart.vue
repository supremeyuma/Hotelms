<template>
<div class="bg-white p-4 rounded shadow">
<h3 class="font-semibold mb-2">{{ title }}</h3>
<canvas ref="el"></canvas>
</div>
</template>


<script setup>
import { onMounted, ref } from 'vue'
import Chart from 'chart.js/auto'
import axios from 'axios'


const props = defineProps({ title:String, endpoint:String })
const el = ref(null)


onMounted(async ()=>{
const { data } = await axios.get(props.endpoint)
new Chart(el.value, {
type: 'line',
data: {
labels: data.map(d=>d.date),
datasets:[{ label: props.title, data: data.map(d=>d.total ?? d.bookings) }]
}
})
})
</script>