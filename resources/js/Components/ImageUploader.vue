<template>
  <div class="bg-white p-4 rounded shadow">
    <div class="mb-3">
      <label class="block text-sm font-medium mb-1">Upload images</label>
      <input
        ref="fileInput"
        type="file"
        multiple
        accept="image/*"
        @change="onFiles"
        class="hidden"
      />
      <div
        class="border-2 border-dashed border-gray-200 rounded p-6 text-center cursor-pointer"
        @click="trigger"
      >
        <div class="text-sm text-gray-600">Drag & drop files here or click to browse</div>
        <div class="text-xs text-gray-400 mt-2">Max size: 5MB per file</div>
      </div>
    </div>

    <div v-if="previews.length" class="grid grid-cols-3 gap-3">
      <div v-for="(p, idx) in previews" :key="idx" class="relative border rounded overflow-hidden">
        <img :src="p.url" class="object-cover w-full h-32" />
        <div class="p-2 flex justify-between items-center">
          <input v-model="p.caption" placeholder="Caption" class="text-xs border p-1 rounded w-2/3" />
          <div class="flex space-x-1">
            <button @click="removePreview(idx)" type="button" class="text-red-600 text-xs">Remove</button>
            <button @click="uploadSingle(idx)" type="button" class="text-indigo-600 text-xs">Upload</button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="serverImages.length" class="mt-4">
      <h4 class="text-sm font-medium mb-2">Existing Images</h4>
      <div class="grid grid-cols-4 gap-3">
        <div v-for="img in serverImages" :key="img.id" class="relative border rounded overflow-hidden">
          <img :src="img.url" class="object-cover w-full h-28" />
          <div class="p-2 flex justify-between items-center">
            <button @click="setPrimary(img)" :disabled="img.is_primary" class="text-xs text-indigo-600">
              {{ img.is_primary ? 'Primary' : 'Set primary' }}
            </button>
            <button @click="deleteImage(img)" class="text-xs text-red-600">Delete</button>
          </div>
        </div>
      </div>
    </div>

    <div class="mt-4 flex justify-end">
      <button @click="uploadAll" class="bg-indigo-600 text-white px-4 py-2 rounded" :disabled="!previews.length">
        Upload All
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/inertia-vue3';

const props = defineProps({
  imageable_type: { type: String, required: true }, // 'rooms', 'room-types', etc.
  imageable_id: { type: [String, Number], required: true },
  fetchUrl: { type: String, default: null } // endpoint to fetch existing images as JSON
});

const fileInput = ref(null);
const previews = reactive([]);
const serverImages = ref([]);

const trigger = () => fileInput.value.click();

function onFiles(e) {
  const files = Array.from(e.target.files || []);
  files.forEach((file) => {
    const reader = new FileReader();
    reader.onload = (ev) => {
      previews.push({
        file,
        url: ev.target.result,
        caption: '',
      });
    };
    reader.readAsDataURL(file);
  });
  // reset input
  e.target.value = null;
}

function removePreview(index) {
  previews.splice(index, 1);
}

async function uploadSingle(index) {
  const p = previews[index];
  const form = new FormData();
  form.append('image', p.file);
  form.append('caption', p.caption || '');
  form.append('is_primary', false);

  try {
    await axios.post(`/images/${props.imageable_type}/${props.imageable_id}`, form, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
    // refresh server images
    await fetchServerImages();
    removePreview(index);
  } catch (err) {
    console.error(err);
    alert('Upload failed');
  }
}

async function uploadAll() {
  for (let i = previews.length - 1; i >= 0; i--) {
    await uploadSingle(i);
  }
}

async function fetchServerImages() {
  const url = props.fetchUrl ?? `/api/${props.imageable_type}/${props.imageable_id}/images`;
  try {
    const res = await axios.get(url);
    serverImages.value = res.data.images.map(i => ({
      ...i,
      url: i.url || i.path
    }));
  } catch (e) {
    // ignore
    serverImages.value = [];
  }
}

async function deleteImage(img) {
  if (!confirm('Delete image?')) return;
  try {
    await axios.delete(`/images/${img.id}`);
    await fetchServerImages();
  } catch (e) {
    alert('Delete failed');
  }
}

async function setPrimary(img) {
  try {
    await axios.patch(`/images/${img.id}/primary`);
    await fetchServerImages();
  } catch (e) {
    alert('Could not set primary');
  }
}

onMounted(() => {
  fetchServerImages();
});
</script>

<style scoped>
/* minimal styling adjustments */
</style>
