<!-- resources/js/Components/TrendChart.vue -->
<template>
  <div class="bg-white dark:bg-gray-900 p-4 rounded-2xl shadow-sm">
    <div class="flex items-center justify-between mb-3">
      <h3 class="font-semibold text-gray-800 dark:text-gray-100">
        {{ title }}
      </h3>
      <select
        v-model="range"
        class="text-sm border rounded px-2 py-1 dark:bg-gray-800 dark:border-gray-700"
      >
        <option value="7">7D</option>
        <option value="30">30D</option>
        <option value="90">90D</option>
      </select>
    </div>

    <div class="relative h-64">
      <canvas ref="canvas"></canvas>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import Chart from 'chart.js/auto'
import axios from 'axios'

const props = defineProps({
  title: String,
  endpoint: String,
})

const canvas = ref(null)
const chart = ref(null)
const range = ref(30)

const load = async () => {
  const { data } = await axios.get(props.endpoint, {
    params: { days: range.value },
  })

  if (chart.value) chart.value.destroy()

  chart.value = new Chart(canvas.value, {
    type: 'line',
    data: {
      labels: data.labels,
      datasets: [
        {
          label: props.title,
          data: data.values,
          tension: 0.4,
          fill: true,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
    },
  })
}

onMounted(load)
watch(range, load)
</script>
