<!-- resources/js/Pages/Admin/Events/Index.vue -->
<script setup>
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import { useForm } from '@inertiajs/vue3'

defineProps({ events: Array })

const form = useForm({
  title: '',
  description: '',
  event_date: '',
  start_time: '',
  end_time: ''
})

const submit = () => form.post('/admin/events')
</script>

<template>
  <ManagerLayout>
    <div class="max-w-5xl mx-auto p-6 space-y-8">
      <h1 class="text-2xl font-black">Club Events</h1>

      <form @submit.prevent="submit" class="grid grid-cols-2 gap-4">
        <input v-model="form.title" class="input" placeholder="Event title" />
        <input type="date" v-model="form.event_date" class="input" />
        <input type="time" v-model="form.start_time" class="input" />
        <input type="time" v-model="form.end_time" class="input" />
        <textarea v-model="form.description" class="input col-span-2" />
        <button class="btn-primary col-span-2">Add Event</button>
      </form>

      <div class="space-y-4">
        <div v-for="e in events" :key="e.id" class="border p-4 rounded-xl">
          <h3 class="font-bold">{{ e.title }}</h3>
          <p class="text-sm">{{ e.event_date }}</p>
        </div>
      </div>
    </div>
  </ManagerLayout>
</template>
