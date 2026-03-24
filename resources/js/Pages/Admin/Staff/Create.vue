<template>
  <ManagerLayout>
    <div class="p-6">
      <h2 class="mb-4 text-2xl font-bold">HR Onboarding</h2>

      <form @submit.prevent="submit">
        <FormSection>
          <FormLabel for="name">Name</FormLabel>
          <TextInput v-model="form.name" id="name" required />
          <p v-if="form.errors.name" class="text-sm text-red-500">{{ form.errors.name }}</p>

          <FormLabel for="email">Email</FormLabel>
          <TextInput v-model="form.email" type="email" id="email" required />
          <p v-if="form.errors.email" class="text-sm text-red-500">{{ form.errors.email }}</p>

          <FormLabel for="phone">Phone</FormLabel>
          <TextInput v-model="form.phone" id="phone" />

          <FormLabel for="position">Position</FormLabel>
          <TextInput v-model="form.position" id="position" placeholder="Receptionist, HR Officer, Cleaner..." />

          <FormLabel for="department">Department</FormLabel>
          <SelectInput v-model="form.department_id" :options="departmentOptions" id="department" />

          <FormLabel for="role">Role</FormLabel>
          <SelectInput v-model="form.role" :options="roleOptions" id="role" required />
          <p v-if="form.errors.role" class="text-sm text-red-500">{{ form.errors.role }}</p>

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
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { FormSection, FormLabel, TextInput, SelectInput, PrimaryButton } from '@/Components/'

const props = defineProps({
  roles: Array,
  departments: Array,
  routePrefix: String,
})

const form = useForm({
  name: '',
  email: '',
  phone: '',
  position: '',
  department_id: '',
  role: '',
  password: '',
})

const roleOptions = props.roles.map(role => ({ label: role.name, value: role.name }))
const departmentOptions = props.departments.map(department => ({ label: department.name, value: department.id }))

function submit() {
  form.post(route(`${props.routePrefix}.store`), {
    onSuccess: () => form.reset(),
  })
}
</script>
