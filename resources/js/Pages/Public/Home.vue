<script setup>
import { onMounted, ref, onUnmounted } from 'vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head, usePage, Link } from '@inertiajs/vue3'
import AOS from 'aos'
import Lenis from '@studio-freight/lenis'
import 'aos/dist/aos.css'
import { 
   ChevronDown, Star, Waves, Wifi, Car, Sparkles, 
   Music, WavesLadder, Martini, ArrowRight, ShieldCheck,
   Calendar as CalendarIcon, Clock, Users, Ticket,
 } from 'lucide-vue-next'

const page = usePage()
const isLoading = ref(true)
const mouseX = ref(0)
const mouseY = ref(0)
const isHoveringImage = ref(false)
const scrollProgress = ref(0)

// Featured events from page props
const featuredEvents = page.props.featured_events ?? []

const gallery = page.props.gallery ?? {
  hero: [],
  experience: [],
  club: [],
}
console.log(gallery);

const amenities = [
  { icon: Waves, label: 'Private Beach', desc: 'Pristine white sands', delay: 100 },
  { icon: Martini, label: 'Rooftop Bar', desc: 'Crafted cocktails', delay: 200 },
  { icon: WavesLadder, label: 'Infinity Pool', desc: 'Overlooking the ocean', delay: 300 },
  { icon: Wifi, label: 'Fiber Wi-Fi', desc: 'Always connected', delay: 400 },
  { icon: Car, label: 'Secure Parking', desc: '24/7 Monitored', delay: 500 },
  { icon: ShieldCheck, label: 'VIP Security', desc: 'Complete privacy', delay: 600 },
]

// Magnetic Button Logic
const magneticButton = (e) => {
  const btn = e.currentTarget
  const rect = btn.getBoundingClientRect()
  const x = e.clientX - rect.left - rect.width / 2
  const y = e.clientY - rect.top - rect.height / 2
  btn.style.transform = `translate(${x * 0.3}px, ${y * 0.3}px)`
}

const resetMagnetic = (e) => {
  e.currentTarget.style.transform = `translate(0px, 0px)`
}

onMounted(() => {
  // 1. Initialize Smooth Scroll (Lenis)
  const lenis = new Lenis({
    duration: 1.2,
    easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
    smoothWheel: true,
  })

  function raf(time) {
    lenis.raf(time)
    requestAnimationFrame(raf)
  }
  requestAnimationFrame(raf)

  // 2. Scroll Progress Tracking
  lenis.on('scroll', ({ progress }) => {
    scrollProgress.value = progress
  })

  // 3. Mouse Movement
  window.addEventListener('mousemove', (e) => {
    mouseX.value = e.clientX
    mouseY.value = e.clientY
  })

  // 4. AOS & Loading
  AOS.init({ duration: 1000, once: true, easing: 'ease-in-out-cubic' })

  setTimeout(() => {
    isLoading.value = false
    setTimeout(() => AOS.refresh(), 500)
  }, 2000)
})
</script>

<template>
  <PublicLayout>
    <Head>
      <title>{{ content('seo.home.title', content('site.name')) }}</title>
      <meta name="description" :content="content('seo.home.description')" />
    </Head>

    <div 
      class="custom-cursor hidden lg:block"
      :class="{ 'cursor-grow': isHoveringImage }"
      :style="{ left: `${mouseX}px`, top: `${mouseY}px` }"
    >
      <div v-if="isHoveringImage" class="flex flex-col items-center">
        <span class="text-[8px] font-black uppercase tracking-[0.2em] text-black">Explore</span>
      </div>
    </div>

    <Transition name="curtain">
      <div v-if="isLoading" class="fixed inset-0 z-[100] bg-slate-950 flex flex-col items-center justify-center">
        <div class="overflow-hidden">
          <h2 class="text-white text-2xl font-black uppercase tracking-[0.8em] animate-reveal-text">
            MooreLife
          </h2>
        </div>
        <div class="w-48 h-[1px] bg-white/10 mt-6 relative overflow-hidden">
          <div class="absolute inset-0 bg-indigo-500 animate-loading-bar"></div>
        </div>
      </div>
    </Transition>

    <div class="fixed top-0 left-0 w-full h-[3px] z-[60]">
      <div 
        class="h-full bg-indigo-500 transition-all duration-150" 
        :style="{ width: `${scrollProgress * 100}%` }"
      ></div>
    </div>

    <div :class="{ 'opacity-0 scale-95': isLoading, 'opacity-100 scale-100 transition-all duration-[1.5s] ease-out': !isLoading }">
      
      <section class="relative h-screen w-full overflow-hidden flex items-center justify-center">
        <div class="absolute inset-0 z-0">
          <div
            class="absolute inset-0 bg-cover bg-center bg-no-repeat scale-110 animate-ken-burns"
            :style="{ backgroundImage: `url(${content('home.hero.image')})` }"
          />
          <div class="absolute inset-0 bg-gradient-to-b from-black/80 via-transparent to-slate-950" />
        </div>

        <div class="relative z-10 container mx-auto px-6 text-center text-white">
          <div data-aos="fade-down" class="inline-flex items-center gap-2 px-5 py-2 rounded-full bg-white/5 backdrop-blur-xl border border-white/10 mb-8">
            <Star class="w-3 h-3 text-amber-400 fill-amber-400" />
            <span class="text-[9px] font-black uppercase tracking-[0.5em]">Where Luxury Meets the Horizon</span>
          </div>

          <h1 
            data-aos="zoom-out"
            data-aos-delay="400"
            v-html="content('home.hero.title')"
            class="text-6xl md:text-[11rem] font-black mb-10 tracking-[ -0.05em] leading-[0.8] drop-shadow-2xl"
          />

          <div 
            data-aos="fade-up" 
            data-aos-delay="800"
            class="flex flex-col sm:flex-row items-center justify-center gap-8 mt-12"
          >
            <Link 
              href="/booking" 
              class="btn-primary group shadow-2xl"
              @mousemove="magneticButton"
              @mouseleave="resetMagnetic"
            >
              Reserve Your Stay
              <div class="w-8 h-8 rounded-full bg-black flex items-center justify-center group-hover:bg-indigo-600 transition-colors">
                <ArrowRight class="w-4 h-4 text-white" />
              </div>
            </Link>

            <Link href="/gallery" class="btn-secondary group">
              <span>View Lookbook</span>
              <div class="w-1 h-1 bg-white rounded-full group-hover:w-6 transition-all"></div>
            </Link>
          </div>
        </div>

        <div class="absolute bottom-12 left-0 w-full px-12 flex justify-between items-end text-white/40 text-[10px] font-black uppercase tracking-[0.3em]">
          <div class="flex flex-col gap-2">
            <span>Lat: 5.3600° N</span>
            <span>Long: 3.9400° W</span>
          </div>
          <div class="flex flex-col items-center gap-4">
            <div class="mouse-scroll"></div>
          </div>
          <div class="text-right">
            <span>Scroll to Explore</span>
          </div>
        </div>
      </section>

      <section id="experience" class="relative py-40 bg-white overflow-hidden">
        <div class="container mx-auto px-6">
          <div class="grid lg:grid-cols-12 gap-24 items-center">
            <div class="lg:col-span-6">
              <div 
                class="parallax-image-wrap rounded-[3rem] overflow-hidden"
                @mouseenter="isHoveringImage = true"
                @mouseleave="isHoveringImage = false"
                data-aos="reveal-right"
              >
                <img
                  :src="gallery.hero[0]?.image_url ?? content('home.hero.image')"
                  class="w-full aspect-[4/5] object-cover scale-110 hover:scale-100 transition-transform duration-[2s]"
                />
              </div>
            </div>

            <div class="lg:col-span-6 space-y-12" data-aos="fade-up">
              <div class="space-y-6">
                <div class="h-1 w-20 bg-indigo-600"></div>
                <h2 class="text-5xl md:text-8xl font-black text-slate-900 leading-[0.9] tracking-tighter">
                  The MooreLife <br/> Experience
                </h2>
              </div>
              <p class="text-2xl text-slate-400 font-light leading-relaxed max-w-xl">
                {{ content('home.experience.text') }}
              </p>
              <div class="flex gap-16 pt-10">
                <div v-for="stat in [{v:'24/7', t:'Concierge'}, {v:'100%', t:'Private'}]" :key="stat.t">
                  <span class="block text-4xl font-black text-slate-900">{{ stat.v }}</span>
                  <span class="text-[10px] font-black text-slate-300 uppercase tracking-widest">{{ stat.t }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="py-40 bg-slate-950 text-white relative">
        <div class="container mx-auto px-6 relative z-10">
          <div class="flex flex-col md:flex-row md:items-end justify-between mb-32 gap-10">
            <h2 class="text-5xl md:text-8xl font-black tracking-tighter" data-aos="fade-right">Unrivaled Comfort</h2>
            <p class="text-slate-500 max-w-sm text-lg font-light" data-aos="fade-left">From sunset cocktails to high-speed connectivity, we have thought of everything.</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
            <div 
              v-for="(amenity, index) in amenities" 
              :key="amenity.label"
              data-aos="fade-up"
              :data-aos-delay="index * 100"
              class="group p-16 border border-white/5 hover:bg-white/[0.02] transition-colors relative overflow-hidden"
            >
              <component :is="amenity.icon" class="w-8 h-8 text-indigo-500 mb-8" />
              <h4 class="text-2xl font-black mb-4">{{ amenity.label }}</h4>
              <p class="text-slate-500 font-light leading-relaxed">{{ amenity.desc }}</p>
              <div class="absolute bottom-0 left-0 w-0 h-1 bg-indigo-500 group-hover:w-full transition-all duration-700"></div>
            </div>
          </div>
        </div>
      </section>

      <!-- Featured Events Section -->
      <section v-if="featuredEvents.length > 0" class="py-40 bg-gradient-to-b from-slate-50 to-white">
        <div class="container mx-auto px-6">
          <div class="flex flex-col md:flex-row md:items-end justify-between mb-32 gap-10">
            <div class="space-y-6">
              <div class="h-1 w-20 bg-indigo-600"></div>
              <h2 class="text-5xl md:text-8xl font-black text-slate-900 leading-[0.9] tracking-tighter">
                Upcoming <br/> Events
              </h2>
            </div>
            <Link href="/events" class="inline-flex items-center gap-3 px-8 py-4 bg-slate-900 text-white font-black rounded-full text-sm uppercase tracking-widest hover:scale-105 transition-transform">
              View All Events
              <ArrowRight class="w-4 h-4" />
            </Link>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <div 
              v-for="event in featuredEvents" 
              :key="event.id"
              class="group bg-white rounded-3xl shadow-xl hover:shadow-2xl transition-all duration-500 overflow-hidden hover:-translate-y-2"
              data-aos="fade-up"
            >
              <!-- Event Image -->
              <div class="relative h-64 overflow-hidden">
                <img 
                  :src="event.promotional_media?.[0]?.image_url ?? '/images/default-event.jpg'"
                  :alt="event.title"
                  class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
                />
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent" />
                
                <!-- Event Date Badge -->
                <div class="absolute top-6 left-6">
                  <div class="bg-white/95 backdrop-blur-sm rounded-2xl p-3 text-center min-w-[60px] shadow-lg">
                    <div class="text-2xl font-black text-slate-900">
                      {{ new Date(event.start_datetime).getDate() }}
                    </div>
                    <div class="text-xs font-black text-indigo-600 uppercase tracking-wider">
                      {{ new Date(event.start_datetime).toLocaleString('en-US', { month: 'short' }) }}
                    </div>
                  </div>
                </div>

                <!-- Featured Badge -->
                <div v-if="event.is_featured" class="absolute top-6 right-6">
                  <div class="bg-amber-500 text-white px-4 py-2 rounded-full text-xs font-black uppercase tracking-wider shadow-lg">
                    Featured
                  </div>
                </div>
              </div>

              <!-- Event Content -->
              <div class="p-8 space-y-6">
                <div class="space-y-4">
                  <h3 class="text-2xl font-black text-slate-900 leading-tight">
                    {{ event.title }}
                  </h3>
                  <p class="text-slate-600 font-light line-clamp-3 leading-relaxed">
                    {{ event.description }}
                  </p>
                </div>

                <!-- Event Details -->
                <div class="space-y-3">
                  <div class="flex items-center gap-3 text-sm text-slate-500">
                    <CalendarIcon class="w-4 h-4" />
                    <span class="font-medium">
                      {{ new Date(event.start_datetime).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}
                    </span>
                  </div>
                  <div class="flex items-center gap-3 text-sm text-slate-500">
                    <Clock class="w-4 h-4" />
                    <span class="font-medium">
                      {{ new Date(event.start_datetime).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) }}
                      {{ event.end_datetime ? `- ${new Date(event.end_datetime).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}` : '' }}
                    </span>
                  </div>
                  <div v-if="event.venue" class="flex items-center gap-3 text-sm text-slate-500">
                    <Users class="w-4 h-4" />
                    <span class="font-medium">{{ event.venue }}</span>
                  </div>
                  <div v-if="event.ticket_types?.length > 0" class="flex items-center gap-3 text-sm text-slate-500">
                    <Ticket class="w-4 h-4" />
                    <span class="font-medium">Tickets from ₦{{ Math.min(...event.ticket_types.map(t => t.price)).toLocaleString() }}</span>
                  </div>
                </div>

                <!-- CTA Buttons -->
                <div class="flex gap-4 pt-4">
                  <Link 
                    :href="`/events/${event.id}`" 
                    class="flex-1 text-center px-6 py-3 bg-slate-900 text-white font-black rounded-full text-sm uppercase tracking-wider hover:scale-105 transition-transform"
                  >
                    View Details
                  </Link>
                  <Link 
                    v-if="event.ticket_types?.length > 0"
                    :href="`/events/${event.id}/tickets`"
                    class="flex-1 text-center px-6 py-3 bg-indigo-600 text-white font-black rounded-full text-sm uppercase tracking-wider hover:bg-indigo-700 transition-colors"
                  >
                    Get Tickets
                  </Link>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="py-40 bg-white">
        <div class="container mx-auto px-6">
          <div class="relative rounded-[4rem] overflow-hidden group shadow-3xl">
            <div 
              @mouseenter="isHoveringImage = true"
              @mouseleave="isHoveringImage = false"
              class="h-[700px] overflow-hidden"
            >
              <img
                v-if="gallery.club?.[0]"
                :src="gallery.club[0].image_url"
                class="w-full h-full object-cover transition-transform duration-[5s] group-hover:scale-110"
              />
              <div class="absolute inset-0 bg-black/70 group-hover:bg-black/40 transition-colors" />
            </div>
            
            <div class="absolute inset-0 flex flex-col items-center justify-center text-center p-6 pointer-events-none">
              <div data-aos="zoom-in" class="space-y-8">
                <Music class="w-12 h-12 text-white mx-auto mb-4" />
                <h3 class="text-5xl md:text-[7rem] font-black text-white tracking-tighter leading-none">Club & Rooftop Bar</h3>
                <div class="max-w-xl text-white/80 text-xl font-light mx-auto" v-html="content('club.description')" />
                <Link href="/club-lounge" class="pointer-events-auto inline-block px-12 py-5 bg-white text-black font-black rounded-full uppercase text-xs tracking-widest hover:scale-110 transition-transform mt-8">
                  Explore Nightlife
                </Link>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </PublicLayout>
</template>

<style scoped>
/* LOADER ANIMATIONS */
.animate-reveal-text {
  animation: revealText 1s cubic-bezier(0.77, 0, 0.175, 1) forwards;
}
@keyframes revealText {
  0% { transform: translateY(100%); opacity: 0; }
  100% { transform: translateY(0); opacity: 1; }
}
.animate-loading-bar {
  animation: loadingBar 2s ease-in-out infinite;
}
@keyframes loadingBar {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}

/* CURTAIN TRANSITION */
.curtain-leave-active {
  transition: transform 1.2s cubic-bezier(0.85, 0, 0.15, 1);
}
.curtain-leave-to {
  transform: translateY(-100%);
}

/* CUSTOM CURSOR */
.custom-cursor {
  position: fixed;
  width: 12px;
  height: 12px;
  background-color: white;
  border-radius: 50%;
  pointer-events: none;
  z-index: 9999;
  mix-blend-mode: difference;
  transition: width 0.5s cubic-bezier(0.23, 1, 0.32, 1), height 0.5s cubic-bezier(0.23, 1, 0.32, 1);
  transform: translate(-50%, -50%);
}
.cursor-grow {
  width: 100px;
  height: 100px;
  background-color: white;
  mix-blend-mode: normal;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* BUTTONS */
.btn-primary {
  @apply relative px-10 py-5 bg-white text-black font-black rounded-full transition-all flex items-center gap-6 text-sm uppercase tracking-widest;
  transition: transform 0.1s ease-out;
}
.btn-secondary {
  @apply flex items-center gap-4 text-white font-black text-sm uppercase tracking-widest;
}

/* SCROLL UI */
.mouse-scroll {
  width: 20px;
  height: 35px;
  border: 1px solid rgba(255,255,255,0.3);
  border-radius: 20px;
  position: relative;
}
.mouse-scroll::before {
  content: '';
  width: 2px;
  height: 5px;
  background: white;
  position: absolute;
  left: 50%;
  top: 6px;
  transform: translateX(-50%);
  animation: scrollAnim 2s infinite;
}
@keyframes scrollAnim {
  0% { transform: translate(-50%, 0); opacity: 0; }
  50% { opacity: 1; }
  100% { transform: translate(-50%, 15px); opacity: 0; }
}

/* REVEAL CLIP PATH */
[data-aos="reveal-right"] {
  clip-path: inset(0 100% 0 0);
  transition: clip-path 1.5s cubic-bezier(0.77, 0, 0.175, 1);
}
[data-aos="reveal-right"].aos-animate {
  clip-path: inset(0 0 0 0);
}

@keyframes ken-burns {
  0% { transform: scale(1); }
  100% { transform: scale(1.15); }
}
.animate-ken-burns {
  animation: ken-burns 20s linear infinite alternate;
}
</style>