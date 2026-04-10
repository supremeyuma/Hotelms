<template>
  <ManagerLayout>
    <div class="space-y-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-2">
          <p class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Location setup</p>
          <h1 class="text-3xl font-semibold tracking-tight text-slate-900">Inventory locations</h1>
          <p class="max-w-2xl text-sm text-slate-500">
            Manage the physical stores and service areas that hold inventory, with stock coverage and history visibility.
          </p>
        </div>

        <Link
          class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-indigo-600"
          :href="route('admin.inventory-locations.create')"
        >
          New location
        </Link>
      </div>

      <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-slate-500">
              <tr>
                <th class="px-5 py-4 text-left font-semibold">Name</th>
                <th class="px-5 py-4 text-left font-semibold">Type</th>
                <th class="px-5 py-4 text-left font-semibold">Stocked items</th>
                <th class="px-5 py-4 text-left font-semibold">Movement rows</th>
                <th class="px-5 py-4 text-right font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="loc in locations.data" :key="loc.id" class="border-t border-slate-100">
                <td class="px-5 py-4 font-semibold text-slate-900">{{ loc.name }}</td>
                <td class="px-5 py-4 text-slate-600">{{ formatType(loc.type) }}</td>
                <td class="px-5 py-4 text-slate-600">{{ loc.stocked_items_count }}</td>
                <td class="px-5 py-4 text-slate-600">{{ loc.movements_count }}</td>
                <td class="px-5 py-4">
                  <div class="flex justify-end gap-2">
                    <Link
                      class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                      :href="route('admin.inventory-locations.edit', loc.id)"
                    >
                      Edit
                    </Link>

                    <button
                      class="rounded-xl border border-red-200 px-3 py-2 text-xs font-semibold text-red-700 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60"
                      :disabled="deletingId === loc.id"
                      @click="destroy(loc)"
                    >
                      {{ deletingId === loc.id ? 'Deleting...' : 'Delete' }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <Pagination :links="locations.links" />
    </div>
  </ManagerLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import Pagination from '@/Components/Pagination.vue'

defineProps({
  locations: Object,
  types: Array,
})

const deletingId = ref(null)

function formatType(type) {
  return String(type).replaceAll('_', ' ').replace(/\b\w/g, char => char.toUpperCase())
}

function destroy(location) {
  if (!confirm(`Delete ${location.name}? This only works when the location has no stock history and no remaining stock.`)) {
    return
  }

  deletingId.value = location.id

  router.delete(route('admin.inventory-locations.destroy', location.id), {
    preserveScroll: true,
    onFinish: () => {
      deletingId.value = null
    },
  })
}
</script>
