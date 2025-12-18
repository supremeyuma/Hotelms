<template>
  <AuthenticatedLayout>
    <div class="max-w-xl">
      <h1 class="text-2xl font-semibold mb-6">Create Inventory Item</h1>

      <form @submit.prevent="submit" class="space-y-4">
        <TextInput v-model="form.name" placeholder="Name" />
        <InputError :message="form.errors.name" />

        <TextInput v-model="form.sku" placeholder="SKU" />
        <InputError :message="form.errors.sku" />

        <TextInput type="number" min="0" v-model="form.quantity" placeholder="Quantity" />
        <InputError :message="form.errors.quantity" />

        <TextInput v-model="form.unit" placeholder="Unit (e.g pcs, kg)" />

        <Textarea v-model="metaString" placeholder="Meta (JSON)" />

        <PrimaryButton :disabled="form.processing">Create</PrimaryButton>
      </form>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useForm } from '@inertiajs/vue3'
import TextInput from '@/Components/TextInput.vue'
import Textarea from '@/Components/Textarea.vue'
import InputError from '@/Components/InputError.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import { ref, watch } from 'vue'

const form = useForm({
  name: '',
  sku: '',
  quantity: 0,
  unit: '',
  meta: {}
})

const metaString = ref('{}')
watch(metaString, val => {
  try { form.meta = JSON.parse(val) } catch {}
})

function submit() {
  form.post(route('inventory.store'))
}
</script>
