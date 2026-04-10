<template>
  <ManagerLayout>
    <div class="max-w-2xl space-y-6">
      <div class="space-y-2">
        <p class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Inventory setup</p>
        <h1 class="text-3xl font-semibold tracking-tight text-slate-900">Edit inventory item</h1>
        <p class="text-sm text-slate-500">
          Adjust core details without touching the ledger history. Stock counts should be changed through add, use, transfer, or reconcile actions.
        </p>
      </div>

      <form @submit.prevent="submit" class="space-y-4 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
        <TextInput v-model="form.name" placeholder="Name" />
        <InputError :message="form.errors.name" />

        <TextInput v-model="form.sku" placeholder="SKU" />
        <InputError :message="form.errors.sku" />

        <TextInput v-model="form.unit" placeholder="Unit" />

        <TextInput
          type="number"
          min="0"
          step="0.01"
          v-model="form.low_stock_threshold"
          placeholder="Low Stock Threshold"
        />
        <InputError :message="form.errors.low_stock_threshold" />

        <Textarea v-model="metaString" placeholder="Meta (JSON)" />

        <PrimaryButton :disabled="form.processing">
          {{ form.processing ? 'Saving...' : 'Save changes' }}
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
