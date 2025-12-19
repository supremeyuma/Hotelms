<!--<template>
  <AuthenticatedLayout>
    <div class="space-y-6">
      <h1 class="text-2xl font-semibold">Occupancy Details</h1>

      <form @submit.prevent="load" class="flex gap-4 items-end">
        <div>
          <label class="text-sm">From</label>
          <input type="date" v-model="range.from" class="input" />
        </div>
        <div>
          <label class="text-sm">To</label>
          <input type="date" v-model="range.to" class="input" />
        </div>
        <PrimaryButton>Load</PrimaryButton>
      </form>

      <table class="table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Bookings</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.date">
            <td>{{ row.date }}</td>
            <td>{{ row.bookings }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AuthenticatedLayout>
</template>-->

<!-- File: resources/js/Pages/Admin/Reports/Occupancy.vue -->
<script setup>
defineProps({ rows:Array, summary:Number })
</script>

<template>
  <h2 class="text-xl font-bold">Occupancy {{ summary }}%</h2>
  <ul>
    <li v-for="r in rows" :key="r.date">{{ r.date }} — {{ r.bookings }}</li>
  </ul>
</template>

<!-- File: resources/js/Pages/Admin/Reports/Occupancy.vue -->
<script setup>
defineProps({ rows:Array, summary:Number })
</script>


<!--<script setup>
import { ref } from 'vue'
import axios from 'axios'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const props = defineProps({
  range: Object,
})

const range = ref({ ...props.range })
const rows = ref([])

async function load() {
  const res = await axios.get(route('reports.occupancy'), {
    params: range.value,
  })
  rows.value = res.data
}

load()
</script>-->
