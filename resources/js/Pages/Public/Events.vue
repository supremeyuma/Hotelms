<template>
  <PublicLayout>
    <Head title="Events | MooreLife Resort" />
    <div class="min-h-screen bg-gray-50">
    <!-- Hero Section -->
    <div class="bg-gradient-to-r from-purple-900 to-indigo-900 text-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
        <div class="text-center">
          <h1 class="text-4xl font-extrabold tracking-tight sm:text-5xl lg:text-6xl">
            Upcoming Events
          </h1>
          <p class="mt-6 text-xl text-indigo-200 max-w-3xl mx-auto">
            Experience unforgettable moments at Moorelife Resort
          </p>
        </div>
      </div>
    </div>

    <!-- Featured Events -->
    <div v-if="featuredEvents.length > 0" class="py-16 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
          <h2 class="text-3xl font-extrabold text-gray-900">Featured Events</h2>
          <p class="mt-4 text-lg text-gray-600">Don't miss out on our most anticipated events</p>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
          <div v-for="event in featuredEvents" :key="event.id" 
               class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200 hover:shadow-xl transition-shadow">
            <div class="relative">
              <img v-if="event.image" :src="'/storage/' + event.image" 
                   :alt="event.title" class="w-full h-64 object-cover">
              <div v-else class="w-full h-64 bg-gradient-to-br from-purple-400 to-indigo-400"></div>
              
              <!-- Featured Badge -->
              <div class="absolute top-4 right-4">
                <span class="bg-red-500 text-white text-xs font-bold px-3 py-1 rounded-full">
                  FEATURED
                </span>
              </div>
            </div>
            
            <div class="p-6">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-bold text-gray-900">{{ event.title }}</h3>
                <div class="text-sm text-gray-600">
                  <div>{{ formatEventDateTime(event).date }}</div>
                  <div>{{ formatEventDateTime(event).time }}</div>
                </div>
              </div>
              
              <p v-if="event.description" class="text-gray-600 mb-4 line-clamp-3">
                {{ event.description }}
              </p>
              
              <div class="flex justify-between items-center">
                <div class="text-sm text-gray-600">
                  <span v-if="event.venue">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0l9.193 9.193A11.942 11.942 0 0122 17.07l3.922 3.922A4 4 0 0124 20.928v-2.568c0-2.54-2.817-4.632-4.632H8c-1.815 0-3.483.916-4.632 2.089v2.568a4 4 0 014.632 2.089z" />
                    </svg>
                    {{ event.venue }}
                  </span>
                </div>
                
                <Link :href="'/events/' + event.id" 
                      class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                  View Details
                </Link>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- All Events -->
    <div class="py-16 bg-gray-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
          <h2 class="text-3xl font-extrabold text-gray-900">All Events</h2>
          <p class="mt-4 text-lg text-gray-600">Browse all our upcoming events</p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          <div v-for="event in allEvents" :key="event.id" 
               class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200 hover:shadow-xl transition-shadow">
            <div class="relative">
              <img v-if="event.image" :src="'/storage/' + event.image" 
                   :alt="event.title" class="w-full h-48 object-cover">
              <div v-else class="w-full h-48 bg-gradient-to-br from-purple-400 to-indigo-400"></div>
            </div>
            
            <div class="p-6">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-900">{{ event.title }}</h3>
                <div class="text-sm text-gray-600">
                  <div>{{ formatEventDateTime(event).date }}</div>
                  <div>{{ formatEventDateTime(event).time }}</div>
                </div>
              </div>
              
              <p v-if="event.description" class="text-gray-600 mb-4 line-clamp-2 text-sm">
                {{ event.description }}
              </p>
              
              <div class="flex justify-between items-center">
                <div class="text-sm text-gray-600">
                  <span v-if="event.venue">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0l9.193 9.193A11.942 11.942 0 0122 17.07l3.922 3.922A4 4 0 0124 20.928v-2.568c0-2.54-2.817-4.632-4.632H8c-1.815 0-3.483.916-4.632 2.089v2.568a4 4 0 014.632 2.089z" />
                    </svg>
                    {{ event.venue }}
                  </span>
                </div>
                
                <Link :href="'/events/' + event.id" 
                      class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-indigo-600 bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                  View Event
                </Link>
              </div>
            </div>
          </div>
        </div>
        
        <!-- No Events State -->
        <div v-if="allEvents.length === 0" class="text-center py-16">
          <div class="max-w-md mx-auto">
            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No Upcoming Events</h3>
            <p class="text-gray-600">Check back soon for new events and experiences at Moorelife Resort.</p>
          </div>
        </div>
      </div>
    </div>
    </div>
  </PublicLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'

// Helper functions for improved date/time formatting
const formatEventDateTime = (event) => {
  if (!event.start_time || !event.end_time) {
    return {
      date: event.formatted_date || 'Date TBD',
      time: 'Time TBD'
    }
  }

  const startDate = new Date(event.start_time)
  const endDate = new Date(event.end_time)
  
  // Check if it's a cross-day event
  const isCrossDay = startDate.toDateString() !== endDate.toDateString()
  
  if (isCrossDay) {
    return {
      date: event.formatted_date,
      time: `${formatTime(startDate)} – ${formatMonthDay(endDate)} • ${formatTime(endDate)}`
    }
  } else {
    return {
      date: event.formatted_date,
      time: `${formatTime(startDate)} – ${formatTime(endDate)}`
    }
  }
}

const formatTime = (date) => {
  return date.toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  })
}

const formatMonthDay = (date) => {
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric'
  })
}

defineProps({
  featuredEvents: Array,
  allEvents: Array,
})
</script>