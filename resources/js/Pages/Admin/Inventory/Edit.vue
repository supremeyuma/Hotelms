<template>
  <AuthenticatedLayout>
    <div class="max-w-xl">
      <h1 class="text-2xl font-semibold mb-6">Edit Inventory Item</h1>

      <form @submit.prevent="submit" class="space-y-4">
        <TextInput v-model="form.name" placeholder="Name" />
        <InputError :message="form.errors.name" />

        <TextInput v-model="form.sku" placeholder="SKU" />
        <InputError :message="form.errors.sku" />

        <TextInput type="number" min="0" v-model="form.quantity" placeholder="Quantity" />
        <InputError :message="form.errors.quantity" />

        <TextInput v-model="form.unit" placeholder="Unit" />

        <Textarea v-model="metaString" placeholder="Meta (JSON)" />

        <PrimaryButton :disabled="form.processing">Update</PrimaryButton>
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

const props = defineProps({ item: Object })

const form = useForm({
  name: props.item.name,
  sku: props.item.sku,
  quantity: props.item.quantity,
  unit: props.item.unit,
  meta: props.item.meta ?? {}
})

const metaString = ref(JSON.stringify(form.meta, null, 2))
watch(metaString, val => {
  try { form.meta = JSON.parse(val) } catch {}
})

function submit() {
  form.put(route('inventory.update', props.item.id))
}
</script>
