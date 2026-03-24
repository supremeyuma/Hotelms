<template>
  <ManagerLayout>
    <div class="space-y-6">
      <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">Audit Logs</h1>

      <div class="grid grid-cols-1 gap-4 rounded-lg bg-white p-4 shadow-sm md:grid-cols-4 dark:bg-gray-900">
        <input v-model="form.user_id" type="text" placeholder="User ID" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
        <input v-model="form.model" type="text" placeholder="Model" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
        <input v-model="form.date_from" type="date" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
        <input v-model="form.date_to" type="date" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
      </div>

      <div class="flex justify-end">
        <button class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700" @click="submit">Apply Filters</button>
      </div>

      <div class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-900">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-800">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">When</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">User</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Action</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Model</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Details</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
              <tr v-for="log in logs.data" :key="log.id">
                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ formatDate(log.created_at) }}</td>
                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ log.user_id || '-' }}</td>
                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ log.action || '-' }}</td>
                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ log.model || '-' }}</td>
                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ stringify(log.context ?? log.metadata ?? {}) }}</td>
              </tr>
              <tr v-if="logs.data.length === 0">
                <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No audit logs matched these filters.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="mt-4">
        <pagination :data="logs" />
      </div>
    </div>
  </ManagerLayout>
</template>

<script setup>
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
  logs: Object,
  filters: Object,
})

const form = useForm({
  user_id: props.filters?.user_id || '',
  model: props.filters?.model || '',
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || '',
})

const submit = () => {
  form.get(route('finance.audit.index'), {
    preserveState: true,
    preserveScroll: true,
  })
}

const formatDate = (value) => new Date(value).toLocaleString()
const stringify = (value) => {
  try {
    return typeof value === 'string' ? value : JSON.stringify(value)
  } catch {
    return ''
  }
}
</script>
