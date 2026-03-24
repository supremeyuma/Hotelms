<template>
  <ManagerLayout>
    <div class="space-y-6">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h2 class="text-2xl font-semibold">HR Staff Directory</h2>
          <p class="text-sm text-slate-500">Onboarding, role assignment, status changes, and staff records live here.</p>
        </div>

        <Link :href="route(`${routePrefix}.create`)" class="inline-flex items-center rounded bg-indigo-600 px-4 py-2 text-white">
          New Staff
        </Link>
      </div>

      <form @submit.prevent="applyFilters" class="grid gap-3 rounded-lg bg-white p-4 shadow sm:grid-cols-2 xl:grid-cols-5">
        <TextInput v-model="filterForm.search" placeholder="Search name or email" />
        <SelectInput v-model="filterForm.role" :options="roleOptions" />
        <SelectInput v-model="filterForm.department" :options="departmentOptions" />
        <SelectInput v-model="filterForm.status" :options="statusOptions" />
        <div class="flex gap-2">
          <PrimaryButton :disabled="filterForm.processing">Filter</PrimaryButton>
          <button type="button" class="rounded border px-4 py-2 text-sm" @click="clearFilters">Clear</button>
        </div>
      </form>

      <Table :headers="['Name', 'Role', 'Department', 'Position', 'Status', 'Actions']">
        <tr v-for="member in staff.data" :key="member.id" :class="member.is_suspended ? 'bg-red-50' : ''">
          <td class="px-4 py-3">
            <div class="font-medium">{{ member.name }}</div>
            <div class="text-sm text-slate-500">{{ member.email }}</div>
          </td>
          <td class="px-4 py-3">{{ member.roles[0]?.name || 'Unassigned' }}</td>
          <td class="px-4 py-3">{{ member.department?.name || 'Not set' }}</td>
          <td class="px-4 py-3">{{ member.staff_profile?.position || 'Not set' }}</td>
          <td class="px-4 py-3">
            <span
              class="rounded px-2 py-1 text-xs font-medium"
              :class="member.is_suspended ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white'"
            >
              {{ member.is_suspended ? 'Suspended' : 'Active' }}
            </span>
          </td>
          <td class="relative px-4 py-3">
            <div class="dropdown-wrapper inline-block text-left">
              <button @click.stop="toggleDropdown(member.id)" class="rounded bg-gray-200 px-2 py-1">Actions</button>

              <div
                v-if="dropdownOpen === member.id"
                class="absolute right-0 z-50 mt-2 w-52 rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5"
              >
                <div class="py-1">
                  <Link :href="route(`${routePrefix}.edit`, member.id)" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Edit Profile</Link>
                  <Link :href="route(`${routePrefix}.threads.index`, member.id)" class="block px-4 py-2 text-sm text-indigo-600 hover:bg-gray-100">Notes & Threads</Link>
                  <button
                    v-if="!member.is_suspended"
                    @click="suspendStaff(member.id)"
                    class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-gray-100"
                  >
                    Suspend
                  </button>
                  <button
                    v-else
                    @click="reinstateStaff(member.id)"
                    class="block w-full px-4 py-2 text-left text-sm text-emerald-600 hover:bg-gray-100"
                  >
                    Reinstate
                  </button>
                  <button @click="deleteStaff(member.id)" class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-gray-100">Archive</button>
                </div>
              </div>
            </div>
          </td>
        </tr>
      </Table>

      <Pagination :links="staff.links" />
    </div>
  </ManagerLayout>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import Table from '@/Components/Ui/Table.vue'
import Pagination from '@/Components/Pagination.vue'
import TextInput from '@/Components/TextInput.vue'
import SelectInput from '@/Components/SelectInput.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  staff: Object,
  roles: Array,
  departments: Array,
  filters: Object,
  routePrefix: String,
})

const dropdownOpen = ref(null)

const filterForm = useForm({
  search: props.filters?.search ?? '',
  role: props.filters?.role ?? '',
  department: props.filters?.department ?? '',
  status: props.filters?.status ?? '',
})

const roleOptions = props.roles.map(role => ({ label: role.name, value: role.name }))
const departmentOptions = props.departments.map(department => ({ label: department.name, value: department.id }))
const statusOptions = [
  { label: 'Active', value: 'active' },
  { label: 'Suspended', value: 'suspended' },
]

function toggleDropdown(id) {
  dropdownOpen.value = dropdownOpen.value === id ? null : id
}

function applyFilters() {
  filterForm.get(route(`${props.routePrefix}.index`), {
    preserveState: true,
    replace: true,
  })
}

function clearFilters() {
  filterForm.search = ''
  filterForm.role = ''
  filterForm.department = ''
  filterForm.status = ''
  applyFilters()
}

function deleteStaff(id) {
  if (!confirm('Archive this staff record?')) return

  router.delete(route(`${props.routePrefix}.destroy`, id), {
    preserveScroll: true,
  })
}

function suspendStaff(id) {
  if (!confirm('Suspend this staff member?')) return
  router.post(route(`${props.routePrefix}.suspend`, id))
}

function reinstateStaff(id) {
  if (!confirm('Reinstate this staff member?')) return
  router.post(route(`${props.routePrefix}.reinstate`, id))
}

function handleClickOutside(event) {
  const dropdowns = document.querySelectorAll('.dropdown-wrapper')
  let clickedInside = false

  dropdowns.forEach((el) => {
    if (el.contains(event.target)) clickedInside = true
  })

  if (!clickedInside) dropdownOpen.value = null
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>
