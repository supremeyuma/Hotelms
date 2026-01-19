<script setup>
import { onMounted } from 'vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
import AOS from 'aos'
import { FileText, ShieldAlert, HelpCircle, PhoneCall } from 'lucide-vue-next'

onMounted(() => {
  AOS.init({ duration: 1000, once: true })
})
</script>

<template>
  <PublicLayout>
    <Head title="Hotel Policies | MooreLife Resort" />

    <section class="pt-32 pb-20 bg-slate-50 border-b border-slate-100">
      <div class="container mx-auto px-6">
        <div class="max-w-4xl" data-aos="fade-up">
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 border border-indigo-100 mb-6">
            <FileText class="w-3.5 h-3.5 text-indigo-600" />
            <span class="text-[10px] font-black uppercase tracking-widest text-indigo-600">Terms & Conditions</span>
          </div>
          <h1 class="text-5xl md:text-7xl font-black text-slate-950 tracking-tighter mb-6">
            Our Policies.
          </h1>
          <p class="text-xl text-slate-500 font-light max-w-2xl leading-relaxed">
            Ensuring a seamless, safe, and exclusive experience for every guest at MooreLife.
          </p>
        </div>
      </div>
    </section>

    <section class="py-24 bg-white">
      <div class="container mx-auto px-6">
        <div class="grid lg:grid-cols-12 gap-20">
          
          <div class="lg:col-span-8">
            <div 
              class="prose prose-slate prose-lg max-w-none 
                     prose-h3:text-2xl prose-h3:font-black prose-h3:tracking-tight prose-h3:text-slate-900 prose-h3:mb-4
                     prose-p:text-slate-500 prose-p:font-light prose-p:leading-relaxed prose-p:mb-12
                     policies-content"
              v-html="content('policies.content')"
              data-aos="fade-up"
            ></div>
          </div>

          <div class="lg:col-span-4" data-aos="fade-left">
            <div class="sticky top-32 space-y-8">
              
              <div class="p-10 rounded-[2.5rem] bg-slate-50 border border-slate-100 space-y-6">
                <HelpCircle class="w-8 h-8 text-indigo-600" />
                <h4 class="text-xl font-black text-slate-950">Questions?</h4>
                <p class="text-slate-500 text-sm font-light leading-relaxed">
                  If you have questions regarding your booking or specific stay requirements, our team is here to assist.
                </p>
                <div class="pt-4 space-y-4">
                  <a href="mailto:concierge@moorelife.com" class="flex items-center gap-3 text-sm font-black text-slate-950 hover:text-indigo-600 transition-colors">
                    <PhoneCall class="w-4 h-4" />
                    Talk to Concierge
                  </a>
                </div>
              </div>

              <div class="p-10 rounded-[2.5rem] bg-indigo-600 text-white space-y-4">
                <ShieldAlert class="w-8 h-8 text-indigo-300" />
                <h4 class="text-lg font-black">Right of Admission</h4>
                <p class="text-indigo-100 text-sm font-light leading-relaxed opacity-80">
                  To maintain our exclusive atmosphere, management reserves the right to refuse entry to the club or resort premises.
                </p>
              </div>

            </div>
          </div>

        </div>
      </div>
    </section>
  </PublicLayout>
</template>

<style scoped>
/* Scoped adjustments for the DB-rendered content */
:deep(.policies-content h3) {
  @apply border-b border-slate-100 pb-4 mt-12;
}

:deep(.policies-content h3:first-child) {
  @apply mt-0;
}
</style>