<template>
  <AuthenticatedLayout>
    <div class="max-w-xl space-y-6">
      <h1 class="text-2xl font-semibold">Create Inventory Location</h1>

      <form @submit.prevent="submit" class="space-y-4">
        <TextInput v-model="form.name" placeholder="Location Name" />
        <InputError :message="form.errors.name" />

        <TextInput v-model="form.type" placeholder="Type (kitchen, bar, store)" />
        <InputError :message="form.errors.type" />

        <PrimaryButton :disabled="form.processing">
          Create
        </PrimaryButton>
      </form>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import TextInput from '@/Components/TextInput.vue'
import InputError from '@/Components/InputError.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const form = useForm({
  name: '',
  type: ''
})

function submit() {
  form.post(route('admin.inventory-locations.store'))
}
</script>
