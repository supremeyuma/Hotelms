<template>
  <AuthenticatedLayout>
    <div>
      <h2 class="text-2xl mb-4">Edit Room {{ room.room_number }}</h2>

      <form @submit.prevent="submit">
        <FormSection>
          <FormLabel for="room_type_id">Room Type</FormLabel>
          <SelectInput v-model="form.room_type_id" :options="typeOptions" id="room_type_id" required />
          <InputError :message="form.errors.room_type_id" />

          <FormLabel for="room_number">Room Number</FormLabel>
          <TextInput v-model="form.room_number" id="room_number" required />
          <InputError :message="form.errors.room_number" />

          <FormLabel for="status">Status</FormLabel>
          <SelectInput v-model="form.status" :options="statusOptions" id="status" />
          <InputError :message="form.errors.status" />

          <FormLabel for="meta">Meta</FormLabel>
          <Textarea v-model="metaString" id="meta" placeholder="JSON attributes..." />
          <InputError :message="form.errors.meta" />

          <PrimaryButton :disabled="form.processing">Update Room</PrimaryButton>
        </FormSection>
      </form>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { FormSection, FormLabel, TextInput, Textarea, SelectInput, InputError, PrimaryButton } from '@/Components/';

const props = defineProps({
  room: Object,
  types: Array
});

const form = useForm({
  room_type_id: props.room.room_type_id,
  room_number: props.room.room_number,
  status: props.room.status,
  meta: props.room.meta || {} // <-- important: load existing meta
});

// Convert object to string for Textarea
const metaString = ref(JSON.stringify(form.meta, null, 2));

// Watch changes in textarea and update form.meta
watch(metaString, (val) => {
  try {
    form.meta = JSON.parse(val);
  } catch (e) {
    console.log("invalid JSON, maybe keep form.meta unchanged");
  }
});



const typeOptions = props.types.map(t => ({ label: t.title, value: t.id }));
const statusOptions = [
  { label: 'Available', value: 'available' },
  { label: 'Occupied', value: 'occupied' },
  { label: 'Maintenance', value: 'maintenance' }
];

function submit() {
  form.put(`/admin/rooms/${props.room.id}`);
}
</script>
