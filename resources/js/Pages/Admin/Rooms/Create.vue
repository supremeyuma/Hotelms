<template>
  <AuthenticatedLayout>
    <div>
      <h2 class="text-2xl mb-4">New Room</h2>

      <form @submit.prevent="submit">
        <FormSection>
          <FormLabel for="property_id">Property</FormLabel>
          <SelectInput v-model="form.property_id" :options="propertyOptions" id="property_id" required />
          <InputError :message="form.errors.property_id" />

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
          <Textarea v-model="form.meta" id="meta" placeholder="JSON attributes..." />
          <InputError :message="form.errors.meta" />

          <PrimaryButton :disabled="form.processing">Create Room</PrimaryButton>
        </FormSection>
      </form>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { FormSection, FormLabel, TextInput, Textarea, SelectInput, InputError, PrimaryButton } from '@/Components/';

const props = defineProps({
  types: Array,
  properties: Array
});

const form = useForm({
  property_id: '',
  room_type_id: '',
  room_number: '',
  status: 'available',
  meta: ''
});

const typeOptions = props.types.map(t => ({ label: t.title, value: t.id }));
const propertyOptions = props.properties.map(p => ({ label: p.name, value: p.id }));
const statusOptions = [
  { label: 'Available', value: 'available' },
  { label: 'Occupied', value: 'occupied' },
  { label: 'Maintenance', value: 'maintenance' }
];

function submit() {
  form.post('/admin/rooms', {
    onSuccess: () => form.reset()
  });
}
</script>
