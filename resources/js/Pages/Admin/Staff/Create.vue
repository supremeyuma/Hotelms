<template>
  <ManagerLayout>
    <div class="p-6">
      <h2 class="text-2xl mb-4 font-bold">Create New Staff</h2>

      <form @submit.prevent="submit">
        <FormSection>
          <FormLabel for="name">Name</FormLabel>
          <TextInput v-model="form.name" id="name" required />
          <p v-if="form.errors.name" class="text-red-500 text-sm">{{ form.errors.name }}</p>

          <FormLabel for="email">Email</FormLabel>
          <TextInput v-model="form.email" type="email" id="email" required />
          <p v-if="form.errors.email" class="text-red-500 text-sm">{{ form.errors.email }}</p>

          <FormLabel for="phone">Phone</FormLabel>
          <TextInput v-model="form.phone" id="phone" />

          <FormLabel for="role">Role</FormLabel>
          <SelectInput v-model="form.role" :options="roleOptions" id="role" required />
          <p v-if="form.errors.role" class="text-red-500 text-sm">{{ form.errors.role }}</p>

          <FormLabel for="password">Temporary Password</FormLabel>
          <TextInput v-model="form.password" type="password" id="password" required />

          <div class="mt-4">
            <PrimaryButton :disabled="form.processing">Create Staff</PrimaryButton>
          </div>
        </FormSection>
      </form>
    </div>
  </ManagerLayout>
</template>

<script setup>
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { FormSection, FormLabel, TextInput, SelectInput, PrimaryButton } from '@/Components/';

const props = defineProps({ 
  roles: Array 
});

// 1. Initialized form with empty strings instead of props.staff
const form = useForm({
  name: '',
  email: '',
  phone: '',
  role: '',
  password: '', // New staff usually need an initial password
});

// 2. Map roles for the select input
const roleOptions = props.roles.map(r => ({ label: r.name, value: r.name }));

// 3. Change submit to POST to the store route
function submit() {
  form.post('/admin/staff', {
    onSuccess: () => form.reset(),
  });
}

// NOTE: I removed the "Staff Notes" section because you cannot 
// add notes to a staff member that hasn't been created yet.
</script>