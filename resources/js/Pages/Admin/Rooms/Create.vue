<script setup>
import InputError from '@/Components/InputError.vue'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { ArrowLeft, ImagePlus, Star } from 'lucide-vue-next'

const props = defineProps({
  types: Array,
  properties: Array,
})

const form = useForm({
  property_id: props.properties?.[0]?.id ?? '',
  room_type_id: props.types?.[0]?.id ?? '',
  name: '',
  room_number: '',
  code: '',
  floor: '',
  status: 'available',
  meta: '{\n  "view": "",\n  "notes": ""\n}',
  images: [],
  primary_upload_index: null,
})

const statusOptions = [
  { label: 'Available', value: 'available' },
  { label: 'Occupied', value: 'occupied' },
  { label: 'Dirty', value: 'dirty' },
  { label: 'Reserved', value: 'reserved' },
  { label: 'Maintenance', value: 'maintenance' },
  { label: 'Unavailable', value: 'unavailable' },
]

const uploadPreviews = ref([])

const selectedType = computed(() => props.types.find((type) => type.id === form.room_type_id))

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
  form.post(route('admin.rooms.store'), {
    forceFormData: true,
  })
}
</script>

<template>
  <ManagerLayout>
    <Head title="Create Room" />

    <div class="mx-auto max-w-6xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <Link :href="route('admin.rooms.index')" class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 transition hover:text-slate-700">
            <ArrowLeft class="h-4 w-4" />
            Back to rooms
          </Link>
          <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-900">Create a new room</h1>
          <p class="mt-2 text-sm text-slate-600">Set the operational details and upload the image guests should see first in booking flow.</p>
        </div>
      </div>

      <form @submit.prevent="submit" class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="space-y-6 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
          <div class="grid gap-5 md:grid-cols-2">
            <label class="space-y-2">
              <span class="text-sm font-bold text-slate-700">Property</span>
              <select v-model="form.property_id" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                <option v-for="property in properties" :key="property.id" :value="property.id">{{ property.name }}</option>
              </select>
              <InputError :message="form.errors.property_id" />
            </label>

            <label class="space-y-2">
              <span class="text-sm font-bold text-slate-700">Room type</span>
              <select v-model="form.room_type_id" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                <option v-for="type in types" :key="type.id" :value="type.id">{{ type.title }}</option>
              </select>
              <InputError :message="form.errors.room_type_id" />
            </label>

            <label class="space-y-2">
              <span class="text-sm font-bold text-slate-700">Room name</span>
              <input v-model="form.name" type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" placeholder="Amber 201" />
              <InputError :message="form.errors.name" />
            </label>

            <label class="space-y-2">
              <span class="text-sm font-bold text-slate-700">Room number</span>
              <input v-model="form.room_number" type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" placeholder="201" />
              <InputError :message="form.errors.room_number" />
            </label>

            <label class="space-y-2">
              <span class="text-sm font-bold text-slate-700">Internal code</span>
              <input v-model="form.code" type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" placeholder="AMB201" />
              <InputError :message="form.errors.code" />
            </label>

            <label class="space-y-2">
              <span class="text-sm font-bold text-slate-700">Floor</span>
              <input v-model="form.floor" type="number" min="0" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" placeholder="2" />
              <InputError :message="form.errors.floor" />
            </label>
          </div>

          <label class="space-y-2">
            <span class="text-sm font-bold text-slate-700">Operational status</span>
            <select v-model="form.status" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
              <option v-for="status in statusOptions" :key="status.value" :value="status.value">{{ status.label }}</option>
            </select>
            <InputError :message="form.errors.status" />
          </label>

          <label class="space-y-2">
            <span class="text-sm font-bold text-slate-700">Room metadata</span>
            <textarea v-model="form.meta" rows="6" class="w-full rounded-3xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" />
            <p class="text-xs text-slate-500">Optional JSON for internal room attributes like wing, view, or notes.</p>
            <InputError :message="form.errors.meta" />
          </label>
        </section>

        <aside class="space-y-6">
          <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center gap-2 text-slate-900">
              <ImagePlus class="h-5 w-5" />
              <h2 class="text-lg font-black">Room images</h2>
            </div>
            <p class="mt-2 text-sm text-slate-600">Upload room photos and choose which one should represent this room first.</p>

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
            <h2 class="mt-3 text-2xl font-black tracking-tight">{{ form.name || 'Untitled room' }}</h2>
            <p class="mt-2 text-sm text-slate-300">
              {{ selectedType?.title || 'Room type pending' }}
              <span v-if="form.room_number">· Room {{ form.room_number }}</span>
              <span v-if="form.floor !== ''">· Floor {{ form.floor }}</span>
            </p>
            <p class="mt-4 text-sm text-slate-300">
              Primary image selection here controls the room thumbnail guests see during booking.
            </p>
          </section>

          <button type="submit" :disabled="form.processing" class="w-full rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60">
            {{ form.processing ? 'Creating room...' : 'Create room' }}
          </button>
        </aside>
      </form>
    </div>
  </ManagerLayout>
</template>
