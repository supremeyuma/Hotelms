<template>
  <AuthenticatedLayout>
    <div>
      <h2 class="text-2xl mb-4">Edit Staff {{ staff.name }}</h2>

      <form @submit.prevent="submit">
        <FormSection>
          <FormLabel for="name">Name</FormLabel>
          <TextInput v-model="form.name" id="name" required />

          <FormLabel for="email">Email</FormLabel>
          <TextInput v-model="form.email" id="email" required />

          <FormLabel for="phone">Phone</FormLabel>
          <TextInput v-model="form.phone" id="phone" />

          <FormLabel for="role">Role</FormLabel>
          <SelectInput v-model="form.role" :options="roleOptions" id="role" />

          <FormLabel for="action_code">Action Code</FormLabel>
          <TextInput v-model="form.action_code" id="action_code" />

          <PrimaryButton :disabled="form.processing">Update Staff</PrimaryButton>
        </FormSection>
      </form>

      <section class="mt-6">
        <h3 class="text-xl mb-2">Staff Notes</h3>

        <form @submit.prevent="submitNote">
          <FormSection>
            <FormLabel for="note_type">Type</FormLabel>
            <SelectInput v-model="note.type" :options="noteTypeOptions" id="note_type" />
            <FormLabel for="note_message">Message</FormLabel>
            <Textarea v-model="note.message" id="note_message" />
            <PrimaryButton :disabled="noteProcessing">Add Note</PrimaryButton>
          </FormSection>
        </form>

        <div class="mt-4">
          <ul>
            <li v-for="n in staff.notes" :key="n.id">
              <strong>{{ n.type }}</strong> by {{ n.admin.name }}: {{ n.message }}
            </li>
          </ul>
        </div>
      </section>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { FormSection, FormLabel, TextInput, Textarea, SelectInput, PrimaryButton } from '@/Components/';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({ staff: Object, roles: Array });

const form = useForm({
  name: props.staff.name,
  email: props.staff.email,
  phone: props.staff.staffProfile?.phone || '',
  role: props.staff.roles[0]?.name || '',
  action_code: ''
});

const roleOptions = props.roles.map(r => ({ label: r.name, value: r.name }));

function submit() {
  form.put(`/admin/staff/${props.staff.id}`);
}

// Staff notes
const note = ref({ type: 'query', message: '' });
const noteProcessing = ref(false);
const noteTypeOptions = [
  { label: 'Query', value: 'query' },
  { label: 'Commendation', value: 'commendation' }
];

function submitNote() {
  noteProcessing.value = true;
  axios.post(`/admin/staff/${props.staff.id}/notes`, note.value)
    .then(() => {
      note.value.message = '';
      noteProcessing.value = false;
      location.reload();
    });
}
</script>
