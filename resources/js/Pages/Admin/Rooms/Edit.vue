<template>
  <ManagerLayout>
    <div>
      <h2 class="text-2xl mb-4">Edit Room {{ room.name }}</h2>

      <form @submit.prevent="submit">
        <FormSection>
          <FormLabel for="room_type_id">Room Type</FormLabel>
          <SelectInput v-model="form.room_type_id" :options="typeOptions" id="room_type_id" required />
          <InputError :message="form.errors.room_type_id" />

          <FormLabel for="name">Room Name</FormLabel>
          <TextInput v-model="form.name" id="name" required />
          <InputError :message="form.errors.name" />

          <FormLabel for="status">Status</FormLabel>
          <SelectInput v-model="form.status" :options="statusOptions" id="status" />
          <InputError :message="form.errors.status" />

          <FormLabel>Meta</FormLabel>
          <Textarea v-model="metaString" placeholder="JSON attributes..." />
          <InputError :message="form.errors.meta" />

          <FormLabel>Room Images</FormLabel>
          <input type="file" multiple accept="image/*" @change="handleFiles" />
          <p class="text-xs text-gray-500">Upload new images if needed.</p>

          <div class="mt-4 grid grid-cols-4 gap-2">
            <div v-for="img in room.images" :key="img.id" class="relative group border p-1 rounded">
              <img :src="`/storage/${img.path}`" class="w-full h-24 object-cover rounded" />

              <!-- Remove button -->
              <button 
                type="button" 
                @click="removeExistingImage(img.id)" 
                class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition"
              >
                &times;
              </button>

              <!-- Primary selector -->
              <div class="mt-1 flex items-center">
                <input
                  type="radio"
                  :value="img.id"
                  v-model="form.primary_image_id"
                  :id="`primary-${img.id}`"
                  class="mr-1"
                />
                <label :for="`primary-${img.id}`" class="text-sm">Primary</label>
              </div>
            </div>
          </div>


          <PrimaryButton :disabled="form.processing">Update Room</PrimaryButton>
        </FormSection>
      </form>
    </div>
  </ManagerLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { FormSection, FormLabel, TextInput, Textarea, SelectInput, InputError, PrimaryButton } from '@/Components/';

const props = defineProps({
  room: Object,
  types: Array
});



const form = useForm({
  room_type_id: props.room.room_type_id,
  name: props.room.name,
  status: props.room.status,
  meta: props.room.meta || {},
  images: [],         // New images to upload
  remove_images: [],   // IDs of existing images to remove
  primary_image_id: props.room.images.find(i => i.is_primary)?.id || null, // preselect current primary
});

//console.log(props.room);

// Convert meta to JSON string for textarea
const metaString = ref(JSON.stringify(form.meta, null, 2));
watch(metaString, val => {
  try { form.meta = JSON.parse(val); } catch(e) {}
});

const typeOptions = props.types.map(t => ({ label: t.title, value: t.id }));
const statusOptions = [
  { label: 'Available', value: 'available' },
  { label: 'Occupied', value: 'occupied' },
  { label: 'Maintenance', value: 'maintenance' }
];

function handleFiles(event) {
  form.images = Array.from(event.target.files);
}

function removeExistingImage(id) {
  form.remove_images.push(id);
  props.room.images = props.room.images.filter(i => i.id !== id);
}

function submit() {
  form.put(`/admin/rooms/${props.room.id}`, { forceFormData: true });
}
</script>
