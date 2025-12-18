<!-- resources/js/Pages/Admin/Settings/Index.vue -->
<template>
  <AuthenticatedLayout>
    <div class="max-w-4xl">
      <h2 class="text-2xl font-semibold mb-6">Application Settings</h2>

      <form @submit.prevent="submit">
        <FormSection>
          <!-- Site Name -->
          <FormLabel for="site_name">Site Name</FormLabel>
          <TextInput id="site_name" v-model="form.site_name" />
          <InputError :message="form.errors.site_name" />

          <!-- Contact Email -->
          <FormLabel for="contact_email">Contact Email</FormLabel>
          <TextInput id="contact_email" type="email" v-model="form.contact_email" />
          <InputError :message="form.errors.contact_email" />

          <!-- Contact Phone -->
          <FormLabel for="contact_phone">Contact Phone</FormLabel>
          <TextInput id="contact_phone" v-model="form.contact_phone" />
          <InputError :message="form.errors.contact_phone" />

          <!-- Logo -->
          <FormLabel>Logo</FormLabel>
          <input type="file" accept="image/*" @change="e => form.logo = e.target.files[0]" />
          <InputError :message="form.errors.logo" />

          <img
            v-if="settings.logo"
            :src="`/storage/${settings.logo}`"
            class="h-16 mt-2 rounded"
          />

          <!-- Banner -->
          <FormLabel class="mt-4">Banner</FormLabel>
          <input type="file" accept="image/*" @change="e => form.banner = e.target.files[0]" />
          <InputError :message="form.errors.banner" />

          <img
            v-if="settings.banner"
            :src="`/storage/${settings.banner}`"
            class="h-24 mt-2 rounded object-cover"
          />

          <!-- Room Service Menu -->
          <FormLabel class="mt-4">Room Service Menu (JSON)</FormLabel>
          <Textarea
            v-model="roomServiceString"
            placeholder='[{ "name": "Burger", "price": 5000 }]'
          />
          <InputError :message="form.errors.room_service_menu" />

          <PrimaryButton :disabled="form.processing" class="mt-6">
            Save Settings
          </PrimaryButton>
        </FormSection>
      </form>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import {
  FormSection,
  FormLabel,
  TextInput,
  Textarea,
  InputError,
  PrimaryButton
} from '@/Components/'

const props = defineProps({
  settings: Object
})

const form = useForm({
  site_name: props.settings.site_name || '',
  contact_email: props.settings.contact_email || '',
  contact_phone: props.settings.contact_phone || '',
  logo: null,
  banner: null,
  room_service_menu: null
})

const roomServiceString = ref(
  props.settings.room_service_menu
    ? JSON.stringify(props.settings.room_service_menu, null, 2)
    : ''
)

watch(roomServiceString, val => {
  try {
    form.room_service_menu = JSON.parse(val)
  } catch (e) {
    form.room_service_menu = null
  }
})

function submit() {
  form.put('/admin/settings', {
    forceFormData: true,
    preserveScroll: true
  })
}
</script>
