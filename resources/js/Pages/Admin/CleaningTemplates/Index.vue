<script setup>
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const props = defineProps({
  templates: Array,
  roomTypes: Array,
  inventoryItems: Array,
  sourceLocation: String,
})

const search = ref('')

const cloneForm = useForm({
  from_room_type_id: '',
  to_room_type_id: '',
})

const form = useForm({
  room_type_id: '',
  inventory_item_id: '',
  quantity: '',
})

const filtered = computed(() => {
  if (!search.value) {
    return props.templates
  }

  const q = search.value.toLowerCase()

  return props.templates.filter(template =>
    template.room_type.title.toLowerCase().includes(q) ||
    template.inventory_item.name.toLowerCase().includes(q)
  )
})

const grouped = computed(() => filtered.value.reduce((groups, template) => {
  const key = template.room_type.title

  if (!groups[key]) {
    groups[key] = []
  }

  groups[key].push(template)

  return groups
}, {}))

function roomTotals(group) {
  return group.reduce((sum, template) => sum + Number(template.quantity), 0)
}

function submit() {
  form.post(route('admin.cleaning-templates.store'), {
    onSuccess: () => form.reset(),
  })
}

function updateQuantity(template, quantity) {
  router.post(route('admin.cleaning-templates.update', template.id), {
    quantity,
  }, {
    preserveScroll: true,
  })
}

function destroy(id) {
  if (!confirm('Remove template?')) {
    return
  }

  router.delete(route('admin.cleaning-templates.destroy', id), {
    preserveScroll: true,
  })
}

function cloneTemplates() {
  cloneForm.post(route('admin.cleaning-templates.clone'))
}

function formatQuantity(value) {
  return Number(value ?? 0).toLocaleString(undefined, {
    minimumFractionDigits: Number(value ?? 0) % 1 === 0 ? 0 : 2,
    maximumFractionDigits: 2,
  })
}
</script>

<template>
  <ManagerLayout>
    <div class="space-y-6">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="space-y-2">
          <h1 class="text-2xl font-semibold">Cleaning Inventory Templates</h1>
          <p class="text-sm text-slate-500">
            Housekeeping deductions will pull stock from <strong class="text-slate-700">{{ sourceLocation }}</strong>.
          </p>
        </div>

        <input
          v-model="search"
          class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm lg:w-64"
          placeholder="Search room or item..."
        />
      </div>

      <form
        class="grid gap-3 rounded-[2rem] bg-white p-4 shadow-sm md:grid-cols-4"
        @submit.prevent="submit"
      >
        <select v-model="form.room_type_id" required class="rounded-xl border border-slate-200 p-2">
          <option disabled value="">Room Type</option>
          <option v-for="roomType in roomTypes" :key="roomType.id" :value="roomType.id">
            {{ roomType.title }}
          </option>
        </select>

        <select v-model="form.inventory_item_id" required class="rounded-xl border border-slate-200 p-2">
          <option disabled value="">Item</option>
          <option v-for="item in inventoryItems" :key="item.id" :value="item.id">
            {{ item.name }} ({{ item.unit }}) - {{ formatQuantity(item.total_stock) }} available
          </option>
        </select>

        <input
          v-model="form.quantity"
          type="number"
          min="0.01"
          step="0.01"
          class="rounded-xl border border-slate-200 p-2"
          placeholder="Qty"
        />

        <PrimaryButton :disabled="form.processing">
          {{ form.processing ? 'Saving...' : 'Create' }}
        </PrimaryButton>
      </form>

      <div class="flex flex-col gap-3 rounded-[2rem] bg-white p-4 shadow-sm md:flex-row">
        <select v-model="cloneForm.from_room_type_id" class="rounded-xl border border-slate-200 p-2">
          <option disabled value="">Clone from</option>
          <option v-for="roomType in roomTypes" :key="roomType.id" :value="roomType.id">
            {{ roomType.title }}
          </option>
        </select>

        <select v-model="cloneForm.to_room_type_id" class="rounded-xl border border-slate-200 p-2">
          <option disabled value="">Clone to</option>
          <option v-for="roomType in roomTypes" :key="roomType.id" :value="roomType.id">
            {{ roomType.title }}
          </option>
        </select>

        <PrimaryButton :disabled="cloneForm.processing" @click="cloneTemplates">
          {{ cloneForm.processing ? 'Cloning...' : 'Clone' }}
        </PrimaryButton>
      </div>

      <div v-for="(group, room) in grouped" :key="room" class="overflow-hidden rounded-[2rem] bg-white shadow-sm">
        <div class="flex justify-between border-b border-slate-100 p-4 font-semibold">
          <span>{{ room }}</span>
          <span class="text-sm text-slate-500">Total items: {{ formatQuantity(roomTotals(group)) }}</span>
        </div>

        <table class="w-full text-sm">
          <tbody>
            <tr v-for="template in group" :key="template.id" class="border-t border-slate-100">
              <td class="p-3">
                {{ template.inventory_item.name }}
                <span class="text-xs text-slate-500">({{ template.inventory_item.unit }})</span>

                <div v-if="template.quantity > template.inventory_item.total_stock" class="text-xs font-semibold text-red-600">
                  Insufficient stock
                </div>
                <div v-else-if="template.inventory_item.low_stock" class="text-xs font-semibold text-amber-600">
                  Low stock
                </div>
              </td>

              <td class="p-3 text-slate-500">{{ formatQuantity(template.inventory_item.total_stock) }} available</td>

              <td class="w-32 p-3">
                <input
                  :value="template.quantity"
                  type="number"
                  step="0.01"
                  min="0.01"
                  class="w-full rounded-xl border border-slate-200 px-2 py-1"
                  @change="updateQuantity(template, $event.target.value)"
                />
              </td>

              <td class="p-3 text-right">
                <button class="btn-danger" @click="destroy(template.id)">
                  Remove
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </ManagerLayout>
</template>
