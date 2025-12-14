<template>
  <AuthenticatedLayout>
    <div>
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-2xl">Staff</h2>
        <Link href="/admin/staff/create" class="bg-indigo-600 text-white px-3 py-2 rounded">New Staff</Link>
      </div>

      <Table :headers="['Name','Email','Role','Active', 'Actions']">
        <tr v-for="s in staff.data" :key="s.id" :class="s.is_suspended ? 'bg-red-50 opacity-70' : ''">
          <td>{{ s.name }}</td>
          <td>{{ s.email }}</td>
          <td>{{ s.roles[0]?.name || 'N/A' }}</td>

          <td>
            <span
                v-if="s.is_suspended"
                class="px-2 py-1 text-xs bg-red-600 text-white rounded"
            >
                Suspended
            </span>
            <span
                v-else
                class="px-2 py-1 text-xs bg-green-600 text-white rounded"
            >
                Active
            </span>
            </td>

          
          <td class="relative">
            <!-- Actions Dropdown -->
            <div class="inline-block text-left dropdown-wrapper">
                <button @click.stop="toggleDropdown(s.id)" class="bg-gray-200 px-2 py-1 rounded">Actions</button>

                <!-- Dropdown Menu -->
                <div
                    v-if="dropdownOpen === s.id"
                    class="absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50"
                >
                    <div class="py-1">
                        <Link :href="`/admin/staff/${s.id}/edit`" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Edit</Link>
                        <Link :href="`/admin/staff/${s.id}/threads`" class="block px-4 py-2 text-sm text-indigo-600 hover:bg-gray-100">View Notes</Link>
                        <button
                            v-if="!staff.is_suspended"
                            @click="suspendStaff(staff.id)"
                            class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100"
                            >
                            Suspend
                            </button>

                            <button
                            v-else
                            @click="reinstateStaff(staff.id)"
                            class="text-green-600"
                            >
                            Reinstate
                        </button>

                        <button @click="deleteStaff(s.id)" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">Delete</button>
                        
                    </div>
                </div>
            </div>
            </td>


        </tr>
      </Table>

      <pagination :links="staff.links" />
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Table from '@/Components/Ui/Table.vue';
import Pagination from '@/Components/Pagination.vue';
import { usePage, router, Link } from '@inertiajs/vue3';

const staff = usePage().props.staff;

// Track which dropdown is open
const dropdownOpen = ref(null);

function toggleDropdown(id) {
  dropdownOpen.value = dropdownOpen.value === id ? null : id;
}

function deleteStaff(id) {
  if (!confirm('Delete staff?')) return;
  router.delete(`/admin/staff/${id}`, {
    preserveScroll: true,
    onSuccess: () => {
      router.reload({ only: ['staff'] });
    }
  });
}

function suspendStaff(id) {
  if (!confirm('Suspend staff?')) return;
  router.post(`/admin/staff/${id}/suspend`);
}

// --- Click outside handling ---
function handleClickOutside(event) {
  const dropdowns = document.querySelectorAll('.dropdown-wrapper');
  let clickedInside = false;

  dropdowns.forEach((el) => {
    if (el.contains(event.target)) clickedInside = true;
  });

  if (!clickedInside) dropdownOpen.value = null;
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>
