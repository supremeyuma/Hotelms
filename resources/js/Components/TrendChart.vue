<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Chart from 'chart.js/auto'
import axios from 'axios'

const props = defineProps({
  title: String,
  endpoint: String,
})

const canvas = ref(null)
const chart = ref(null)
const range = ref(30)
const isLoading = ref(false)
const error = ref('')
const hasData = ref(true)

const rangeLabel = computed(() => `${range.value} day view`)

const load = async () => {
  isLoading.value = true
  error.value = ''

  try {
    const { data } = await axios.get(props.endpoint, {
      params: { days: range.value },
    })

    const labels = Array.isArray(data?.labels) ? data.labels : []
    const values = Array.isArray(data?.values) ? data.values : []

    hasData.value = values.some((value) => Number(value || 0) > 0)

    if (chart.value) {
      chart.value.destroy()
      chart.value = null
    }

    if (!canvas.value) {
      return
    }

    chart.value = new Chart(canvas.value, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: props.title,
            data: values,
            borderColor: '#4f46e5',
            backgroundColor: 'rgba(79, 70, 229, 0.12)',
            borderWidth: 2,
            pointRadius: 0,
            pointHoverRadius: 4,
            tension: 0.35,
            fill: true,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          intersect: false,
          mode: 'index',
        },
        plugins: {
          legend: { display: false },
        },
        scales: {
          x: {
            grid: {
              display: false,
            },
            ticks: {
              color: '#64748b',
            },
          },
          y: {
            beginAtZero: true,
            grid: {
              color: 'rgba(148, 163, 184, 0.18)',
            },
            ticks: {
              color: '#64748b',
            },
          },
        },
      },
    })
  } catch (err) {
    error.value = err?.response?.data?.message || 'Unable to load chart data right now.'
  } finally {
    isLoading.value = false
  }
}

onMounted(load)
onBeforeUnmount(() => {
  if (chart.value) {
    chart.value.destroy()
  }
})

watch([range, () => props.endpoint], load)
</script>

<template>
  <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Trend view</p>
        <h3 class="mt-2 text-xl font-black tracking-tight text-slate-900">
          {{ title }}
        </h3>
      </div>

      <div class="flex items-center gap-3">
        <p class="hidden text-xs font-semibold text-slate-400 sm:block">{{ rangeLabel }}</p>
        <select
          v-model="range"
          class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
        >
          <option value="7">Last 7 days</option>
          <option value="30">Last 30 days</option>
          <option value="90">Last 90 days</option>
        </select>
      </div>
    </div>

    <div class="relative h-72">
      <div
        v-if="isLoading"
        class="absolute inset-0 z-10 flex items-center justify-center rounded-[1.5rem] bg-white/75 backdrop-blur-sm"
      >
        <div class="space-y-2 text-center">
          <div class="mx-auto h-9 w-9 animate-spin rounded-full border-2 border-slate-200 border-t-indigo-600" />
          <p class="text-sm font-medium text-slate-500">Loading chart…</p>
        </div>
      </div>

      <div
        v-else-if="error"
        class="absolute inset-0 z-10 flex items-center justify-center rounded-[1.5rem] border border-rose-200 bg-rose-50 px-6 text-center"
      >
        <p class="max-w-sm text-sm font-medium text-rose-700">{{ error }}</p>
      </div>

      <div
        v-else-if="!hasData"
        class="absolute inset-0 z-10 flex items-center justify-center rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50 px-6 text-center"
      >
        <p class="max-w-sm text-sm font-medium text-slate-500">No chart activity is available for this range yet.</p>
      </div>

      <canvas ref="canvas"></canvas>
    </div>
  </div>
</template>
