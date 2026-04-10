<script setup>
import InputError from '@/Components/InputError.vue'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ArrowLeft, BedDouble, ImagePlus, Star, Trash2 } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  type: Object,
  properties: Array,
})

const featuresText = ref((props.type.features ?? []).join('\n'))
const existingImages = ref([...(props.type.images ?? [])])
const uploadPreviews = ref([])

const form = useForm({
  property_id: props.type.property_id,
  title: props.type.title ?? '',
  max_occupancy: props.type.max_occupancy ?? 1,
  base_price: props.type.base_price ?? '',
  features: props.type.features ?? [],
  images: [],
  remove_images: [],
  primary_image_id: existingImages.value.find((image) => image.is_primary)?.id ?? null,
  primary_upload_index: null,
})

watch(featuresText, (value) => {
  form.features = value
    .split('\n')
    .map((feature) => feature.trim())
    .filter(Boolean)
})

const featureCount = computed(() => form.features.length)

function handleFiles(event) {
  const files = Array.from(event.target.files ?? [])
  form.images = files
  uploadPreviews.value = files.map((file, index) => ({
    index,
    name: file.name,
    sizeLabel: `${(file.size / 1024 / 1024).toFixed(1)} MB`,
    url: URL.createObjectURL(file),
  }))
}

function markExistingPrimary(id) {
  form.primary_image_id = id
  form.primary_upload_index = null
}

function markUploadPrimary(index) {
  form.primary_upload_index = index
  form.primary_image_id = null
}

function removeExistingImage(id) {
  if (!form.remove_images.includes(id)) {
    form.remove_images.push(id)
  }

  existingImages.value = existingImages.value.filter((image) => image.id !== id)

  if (form.primary_image_id === id) {
    form.primary_image_id = existingImages.value[0]?.id ?? null
  }
}

function submit() {
  form.put(route('admin.room-types.update', props.type.id), {
    forceFormData: true,
  })
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
          <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center gap-2 text-slate-900">
              <ImagePlus class="h-5 w-5" />
              <h2 class="text-lg font-black">Room type images</h2>
            </div>
            <p class="mt-2 text-sm text-slate-600">Manage the images shown to guests when this room type appears in booking.</p>

            <div v-if="existingImages.length" class="mt-5 space-y-3">
              <article v-for="image in existingImages" :key="image.id" class="overflow-hidden rounded-[1.5rem] border border-slate-200">
                <img :src="image.url" :alt="type.title" class="h-36 w-full object-cover" />
                <div class="flex items-center justify-between gap-3 p-3">
                  <button
                    type="button"
                    @click="markExistingPrimary(image.id)"
                    :class="[
                      'inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-bold transition',
                      form.primary_image_id === image.id
                        ? 'border-amber-200 bg-amber-50 text-amber-700'
                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                    ]"
                  >
                    <Star class="h-3.5 w-3.5" />
                    {{ form.primary_image_id === image.id ? 'Primary image' : 'Make primary' }}
                  </button>

                  <button type="button" @click="removeExistingImage(image.id)" class="inline-flex items-center gap-2 rounded-full border border-rose-200 px-3 py-1.5 text-xs font-bold text-rose-700 transition hover:bg-rose-50">
                    <Trash2 class="h-3.5 w-3.5" />
                    Remove
                  </button>
                </div>
              </article>
            </div>

            <label class="mt-5 block cursor-pointer rounded-[1.5rem] border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center transition hover:border-slate-400 hover:bg-slate-100">
              <input type="file" multiple accept="image/*" class="hidden" @change="handleFiles" />
              <span class="text-sm font-bold text-slate-700">Add more images</span>
              <span class="mt-1 block text-xs text-slate-500">You can mark one of the new uploads as the primary booking image before saving.</span>
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
                    @click="markUploadPrimary(preview.index)"
                    :class="[
                      'inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-bold transition',
                      form.primary_upload_index === preview.index
                        ? 'border-amber-200 bg-amber-50 text-amber-700'
                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                    ]"
                  >
                    <Star class="h-3.5 w-3.5" />
                    {{ form.primary_upload_index === preview.index ? 'Primary upload' : 'Make primary' }}
                  </button>
                </div>
              </article>
            </div>

            <InputError :message="form.errors.images" />
          </section>

          <section class="rounded-[2rem] border border-slate-200 bg-[linear-gradient(145deg,_#0f172a,_#1e293b)] p-6 text-white shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.24em] text-slate-300">Live summary</p>
            <h2 class="mt-3 text-2xl font-black tracking-tight">{{ form.title || type.title }}</h2>
            <p class="mt-2 inline-flex items-center gap-2 text-sm text-slate-300">
              <BedDouble class="h-4 w-4" />
              {{ form.max_occupancy || 1 }} guest{{ Number(form.max_occupancy || 1) === 1 ? '' : 's' }}
            </p>
            <p class="mt-2 text-sm text-slate-300">Base price: {{ form.base_price ? `$${Number(form.base_price).toFixed(2)}` : 'Pending' }}</p>
            <p class="mt-4 text-sm text-slate-300">{{ featureCount }} feature{{ featureCount === 1 ? '' : 's' }} configured</p>
            <p class="mt-3 text-sm text-slate-300">The primary image here is what guests see first during room selection.</p>
          </section>

          <button type="submit" :disabled="form.processing" class="w-full rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60">
            {{ form.processing ? 'Saving room type...' : 'Save room type' }}
          </button>
        </aside>
      </form>
    </div>
  </ManagerLayout>
</template>
