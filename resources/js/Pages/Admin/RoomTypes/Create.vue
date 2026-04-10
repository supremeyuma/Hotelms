<script setup>
import InputError from '@/Components/InputError.vue'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ArrowLeft, ImagePlus, Plus, Star } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  properties: Array,
})

const featuresText = ref('')

const form = useForm({
  property_id: props.properties?.[0]?.id ?? '',
  title: '',
  max_occupancy: 1,
  base_price: '',
  features: [],
  images: [],
  primary_upload_index: null,
})

watch(featuresText, (value) => {
  form.features = value
    .split('\n')
    .map((feature) => feature.trim())
    .filter(Boolean)
})

const featureCount = computed(() => form.features.length)
const uploadPreviews = ref([])

function handleFiles(event) {
  const files = Array.from(event.target.files ?? [])
  form.images = files
  uploadPreviews.value = files.map((file, index) => ({
    index,
    name: file.name,
    sizeLabel: `${(file.size / 1024 / 1024).toFixed(1)} MB`,
    url: URL.createObjectURL(file),
  }))

  if (files.length > 0 && form.primary_upload_index === null) {
    form.primary_upload_index = 0
  }
}

function choosePrimary(index) {
  form.primary_upload_index = index
}

function submit() {
  form.post(route('admin.room-types.store'), {
    forceFormData: true,
  })
}
</script>

<template>
  <ManagerLayout>
    <Head title="Create Room Type" />

    <div class="mx-auto max-w-6xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
      <div>
        <Link :href="route('admin.room-types.index')" class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 transition hover:text-slate-700">
          <ArrowLeft class="h-4 w-4" />
          Back to room types
        </Link>
        <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-900">Create a room type</h1>
        <p class="mt-2 text-sm text-slate-600">Set the commercial defaults and guest-facing feature list for this room category.</p>
      </div>

      <form @submit.prevent="submit" class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="space-y-6 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
          <div class="grid gap-5 md:grid-cols-2">
            <label class="space-y-2 md:col-span-2">
              <span class="text-sm font-bold text-slate-700">Property</span>
              <select v-model="form.property_id" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                <option v-for="property in properties" :key="property.id" :value="property.id">{{ property.name }}</option>
              </select>
              <InputError :message="form.errors.property_id" />
            </label>

            <label class="space-y-2 md:col-span-2">
              <span class="text-sm font-bold text-slate-700">Room type name</span>
              <input v-model="form.title" type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" placeholder="Executive Suite" />
              <InputError :message="form.errors.title" />
            </label>

            <label class="space-y-2">
              <span class="text-sm font-bold text-slate-700">Maximum occupancy</span>
              <input v-model="form.max_occupancy" type="number" min="1" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" />
              <InputError :message="form.errors.max_occupancy" />
            </label>

            <label class="space-y-2">
              <span class="text-sm font-bold text-slate-700">Base price</span>
              <input v-model="form.base_price" type="number" min="0" step="0.01" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" placeholder="250.00" />
              <InputError :message="form.errors.base_price" />
            </label>
          </div>

          <label class="space-y-2">
            <span class="text-sm font-bold text-slate-700">Features</span>
            <textarea
              v-model="featuresText"
              rows="7"
              class="w-full rounded-3xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
              placeholder="King bed&#10;Balcony&#10;Rain shower"
            />
            <p class="text-xs text-slate-500">Enter one feature per line. These are stored as the structured feature list for the room type.</p>
            <InputError :message="form.errors.features" />
          </label>
        </section>

        <aside class="space-y-6">
          <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center gap-2 text-slate-900">
              <ImagePlus class="h-5 w-5" />
              <h2 class="text-lg font-black">Room type images</h2>
            </div>
            <p class="mt-2 text-sm text-slate-600">These images are used in the guest booking flow when this room type is displayed.</p>

            <label class="mt-5 block cursor-pointer rounded-[1.5rem] border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center transition hover:border-slate-400 hover:bg-slate-100">
              <input type="file" multiple accept="image/*" class="hidden" @change="handleFiles" />
              <span class="text-sm font-bold text-slate-700">Select images</span>
              <span class="mt-1 block text-xs text-slate-500">JPG, PNG, or WebP up to 8MB each</span>
            </label>

            <div v-if="uploadPreviews.length" class="mt-5 space-y-3">
              <article v-for="preview in uploadPreviews" :key="preview.index" class="overflow-hidden rounded-[1.5rem] border border-slate-200">
                <img :src="preview.url" :alt="preview.name" class="h-36 w-full object-cover" />
                <div class="flex items-center justify-between gap-3 p-3">
                  <div class="min-w-0">
                    <p class="truncate text-sm font-bold text-slate-900">{{ preview.name }}</p>
                    <p class="text-xs text-slate-500">{{ preview.sizeLabel }}</p>
                  </div>
                  <button
                    type="button"
                    @click="choosePrimary(preview.index)"
                    :class="[
                      'inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-bold transition',
                      form.primary_upload_index === preview.index
                        ? 'border-amber-200 bg-amber-50 text-amber-700'
                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                    ]"
                  >
                    <Star class="h-3.5 w-3.5" />
                    {{ form.primary_upload_index === preview.index ? 'Primary' : 'Make primary' }}
                  </button>
                </div>
              </article>
            </div>

            <InputError :message="form.errors.images" />
          </section>

          <section class="rounded-[2rem] border border-slate-200 bg-[linear-gradient(145deg,_#0f172a,_#1e293b)] p-6 text-white shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.24em] text-slate-300">Preview</p>
            <h2 class="mt-3 text-2xl font-black tracking-tight">{{ form.title || 'Untitled room type' }}</h2>
            <p class="mt-2 text-sm text-slate-300">
              Fits {{ form.max_occupancy || 1 }} guest{{ Number(form.max_occupancy || 1) === 1 ? '' : 's' }}
            </p>
            <p class="mt-2 text-sm text-slate-300">
              Base price: {{ form.base_price ? `$${Number(form.base_price).toFixed(2)}` : 'Pending' }}
            </p>
            <p class="mt-4 inline-flex items-center gap-2 text-sm text-slate-300">
              <Plus class="h-4 w-4" />
              {{ featureCount }} feature{{ featureCount === 1 ? '' : 's' }} listed
            </p>
            <p class="mt-3 text-sm text-slate-300">
              {{ uploadPreviews.length ? 'The selected primary image becomes the booking card image guests see first.' : 'Add images so guests see this room type during booking.' }}
            </p>
          </section>

          <button type="submit" :disabled="form.processing" class="w-full rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60">
            {{ form.processing ? 'Creating room type...' : 'Create room type' }}
          </button>
        </aside>
      </form>
    </div>
  </ManagerLayout>
</template>
