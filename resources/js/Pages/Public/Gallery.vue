<template>
  <GuestLayout>
    <PageHeader title="Gallery" subtitle="Explore our hotel and amenities" />

    <section class="py-12 px-4 max-w-7xl mx-auto">
      <ImageGrid
        :images="images"
        :loading="loading"
        @open-modal="openModal"
      />

      <!-- Optional: Lightbox modal -->
      <ImageLightbox
        v-if="selectedImage"
        :images="images"
        :initial-index="selectedIndex"
        @close="closeModal"
      />

    </section>
  </GuestLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
//import PageHeader from '@/Components/PageHeader.vue';
//import ImageGrid from '@/Components/ImageGrid.vue';
//import ImageLightbox from '@/Components/ImageLightbox.vue';
import axios from 'axios';

const images = ref([]);
const loading = ref(true);
const selectedImage = ref(null);
const selectedIndex = ref(0);

const fetchImages = async () => {
  loading.value = true;
  try {
    const response = await axios.get('/api/gallery'); // assumes you have an API endpoint
    images.value = response.data.data;
  } catch (err) {
    console.error(err);
  } finally {
    loading.value = false;
  }
};

const openModal = (index) => {
  selectedIndex.value = index;
  selectedImage.value = images.value[index];
};

const closeModal = () => {
  selectedImage.value = null;
};

onMounted(() => {
  fetchImages();
});
</script>

<style scoped>
/* Optional custom styling if needed */
</style>
