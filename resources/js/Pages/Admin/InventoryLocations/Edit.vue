<template>
  <ManagerLayout>
    <div class="max-w-xl space-y-6">
      <h1 class="text-2xl font-semibold">Edit Inventory Location</h1>

      <form @submit.prevent="submit" class="space-y-4">
        <TextInput v-model="form.name" placeholder="Location Name" />
        <InputError :message="form.errors.name" />

        <TextInput v-model="form.type" placeholder="Type" />
        <InputError :message="form.errors.type" />

        <PrimaryButton :disabled="form.processing">
          Update
        </PrimaryButton>
      </form>
    </div>
  </ManagerLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import TextInput from '@/Components/TextInput.vue'
import InputError from '@/Components/InputError.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const props = defineProps({
  location: Object
})

const form = useForm({
  name: props.location.name,
  type: props.location.type
})

function submit() {
  form.put(
    route('admin.inventory-locations.update', props.location.id)
  )
}
</script>
