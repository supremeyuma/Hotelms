<script setup>
import InputError from '@/Components/InputError.vue'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ArrowLeft, BedDouble } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  type: Object,
  properties: Array,
})

const featuresText = ref((props.type.features ?? []).join('\n'))

const form = useForm({
  property_id: props.type.property_id,
  title: props.type.title ?? '',
  max_occupancy: props.type.max_occupancy ?? 1,
  base_price: props.type.base_price ?? '',
  features: props.type.features ?? [],
})

watch(featuresText, (value) => {
  form.features = value
    .split('\n')
    .map((feature) => feature.trim())
    .filter(Boolean)
})

const featureCount = computed(() => form.features.length)

function submit() {
  form.put(route('admin.room-types.update', props.type.id))
}
</script>

<template>
  <ManagerLayout>
    <Head :title="`Edit ${type.title}`" />

    <div class="mx-auto max-w-6xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
      <div>
        <Link :href="route('admin.room-types.index')" class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 transition hover:text-slate-700">
          <ArrowLeft class="h-4 w-4" />
          Back to room types
        </Link>
        <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-900">Edit {{ type.title }}</h1>
        <p class="mt-2 text-sm text-slate-600">Keep the room catalogue accurate so pricing, occupancy rules, and guest expectations stay aligned.</p>
      </div>

      <form @submit.prevent="submit" class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="space-y-6 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
          <div class="grid gap-5 md:grid-cols-2">
            <label class="space-y-2 md:col-span-2">
              <span class="text-sm font-bold text-slate-700">Property</span>
              <select v-model="form.property_id" disabled class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-500 outline-none">
                <option v-for="property in properties" :key="property.id" :value="property.id">{{ property.name }}</option>
              </select>
              <p class="text-xs text-slate-500">Property assignment is currently preserved from the existing room type record.</p>
            </label>

            <label class="space-y-2 md:col-span-2">
              <span class="text-sm font-bold text-slate-700">Room type name</span>
              <input v-model="form.title" type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" />
              <InputError :message="form.errors.title" />
            </label>

            <label class="space-y-2">
              <span class="text-sm font-bold text-slate-700">Maximum occupancy</span>
              <input v-model="form.max_occupancy" type="number" min="1" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" />
              <InputError :message="form.errors.max_occupancy" />
            </label>

            <label class="space-y-2">
              <span class="text-sm font-bold text-slate-700">Base price</span>
              <input v-model="form.base_price" type="number" min="0" step="0.01" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" />
              <InputError :message="form.errors.base_price" />
            </label>
          </div>

          <label class="space-y-2">
            <span class="text-sm font-bold text-slate-700">Features</span>
            <textarea v-model="featuresText" rows="7" class="w-full rounded-3xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" />
            <p class="text-xs text-slate-500">Keep one feature per line so the record stays easy to review and maintain.</p>
            <InputError :message="form.errors.features" />
          </label>
        </section>

        <aside class="space-y-6">
          <section class="rounded-[2rem] border border-slate-200 bg-[linear-gradient(145deg,_#0f172a,_#1e293b)] p-6 text-white shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.24em] text-slate-300">Live summary</p>
            <h2 class="mt-3 text-2xl font-black tracking-tight">{{ form.title || type.title }}</h2>
            <p class="mt-2 inline-flex items-center gap-2 text-sm text-slate-300">
              <BedDouble class="h-4 w-4" />
              {{ form.max_occupancy || 1 }} guest{{ Number(form.max_occupancy || 1) === 1 ? '' : 's' }}
            </p>
            <p class="mt-2 text-sm text-slate-300">Base price: {{ form.base_price ? `$${Number(form.base_price).toFixed(2)}` : 'Pending' }}</p>
            <p class="mt-4 text-sm text-slate-300">{{ featureCount }} feature{{ featureCount === 1 ? '' : 's' }} configured</p>
          </section>

          <button type="submit" :disabled="form.processing" class="w-full rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60">
            {{ form.processing ? 'Saving room type...' : 'Save room type' }}
          </button>
        </aside>
      </form>
    </div>
  </ManagerLayout>
</template>
