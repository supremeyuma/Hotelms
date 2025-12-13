<template>
  <AuthenticatedLayout>
    <div>
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-2xl">Rooms</h2>
        <Link href="/admin/rooms/create" class="bg-indigo-600 text-white px-3 py-2 rounded">New Room</Link>
      </div>

      <Table :headers="['Room #', 'Type', 'Status', 'Actions']">
        <tr v-for="r in rooms.data" :key="r.id">
          <td class="px-4 py-2">{{ r.room_number }}</td>
          <td class="px-4 py-2">{{ r.room_type.title }}</td>
          <td class="px-4 py-2">{{ r.status }}</td>
          <td class="px-4 py-2">
            <Link :href="`/admin/rooms/${r.id}/edit`" class="text-indigo-600 mr-2">Edit</Link>
            <button @click="deleteRoom(r.id)" class="text-red-600">Delete</button>
          </td>
        </tr>
      </Table>

      <!-- Pagination controls from Inertia props -->
      <div class="mt-4">
        <pagination :links="rooms.links" />
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Table from '@/Components/Ui/Table.vue';
import { usePage,router, Link } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue'; // <-- add this
//import { Inertia } from '@inertiajs/inertia';
const props = usePage().props;
const rooms = props.rooms;

function deleteRoom(id) {
  if (!confirm('Delete room?')) return;
  Inertia.delete(`/admin/rooms/${id}`);
}
</script>
