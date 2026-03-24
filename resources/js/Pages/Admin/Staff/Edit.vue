<template>
  <ManagerLayout>
    <div class="space-y-6">
      <div>
        <h2 class="text-2xl font-semibold">Edit Staff {{ staff.name }}</h2>
        <p class="text-sm text-slate-500">HR can update employment details, role assignment, and internal notes.</p>
      </div>

      <form @submit.prevent="submit">
        <FormSection>
          <FormLabel for="name">Name</FormLabel>
          <TextInput v-model="form.name" id="name" required />

          <FormLabel for="email">Email</FormLabel>
          <TextInput v-model="form.email" id="email" required />

          <FormLabel for="phone">Phone</FormLabel>
          <TextInput v-model="form.phone" id="phone" />

          <FormLabel for="position">Position</FormLabel>
          <TextInput v-model="form.position" id="position" />

          <FormLabel for="department">Department</FormLabel>
          <SelectInput v-model="form.department_id" :options="departmentOptions" id="department" />

          <FormLabel for="role">Role</FormLabel>
          <SelectInput v-model="form.role" :options="roleOptions" id="role" />

          <FormLabel for="password">Reset Password</FormLabel>
          <TextInput v-model="form.password" id="password" type="password" placeholder="Leave blank to keep current password" />

          <FormLabel for="action_code">Replace Action Code</FormLabel>
          <TextInput v-model="form.action_code" id="action_code" placeholder="Optional" />

          <PrimaryButton :disabled="form.processing">Update Staff</PrimaryButton>
        </FormSection>
      </form>

      <section class="space-y-4">
        <h3 class="text-xl font-semibold">HR Notes</h3>

        <form @submit.prevent="submitNote">
          <FormSection>
            <FormLabel for="note_type">Type</FormLabel>
            <SelectInput v-model="noteForm.type" :options="noteTypeOptions" id="note_type" />
            <FormLabel for="note_message">Message</FormLabel>
            <Textarea v-model="noteForm.message" id="note_message" />
            <PrimaryButton :disabled="noteForm.processing">Add Note</PrimaryButton>
          </FormSection>
        </form>

        <div class="rounded-lg bg-white p-4 shadow">
          <ul class="space-y-3">
            <li v-for="note in staff.notes" :key="note.id" class="border-b pb-3 last:border-b-0">
              <div class="flex items-center justify-between gap-4">
                <strong class="uppercase text-slate-700">{{ note.type }}</strong>
                <span class="text-xs text-slate-400">{{ new Date(note.created_at).toLocaleString() }}</span>
              </div>
              <p class="mt-1 text-sm text-slate-700">{{ note.message }}</p>
              <p class="mt-1 text-xs text-slate-500">By {{ note.admin.name }}</p>
            </li>
          </ul>
        </div>
      </section>
    </div>
  </ManagerLayout>
</template>

<script setup>
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { FormSection, FormLabel, TextInput, Textarea, SelectInput, PrimaryButton } from '@/Components/'

const props = defineProps({
  staff: Object,
  roles: Array,
  departments: Array,
  routePrefix: String,
})

const form = useForm({
  name: props.staff.name,
  email: props.staff.email,
  phone: props.staff.staff_profile?.phone || '',
  position: props.staff.staff_profile?.position || '',
  department_id: props.staff.department_id || '',
  role: props.staff.roles[0]?.name || '',
  password: '',
  action_code: '',
})

const noteForm = useForm({
  type: 'query',
  message: '',
})

const roleOptions = props.roles.map(role => ({ label: role.name, value: role.name }))
const departmentOptions = props.departments.map(department => ({ label: department.name, value: department.id }))
const noteTypeOptions = [
  { label: 'Query', value: 'query' },
  { label: 'Commendation', value: 'commendation' },
  { label: 'Performance', value: 'performance' },
  { label: 'Disciplinary', value: 'disciplinary' },
]

function submit() {
  form.put(route(`${props.routePrefix}.update`, props.staff.id))
}

function submitNote() {
  noteForm.post(route(`${props.routePrefix}.notes.store`, props.staff.id), {
    preserveScroll: true,
    onSuccess: () => noteForm.reset('message'),
  })
}
</script>
