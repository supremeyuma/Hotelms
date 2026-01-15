<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
import { ChevronDown, MapPin, Phone, Star } from 'lucide-vue-next'
</script>

<template>
  <PublicLayout>
    <Head>
      <title>{{ content('seo.home.title', content('site.name')) }}</title>
      <meta name="description" :content="content('seo.home.description')" />
    </Head>

    <section class="relative h-[92vh] w-full overflow-hidden flex items-center justify-center">
      <div 
        class="absolute inset-0 bg-cover bg-center bg-no-repeat transition-transform duration-1000 scale-105"
        :style="{ backgroundImage: `url(${content('home.hero.image')})` }"
      >
        <div class="absolute inset-0 bg-black/40 backdrop-blur-[2px]"></div>
      </div>

      <div class="relative z-10 container mx-auto px-6 text-center text-white">
        <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/10 backdrop-blur-md border border-white/20 mb-6 animate-fade-in">
          <Star class="w-4 h-4 text-amber-400 fill-amber-400" />
          <span class="text-xs font-bold uppercase tracking-widest">The Ultimate Beachfront Luxury</span>
        </div>

        <h1 
          v-html="content('home.hero.title')" 
          class="text-5xl md:text-8xl font-black mb-6 tracking-tighter leading-[1.1]"
        ></h1>

        <p class="text-lg md:text-xl text-white/80 max-w-2xl mx-auto mb-10 font-light leading-relaxed">
          {{ content('home.hero.subtitle') }}
        </p>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
          <a href="/booking" class="group relative px-10 py-4 bg-white text-black font-black rounded-full overflow-hidden transition-all hover:pr-12">
            <span class="relative z-10">Book Your Stay</span>
            <div class="absolute right-4 top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 transition-all">→</div>
          </a>
          <a href="#experience" class="px-10 py-4 bg-transparent border border-white/40 text-white font-bold rounded-full hover:bg-white/10 transition-all">
            Explore Resort
          </a>
        </div>
      </div>

      <div class="absolute bottom-10 left-1/2 -translate-x-1/2 animate-bounce opacity-50">
        <ChevronDown class="text-white w-8 h-8" />
      </div>
    </section>

    <section id="experience" class="relative py-24 bg-white overflow-hidden">
      <div class="container mx-auto px-6">
        <div class="grid lg:grid-cols-2 gap-16 items-center">
          
          <div class="relative">
            <div class="aspect-[4/5] rounded-3xl overflow-hidden shadow-2xl">
                <img :src="content('home.hero.image')" class="w-full h-full object-cover" />
            </div>
            <div class="absolute -bottom-10 -right-10 hidden md:block w-72 aspect-square rounded-3xl overflow-hidden border-8 border-white shadow-2xl">
                <img :src="content('home.hero.image')" class="w-full h-full object-cover grayscale" />
            </div>
          </div>

          <div class="space-y-8">
            <div class="space-y-4">
                <h3 class="text-indigo-600 font-black uppercase tracking-[0.3em] text-sm">Paradise Found</h3>
                <h2 class="text-4xl md:text-5xl font-black text-slate-900 leading-tight">
                    The MooreLife <br/> Experience
                </h2>
            </div>
            
            <p class="text-xl text-slate-600 leading-relaxed font-light">
                {{ content('home.experience.text') }}
            </p>

            <div class="pt-6 grid grid-cols-2 gap-8 border-t border-slate-100">
                <div>
                    <h4 class="font-black text-2xl text-slate-900">24/7</h4>
                    <p class="text-sm text-slate-500 uppercase tracking-widest font-bold">Service</p>
                </div>
                <div>
                    <h4 class="font-black text-2xl text-slate-900">100%</h4>
                    <p class="text-sm text-slate-500 uppercase tracking-widest font-bold">Privacy</p>
                </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="py-24 bg-slate-50">
      <div class="container mx-auto px-6">
        <div class="grid md:grid-cols-2 gap-12">
            
            <div class="bg-white p-10 rounded-[2.5rem] shadow-sm border border-slate-100">
                <h3 class="text-2xl font-black mb-8 flex items-center gap-3">
                    <span class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center uppercase text-xs">AM</span>
                    Resort Amenities
                </h3>
                <div 
                  class="prose prose-slate prose-li:font-medium prose-li:text-slate-600 marker:text-indigo-500"
                  v-html="content('amenities.content')"
                ></div>
            </div>

            <div class="space-y-8">
                <div class="bg-indigo-900 text-white p-10 rounded-[2.5rem] shadow-xl relative overflow-hidden group">
                    <div class="relative z-10">
                        <h3 class="text-2xl font-black mb-4">The Club & Lounge</h3>
                        <div class="text-indigo-100/80 leading-relaxed" v-html="content('club.description')"></div>
                    </div>
                    <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full group-hover:scale-150 transition-transform duration-700"></div>
                </div>

                <div class="p-10">
                    <h3 class="text-xl font-black mb-6">Resort Policies</h3>
                    <div class="prose prose-sm prose-h3:text-sm prose-h3:uppercase prose-h3:tracking-widest" v-html="content('policies.content')"></div>
                </div>
            </div>

        </div>
      </div>
    </section>

    <footer class="bg-slate-900 py-16 text-white">
        <div class="container mx-auto px-6 grid md:grid-cols-3 gap-12 border-b border-white/10 pb-16">
            <div class="space-y-4">
                <h2 class="text-xl font-black uppercase tracking-tighter">{{ content('site.name') }}</h2>
                <p class="text-slate-400 text-sm leading-relaxed">{{ content('footer.about') }}</p>
            </div>
            <div class="space-y-4">
                <h4 class="text-xs font-black uppercase tracking-widest text-slate-500">Location</h4>
                <div class="flex items-start gap-3 text-slate-300">
                    <MapPin class="w-5 h-5 text-indigo-400 shrink-0" />
                    <p class="text-sm">{{ content('footer.location') }}</p>
                </div>
            </div>
            <div class="space-y-4">
                <h4 class="text-xs font-black uppercase tracking-widest text-slate-500">Contact</h4>
                <div class="flex items-start gap-3 text-slate-300">
                    <Phone class="w-5 h-5 text-indigo-400 shrink-0" />
                    <p class="text-sm">{{ content('footer.contact') }}</p>
                </div>
            </div>
        </div>
        <div class="container mx-auto px-6 pt-8 text-center text-slate-500 text-xs tracking-widest uppercase">
            &copy; 2026 {{ content('site.name') }}. All Rights Reserved.
        </div>
    </footer>
  </PublicLayout>
</template>

<style scoped>
@keyframes fade-in {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
    animation: fade-in 0.8s ease-out forwards;
}

/* Fix for database strings that might have whitespace */
:deep(ul) {
    list-style-type: disc;
    padding-left: 1.25rem;
}
:deep(li) {
    margin-bottom: 0.5rem;
}
</style>