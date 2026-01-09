<!-- resources/js/Pages/Public/ClubLounge.vue -->
<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
defineProps({ events: Array })
</script>

<template>
  <PublicLayout>
    <div class="max-w-6xl mx-auto px-4 py-16 space-y-12">
      <h1 class="text-4xl font-black">Club & Lounge</h1>

      <div v-html="content('club.description')" class="prose"></div>

      <div>
        <h2 class="text-2xl font-black mb-6">Upcoming Events</h2>

        <div class="grid md:grid-cols-2 gap-6">
          <div
            v-for="event in events"
            :key="event.id"
            class="border rounded-2xl p-6"
          >
            <h3 class="font-bold text-lg">{{ event.title }}</h3>
            <p class="text-sm text-slate-500">
              {{ event.event_date }} · {{ event.start_time }}
            </p>
            <p class="mt-2">{{ event.description }}</p>
          </div>
        </div>
      </div>
    </div>
  </PublicLayout>
</template>
