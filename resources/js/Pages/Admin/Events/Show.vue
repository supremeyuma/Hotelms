<script setup>
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
  event: Object,
  statistics: Object,
  qr_code: String
})

const deleteForm = useForm({})
const showDeleteModal = ref(false)
const activeTab = ref('overview')

const deleteEvent = () => {
  deleteForm.delete(`/admin/events/${props.event.id}`, {
    onSuccess: () => {
      showDeleteModal.value = false
    }
  })
}

const downloadQRCode = () => {
  const link = document.createElement('a')
  link.href = props.qr_code
  link.download = `event-${props.event.id}-qr.png`
  link.click()
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const formatTime = (time) => {
  if (!time) return 'Not set'
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
  }).format(amount)
}

const getMainImage = () => {
  if (props.event.image) {
    return `/storage/${props.event.image}`
  }
  
  const mainMedia = props.event.promotional_media?.find(media => media.is_main_image)
  if (mainMedia) {
    return `/storage/${mainMedia.media_url}`
  }
  
  const firstImage = props.event.promotional_media?.find(media => media.media_type === 'image')
  if (firstImage) {
    return `/storage/${firstImage.media_url}`
  }
  
  return 'https://via.placeholder.com/800x400?text=No+Image'
}
</script>

<template>
  <ManagerLayout>
    <div class="max-w-7xl mx-auto p-6 space-y-8">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-black text-slate-900">{{ event.title }}</h1>
          <div class="flex items-center gap-4 mt-2 text-sm text-slate-600">
            <span>{{ formatDate(event.event_date) }}</span>
            <span v-if="event.start_time">{{ formatTime(event.start_time) }}</span>
            <span v-if="event.venue">• {{ event.venue }}</span>
          </div>
        </div>
        
        <div class="flex items-center gap-3">
          <Link 
            :href="`/events/${event.id}`"
            target="_blank"
            class="btn-secondary"
          >
            View Public Page
          </Link>
          
          <button 
            @click="downloadQRCode"
            class="btn-secondary"
          >
            Download QR Code
          </button>
          
          <Link 
            :href="`/admin/events/${event.id}/edit`"
            class="btn-primary"
          >
            Edit Event
          </Link>
          
          <button 
            @click="showDeleteModal = true"
            class="btn-danger"
          >
            Delete
          </button>
        </div>
      </div>

      <!-- Main Image -->
      <div class="relative h-96 rounded-xl overflow-hidden">
        <img 
          :src="getMainImage()" 
          :alt="event.title"
          class="w-full h-full object-cover"
        />
        <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
        <div class="absolute bottom-6 left-6 text-white">
          <div class="flex items-center gap-2">
            <span v-if="event.is_active" class="bg-green-500 text-white text-xs px-2 py-1 rounded-full">Active</span>
            <span v-if="event.is_featured" class="bg-yellow-500 text-white text-xs px-2 py-1 rounded-full">Featured</span>
            <span class="bg-blue-500 text-white text-xs px-2 py-1 rounded-full">{{ event.status }}</span>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="border-b border-slate-200">
        <nav class="flex space-x-8">
          <button
            v-for="tab in ['overview', 'media', 'tickets', 'analytics']"
            :key="tab"
            @click="activeTab = tab"
            class="py-4 px-1 border-b-2 font-medium text-sm transition-colors"
            :class="activeTab === tab 
              ? 'border-blue-500 text-blue-600' 
              : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
          >
            {{ tab.charAt(0).toUpperCase() + tab.slice(1) }}
          </button>
        </nav>
      </div>

      <!-- Tab Content -->
      <div class="space-y-8">
        <!-- Overview Tab -->
        <div v-if="activeTab === 'overview'" class="space-y-8">
          <!-- Event Details -->
          <section class="bg-white rounded-lg border border-slate-200 p-6">
            <h2 class="text-xl font-semibold text-slate-900 mb-4">Event Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Date & Time</h3>
                <p class="text-slate-900">{{ formatDate(event.event_date) }} at {{ formatTime(event.start_time) }}</p>
              </div>
              <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Venue</h3>
                <p class="text-slate-900">{{ event.venue || 'Not specified' }}</p>
              </div>
              <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Capacity</h3>
                <p class="text-slate-900">{{ event.capacity || 'Unlimited' }} attendees</p>
              </div>
              <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Status</h3>
                <p class="text-slate-900">{{ event.status }}</p>
              </div>
            </div>
          </section>

          <!-- Description -->
          <section class="bg-white rounded-lg border border-slate-200 p-6">
            <h2 class="text-xl font-semibold text-slate-900 mb-4">Description</h2>
            <div class="prose max-w-none text-slate-700">
              <p v-if="event.description">{{ event.description }}</p>
              <p v-else class="text-slate-400 italic">No description provided</p>
            </div>
          </section>

          <!-- Quick Stats -->
          <section class="bg-white rounded-lg border border-slate-200 p-6">
            <h2 class="text-xl font-semibold text-slate-900 mb-4">Quick Stats</h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
              <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Total Revenue</h3>
                <p class="text-2xl font-bold text-slate-900">{{ formatCurrency(statistics.total_revenue || 0) }}</p>
              </div>
              <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Tickets Sold</h3>
                <p class="text-2xl font-bold text-slate-900">{{ statistics.tickets_sold || 0 }}</p>
              </div>
              <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Tables Reserved</h3>
                <p class="text-2xl font-bold text-slate-900">{{ statistics.tables_reserved || 0 }}</p>
              </div>
              <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Total Attendees</h3>
                <p class="text-2xl font-bold text-slate-900">{{ statistics.total_attendees || 0 }}</p>
              </div>
            </div>
          </section>
        </div>

        <!-- Media Tab -->
        <div v-if="activeTab === 'media'" class="space-y-8">
          <section class="bg-white rounded-lg border border-slate-200 p-6">
            <div class="flex items-center justify-between mb-6">
              <h2 class="text-xl font-semibold text-slate-900">Promotional Media</h2>
              <Link 
                :href="`/admin/events/${event.id}/edit`"
                class="btn-secondary"
              >
                Manage Media
              </Link>
            </div>
            
            <div v-if="event.promotional_media && event.promotional_media.length > 0" class="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div 
                v-for="media in event.promotional_media" 
                :key="media.id"
                class="relative group"
              >
                <div class="aspect-square rounded-lg overflow-hidden border-2 transition-colors"
                     :class="media.is_main_image ? 'border-blue-500' : 'border-slate-200'">
                  <img 
                    v-if="media.media_type === 'image'" 
                    :src="`/storage/${media.media_url}`" 
                    :alt="media.title || 'Event media'"
                    class="w-full h-full object-cover"
                  />
                  <video 
                    v-else 
                    :src="`/storage/${media.media_url}`" 
                    class="w-full h-full object-cover"
                    controls
                  ></video>
                </div>
                
                <div v-if="media.is_main_image" class="absolute top-2 left-2 bg-blue-500 text-white text-xs px-2 py-1 rounded">
                  Main Image
                </div>
              </div>
            </div>
            
            <div v-else class="text-center py-12 text-slate-400">
              <div class="text-6xl mb-4">📷</div>
              <p class="text-lg font-medium mb-2">No media uploaded</p>
              <p class="text-sm">Upload images and videos to showcase your event</p>
            </div>
          </section>
        </div>

        <!-- Tickets Tab -->
        <div v-if="activeTab === 'tickets'" class="space-y-8">
          <section class="bg-white rounded-lg border border-slate-200 p-6">
            <div class="flex items-center justify-between mb-6">
              <h2 class="text-xl font-semibold text-slate-900">Ticket Types</h2>
              <Link 
                :href="`/admin/events/${event.id}/edit`"
                class="btn-secondary"
              >
                Manage Tickets
              </Link>
            </div>
            
            <div v-if="event.ticket_types && event.ticket_types.length > 0" class="space-y-4">
              <div 
                v-for="ticket in event.ticket_types" 
                :key="ticket.id"
                class="border border-slate-200 rounded-lg p-4"
              >
                <div class="flex items-center justify-between">
                  <div>
                    <h3 class="font-medium text-slate-900">{{ ticket.name }}</h3>
                    <p class="text-sm text-slate-600">{{ ticket.description }}</p>
                  </div>
                  <div class="text-right">
                    <p class="text-lg font-bold text-slate-900">{{ formatCurrency(ticket.price) }}</p>
                    <p class="text-sm text-slate-600">{{ ticket.quantity_available }} available</p>
                  </div>
                </div>
              </div>
            </div>
            
            <div v-else class="text-center py-12 text-slate-400">
              <div class="text-6xl mb-4">🎫</div>
              <p class="text-lg font-medium mb-2">No ticket types configured</p>
              <p class="text-sm">Create ticket types to start selling tickets</p>
            </div>
          </section>

          <!-- Table Reservations -->
          <section v-if="event.has_table_reservations" class="bg-white rounded-lg border border-slate-200 p-6">
            <h2 class="text-xl font-semibold text-slate-900 mb-4">Table Reservations</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Table Capacity</h3>
                <p class="text-slate-900">{{ event.table_capacity }} tables</p>
              </div>
              <div>
                <h3 class="text-sm font-medium text-slate-500 mb-1">Price per Table</h3>
                <p class="text-slate-900">{{ formatCurrency(event.table_price) }}</p>
              </div>
            </div>
          </section>
        </div>

        <!-- Analytics Tab -->
        <div v-if="activeTab === 'analytics'" class="space-y-8">
          <section class="bg-white rounded-lg border border-slate-200 p-6">
            <h2 class="text-xl font-semibold text-slate-900 mb-6">Event Analytics</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
              <div class="bg-slate-50 rounded-lg p-4">
                <h3 class="text-sm font-medium text-slate-500 mb-2">Ticket Revenue</h3>
                <p class="text-2xl font-bold text-slate-900">{{ formatCurrency(statistics.ticket_revenue || 0) }}</p>
                <p class="text-sm text-slate-600">{{ statistics.tickets_sold || 0 }} tickets</p>
              </div>
              
              <div class="bg-slate-50 rounded-lg p-4">
                <h3 class="text-sm font-medium text-slate-500 mb-2">Table Revenue</h3>
                <p class="text-2xl font-bold text-slate-900">{{ formatCurrency(statistics.table_revenue || 0) }}</p>
                <p class="text-sm text-slate-600">{{ statistics.tables_reserved || 0 }} tables</p>
              </div>
              
              <div class="bg-slate-50 rounded-lg p-4">
                <h3 class="text-sm font-medium text-slate-500 mb-2">Average Order Value</h3>
                <p class="text-2xl font-bold text-slate-900">{{ formatCurrency(statistics.avg_order_value || 0) }}</p>
                <p class="text-sm text-slate-600">{{ statistics.total_orders || 0 }} orders</p>
              </div>
              
              <div class="bg-slate-50 rounded-lg p-4">
                <h3 class="text-sm font-medium text-slate-500 mb-2">Conversion Rate</h3>
                <p class="text-2xl font-bold text-slate-900">{{ statistics.conversion_rate || 0 }}%</p>
                <p class="text-sm text-slate-600">Views to purchases</p>
              </div>
            </div>
          </section>
        </div>
      </div>

      <!-- Delete Confirmation Modal -->
      <div v-if="showDeleteModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
          <h2 class="text-xl font-semibold text-slate-900 mb-4">Delete Event</h2>
          <p class="text-slate-600 mb-6">
            Are you sure you want to delete "{{ event.title }}"? This action cannot be undone and will remove all associated tickets, reservations, and data.
          </p>
          
          <div class="flex justify-end space-x-3">
            <button 
              @click="showDeleteModal = false"
              class="btn-secondary"
              :disabled="deleteForm.processing"
            >
              Cancel
            </button>
            <button 
              @click="deleteEvent"
              class="btn-danger"
              :disabled="deleteForm.processing"
            >
              {{ deleteForm.processing ? 'Deleting...' : 'Delete Event' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </ManagerLayout>
</template>