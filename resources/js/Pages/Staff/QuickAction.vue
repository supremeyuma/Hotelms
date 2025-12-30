<template>
  <AuthenticatedLayout>
    <div class="max-w-md mx-auto bg-white p-6 rounded shadow">
      <h2 class="text-xl mb-4">Quick Action</h2>

      <form @submit.prevent="submit">
        <div class="mb-3">
          <label class="text-sm">Staff ID</label>
          <input v-model="form.staff_id" readonly class="w-full border p-2 rounded bg-gray-100" />
          <p class="text-xs text-gray-500">Prefilled from auth session (editable if allowed)</p>
        </div>

        <div class="mb-3">
          <label class="text-sm">Action Code</label>
          <input v-model="form.action_code" type="password" class="w-full border p-2 rounded" />
        </div>

        <div class="flex justify-end">
          <button class="bg-indigo-600 text-white px-4 py-2 rounded">Submit</button>
        </div>
      </form>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const user = usePage().props.auth.user;
const form = ref({
  staff_id: user?.id,
  action_code: '',
});

function submit() {
  // Uses StaffActionController endpoint
  router.post('/staff/action/verify', { action_code: form.value.action_code });
}
</script>
