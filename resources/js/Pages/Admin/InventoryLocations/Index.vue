<template>
  <ManagerLayout>
    <div class="space-y-6">

      <div class="flex justify-between items-center">
        <h1 class="text-2xl font-semibold">Inventory Locations</h1>
        <Link class="btn-primary" :href="route('admin.inventory-locations.create')">
          New Location
        </Link>
      </div>

      <div class="card">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b">
              <th class="p-3 text-left">Name</th>
              <th class="p-3 text-left">Type</th>
              <th class="p-3 text-right">Actions</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="loc in locations.data" :key="loc.id" class="border-b">
              <td class="p-3 font-semibold">{{ loc.name }}</td>
              <td class="p-3">{{ loc.type }}</td>
              <td class="p-3 text-right space-x-2">
                <Link
                  class="btn-secondary"
                  :href="route('admin.inventory-locations.edit', loc.id)"
                >
                  Edit
                </Link>

                <button class="btn-danger" @click="destroy(loc.id)">
                  Delete
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination :links="locations.links" />
    </div>
  </ManagerLayout>
</template>

<script setup>
import { router, Link } from '@inertiajs/vue3'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import Pagination from '@/Components/Pagination.vue'

defineProps({
  locations: Object
})

function destroy(id) {
  if (!confirm('Delete this location?')) return

  router.delete(
    route('admin.inventory-locations.destroy', id),
    { preserveScroll: true }
  )
}
</script>
