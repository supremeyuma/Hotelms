<template>
  <GuestLayout>
    <PageHeader title="Contact Us" subtitle="Get in touch with us" />

    <section class="py-12 px-4 max-w-3xl mx-auto">
      <div class="bg-white rounded-lg shadow-lg p-6">
        <form @submit.prevent="submitForm">
          <FormSection title="Send us a message">
            <div class="grid grid-cols-1 gap-4">
              <FormInput
                label="Name"
                v-model="form.name"
                :error="form.errors.name"
                required
              />
              <FormInput
                label="Email"
                type="email"
                v-model="form.email"
                :error="form.errors.email"
                required
              />
              <FormInput
                label="Phone"
                type="tel"
                v-model="form.phone"
                :error="form.errors.phone"
              />
              <FormTextarea
                label="Message"
                v-model="form.message"
                :error="form.errors.message"
                required
              />
            </div>

            <PrimaryButton type="submit" :disabled="form.processing">
              <span v-if="form.processing">Sending...</span>
              <span v-else>Send Message</span>
            </PrimaryButton>
          </FormSection>
        </form>

        <div v-if="flash.success" class="mt-4 p-3 bg-green-100 text-green-800 rounded">
          {{ flash.success }}
        </div>
      </div>

      <div class="mt-12">
        <h2 class="text-xl font-semibold mb-2">Our Contact Info</h2>
        <p>Phone: {{ settings.phone }}</p>
        <p>Email: {{ settings.email }}</p>
        <p>Address: {{ settings.address }}</p>

        <div class="mt-6 h-64 w-full">
          <iframe
            class="w-full h-full rounded-lg"
            :src="settings.map_embed_url"
            frameborder="0"
            allowfullscreen
          ></iframe>
        </div>
      </div>
    </section>
  </GuestLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const form = useForm({
  name: '',
  email: '',
  phone: '',
  message: '',
});

const flash = ref({ success: null });

// Fetch hotel settings from page props or API
const settings = ref({
  phone: '123-456-7890',
  email: 'info@hotel.com',
  address: '123 Hotel St, City, Country',
  map_embed_url: 'https://www.google.com/maps/embed?pb=!1m18!...',
});

const submitForm = () => {
  form.post(route('public.contact.submit'), {
    onSuccess: (page) => {
      flash.value.success = page.props.flash.success;
      form.reset();
    },
  });
};
</script>

<style scoped>
/* Optional custom styling */
</style>
