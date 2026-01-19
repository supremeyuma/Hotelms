<script setup>
import { onMounted, ref, computed } from 'vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import AOS from 'aos'
import { Camera, Maximize2, X, ChevronLeft, ChevronRight } from 'lucide-vue-next'

const page = usePage()
const props = defineProps({ items: Object })

// Lightbox Logic
const selectedImage = ref(null)
const selectedCategoryImages = ref([])
const currentIndex = ref(0)

const openLightbox = (images, index) => {
  selectedCategoryImages.value = images
  currentIndex.value = index
  selectedImage.value = images[index]
  document.body.style.overflow = 'hidden' // Prevent scroll
}

const closeLightbox = () => {
  selectedImage.value = null
  document.body.style.overflow = ''
}

const nextImage = () => {
  currentIndex.value = (currentIndex.value + 1) % selectedCategoryImages.value.length
  selectedImage.value = selectedCategoryImages.value[currentIndex.value]
}

const prevImage = () => {
  currentIndex.value = (currentIndex.value - 1 + selectedCategoryImages.value.length) % selectedCategoryImages.value.length
  selectedImage.value = selectedCategoryImages.value[currentIndex.value]
}

const getImagePath = (path) => `/storage/${path.replace('public/','')}`

const getCategoryDescription = (category) => {
  // 1. Normalize name (e.g., "Rooftop Bar" -> "bar")
  const slug = category.toLowerCase().replace('rooftop', '').trim();
  
  // 2. Construct the key
  const contentKey = `gallery.${slug}.description`;
  
  // 3. Access the content object from global props
  // Adjust 'page.props.content' to match how you share data in HandleInertiaRequests.php
  const siteContent = page.props.content || {}; 
  
  // 4. Return the value if it exists, otherwise a premium fallback
  return siteContent[contentKey] 
    ? siteContent[contentKey] 
    : `Capturing the unique essence and architectural rhythm of our ${category} spaces.`;
}

onMounted(() => {
  AOS.init({ duration: 1000, once: true })
})
</script>

<template>
  <PublicLayout>
    <Head title="Lookbook | Gallery" />

    <section class="relative h-[70vh] flex items-center justify-center bg-slate-950 overflow-hidden">
      <div class="absolute inset-0 z-0">
        <img 
          src="https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&q=80" 
          class="w-full h-full object-cover scale-110 animate-ken-burns opacity-60"
        />
        <div class="absolute inset-0 bg-gradient-to-b from-slate-950/20 via-transparent to-white" />
      </div>
      
      <div class="relative z-10 text-center space-y-6 px-6" data-aos="zoom-out">
        <div class="inline-flex items-center gap-2 px-5 py-2 rounded-full bg-white/10 backdrop-blur-xl border border-white/20 mb-4">
          <Camera class="w-3.5 h-3.5 text-indigo-400" />
          <span class="text-[10px] font-black uppercase tracking-[0.5em] text-white">The MooreLife Aesthetic</span>
        </div>
        <h1 class="text-6xl md:text-[10rem] font-black text-slate-900 tracking-tighter leading-none">The Lookbook</h1>
        <p class="text-slate-300 font-light text-xl max-w-xl mx-auto">A visual journey through our architecture, tranquility, and soul.</p>
      </div>
    </section>

    <div class="bg-white pb-40 -mt-20 relative z-20">
      <div class="max-w-screen-2xl mx-auto px-6 md:px-12">
        
        <div v-for="(images, category) in items" :key="category" class="pt-32">
          <div class="grid lg:grid-cols-12 gap-10 mb-20 items-end">
            <div class="lg:col-span-4 space-y-4" data-aos="fade-right">
              <!--<span class="text-indigo-600 font-black text-[10px] uppercase tracking-[0.4em]">Category {{ Object.keys(items).indexOf(category) + 1 }}</span>-->
              <h2 class="text-5xl md:text-7xl font-black capitalize tracking-tighter text-slate-950">{{ category }}</h2>
            </div>
            <div class="lg:col-span-5 lg:col-start-6" data-aos="fade-left">
              <p class="text-slate-400 font-light text-lg italic border-l-2 border-indigo-100 pl-8 transition-all">
                {{ getCategoryDescription(category) }}
              </p>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
            <div 
              v-for="(img, index) in images" 
              :key="img.id"
              class="group relative cursor-none"
              :class="[
                index % 3 === 1 ? 'lg:-translate-y-16' : '',
                index % 3 === 2 ? 'lg:translate-y-16' : ''
              ]"
              data-aos="fade-up"
              :data-aos-delay="index * 100"
              @click="openLightbox(images, index)"
            >
              <div class="overflow-hidden rounded-[2.5rem] bg-slate-100 aspect-[4/5] shadow-2xl transition-all duration-700 group-hover:shadow-indigo-500/10">
                <img
                  :src="getImagePath(img.image_path)"
                  class="w-full h-full object-cover transition-transform duration-[2s] ease-out group-hover:scale-110"
                />
                <div class="absolute inset-0 bg-indigo-900/40 opacity-0 group-hover:opacity-100 transition-all duration-500 backdrop-blur-[2px] flex items-center justify-center">
                  <div class="w-20 h-20 rounded-full bg-white flex items-center justify-center translate-y-10 group-hover:translate-y-0 transition-all duration-500 shadow-2xl">
                    <Maximize2 class="w-6 h-6 text-indigo-950" />
                  </div>
                </div>
              </div>
              
              <div class="mt-6 flex justify-between items-center px-4 opacity-0 group-hover:opacity-100 transition-opacity">
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">MooreLife Digital Archive</span>
                <span class="text-[10px] font-mono text-slate-300">#0{{ img.id }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <Transition name="fade">
      <div v-if="selectedImage" class="fixed inset-0 z-[100] bg-slate-950/95 backdrop-blur-2xl flex flex-col">
        <div class="p-8 flex justify-between items-center text-white">
          <span class="font-black uppercase tracking-[0.5em] text-[10px]">Gallery Viewer / {{ currentIndex + 1 }} of {{ selectedCategoryImages.length }}</span>
          <button @click="closeLightbox" class="p-4 hover:rotate-90 transition-transform duration-500">
            <X class="w-8 h-8" />
          </button>
        </div>

        <div class="flex-1 relative flex items-center justify-center p-4 md:p-12">
          <button @click="prevImage" class="absolute left-8 z-10 p-4 bg-white/5 hover:bg-white/10 rounded-full transition-colors text-white">
            <ChevronLeft class="w-8 h-8" />
          </button>
          
          <img 
            :src="getImagePath(selectedImage.image_path)" 
            class="max-w-full max-h-full object-contain rounded-xl shadow-3xl"
            data-aos="zoom-in"
          />

          <button @click="nextImage" class="absolute right-8 z-10 p-4 bg-white/5 hover:bg-white/10 rounded-full transition-colors text-white">
            <ChevronRight class="w-8 h-8" />
          </button>
        </div>
        
        <div class="p-12 text-center">
          <p class="text-white/40 text-[10px] font-black uppercase tracking-[0.3em]">MooreLife Resort & Spa — All Rights Reserved</p>
        </div>
      </div>
    </Transition>

    <section class="py-40 bg-white border-t border-slate-50 overflow-hidden">
      <div class="max-w-4xl mx-auto px-6 text-center" data-aos="fade-up">
        <h2 class="text-5xl md:text-8xl font-black text-slate-950 tracking-tighter mb-12">Ready to see it in person?</h2>
        <Link href="/booking" class="px-16 py-6 bg-slate-950 text-white rounded-full font-black uppercase text-xs tracking-[0.4em] hover:bg-indigo-600 transition-all hover:scale-105 shadow-2xl">
          Reserve Your Room
        </Link>
      </div>
    </section>
  </PublicLayout>
</template>

<style scoped>
@keyframes ken-burns {
  0% { transform: scale(1); }
  100% { transform: scale(1.15); }
}
.animate-ken-burns {
  animation: ken-burns 20s linear infinite alternate;
}

.fade-enter-active, .fade-leave-active {
  transition: opacity 0.5s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}

/* Shadow for the lightbox image */
.shadow-3xl {
  box-shadow: 0 50px 100px -20px rgba(0, 0, 0, 0.7);
}

/* Custom transitions for the image offset to prevent jarring moves on resize */
@media (min-width: 1024px) {
  .grid > div {
    transition: transform 1.2s cubic-bezier(0.22, 1, 0.36, 1);
  }
}
</style>