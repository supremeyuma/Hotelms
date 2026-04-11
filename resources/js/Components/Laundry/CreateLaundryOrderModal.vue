<script setup>
import Modal from '@/Components/Modal.vue'
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { Camera, Minus, Plus, Shirt, User, X } from 'lucide-vue-next'

const props = defineProps({
  show: Boolean,
  rooms: { type: Array, default: () => [] },
  items: { type: Array, default: () => [] },
  submitUrl: { type: String, required: true },
})

const emit = defineEmits(['close'])

const form = ref({
  room_id: '',
  payment_mode: 'postpaid',
  items: [],
})

const files = ref([])
const filePreviews = ref([])
const submitting = ref(false)

watch(
  () => props.show,
  (visible) => {
    if (visible) {
      resetForm()
    }
  }
)

watch(files, (value) => {
  filePreviews.value.forEach((url) => URL.revokeObjectURL(url))
  filePreviews.value = value.map((file) => URL.createObjectURL(file))
})

const selectedRoom = computed(() =>
  props.rooms.find((room) => String(room.id) === String(form.value.room_id)) || null
)

const total = computed(() =>
  form.value.items.reduce((sum, item) => sum + (Number(item.price) * Number(item.quantity)), 0)
)

function resetForm() {
  form.value = {
    room_id: '',
    payment_mode: 'postpaid',
    items: [],
  }
  files.value = []
  filePreviews.value.forEach((url) => URL.revokeObjectURL(url))
  filePreviews.value = []
  submitting.value = false
}

function selectedQuantity(itemId) {
  return form.value.items.find((item) => item.laundry_item_id === itemId)?.quantity ?? 0
}

function setQuantity(item, quantity) {
  const nextQuantity = Math.max(0, Number(quantity) || 0)
  const existing = form.value.items.find((entry) => entry.laundry_item_id === item.id)

  if (!nextQuantity) {
    form.value.items = form.value.items.filter((entry) => entry.laundry_item_id !== item.id)
    return
  }

  if (existing) {
    existing.quantity = nextQuantity
    return
  }

  form.value.items.push({
    laundry_item_id: item.id,
    quantity: nextQuantity,
    price: item.price,
  })
}

function handleFiles(event) {
  files.value = [...files.value, ...Array.from(event.target.files || [])]
}

function removeFile(index) {
  const [removed] = files.value.splice(index, 1)

  if (removed && filePreviews.value[index]) {
    URL.revokeObjectURL(filePreviews.value[index])
  }
}

function submit() {
  if (submitting.value) return

  submitting.value = true

  const payload = new FormData()
  payload.append('room_id', form.value.room_id)
  payload.append('payment_mode', form.value.payment_mode)

  form.value.items.forEach((item, index) => {
    payload.append(`items[${index}][laundry_item_id]`, item.laundry_item_id)
    payload.append(`items[${index}][quantity]`, item.quantity)
  })

  files.value.forEach((file) => payload.append('images[]', file))

  router.post(props.submitUrl, payload, {
    forceFormData: true,
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
        <h2 class="text-xl font-black text-slate-900">Create Laundry Order</h2>
        <p class="mt-1 text-sm text-slate-500">Create a laundry request for an occupied room using the same service details guests provide.</p>
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

          <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Active Guest</p>
            <p class="mt-2 flex items-center gap-2 text-sm font-bold text-slate-800">
              <User class="h-4 w-4 text-indigo-500" />
              {{ selectedRoom?.active_booking?.guest_name || 'Select a room to load the active booking' }}
            </p>
            <p class="mt-1 text-xs font-medium text-slate-500">
              {{ selectedRoom?.active_booking?.booking_code ? `Booking ${selectedRoom.active_booking.booking_code}` : 'Laundry orders are attached to the room’s current in-house booking.' }}
            </p>
          </div>
        </div>

        <div class="space-y-4">
          <div
            v-for="item in items"
            :key="item.id"
            class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50/70 p-4"
          >
            <div class="flex items-start gap-3">
              <div class="rounded-2xl bg-white p-3 text-slate-500 shadow-sm">
                <Shirt class="h-5 w-5" />
              </div>
              <div>
                <p class="font-bold text-slate-900">{{ item.name }}</p>
                <p class="text-sm text-slate-500">NGN {{ Number(item.price).toLocaleString() }}</p>
                <p v-if="item.description" class="mt-1 text-xs text-slate-400">{{ item.description }}</p>
              </div>
            </div>

            <div class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-2 py-1">
              <button
                type="button"
                class="flex h-9 w-9 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100"
                @click="setQuantity(item, selectedQuantity(item.id) - 1)"
              >
                <Minus class="h-4 w-4" />
              </button>
              <span class="w-8 text-center text-sm font-black text-slate-900">
                {{ selectedQuantity(item.id) }}
              </span>
              <button
                type="button"
                class="flex h-9 w-9 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100"
                @click="setQuantity(item, selectedQuantity(item.id) + 1)"
              >
                <Plus class="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>

        <div class="space-y-3">
          <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Condition Photos</label>
          <div class="flex flex-wrap gap-3">
            <label class="flex h-20 w-20 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 text-slate-400 transition hover:border-indigo-400 hover:text-indigo-600">
              <Camera class="mb-1 h-6 w-6" />
              <span class="text-[8px] font-black uppercase">Add</span>
              <input type="file" multiple accept="image/*" class="hidden" @change="handleFiles" />
            </label>

            <div v-for="(preview, index) in filePreviews" :key="preview" class="group relative">
              <img :src="preview" class="h-20 w-20 rounded-2xl border border-slate-200 object-cover shadow-sm" />
              <button
                type="button"
                class="absolute inset-0 hidden items-center justify-center rounded-2xl bg-black/40 text-white group-hover:flex"
                @click="removeFile(index)"
              >
                <X class="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>

        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
          <p class="text-[10px] font-black uppercase tracking-widest text-amber-700">Payment Option</p>
          <p class="mt-2 text-sm font-bold text-amber-900">Pay on Delivery</p>
          <p class="mt-1 text-xs font-medium text-amber-800">Laundry charges are added to the room bill for settlement at delivery or checkout.</p>
        </div>

        <div class="flex items-center justify-between rounded-2xl bg-slate-900 px-5 py-4 text-white">
          <div>
            <p class="text-xs font-black uppercase tracking-wider text-slate-300">Estimated total</p>
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
