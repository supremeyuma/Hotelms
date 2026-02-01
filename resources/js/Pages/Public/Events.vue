<template>
  <PublicLayout>
    <Head title="Events | MooreLife Resort" />

    <div class="min-h-screen bg-slate-50">
      <div class="relative bg-indigo-950 pt-32 pb-24 overflow-hidden">
        <div class="absolute inset-0 opacity-20">
          <div class="absolute inset-0 bg-gradient-to-br from-indigo-600/20 to-purple-900/20"></div>
          <svg class="h-full w-full" width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
            <defs>
              <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="0.5" />
              </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#grid)" />
          </svg>
        </div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
          <div class="inline-flex items-center space-x-2 px-3 py-1 mb-6 text-xs font-bold tracking-widest text-indigo-300 uppercase bg-indigo-500/10 border border-indigo-500/20 rounded-full backdrop-blur-md">
            <span class="relative flex h-2 w-2">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
            </span>
            <span>MooreLife Experiences</span>
          </div>
          <h1 class="text-6xl md:text-8xl font-black text-white tracking-tighter mb-6">
            LIVING <br />
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-purple-400">MEMORIES</span>
          </h1>
          <p class="text-lg md:text-xl text-indigo-100/60 max-w-2xl mx-auto font-medium leading-relaxed">
            Curated events designed for the extraordinary. Discover our seasonal calendar and secure your place.
          </p>
        </div>
      </div>

      <section v-if="featuredEvents.length" class="py-24 -mt-16 relative z-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="flex items-center justify-between mb-10 text-white md:text-gray-900">
            <h2 class="text-3xl font-black tracking-tight uppercase">Hot This Month</h2>
            <div class="hidden md:block h-px flex-1 mx-8 bg-gray-200"></div>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div v-for="event in featuredEvents" :key="event.id"
                 class="group relative bg-white rounded-3xl shadow-2xl shadow-indigo-100 overflow-hidden border border-gray-100 hover:border-indigo-200 transition-all duration-500">
              
              <div class="relative h-[400px] overflow-hidden">
                <img v-if="event.image" :src="'/storage/' + event.image"
                     class="w-full h-full object-cover transition-transform duration-1000 group-hover:scale-110" />
                <div v-else class="w-full h-full bg-slate-200 animate-pulse"></div>
                
                <div class="absolute inset-x-4 bottom-4">
                  <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl p-6 text-white shadow-2xl">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                      <div class="space-y-1">
                        <p class="text-[10px] uppercase tracking-[0.2em] font-black text-indigo-300">Countdown to Kickoff</p>
                        <EventCountdown :targetDate="event.start_datetime" class="text-white" />
                      </div>
                      <Link :href="`/events/${event.id}`"
                            class="bg-white text-indigo-950 px-8 py-4 rounded-xl font-bold text-sm uppercase tracking-widest hover:bg-indigo-500 hover:text-white transition-colors text-center">
                        Secure Spot
                      </Link>
                    </div>
                  </div>
                </div>

                <div class="absolute top-6 left-6">
                  <span class="bg-indigo-600 text-white text-[10px] font-black px-4 py-2 rounded-full uppercase tracking-tighter shadow-xl">
                    Featured Event
                  </span>
                </div>
              </div>

              <div class="p-8">
                <div class="flex flex-wrap items-center gap-4 mb-4 text-xs font-bold uppercase tracking-widest text-indigo-600">
                  <span class="flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    {{ formatEventDateTime(event).date }}
                  </span>
                  <span class="text-gray-300">|</span>
                  <span class="flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ formatEventDateTime(event).time }}
                  </span>
                </div>
                <h3 class="text-3xl font-black text-slate-900 mb-4 group-hover:text-indigo-600 transition-colors">{{ event.title }}</h3>
                <p class="text-slate-500 leading-relaxed mb-0">{{ event.description }}</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="py-24 bg-white border-t border-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="mb-16">
            <h2 class="text-4xl font-black text-slate-900 tracking-tight">Full Calendar</h2>
            <p class="text-slate-500 mt-2 font-medium">Browse all upcoming activities and resort specials.</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
            <div v-for="event in allEvents" :key="event.id"
                 class="group bg-slate-50 rounded-3xl p-4 border border-transparent hover:border-indigo-100 hover:bg-white hover:shadow-xl transition-all duration-300">
              
              <div class="relative h-56 overflow-hidden rounded-2xl mb-6">
                <img v-if="event.image" :src="'/storage/' + event.image"
                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                <div class="absolute top-3 right-3">
                  <div class="bg-white/90 backdrop-blur-sm px-3 py-2 rounded-xl shadow-sm">
                    <EventCountdown :targetDate="event.start_datetime" class="scale-90 text-indigo-900" />
                  </div>
                </div>
              </div>

              <div class="px-2 pb-2">
                <div class="text-[10px] font-black uppercase tracking-widest text-indigo-500 mb-2">
                  {{ formatEventDateTime(event).date }}
                </div>
                <h4 class="text-xl font-bold text-slate-900 mb-2 truncate group-hover:text-indigo-600 transition-colors">
                  {{ event.title }}
                </h4>
                <p class="text-sm text-slate-500 mb-6 line-clamp-2">{{ event.description }}</p>
                
                <div class="flex items-center justify-between">
                  <div class="text-xs font-bold text-slate-400 uppercase tracking-tighter">
                    {{ event.venue || 'Main Resort' }}
                  </div>
                  <Link :href="`/events/${event.id}`"
                        class="p-2 text-indigo-600 hover:text-indigo-800 font-black uppercase text-[11px] tracking-widest flex items-center group/btn">
                    Details
                    <svg class="w-4 h-4 ml-1 transform group-hover/btn:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                  </Link>
                </div>
              </div>
            </div>
          </div>

          <div v-if="!allEvents.length && !featuredEvents.length" class="text-center py-20">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-slate-100 text-slate-400 mb-6">
              <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <h3 class="text-2xl font-black text-slate-900">Quiet for Now</h3>
            <p class="text-slate-500 mt-2">Check back soon for new MooreLife experiences.</p>
          </div>
        </div>
      </section>
    </div>
  </PublicLayout>
</template>

<script setup>
import { Link, Head } from '@inertiajs/vue3'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import EventCountdown from '@/Components/EventCountdown.vue'

defineProps({
  featuredEvents: Array,
  allEvents: Array,
})

const formatEventDateTime = (event) => {
  if (!event.start_datetime) {
    return { date: 'Date TBD', time: 'Time TBD' }
  }

  const start = new Date(event.start_datetime)
  const end = event.end_datetime ? new Date(event.end_datetime) : null

  const date = start.toLocaleDateString('en-NG', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })

  const startTime = formatTime(start)
  const endTime = end ? formatTime(end) : null

  return {
    date,
    time: endTime ? `${startTime} – ${endTime}` : startTime,
  }
}

const formatTime = (date) =>
  date.toLocaleTimeString('en-NG', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  })
</script>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>