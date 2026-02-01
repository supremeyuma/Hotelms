<template>
  <PublicLayout>
    <Head :title="`${event.title} | MooreLife Resort`" />

    <div class="min-h-screen bg-slate-50 pb-20">
      <div class="relative h-[60vh] min-h-[500px] overflow-hidden">
        <img v-if="event.image" :src="'/storage/' + event.image" 
             :alt="event.title" class="w-full h-full object-cover">
        <div v-else class="w-full h-full bg-indigo-950"></div>
        
        <div class="absolute inset-0 bg-gradient-to-t from-slate-900 via-slate-900/40 to-transparent"></div>
        
        <div class="absolute inset-0 flex items-end">
          <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full pb-12">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-8">
              <div class="max-w-3xl">
                <div class="flex items-center space-x-3 mb-4">
                  <span class="bg-indigo-600 text-white text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-widest">
                    {{ event.is_active ? 'Booking Open' : 'Private Event' }}
                  </span>
                  <div class="h-px w-8 bg-indigo-400"></div>
                  <span class="text-indigo-200 text-xs font-bold uppercase tracking-widest">
                    {{ event.venue || 'MooreLife Grounds' }}
                  </span>
                </div>
                <h1 class="text-5xl md:text-7xl font-black text-white tracking-tighter mb-6">
                  {{ event.title }}
                </h1>
                
                <div class="flex flex-wrap gap-6 text-indigo-100">
                  <div class="flex items-center bg-white/10 backdrop-blur-md px-4 py-2 rounded-xl border border-white/10">
                    <svg class="w-5 h-5 mr-3 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span class="text-sm font-bold">{{ eventTiming.dateLabel }}</span>
                  </div>
                  <div class="flex items-center bg-white/10 backdrop-blur-md px-4 py-2 rounded-xl border border-white/10">
                    <svg class="w-5 h-5 mr-3 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-sm font-bold">{{ eventTiming.timeLabel }}</span>
                  </div>
                </div>
              </div>

              <div class="bg-white p-6 rounded-3xl shadow-2xl border border-slate-100 hidden md:block">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Event Commences In</p>
                <EventCountdown :targetDate="event.start_datetime" class="text-slate-900" />
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8 relative z-30">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          
          <div class="lg:col-span-2 space-y-8">
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8 md:p-12">
              <h2 class="text-2xl font-black text-slate-900 mb-6 uppercase tracking-tight flex items-center">
                <span class="w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center mr-3 text-sm">01</span>
                Experience Overview
              </h2>
              <div v-if="event.description" class="prose prose-indigo max-w-none text-slate-600 leading-relaxed text-lg">
                {{ event.description }}
              </div>
              
              <div class="grid grid-cols-2 md:grid-cols-3 gap-8 mt-12 pt-12 border-t border-slate-100">
                <div v-if="event.venue">
                  <p class="text-[10px] font-black text-indigo-500 uppercase tracking-widest mb-1">Location</p>
                  <p class="font-bold text-slate-900">{{ event.venue }}</p>
                </div>
                <div v-if="event.capacity">
                  <p class="text-[10px] font-black text-indigo-500 uppercase tracking-widest mb-1">Availability</p>
                  <p class="font-bold text-slate-900">{{ event.capacity }} Capacity</p>
                </div>
                <div>
                  <p class="text-[10px] font-black text-indigo-500 uppercase tracking-widest mb-1">Status</p>
                  <p class="font-bold text-slate-900 flex items-center">
                    <span class="w-2 h-2 rounded-full mr-2" :class="event.is_active ? 'bg-green-500' : 'bg-red-500'"></span>
                    {{ event.is_active ? 'Booking Live' : 'Sold Out' }}
                  </p>
                </div>
              </div>
            </div>

            <div v-if="event.promotional_media?.length" class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8 md:p-12">
              <h2 class="text-2xl font-black text-slate-900 mb-8 uppercase tracking-tight flex items-center">
                <span class="w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center mr-3 text-sm">02</span>
                Visual Gallery
              </h2>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div v-for="media in event.promotional_media" :key="media.id" class="group relative">
                  <div class="aspect-video rounded-2xl overflow-hidden bg-slate-100 border border-slate-100 shadow-inner">
                    <img v-if="media.media_type === 'image' || isImageFile(media.media_url)" 
                         :src="'/storage/' + media.media_url" 
                         class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" />
                    <video v-else-if="media.media_type === 'video' || isVideoFile(media.media_url)" 
                           :src="'/storage/' + media.media_url" controls class="w-full h-full object-cover"></video>
                  </div>
                  <div v-if="media.title" class="mt-4 px-2">
                    <p class="font-bold text-slate-900 text-sm leading-tight">{{ media.title }}</p>
                    <p class="text-xs text-slate-500 mt-1">{{ media.description }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="space-y-6">
            <div class="bg-white rounded-3xl shadow-xl shadow-indigo-100/50 border border-indigo-50 p-8 sticky top-8">
              
              <div v-if="qr_code" class="flex flex-col items-center mb-8 pb-8 border-b border-slate-100">
                <div class="relative group">
                  <div class="absolute -inset-2 bg-gradient-to-tr from-indigo-500 to-purple-500 rounded-2xl opacity-10 blur-xl group-hover:opacity-20 transition-opacity"></div>
                  <div class="relative bg-white p-3 rounded-2xl border border-slate-100 shadow-sm">
                    <img :src="qr_code" alt="QR" class="w-28 h-28">
                  </div>
                </div>
                <p class="text-[10px] font-bold text-slate-400 uppercase mt-4 tracking-widest">Share this experience</p>
              </div>

              <div class="space-y-4">
                <Link v-if="event.ticket_types?.length" 
                      :href="`/events/${event.id}/tickets`"
                      class="flex items-center justify-center w-full px-8 py-5 bg-indigo-600 text-white rounded-2xl font-black text-sm uppercase tracking-widest hover:bg-indigo-700 transition-all hover:shadow-lg hover:shadow-indigo-200">
                  <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                  Get Tickets
                </Link>
                
                <Link v-if="event.has_table_reservations" 
                      :href="`/events/${event.id}/reserve-table`"
                      class="flex items-center justify-center w-full px-8 py-5 bg-slate-900 text-white rounded-2xl font-black text-sm uppercase tracking-widest hover:bg-slate-800 transition-all">
                  <svg class="w-5 h-5 mr-3 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16m-7 6h7"/></svg>
                  Reserve Table
                </Link>
              </div>

              <div class="md:hidden mt-8 pt-8 border-t border-slate-100">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 text-center">Starts In</p>
                <EventCountdown :targetDate="event.start_datetime" class="justify-center" />
              </div>

              <div v-if="event.statistics" class="mt-10 bg-slate-50 rounded-2xl p-6">
                <div class="flex items-center mb-6">
                  <div class="w-2 h-6 bg-indigo-500 rounded-full mr-3"></div>
                  <h3 class="font-black text-slate-900 uppercase text-xs tracking-widest">Live Activity</h3>
                </div>
                <div class="space-y-5">
                  <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-tighter">Tickets Issued</span>
                    <span class="text-sm font-black text-slate-900">{{ event.statistics.total_tickets_sold }}</span>
                  </div>
                  <div class="w-full bg-slate-200 h-1.5 rounded-full overflow-hidden">
                    <div class="bg-indigo-600 h-full rounded-full" :style="`width: ${Math.min((event.statistics.total_tickets_sold / (event.capacity || 100)) * 100, 100)}%`"></div>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-tighter">Tables Secured</span>
                    <span class="text-sm font-black text-slate-900">{{ event.statistics.total_tables_reserved }}</span>
                  </div>
                </div>
              </div>

            </div>
          </div>

        </div>
      </div>
    </div>
  </PublicLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link, Head } from '@inertiajs/vue3'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import EventCountdown from '@/Components/EventCountdown.vue'

const props = defineProps({
  event: Object,
  qr_code: String,
})

const isImageFile = (url) => {
  if (!url) return false
  const extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']
  return extensions.some(ext => url.toLowerCase().endsWith(ext))
}

const isVideoFile = (url) => {
  if (!url) return false
  const extensions = ['mp4', 'mov', 'avi', 'wmv', 'webm']
  return extensions.some(ext => url.toLowerCase().endsWith(ext))
}

const formatNumber = (num) => {
  return new Intl.NumberFormat('en-NG').format(num)
}

const eventTiming = computed(() => {
  if (!props.event.start_datetime) {
    return { dateLabel: 'Date TBD', timeLabel: 'Time TBD' }
  }

  const start = new Date(props.event.start_datetime)
  const end = props.event.end_datetime ? new Date(props.event.end_datetime) : null
  
  const fullDate = (d) => d.toLocaleDateString('en-US', { 
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
  })
  const shortDate = (d) => d.toLocaleDateString('en-US', { 
    month: 'short', day: 'numeric' 
  })
  const timeOnly = (d) => d.toLocaleTimeString('en-US', { 
    hour: 'numeric', minute: '2-digit', hour12: true 
  })

  if (!end) {
    return {
      dateLabel: fullDate(start),
      timeLabel: `Starts at ${timeOnly(start)}`
    }
  }

  const isMultiDay = start.toDateString() !== end.toDateString()
  if (isMultiDay) {
    return {
      dateLabel: `${shortDate(start)} – ${shortDate(end)}`,
      timeLabel: `${timeOnly(start)} to ${timeOnly(end)}`
    }
  }

  return {
    dateLabel: fullDate(start),
    timeLabel: `${timeOnly(start)} – ${timeOnly(end)}`
  }
})
</script>

<style scoped>
.prose p {
  margin-bottom: 1.5em;
}
</style>