<script setup>
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const props = defineProps({
  templates: Array,
  roomTypes: Array,
  inventoryItems: Array,
})

/* ---------------- STATE ---------------- */

const search = ref('')
const editingId = ref(null)

const cloneForm = useForm({
  from_room_type_id: '',
  to_room_type_id: '',
})

const form = useForm({
  room_type_id: '',
  inventory_item_id: '',
  quantity: ''
})

/* ---------------- COMPUTED ---------------- */

const filtered = computed(() => {
  if (!search.value) return props.templates
  const q = search.value.toLowerCase()
  return props.templates.filter(t =>
    t.room_type.title.toLowerCase().includes(q) ||
    t.inventory_item.name.toLowerCase().includes(q)
  )
})

const grouped = computed(() => {
  return filtered.value.reduce((g, t) => {
    const key = t.room_type.title
    if (!g[key]) g[key] = []
    g[key].push(t)
    return g
  }, {})
})

const roomTotals = (group) =>
  group.reduce((sum, t) => sum + Number(t.quantity), 0)

/* ---------------- ACTIONS ---------------- */

function submit() {
  form.post(route('admin.cleaning-templates.store'), {
    onSuccess: () => form.reset()
  })
}

function updateQuantity(t, qty) {
  router.post(
    route('admin.cleaning-templates.update', t.id),
    { quantity: qty },
    { preserveScroll: true }
  )
}

function destroy(id) {
  if (!confirm('Remove template?')) return
  router.delete(route('admin.cleaning-templates.destroy', id))
}

function cloneTemplates() {
  cloneForm.post(route('admin.cleaning-templates.clone'))
}
</script>

<template>
  <ManagerLayout>
    <div class="space-y-6">

      <!-- Header -->
      <div class="flex justify-between items-center">
        <h1 class="text-2xl font-semibold">Cleaning Inventory Templates</h1>

        <input
          v-model="search"
          class="border rounded px-3 py-2 text-sm w-64"
          placeholder="Search room or item..."
        />
      </div>

      <!-- CREATE -->
      <form
        @submit.prevent="submit"
        class="grid grid-cols-4 gap-3 bg-white p-4 rounded shadow"
      >
        <select v-model="form.room_type_id" required class="border p-2 rounded">
          <option disabled value="">Room Type</option>
          <option v-for="r in roomTypes" :key="r.id" :value="r.id">
            {{ r.title }}
          </option>
        </select>

        <select v-model="form.inventory_item_id" required class="border p-2 rounded">
          <option disabled value="">Item</option>
          <option v-for="i in inventoryItems" :key="i.id" :value="i.id">
            {{ i.name }} ({{ i.unit }})
          </option>
        </select>

        <input
          type="number"
          min="0.01"
          step="0.01"
          v-model="form.quantity"
          class="border p-2 rounded"
          placeholder="Qty"
        />

        <PrimaryButton>Create</PrimaryButton>
      </form>

      <!-- CLONE -->
      <div class="bg-white p-4 rounded shadow flex gap-3">
        <select v-model="cloneForm.from_room_type_id" class="border p-2 rounded">
          <option disabled value="">Clone from</option>
          <option v-for="r in roomTypes" :key="r.id" :value="r.id">
            {{ r.title }}
          </option>
        </select>

        <select v-model="cloneForm.to_room_type_id" class="border p-2 rounded">
          <option disabled value="">Clone to</option>
          <option v-for="r in roomTypes" :key="r.id" :value="r.id">
            {{ r.title }}
          </option>
        </select>

        <PrimaryButton @click="cloneTemplates">Clone</PrimaryButton>
      </div>

      <!-- GROUPED TABLE -->
      <div v-for="(group, room) in grouped" :key="room" class="bg-white rounded shadow">
        <div class="p-3 border-b font-semibold flex justify-between">
          <span>{{ room }}</span>
          <span class="text-sm text-gray-500">
            Total items: {{ roomTotals(group) }}
          </span>
        </div>

        <table class="w-full text-sm">
          <tbody>
            <tr v-for="t in group" :key="t.id" class="border-t">
              <td class="p-2">
                {{ t.inventory_item.name }}
                <span class="text-xs text-gray-500">({{ t.inventory_item.unit }})</span>

                <div
                  v-if="t.quantity > t.inventory_item.total_stock"
                  class="text-xs text-red-600 font-semibold"
                >
                  ⚠ Insufficient stock
                </div>
              </td>

              <td class="p-2 w-32">
                <input
                  type="number"
                  step="0.01"
                  min="0.01"
                  :value="t.quantity"
                  @change="updateQuantity(t, $event.target.value)"
                  class="border rounded px-2 py-1 w-full"
                />
              </td>

              <td class="p-2 text-right">
                <button class="btn-danger" @click="destroy(t.id)">
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
