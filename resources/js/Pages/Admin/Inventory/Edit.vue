<template>
  <ManagerLayout>
    <div class="max-w-xl space-y-6">
      <h1 class="text-2xl font-semibold">Edit Inventory Item</h1>

      <form @submit.prevent="submit" class="space-y-4">
        <TextInput v-model="form.name" placeholder="Name" />
        <InputError :message="form.errors.name" />

        <TextInput v-model="form.sku" placeholder="SKU" />
        <InputError :message="form.errors.sku" />

        <TextInput v-model="form.unit" placeholder="Unit" />

        <TextInput
          type="number"
          min="0"
          v-model="form.low_stock_threshold"
          placeholder="Low Stock Threshold"
        />

        <Textarea v-model="metaString" placeholder="Meta (JSON)" />

        <PrimaryButton :disabled="form.processing">
          Update Item
        </PrimaryButton>
      </form>
    </div>
  </ManagerLayout>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import TextInput from '@/Components/TextInput.vue'
import Textarea from '@/Components/Textarea.vue'
import InputError from '@/Components/InputError.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
const props = defineProps({
  item: Object
})

const form = useForm({
  name: props.item.name,
  sku: props.item.sku,
  unit: props.item.unit,
  low_stock_threshold: props.item.low_stock_threshold,
  meta: props.item.meta ?? {}
})

const metaString = ref(JSON.stringify(form.meta, null, 2))

watch(metaString, val => {
  try {
    form.meta = JSON.parse(val)
  } catch {}
})

function submit() {
  form.put(route('admin.inventory.update', props.item.id))
}
</script>
