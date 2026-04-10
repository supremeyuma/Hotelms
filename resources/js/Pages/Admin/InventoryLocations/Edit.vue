<template>
  <ManagerLayout>
    <div class="max-w-2xl space-y-6">
      <div class="space-y-2">
        <p class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Location setup</p>
        <h1 class="text-3xl font-semibold tracking-tight text-slate-900">Edit inventory location</h1>
        <p class="text-sm text-slate-500">
          Keep the location name and type aligned with the real-world store or service area that owns this stock.
        </p>
      </div>

      <form class="space-y-5 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
        <div class="space-y-2">
          <label class="text-sm font-semibold text-slate-700">Location name</label>
          <TextInput v-model="form.name" placeholder="Kitchen Storefront" />
          <InputError :message="form.errors.name" />
        </div>

        <div class="space-y-2">
          <label class="text-sm font-semibold text-slate-700">Location type</label>
          <select v-model="form.type" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" required>
            <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
          </select>
          <InputError :message="form.errors.type" />
        </div>

        <div class="flex justify-end gap-2">
          <Link
            class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600"
            :href="route('admin.inventory-locations.index')"
          >
            Cancel
          </Link>
          <PrimaryButton :disabled="form.processing">
            {{ form.processing ? 'Saving...' : 'Save changes' }}
          </PrimaryButton>
        </div>
      </form>
    </div>
  </ManagerLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import TextInput from '@/Components/TextInput.vue'
import InputError from '@/Components/InputError.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const props = defineProps({
  location: Object,
  types: Array,
})

const form = useForm({
  name: props.location.name,
  type: props.location.type,
})

function submit() {
  form.put(route('admin.inventory-locations.update', props.location.id))
}
</script>
