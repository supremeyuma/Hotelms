<script setup>
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import { Link } from '@inertiajs/vue3'
import { ref } from 'vue'

defineProps({ events: Array })

const searchQuery = ref('')
const filterStatus = ref('all')
const filterDate = ref('')

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

const formatTime = (time) => {
  if (!time) return 'TBD'
  return new Date(`1970-01-01T${time}`).toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  })
}

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN'
  }).format(amount || 0)
}

const getEventStatus = (event) => {
  const now = new Date()
  const eventDate = new Date(event.event_date)
  
  if (!event.is_active) return { text: 'Inactive', color: 'bg-gray-500' }
  if (event.status === 'cancelled') return { text: 'Cancelled', color: 'bg-red-500' }
  if (eventDate < now) return { text: 'Past', color: 'bg-slate-500' }
  if (event.is_featured) return { text: 'Featured', color: 'bg-yellow-500' }
  return { text: 'Upcoming', color: 'bg-green-500' }
}

const getMainImage = (event) => {
  if (event.image) {
    return `/storage/${event.image}`
  }
  
  const mainMedia = event.promotional_media?.find(media => media.is_main_image)
  if (mainMedia) {
    return `/storage/${mainMedia.media_url}`
  }
  
  const firstImage = event.promotional_media?.find(media => media.media_type === 'image')
  if (firstImage) {
    return `/storage/${firstImage.media_url}`
  }
  
  return 'https://via.placeholder.com/400x250?text=' + encodeURIComponent(event.title)
}

const filteredEvents = (events) => {
  let filtered = [...events]
  
  // Search filter
  if (searchQuery.value) {
    filtered = filtered.filter(event => 
      event.title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      event.description?.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
  }
  
  // Status filter
  if (filterStatus.value !== 'all') {
    const status = getEventStatus({ status: filterStatus.value }).text.toLowerCase()
    filtered = filtered.filter(event => 
      getEventStatus(event).text.toLowerCase() === status
    )
  }
  
  // Sort by date (upcoming first)
  filtered.sort((a, b) => new Date(a.event_date) - new Date(b.event_date))
  
  return filtered
}
</script>

<template>
  <ManagerLayout>
    <div class="max-w-7xl mx-auto p-6 space-y-8">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-black text-slate-900">Events</h1>
          <p class="text-slate-600 mt-1">Manage your club events and promotions</p>
        </div>
        <Link href="/admin/events/create" class="btn-primary">
          + Create Event
        </Link>
      </div>

      <!-- Search and Filters -->
      <div class="bg-white rounded-lg border border-slate-200 p-6">
        <div class="flex flex-col md:flex-row gap-4">
          <div class="flex-1">
            <label class="block text-sm font-medium text-slate-700 mb-2">Search Events</label>
            <input 
              v-model="searchQuery"
              type="text" 
              class="input w-full"
              placeholder="Search by title or description..."
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Status</label>
            <select v-model="filterStatus" class="input w-full md:w-40">
              <option value="all">All Events</option>
              <option value="upcoming">Upcoming</option>
              <option value="featured">Featured</option>
              <option value="past">Past</option>
              <option value="inactive">Inactive</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Events Grid -->
      <div v-if="filteredEvents(events).length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div 
          v-for="event in filteredEvents(events)" 
          :key="event.id"
          class="bg-white rounded-lg border border-slate-200 overflow-hidden hover:shadow-lg transition-shadow"
        >
          <!-- Event Image -->
          <div class="relative h-48">
            <img 
              :src="getMainImage(event)" 
              :alt="event.title"
              class="w-full h-full object-cover"
            />
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
            
            <!-- Status Badge -->
            <div class="absolute top-4 left-4">
              <span :class="`${getEventStatus(event).color} text-white text-xs px-2 py-1 rounded-full`">
                {{ getEventStatus(event).text }}
              </span>
            </div>
            
            <!-- Featured Badge -->
            <div v-if="event.is_featured" class="absolute top-4 right-4">
              <span class="bg-yellow-500 text-white text-xs px-2 py-1 rounded-full">
                ⭐ Featured
              </span>
            </div>
          </div>
          
          <!-- Event Details -->
          <div class="p-6">
            <div class="mb-3">
              <h3 class="text-lg font-semibold text-slate-900 line-clamp-1">{{ event.title }}</h3>
              <p class="text-sm text-slate-600 line-clamp-2 mt-1">{{ event.description || 'No description' }}</p>
            </div>
            
            <div class="space-y-2 mb-4">
              <div class="flex items-center text-sm text-slate-600">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                {{ formatDate(event.event_date) }}
              </div>
              
              <div class="flex items-center text-sm text-slate-600">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {{ formatTime(event.start_time) }}
              </div>
              
              <div v-if="event.venue" class="flex items-center text-sm text-slate-600">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                {{ event.venue }}
              </div>
            </div>
            
            <!-- Stats -->
            <div class="grid grid-cols-3 gap-2 mb-4">
              <div class="text-center">
                <p class="text-lg font-bold text-slate-900">{{ event.tickets_sold || 0 }}</p>
                <p class="text-xs text-slate-500">Tickets</p>
              </div>
              <div class="text-center">
                <p class="text-lg font-bold text-slate-900">{{ event.tables_reserved || 0 }}</p>
                <p class="text-xs text-slate-500">Tables</p>
              </div>
              <div class="text-center">
                <p class="text-lg font-bold text-slate-900">{{ formatCurrency(event.total_revenue) }}</p>
                <p class="text-xs text-slate-500">Revenue</p>
              </div>
            </div>
            
            <!-- Actions -->
            <div class="flex items-center gap-2">
              <Link 
                :href="`/admin/events/${event.id}`"
                class="flex-1 btn-secondary text-sm text-center"
              >
                View
              </Link>
              <Link 
                :href="`/admin/events/${event.id}/edit`"
                class="flex-1 btn-primary text-sm text-center"
              >
                Edit
              </Link>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="text-center py-12 bg-white rounded-lg border border-slate-200">
        <div class="text-6xl mb-4">📅</div>
        <h3 class="text-lg font-medium text-slate-900 mb-2">No events found</h3>
        <p class="text-slate-600 mb-6">
          {{ searchQuery || filterStatus !== 'all' ? 'Try adjusting your filters' : 'Get started by creating your first event' }}
        </p>
        <Link v-if="!searchQuery && filterStatus === 'all'" href="/admin/events/create" class="btn-primary">
          Create Your First Event
        </Link>
      </div>
    </div>
  </ManagerLayout>
</template>

<style scoped>
.line-clamp-1 {
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
}

.line-clamp-2 {
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}
</style>
