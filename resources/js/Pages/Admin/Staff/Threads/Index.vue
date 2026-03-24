<template>
  <ManagerLayout>
    <div>
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-2xl">Staff Queries & Commendations</h2>
        <Link 
          :href="route(`${routePrefix}.threads.create`, staffId)" 
          class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700"
        >
          New Thread
        </Link>
      </div>

      <table class="table-auto w-full border">
        <thead>
          <tr>
            <th>Staff</th>
            <th>Type</th>
            <th>Title</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="thread in threads.data" :key="thread.id">
            <td>{{ thread.staff.name }}</td>
            <td>{{ thread.type }}</td>
            <td>{{ thread.title || '-' }}</td>
            <td>{{ new Date(thread.created_at).toLocaleString() }}</td>
            <td>
              <Link :href="route(`${routePrefix}.threads.show`, thread.id)" class="text-blue-600 mr-2">View</Link>
              <Link :href="route(`${routePrefix}.edit`, thread.staff.id)" class="text-green-600 mr-2">Edit Staff</Link>
              <button @click="suspendStaff(thread.staff.id)" class="text-red-600">Suspend</button>
            </td>
          </tr>
        </tbody>
      </table>

      <pagination :links="threads.links" />
    </div>
  </ManagerLayout>
</template>

<script setup>
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';

const props = usePage().props;
const threads = props.threads;
const staffId = props.staffId; // pass this from controller when rendering
const routePrefix = props.routePrefix;

function suspendStaff(id) {
  if (!confirm('Suspend this staff member?')) return;
  router.post(route(`${routePrefix}.suspend`, id));
}
</script>
