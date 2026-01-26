<template>
  <AuthenticatedLayout>
    <div class="max-w-xl space-y-6">
      <h1 class="text-2xl font-semibold">Create Inventory Item</h1>

      <form @submit.prevent="submit" class="space-y-4">
        <TextInput v-model="form.name" placeholder="Name" />
        <InputError :message="form.errors.name" />

        <TextInput v-model="form.sku" placeholder="SKU" />
        <InputError :message="form.errors.sku" />

        <TextInput v-model="form.unit" placeholder="Unit (pcs, kg, bottles)" />

        <TextInput
          type="number"
          min="0"
          v-model="form.low_stock_threshold"
          placeholder="Low Stock Threshold"
        />

        <div>
          <Textarea
            v-model="metaString"
            placeholder='Optional JSON (e.g {"reorder_qty":50,"is_consumable":true})'
          />
          <p class="text-xs text-slate-500 mt-1">
            Leave empty if not needed
          </p>
        </div>

        <PrimaryButton :disabled="form.processing">
          Create Item
        </PrimaryButton>
      </form>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import TextInput from '@/Components/TextInput.vue'
import Textarea from '@/Components/Textarea.vue'
import InputError from '@/Components/InputError.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const form = useForm({
  name: '',
  sku: '',
  unit: '',
  low_stock_threshold: 10,
  meta: {}
})

const metaString = ref('')

watch(metaString, val => {
  if (!val) {
    form.meta = {}
    return
  }

  try {
    form.meta = JSON.parse(val)
  } catch {
    // silently ignore invalid JSON while typing
  }
})
function submit() {
  form.post(route('admin.inventory.store'))
}
</script>
