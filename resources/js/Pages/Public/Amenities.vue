<script setup>
import { onMounted } from 'vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
import AOS from 'aos'
import { 
  Waves, Martini, WavesLadder, Wifi, Car, 
  ShieldCheck, Utensils, Coffee, Leaf 
} from 'lucide-vue-next'

const props = defineProps({
  // props if needed
})

// Define a structured list to pair with your DB content for a richer UI
const featureAmenities = [
  { icon: Waves, title: 'Private Beach', desc: 'Exclusive access to pristine sands and private cabanas.', delay: 100 },
  { icon: WavesLadder, title: 'Infinity Pool', desc: 'Temperature-controlled waters overlooking the Atlantic.', delay: 200 },
  { icon: Martini, title: 'Rooftop Bar', desc: 'Signature cocktails served with 360° coastal views.', delay: 300 },
  { icon: Utensils, title: 'Fine Dining', desc: 'A fusion of local flavors and international culinary arts.', delay: 400 },
  { icon: Leaf, title: 'Wellness Spa', desc: 'Holistic treatments designed to restore balance.', delay: 500 },
  { icon: ShieldCheck, title: '24/7 Security', desc: 'Unobtrusive, world-class protection for your peace of mind.', delay: 600 },
]

onMounted(() => {
  AOS.init({ duration: 1000, once: true })
})
</script>

<template>
  <PublicLayout>
    <Head title="Amenities & Facilities | MooreLife" />

    <section class="relative h-[50vh] flex items-center bg-slate-950 overflow-hidden">
      <div class="absolute inset-0 opacity-40">
        <img 
          src="https://images.unsplash.com/photo-1576013551627-0cc20b96c2a7?auto=format&fit=crop&q=80" 
          class="w-full h-full object-cover"
        />
      </div>
      <div class="container mx-auto px-6 relative z-10">
        <div class="max-w-3xl" data-aos="fade-right">
          <span class="text-indigo-400 font-black text-[10px] uppercase tracking-[0.5em] mb-4 block">Exceptional Living</span>
          <h1 class="text-6xl md:text-8xl font-black text-white tracking-tighter leading-none">
            Our Facilities.
          </h1>
        </div>
      </div>
    </section>

    <section class="py-32 bg-white">
      <div class="container mx-auto px-6">
        <div class="grid lg:grid-cols-12 gap-20">
          
          <div class="lg:col-span-8">
            <div class="grid md:grid-cols-2 gap-8">
              <div 
                v-for="item in featureAmenities" 
                :key="item.title"
                class="p-12 rounded-[3rem] bg-slate-50 border border-slate-100 hover:bg-white hover:shadow-2xl hover:shadow-indigo-500/5 transition-all duration-500 group"
                data-aos="fade-up"
                :data-aos-delay="item.delay"
              >
                <div class="w-14 h-14 rounded-2xl bg-white flex items-center justify-center mb-8 shadow-sm group-hover:bg-indigo-600 transition-colors">
                  <component :is="item.icon" class="w-6 h-6 text-indigo-600 group-hover:text-white transition-colors" />
                </div>
                <h3 class="text-2xl font-black text-slate-900 mb-4">{{ item.title }}</h3>
                <p class="text-slate-500 font-light leading-relaxed">
                  {{ item.desc }}
                </p>
              </div>
            </div>
          </div>

          <div class="lg:col-span-4" data-aos="fade-left">
            <div class="sticky top-32 space-y-10">
              <div class="space-y-4">
                <h2 class="text-xs font-black uppercase tracking-[0.4em] text-indigo-600">Full Directory</h2>
                <div 
                  class="prose prose-slate prose-lg max-w-none font-light amenities-html-content"
                  v-html="content('amenities.content')"
                ></div>
              </div>

              <div class="p-10 rounded-[2.5rem] bg-slate-950 text-white space-y-6">
                <Coffee class="w-8 h-8 text-indigo-400" />
                <h4 class="text-xl font-black">Need something specific?</h4>
                <p class="text-slate-400 text-sm font-light">Our concierge is available 24/7 to arrange private excursions or specific requirements.</p>
                <a href="tel:+234000000" class="block text-center py-4 bg-white text-slate-950 rounded-full font-black text-[10px] uppercase tracking-widest hover:bg-indigo-500 hover:text-white transition-all">
                  Contact Concierge
                </a>
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>

    <section class="py-40 bg-slate-50 overflow-hidden">
      <div class="container mx-auto px-6 text-center">
        <div class="max-w-4xl mx-auto space-y-8" data-aos="zoom-in">
          <span class="text-slate-300 text-6xl font-serif italic">“</span>
          <p class="text-3xl md:text-5xl font-light text-slate-900 leading-tight tracking-tight">
            Luxury is not just an amenity; it’s a <span class="font-black italic">state of mind</span> we provide through every detail.
          </p>
          <div class="h-1 w-20 bg-indigo-500 mx-auto mt-12"></div>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>

<style scoped>
/* Scoped styling to clean up the HTML coming from your DB */
:deep(.amenities-html-content ul) {
  list-style: none;
  padding: 0;
}

:deep(.amenities-html-content li) {
  @apply py-4 border-b border-slate-100 flex items-center gap-4 text-slate-600 font-medium;
}

:deep(.amenities-html-content li::before) {
  content: '';
  @apply w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0;
}
</style>