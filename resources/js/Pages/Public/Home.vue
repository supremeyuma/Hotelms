<!-- resources/js/Pages/Public/Home.vue -->
<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
</script>

<template>
  <PublicLayout>
    <Head>
      <title>{{ content('seo.home.title') }}</title>
      <meta name="description" :content="content('seo.home.description')" />
      <meta name="keywords" :content="content('seo.home.keywords')" />
    </Head>
    <section
      class="h-[85vh] flex items-center justify-center text-center bg-cover bg-center"
      :style="{ backgroundImage: `url(${content('home.hero.image')})` }"
    >
      <div class="bg-black/60 p-12 rounded-3xl text-white max-w-2xl">
        <h1 class="text-4xl md:text-5xl font-black mb-4">
          {{ content('home.hero.title', 'Private Beachfront Resort') }}
        </h1>

        <p class="text-lg mb-8">
          {{ content('home.hero.subtitle') }}
        </p>

        <a
          href="/booking"
          class="inline-block px-8 py-4 bg-white text-black font-bold rounded-xl"
        >
          Book Now
        </a>
      </div>
    </section>

    <section class="max-w-6xl mx-auto px-4 py-20 text-center">
      <h2 class="text-3xl font-black mb-6">The Experience</h2>
      <p class="text-slate-600 max-w-3xl mx-auto">
        {{ content('home.experience.text') }}
      </p>
    </section>
  </PublicLayout>
</template>
