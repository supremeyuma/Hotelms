<template>
  <ManagerLayout>
    <div class="max-w-2xl space-y-6">
      <div class="space-y-2">
        <p class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Inventory setup</p>
        <h1 class="text-3xl font-semibold tracking-tight text-slate-900">Create inventory item</h1>
        <p class="text-sm text-slate-500">
          Create a stock record with decimal-safe thresholds for items that may be counted in pieces, kilograms, or litres.
        </p>
      </div>

      <form @submit.prevent="submit" class="space-y-4 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
        <TextInput v-model="form.name" placeholder="Name" />
        <InputError :message="form.errors.name" />

        <TextInput v-model="form.sku" placeholder="SKU" />
        <InputError :message="form.errors.sku" />

        <TextInput v-model="form.unit" placeholder="Unit (pcs, kg, bottles)" />

        <TextInput
          type="number"
          min="0"
          step="0.01"
          v-model="form.low_stock_threshold"
          placeholder="Low Stock Threshold"
        />
        <InputError :message="form.errors.low_stock_threshold" />

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
          {{ form.processing ? 'Creating...' : 'Create item' }}
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
