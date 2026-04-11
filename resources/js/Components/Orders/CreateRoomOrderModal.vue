<script setup>
import Modal from '@/Components/Modal.vue'
import { computed, reactive, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  show: Boolean,
  area: { type: String, required: true },
  rooms: { type: Array, default: () => [] },
  menuItems: { type: Array, default: () => [] },
  submitUrl: { type: String, required: true },
})

const emit = defineEmits(['close'])

const form = reactive({
  room_id: '',
  notes: '',
  items: [],
})

const submitting = ref(false)

watch(
  () => props.show,
  (visible) => {
    if (visible) {
      resetForm()
    }
  }
)

const groupedItems = computed(() => {
  const groups = new Map()

  props.menuItems.forEach((item) => {
    const groupName = item.subcategory?.name || item.category?.name || 'Menu'

    if (!groups.has(groupName)) {
      groups.set(groupName, [])
    }

    groups.get(groupName).push(item)
  })

  return Array.from(groups.entries()).map(([label, items]) => ({ label, items }))
})

const total = computed(() =>
  form.items.reduce((sum, item) => sum + (Number(item.price) * Number(item.quantity)), 0)
)

function resetForm() {
  form.room_id = ''
  form.notes = ''
  form.items = []
  submitting.value = false
}

function selectedQuantity(itemId) {
  return form.items.find((item) => item.menu_item_id === itemId)?.quantity ?? 0
}

function selectedNote(itemId) {
  return form.items.find((item) => item.menu_item_id === itemId)?.note ?? ''
}

function setQuantity(item, quantity) {
  const nextQuantity = Math.max(0, Number(quantity) || 0)
  const existing = form.items.find((entry) => entry.menu_item_id === item.id)

  if (!nextQuantity) {
    form.items = form.items.filter((entry) => entry.menu_item_id !== item.id)
    return
  }

  if (existing) {
    existing.quantity = nextQuantity
    return
  }

  form.items.push({
    menu_item_id: item.id,
    quantity: nextQuantity,
    note: '',
    name: item.name,
    price: item.price,
  })
}

function setItemNote(itemId, note) {
  const existing = form.items.find((entry) => entry.menu_item_id === itemId)

  if (existing) {
    existing.note = note
  }
}

function submit() {
  if (submitting.value) return

  submitting.value = true

  router.post(props.submitUrl, {
    room_id: form.room_id,
    notes: form.notes,
    items: form.items.map((item) => ({
      menu_item_id: item.menu_item_id,
      quantity: item.quantity,
      note: item.note || null,
    })),
  }, {
    preserveScroll: true,
    onSuccess: () => {
      resetForm()
      emit('close')
    },
    onFinish: () => {
      submitting.value = false
    },
  })
}
</script>

<template>
  <Modal :show="show" @close="emit('close')">
    <template #title>
      <div>
        <h2 class="text-xl font-black text-slate-900">Create {{ area }} order</h2>
        <p class="mt-1 text-sm text-slate-500">Attach the order to an occupied room and its active booking.</p>
      </div>
    </template>

    <template #content>
      <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-2">
          <label class="space-y-2 text-sm font-semibold text-slate-700">
            <span>Occupied room</span>
            <select
              v-model="form.room_id"
              class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
            >
              <option value="">Select room</option>
              <option v-for="room in rooms" :key="room.id" :value="room.id">
                {{ room.name }}{{ room.active_booking?.guest_name ? ` - ${room.active_booking.guest_name}` : '' }}
              </option>
            </select>
          </label>

          <label class="space-y-2 text-sm font-semibold text-slate-700">
            <span>Order note</span>
            <textarea
              v-model="form.notes"
              rows="3"
              class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
              placeholder="Optional room-level note"
            />
          </label>
        </div>

        <div class="space-y-4">
          <div
            v-for="group in groupedItems"
            :key="group.label"
            class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4"
          >
            <h3 class="mb-4 text-sm font-black uppercase tracking-wider text-slate-500">{{ group.label }}</h3>

            <div class="space-y-3">
              <div
                v-for="item in group.items"
                :key="item.id"
                class="rounded-2xl bg-white p-4 shadow-sm"
              >
                <div class="flex items-start justify-between gap-4">
                  <div>
                    <p class="font-bold text-slate-900">{{ item.name }}</p>
                    <p class="text-sm text-slate-500">NGN {{ Number(item.price).toLocaleString() }}</p>
                  </div>

                  <div class="flex items-center gap-2">
                    <button
                      type="button"
                      class="h-9 w-9 rounded-xl border border-slate-200 text-lg font-black text-slate-700"
                      @click="setQuantity(item, selectedQuantity(item.id) - 1)"
                    >
                      -
                    </button>
                    <span class="w-8 text-center text-sm font-black text-slate-900">
                      {{ selectedQuantity(item.id) }}
                    </span>
                    <button
                      type="button"
                      class="h-9 w-9 rounded-xl border border-slate-200 text-lg font-black text-slate-700"
                      @click="setQuantity(item, selectedQuantity(item.id) + 1)"
                    >
                      +
                    </button>
                  </div>
                </div>

                <textarea
                  v-if="selectedQuantity(item.id)"
                  :value="selectedNote(item.id)"
                  rows="2"
                  class="mt-3 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                  placeholder="Item note"
                  @input="setItemNote(item.id, $event.target.value)"
                />
              </div>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-between rounded-2xl bg-slate-900 px-5 py-4 text-white">
          <div>
            <p class="text-xs font-black uppercase tracking-wider text-slate-300">Order total</p>
            <p class="text-lg font-black">NGN {{ total.toLocaleString() }}</p>
          </div>

          <button
            type="button"
            class="rounded-xl bg-white px-5 py-3 text-xs font-black uppercase tracking-wider text-slate-900 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="submitting || !form.room_id || !form.items.length"
            @click="submit"
          >
            {{ submitting ? 'Creating...' : 'Create Order' }}
          </button>
        </div>
      </div>
    </template>
  </Modal>
</template>
