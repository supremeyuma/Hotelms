<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Event Hero -->
    <div class="relative">
      <img v-if="event.image" :src="'/storage/' + event.image" 
           :alt="event.title" class="w-full h-96 object-cover">
      <div v-else class="w-full h-96 bg-gradient-to-br from-purple-900 to-indigo-900"></div>
      
      <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center">
        <div class="text-center text-white px-4">
          <h1 class="text-4xl md:text-5xl font-extrabold mb-4">{{ event.title }}</h1>
          <div class="flex flex-col md:flex-row md:space-x-8 justify-center text-lg">
            <div class="flex items-center">
              <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              {{ formatEventDateTime(event).date }}
            </div>
            <div class="flex items-center">
              <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 00-9-9v9m0-6v3m0-6h6m-9 6v6m0-6h9" />
              </svg>
              {{ formatEventDateTime(event).time }}
            </div>
            <div v-if="event.venue" class="flex items-center">
              <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0l9.193 9.193A11.942 11.942 0 0122 17.07l3.922 3.922A4 4 0 0124 20.928v-2.568c0-2.54-2.817-4.632-4.632H8c-1.815 0-3.483.916-4.632 2.089v2.568a4 4 0 014.632 2.089z" />
              </svg>
              {{ event.venue }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Event Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Main Info -->
        <div class="lg:col-span-2">
          <div class="bg-white rounded-lg shadow-md p-8 mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">About This Event</h2>
            <div v-if="event.description" class="prose prose-lg text-gray-600 mb-6">
              <p>{{ event.description }}</p>
            </div>
            
            <div v-if="event.venue || event.capacity" class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
              <div v-if="event.venue" class="text-center">
                <div class="text-sm text-gray-600 mb-2">Venue</div>
                <div class="text-lg font-semibold text-gray-900">{{ event.venue }}</div>
              </div>
              <div v-if="event.capacity" class="text-center">
                <div class="text-sm text-gray-600 mb-2">Capacity</div>
                <div class="text-lg font-semibold text-gray-900">{{ event.capacity }} guests</div>
              </div>
            </div>
          </div>

          <!-- Promotional Media -->
          <div v-if="event.promotional_media && event.promotional_media.length > 0" class="bg-white rounded-lg shadow-md p-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Event Gallery</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div v-for="media in event.promotional_media" :key="media.id" class="relative group">
                <div v-if="media.is_image">
                  <img :src="'/storage/' + media.media_url" 
                       :alt="media.title" 
                       class="w-full h-48 object-cover rounded-lg">
                </div>
                <div v-else class="relative">
                  <video :src="'/storage/' + media.media_url" 
                          controls
                          class="w-full h-48 object-cover rounded-lg">
                  </video>
                  <div class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-30 rounded-lg">
                    <svg class="w-12 h-12 text-white" fill="currentColor" viewBox="0 0 24 24">
                      <path d="M10 18a8 8 0 100-16 9.732 4H7.268A8 8 0 006 18v2h4v-2z"/>
                    </svg>
                  </div>
                </div>
                <div v-if="media.title || media.description" class="mt-3">
                  <h3 v-if="media.title" class="text-lg font-semibold text-gray-900">{{ media.title }}</h3>
                  <p v-if="media.description" class="text-sm text-gray-600">{{ media.description }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Actions Sidebar -->
        <div>
          <div class="bg-white rounded-lg shadow-md p-6 mb-6 sticky top-6">
            <!-- QR Code -->
            <div v-if="qr_code" class="text-center mb-6">
              <div class="text-sm text-gray-600 mb-2">Event QR Code</div>
              <div class="bg-white p-4 inline-block rounded-lg shadow-md border">
                <img :src="qr_code" alt="Event QR Code" class="w-32 h-32">
              </div>
              <p class="text-xs text-gray-500 mt-2">Scan to share this event</p>
            </div>

            <!-- Action Buttons -->
            <div class="space-y-4">
              <Link v-if="event.ticket_types && event.ticket_types.length > 0" 
                    :href="'/events/' + event.id + '/tickets'"
                    class="w-full flex items-center justify-center px-4 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-4h-4M14 3l-6 6 4-2M5 3v4h4" />
                </svg>
                Buy Tickets
              </Link>
              
              <Link v-if="event.has_table_reservations" 
                    :href="'/events/' + event.id + '/reserve-table'"
                    class="w-full flex items-center justify-center px-4 py-3 border border-transparent text-base font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h18v18H3V3zm18 2v14H5V5h16z" />
                </svg>
                Reserve Table
              </Link>
            </div>

            <!-- Event Status -->
            <div class="mt-6 p-4 bg-gray-50 rounded-lg">
              <div class="text-sm text-gray-600 mb-2">Event Status</div>
              <div class="flex items-center">
                <div class="w-3 h-3 rounded-full mr-2" 
                     :class="event.is_active ? 'bg-green-500' : 'bg-red-500'"></div>
                <span class="text-sm font-medium text-gray-900">
                  {{ event.is_active ? 'Active' : 'Inactive' }}
                </span>
              </div>
            </div>
          </div>

          <!-- Quick Stats -->
          <div v-if="event.statistics" class="bg-indigo-50 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-indigo-900 mb-4">Event Statistics</h3>
            <div class="space-y-3">
              <div class="flex justify-between">
                <span class="text-sm text-indigo-600">Tickets Sold</span>
                <span class="font-semibold text-indigo-900">{{ event.statistics.total_tickets_sold }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-sm text-indigo-600">Tables Reserved</span>
                <span class="font-semibold text-indigo-900">{{ event.statistics.total_tables_reserved }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-sm text-indigo-600">Total Revenue</span>
                <span class="font-semibold text-indigo-900">₦{{ formatNumber(event.statistics.total_revenue) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  event: Object,
  qr_code: String,
})

const formatNumber = (num) => {
  return new Intl.NumberFormat('en-NG').format(num)
}

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
</script>