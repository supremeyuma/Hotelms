<template>
  <AuthenticatedLayout>
    <div class="max-w-3xl mx-auto">
      <h2 class="text-2xl font-semibold mb-6">
        New {{ form.type === 'commendation' ? 'Commendation' : 'Query' }}
      </h2>

      <form @submit.prevent="submit" class="bg-white shadow rounded-lg p-6 space-y-4">
        <!-- Type -->
        <div>
          <FormLabel for="type">Type</FormLabel>
          <SelectInput
            id="type"
            v-model="form.type"
            :options="typeOptions"
            required
          />
          <InputError :message="form.errors.type" />
        </div>

        <!-- Title -->
        <div>
          <FormLabel for="title">Title (optional)</FormLabel>
          <TextInput
            id="title"
            v-model="form.title"
            placeholder="Short summary"
          />
          <InputError :message="form.errors.title" />
        </div>

        <!-- Message -->
        <div>
          <FormLabel for="message">Message</FormLabel>
          <Textarea
            id="message"
            v-model="form.message"
            rows="6"
            placeholder="Write the message..."
            required
          />
          <InputError :message="form.errors.message" />
        </div>

        <!-- Attachments -->
        <div>
          <FormLabel>Attachments (optional)</FormLabel>
          <input
            type="file"
            multiple
            accept="image/*,.pdf,.doc,.docx"
            @change="handleFiles"
            class="block w-full text-sm"
          />
          <p class="text-xs text-gray-500 mt-1">
            Images or documents allowed
          </p>
        </div>

        <!-- Actions -->
        <div class="flex justify-end space-x-3 pt-4">
          <Link
            :href="`/admin/staff/${staffId}/threads`"
            class="px-4 py-2 border rounded"
          >
            Cancel
          </Link>

          <PrimaryButton :disabled="form.processing">
            Send
          </PrimaryButton>
        </div>
      </form>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm, Link, usePage } from '@inertiajs/vue3';
import {
  FormLabel,
  TextInput,
  Textarea,
  SelectInput,
  InputError,
  PrimaryButton
} from '@/Components/';

const props = usePage().props;
const staffId = props.staffId;

const form = useForm({
  type: 'query',
  title: '',
  message: '',
  attachments: []
});

const typeOptions = [
  { label: 'Query', value: 'query' },
  { label: 'Commendation', value: 'commendation' }
];

function handleFiles(event) {
  form.attachments = Array.from(event.target.files);
}

function submit() {
  form.post(`/admin/staff/${staffId}/threads`, {
    forceFormData: true
  });
}
</script>
