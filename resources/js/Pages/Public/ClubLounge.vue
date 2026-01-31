<script setup>
import { onMounted } from 'vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import AOS from 'aos'
import { Music, Calendar, Clock, Sparkles, GlassWater } from 'lucide-vue-next'

const props = defineProps({ events: Array })

onMounted(() => {
  AOS.init({ duration: 1000, once: true })
})
</script>

<template>
  <PublicLayout>
    <Head title="Club & Rooftop Bar | Nightlife at MooreLife" />

    <section class="relative h-[80vh] flex items-center justify-center bg-slate-950 overflow-hidden">
      <div class="absolute inset-0 z-0">
        <img 
          src="https://images.unsplash.com/photo-1571450669798-fcb4c543f6a4?auto=format&fit=crop&q=80" 
          class="w-full h-full object-cover opacity-50 scale-105 animate-ken-burns"
        />
        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/20 to-transparent" />
      </div>

      <div class="relative z-10 text-center px-6" data-aos="zoom-out">
        <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-indigo-500/20 backdrop-blur-md border border-indigo-400/30 mb-6">
          <Music class="w-3.5 h-3.5 text-indigo-400" />
          <span class="text-[10px] font-black uppercase tracking-[0.5em] text-indigo-200">After Dark Experience</span>
        </div>
        <h1 class="text-7xl md:text-9xl font-black text-white tracking-tighter leading-none mb-4">The Club.</h1>
        <p class="text-indigo-100/60 font-light text-xl max-w-xl mx-auto">Where curated rhythms meet the salt of the Atlantic night.</p>
      </div>
    </section>

    <section class="py-32 bg-slate-950 text-white border-b border-white/5">
      <div class="container mx-auto px-6">
        <div class="grid lg:grid-cols-12 gap-16 items-center">
          <div class="lg:col-span-6 space-y-8" data-aos="fade-right">
            <h2 class="text-4xl md:text-5xl font-black tracking-tighter">Sophisticated <br/><span class="text-indigo-500">Nightlife.</span></h2>
            <div 
              v-html="content('club.description')" 
              class="prose prose-invert prose-lg font-light text-indigo-100/70"
            ></div>
            
            <div class="flex gap-8 pt-6 border-t border-white/10">
              <div>
                <p class="text-indigo-400 font-black text-xs uppercase tracking-widest mb-1">Dress Code</p>
                <p class="text-sm font-light">Smart Casual / Elegant</p>
              </div>
              <div>
                <p class="text-indigo-400 font-black text-xs uppercase tracking-widest mb-1">Entry</p>
                <p class="text-sm font-light">Residents & Guestlist</p>
              </div>
            </div>
          </div>

          <div class="lg:col-span-5 lg:col-start-8" data-aos="fade-left">
            <div class="relative group">
              <div class="absolute -inset-4 bg-indigo-500/20 blur-2xl rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
              <img 
                src="https://images.unsplash.com/photo-1551024709-8f23befc6f87?auto=format&fit=crop&q=80" 
                class="relative rounded-[3rem] w-full h-[600px] object-cover shadow-2xl transition-transform duration-700 group-hover:scale-[1.02]"
              />
              <div class="absolute bottom-10 left-10 p-8 bg-slate-950/80 backdrop-blur-xl rounded-3xl border border-white/10">
                 <GlassWater class="w-8 h-8 text-indigo-400 mb-4" />
                 <p class="text-lg font-black italic">"Signature cocktails, globally inspired."</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="py-32 bg-slate-950">
      <div class="container mx-auto px-6">
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-20 gap-6">
          <div data-aos="fade-up">
            <h2 class="text-5xl font-black text-white tracking-tighter">Upcoming Events</h2>
            <p class="text-indigo-300/50 mt-2 font-light uppercase tracking-widest text-xs">Curated weekly lineups</p>
          </div>
          <Link href="/events" class="text-indigo-400 font-black text-xs uppercase tracking-widest hover:text-white transition-colors">
            Book a Table →
          </Link>
        </div>

        <div class="grid md:grid-cols-2 gap-10">
          <div
            v-for="(event, index) in events"
            :key="event.id"
            class="group relative p-10 rounded-[3rem] bg-white/5 border border-white/10 hover:bg-indigo-600/10 hover:border-indigo-500/50 transition-all duration-500"
            data-aos="fade-up"
            :data-aos-delay="index * 100"
          >
            <div class="flex items-start justify-between mb-8">
              <div class="w-16 h-16 rounded-2xl bg-indigo-500/20 flex items-center justify-center group-hover:bg-indigo-500 transition-colors">
                <Sparkles class="w-8 h-8 text-indigo-400 group-hover:text-white" />
              </div>
              <div class="text-right">
                <div class="flex items-center gap-2 text-indigo-300 text-xs font-bold uppercase tracking-widest">
                  <Calendar class="w-3 h-3" />
                  {{ event.event_date }}
                </div>
                <div class="flex items-center gap-2 text-slate-500 text-xs font-bold uppercase tracking-widest mt-1">
                  <Clock class="w-3 h-3" />
                  {{ event.start_time }}
                </div>
              </div>
            </div>

            <h3 class="text-3xl font-black text-white mb-4 group-hover:text-indigo-400 transition-colors">
              {{ event.title }}
            </h3>
            <p class="text-slate-400 font-light leading-relaxed mb-8">
              {{ event.description }}
            </p>
            
            <button class="px-8 py-3 rounded-full border border-white/20 text-[10px] font-black uppercase tracking-widest hover:bg-white hover:text-slate-950 transition-all">
              Add to Calendar
            </button>
          </div>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>

<style scoped>
@keyframes ken-burns {
  0% { transform: scale(1); }
  100% { transform: scale(1.1); }
}
.animate-ken-burns {
  animation: ken-burns 20s linear infinite alternate;
}
</style>