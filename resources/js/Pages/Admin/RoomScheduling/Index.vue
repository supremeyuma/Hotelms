<script setup>
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import InputError from '@/Components/InputError.vue'
import { Head, useForm, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
  CalendarClock,
  CalendarOff,
  CalendarRange,
  Info,
  Pencil,
  Plus,
  Trash2,
  TrendingUp,
  Eye,
  EyeOff,
  BedDouble,
  Layers,
  Receipt,
} from 'lucide-vue-next'

const props = defineProps({
  priceSchedules: Array,
  availabilitySchedules: Array,
  roomTypes: Array,
  rooms: Array,
  properties: Array,
  defaultPropertyId: [Number, null],
  today: String,
  summary: Object,
})

const page = usePage()
const showFlash = computed(() => Boolean(page.props.flash?.success))
const flashMessage = computed(() => page.props.flash?.success || '')

function formatPrice(value) {
  return `₦${Number(value || 0).toLocaleString()}`
}

function priceStatus(schedule) {
  if (!schedule.is_active) return { label: 'Paused', cls: 'bg-slate-100 text-slate-600' }
  if (schedule.is_past) return { label: 'Expired', cls: 'bg-slate-100 text-slate-500' }
  if (schedule.start_date > props.today) return { label: 'Scheduled', cls: 'bg-amber-100 text-amber-700' }
  return { label: 'Live', cls: 'bg-emerald-100 text-emerald-700' }
}

function blockStatus(schedule) {
  if (!schedule.is_unavailable) return { label: 'Lifted', cls: 'bg-slate-100 text-slate-600' }
  if (schedule.is_past) return { label: 'Ended', cls: 'bg-slate-100 text-slate-500' }
  if (schedule.start_date > props.today) return { label: 'Upcoming', cls: 'bg-amber-100 text-amber-700' }
  return { label: 'Blocking', cls: 'bg-rose-100 text-rose-700' }
}

/* ---------------- Price schedule form ---------------- */
const priceForm = useForm({
  room_type_id: '',
  property_id: props.defaultPropertyId ?? '',
  start_date: '',
  end_date: '',
  custom_price: '',
  description: '',
  is_active: true,
})

function submitPrice() {
  priceForm.post(route('admin.room-scheduling.room-type-prices.store'), {
    preserveScroll: true,
    onSuccess: () => priceForm.reset('room_type_id', 'start_date', 'end_date', 'custom_price', 'description'),
  })
}

const editingPriceId = ref(null)
const priceEditForm = useForm({
  start_date: '',
  end_date: '',
  custom_price: '',
  description: '',
  is_active: true,
})

function openPriceEdit(schedule) {
  editingPriceId.value = schedule.id
  priceEditForm.clearErrors()
  priceEditForm.defaults({
    start_date: schedule.start_date,
    end_date: schedule.end_date,
    custom_price: String(schedule.custom_price),
    description: schedule.description || '',
    is_active: schedule.is_active,
  })
  priceEditForm.reset()
}

function savePriceEdit(schedule) {
  priceEditForm.put(route('admin.room-scheduling.room-type-prices.update', schedule.id), {
    preserveScroll: true,
    onSuccess: () => {
      editingPriceId.value = null
      priceEditForm.reset()
    },
  })
}

function togglePrice(schedule) {
  router.put(
    route('admin.room-scheduling.room-type-prices.update', schedule.id),
    { is_active: !schedule.is_active },
    { preserveScroll: true },
  )
}

const destroyForm = useForm({})

async function destroyPrice(schedule) {
  if (!window.confirm(`Delete the ${formatPrice(schedule.custom_price)} price schedule for ${schedule.room_type_title}? This cannot be undone.`)) {
    return
  }
  destroyForm.delete(route('admin.room-scheduling.room-type-prices.destroy', schedule.id), {
    preserveScroll: true,
  })
}

/* ---------------- Availability form ---------------- */
const blockForm = useForm({
  scope: 'room',
  room_id: '',
  room_type_id: '',
  property_id: props.defaultPropertyId ?? '',
  start_date: '',
  end_date: '',
  reason: '',
  notes: '',
  is_unavailable: true,
})

blockForm.transform((data) => {
  const payload = {
    property_id: data.property_id,
    start_date: data.start_date,
    end_date: data.end_date,
    reason: data.reason,
    notes: data.notes || null,
    is_unavailable: data.is_unavailable,
  }
  if (data.scope === 'room' && data.room_id) {
    payload.room_id = data.room_id
  }
  if (data.scope === 'room_type' && data.room_type_id) {
    payload.room_type_id = data.room_type_id
  }
  return payload
})

const scopeSwitcher = {
  room() { blockForm.room_type_id = '' },
  room_type() { blockForm.room_id = '' },
}

function changeScope() {
  scopeSwitcher[blockForm.scope]?.()
}

function submitBlock() {
  blockForm.post(route('admin.room-scheduling.room-availability.store'), {
    preserveScroll: true,
    onSuccess: () => blockForm.reset('room_id', 'room_type_id', 'start_date', 'end_date', 'reason'),
  })
}

const editingBlockId = ref(null)
const blockEditForm = useForm({
  start_date: '',
  end_date: '',
  reason: '',
  notes: '',
  is_unavailable: true,
})

function openBlockEdit(schedule) {
  editingBlockId.value = schedule.id
  blockEditForm.clearErrors()
  blockEditForm.defaults({
    start_date: schedule.start_date,
    end_date: schedule.end_date,
    reason: schedule.reason,
    notes: schedule.notes || '',
    is_unavailable: schedule.is_unavailable,
  })
  blockEditForm.reset()
}

function saveBlockEdit(schedule) {
  blockEditForm.put(route('admin.room-scheduling.room-availability.update', schedule.id), {
    preserveScroll: true,
    onSuccess: () => {
      editingBlockId.value = null
      blockEditForm.reset()
    },
  })
}

function toggleBlock(schedule) {
  router.put(
    route('admin.room-scheduling.room-availability.update', schedule.id),
    { is_unavailable: !schedule.is_unavailable },
    { preserveScroll: true },
  )
}

async function destroyBlock(schedule) {
  if (!window.confirm(`Remove the availability block for ${schedule.label}? This cannot be undone.`)) {
    return
  }
  destroyForm.delete(route('admin.room-scheduling.room-availability.destroy', schedule.id), {
    preserveScroll: true,
  })
}
</script>

<template>
  <ManagerLayout>
    <Head title="Room Scheduling" />

    <div class="space-y-8">
      <div v-if="showFlash" class="rounded-[2rem] border border-emerald-200 bg-emerald-50 px-6 py-4 text-sm font-semibold text-emerald-800">
        {{ flashMessage }}
      </div>

      <section class="overflow-hidden rounded-[2rem] bg-slate-900 text-white shadow-2xl shadow-slate-200">
        <div class="grid gap-6 px-6 py-8 sm:px-8 xl:grid-cols-[1.2fr_0.8fr]">
          <div class="space-y-4">
            <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-xs uppercase font-semibold tracking-wide text-slate-200">
              <TrendingUp class="h-3.5 w-3.5" />
              Yield Management
            </div>
            <h1 class="max-w-3xl text-3xl font-black tracking-tight sm:text-4xl">
              Plan future rates and room availability.
            </h1>
            <p class="max-w-2xl text-sm leading-6 text-slate-300">
              Set custom prices for a room type and block rooms during high-demand dates before guests book them.
            </p>
          </div>

          <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-1">
            <a href="#price-schedules" class="rounded-[1.75rem] border border-white/10 bg-white/10 p-5 transition hover:bg-white/15">
              <p class="text-xs uppercase font-semibold tracking-wide text-slate-300">Active price plans</p>
              <p class="mt-3 text-3xl font-black">{{ summary.active_price_schedules }}</p>
            </a>
            <a href="#availability-blocks" class="rounded-[1.75rem] border border-white/10 bg-white/10 p-5 transition hover:bg-white/15">
              <p class="text-xs uppercase font-semibold tracking-wide text-slate-300">Active blocks</p>
              <p class="mt-3 text-3xl font-black">{{ summary.active_unavailability }}</p>
            </a>
            <a href="#availability-blocks" class="rounded-[1.75rem] border border-white/10 bg-white/10 p-5 transition hover:bg-white/15">
              <p class="text-xs uppercase font-semibold tracking-wide text-slate-300">Rooms blocked</p>
              <p class="mt-3 text-3xl font-black">{{ summary.rooms_blocked }}</p>
            </a>
          </div>
        </div>
      </section>

      <div class="rounded-[2rem] border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-800">
        <div class="flex items-start gap-3">
          <Info class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" />
          <p>
            A custom price applies only when a guest's entire stay falls inside the schedule window. If any night
            falls outside it, the room type's base price is quoted instead. Availability blocks take priority over
            price plans — a blocked room is never offered.
          </p>
        </div>
      </div>

      <section id="price-schedules" class="scroll-mt-8">
        <div class="mb-5 flex items-center gap-3">
          <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-900 text-white">
            <Receipt class="h-5 w-5" />
          </div>
          <div>
            <h2 class="text-2xl font-black tracking-tight text-slate-900">Room-type price schedules</h2>
            <p class="text-sm text-slate-500">Overwrite the base rate for a room type during a future window.</p>
          </div>
        </div>

        <div class="grid gap-8 xl:grid-cols-[0.95fr_1.05fr]">
          <form @submit.prevent="submitPrice" class="h-fit rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="grid gap-5">
              <label class="block">
                <span class="text-sm font-bold text-slate-700">Room type</span>
                <select v-model="priceForm.room_type_id" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400">
                  <option value="" disabled>Select a room type</option>
                  <option v-for="type in roomTypes" :key="type.id" :value="type.id">
                    {{ type.title }} (base {{ formatPrice(type.base_price) }})
                  </option>
                </select>
                <InputError :message="priceForm.errors.room_type_id" class="mt-2" />
              </label>

              <label v-if="properties.length > 1" class="block">
                <span class="text-sm font-bold text-slate-700">Property</span>
                <select v-model="priceForm.property_id" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400">
                  <option v-for="property in properties" :key="property.id" :value="property.id">{{ property.name }}</option>
                </select>
                <InputError :message="priceForm.errors.property_id" class="mt-2" />
              </label>

              <label class="block">
                <span class="text-sm font-bold text-slate-700">Custom nightly price (₦)</span>
                <input v-model="priceForm.custom_price" type="number" min="0" step="0.01" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400" placeholder="60000" />
                <InputError :message="priceForm.errors.custom_price" class="mt-2" />
              </label>

              <div class="grid gap-5 sm:grid-cols-2">
                <label class="block">
                  <span class="text-sm font-bold text-slate-700">Starts</span>
                  <input v-model="priceForm.start_date" type="date" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400" />
                  <InputError :message="priceForm.errors.start_date" class="mt-2" />
                </label>
                <label class="block">
                  <span class="text-sm font-bold text-slate-700">Ends</span>
                  <input v-model="priceForm.end_date" type="date" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400" />
                  <InputError :message="priceForm.errors.end_date" class="mt-2" />
                </label>
              </div>

              <label class="block">
                <span class="text-sm font-bold text-slate-700">Description <span class="font-normal text-slate-400">(optional)</span></span>
                <textarea v-model="priceForm.description" rows="2" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400" placeholder="e.g. Peak season weekend uplift" />
                <InputError :message="priceForm.errors.description" class="mt-2" />
              </label>

              <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                <input v-model="priceForm.is_active" type="checkbox" class="rounded border-slate-300 text-slate-900 focus:ring-slate-400" />
                <span class="text-sm font-semibold text-slate-700">Activate this price immediately</span>
              </label>

              <button
                type="submit"
                :disabled="priceForm.processing"
                class="inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
              >
                <Plus class="h-4 w-4" />
                {{ priceForm.processing ? 'Saving...' : 'Add price schedule' }}
              </button>
            </div>
          </form>

          <section class="space-y-5">
            <article
              v-for="schedule in priceSchedules"
              :key="schedule.id"
              class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm"
            >
              <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-2">
                  <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-2xl font-black tracking-tight text-slate-900">{{ schedule.room_type_title }}</h3>
                    <span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-[0.18em]" :class="priceStatus(schedule).cls">
                      {{ priceStatus(schedule).label }}
                    </span>
                  </div>
                  <p class="text-sm text-slate-500">
                    Base <span class="line-through">{{ formatPrice(schedule.base_price) }}</span>
                    <span class="mx-1">→</span>
                    <span class="font-black text-slate-900">{{ formatPrice(schedule.custom_price) }}</span> / night
                  </p>
                </div>

                <button
                  type="button"
                  @click="togglePrice(schedule)"
                  class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                >
                  <EyeOff v-if="schedule.is_active" class="h-4 w-4" />
                  <Eye v-else class="h-4 w-4" />
                  {{ schedule.is_active ? 'Pause' : 'Activate' }}
                </button>
              </div>

              <div v-if="schedule.description" class="mt-4 text-sm leading-6 text-slate-500">
                {{ schedule.description }}
              </div>

              <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div class="rounded-[1.5rem] bg-slate-50 p-4">
                  <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Window</p>
                  <p class="mt-3 flex items-center gap-2 text-base font-black text-slate-900">
                    <CalendarRange class="h-4 w-4 text-slate-400" />
                    {{ schedule.start_date }} → {{ schedule.end_date }}
                  </p>
                </div>
                <div class="rounded-[1.5rem] bg-slate-50 p-4">
                  <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Applied to stays</p>
                  <p class="mt-3 text-base font-black text-slate-900">{{ schedule.room_type_title }}</p>
                  <p class="mt-1 text-sm text-slate-500">Whole-stay rule applies</p>
                </div>
              </div>

              <div v-if="editingPriceId === schedule.id" class="mt-5 rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Edit schedule</p>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                  <label class="block">
                    <span class="text-sm font-bold text-slate-700">Starts</span>
                    <input v-model="priceEditForm.start_date" type="date" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400" />
                    <InputError :message="priceEditForm.errors.start_date" class="mt-2" />
                  </label>
                  <label class="block">
                    <span class="text-sm font-bold text-slate-700">Ends</span>
                    <input v-model="priceEditForm.end_date" type="date" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400" />
                    <InputError :message="priceEditForm.errors.end_date" class="mt-2" />
                  </label>
                  <label class="block">
                    <span class="text-sm font-bold text-slate-700">Nightly price (₦)</span>
                    <input v-model="priceEditForm.custom_price" type="number" min="0" step="0.01" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400" />
                    <InputError :message="priceEditForm.errors.custom_price" class="mt-2" />
                  </label>
                  <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3">
                    <input v-model="priceEditForm.is_active" type="checkbox" class="rounded border-slate-300 text-slate-900 focus:ring-slate-400" />
                    <span class="text-sm font-semibold text-slate-700">Active</span>
                  </label>
                </div>
                <InputError :message="priceEditForm.errors.description" class="mt-3" />
                <div class="mt-4 flex gap-3">
                  <button
                    type="button"
                    @click="savePriceEdit(schedule)"
                    :disabled="priceEditForm.processing"
                    class="inline-flex items-center gap-2 rounded-2xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                  >
                    {{ priceEditForm.processing ? 'Saving...' : 'Save changes' }}
                  </button>
                  <button
                    type="button"
                    @click="editingPriceId = null"
                    class="inline-flex items-center rounded-2xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
                  >
                    Cancel
                  </button>
                </div>
              </div>

              <div class="mt-5 flex gap-3">
                <button
                  v-if="editingPriceId !== schedule.id"
                  type="button"
                  @click="openPriceEdit(schedule)"
                  class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                >
                  <Pencil class="h-4 w-4" />
                  Edit
                </button>
                <button
                  type="button"
                  @click="destroyPrice(schedule)"
                  :disabled="destroyForm.processing"
                  class="inline-flex items-center gap-2 rounded-2xl border border-rose-200 bg-white px-4 py-2.5 text-sm font-bold text-rose-700 transition hover:border-rose-300 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  <Trash2 class="h-4 w-4" />
                  {{ destroyForm.processing ? 'Deleting...' : 'Delete' }}
                </button>
              </div>
            </article>

            <div v-if="!priceSchedules.length" class="rounded-[2rem] border border-dashed border-slate-300 bg-white px-6 py-16 text-center shadow-sm">
              <Receipt class="mx-auto h-12 w-12 text-slate-300" />
              <h3 class="mt-4 text-xl font-black text-slate-900">No price schedules yet</h3>
              <p class="mt-2 text-sm text-slate-500">Add a price plan to override the base rate for a room type during a future window.</p>
            </div>
          </section>
        </div>
      </section>

      <section id="availability-blocks" class="scroll-mt-8">
        <div class="mb-5 flex items-center gap-3">
          <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-900 text-white">
            <CalendarOff class="h-5 w-5" />
          </div>
          <div>
            <h2 class="text-2xl font-black tracking-tight text-slate-900">Availability blocks</h2>
            <p class="text-sm text-slate-500">Take a specific room or an entire room type out of the booking flow for a window.</p>
          </div>
        </div>

        <div class="grid gap-8 xl:grid-cols-[0.95fr_1.05fr]">
          <form @submit.prevent="submitBlock" class="h-fit rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="grid gap-5">
              <label class="block">
                <span class="text-sm font-bold text-slate-700">Block scope</span>
                <div class="mt-2 grid grid-cols-2 gap-3">
                  <label
                    class="flex cursor-pointer items-center gap-3 rounded-2xl border px-4 py-3 transition"
                    :class="blockForm.scope === 'room' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'"
                  >
                    <input v-model="blockForm.scope" type="radio" value="room" class="sr-only" @change="changeScope" />
                    <BedDouble class="h-4 w-4" />
                    <span class="text-sm font-bold">A specific room</span>
                  </label>
                  <label
                    class="flex cursor-pointer items-center gap-3 rounded-2xl border px-4 py-3 transition"
                    :class="blockForm.scope === 'room_type' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'"
                  >
                    <input v-model="blockForm.scope" type="radio" value="room_type" class="sr-only" @change="changeScope" />
                    <Layers class="h-4 w-4" />
                    <span class="text-sm font-bold">A whole room type</span>
                  </label>
                </div>
              </label>

              <label v-if="blockForm.scope === 'room'" class="block">
                <span class="text-sm font-bold text-slate-700">Room</span>
                <select v-model="blockForm.room_id" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400">
                  <option value="" disabled>Select a room</option>
                  <option v-for="room in rooms" :key="room.id" :value="room.id">
                    {{ room.label }} — {{ room.room_type_title }}
                  </option>
                </select>
                <InputError :message="blockForm.errors.room_id" class="mt-2" />
              </label>

              <label v-else class="block">
                <span class="text-sm font-bold text-slate-700">Room type</span>
                <select v-model="blockForm.room_type_id" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400">
                  <option value="" disabled>Select a room type</option>
                  <option v-for="type in roomTypes" :key="type.id" :value="type.id">{{ type.title }}</option>
                </select>
                <InputError :message="blockForm.errors.room_type_id" class="mt-2" />
              </label>

              <label v-if="properties.length > 1" class="block">
                <span class="text-sm font-bold text-slate-700">Property</span>
                <select v-model="blockForm.property_id" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400">
                  <option v-for="property in properties" :key="property.id" :value="property.id">{{ property.name }}</option>
                </select>
                <InputError :message="blockForm.errors.property_id" class="mt-2" />
              </label>

              <div class="grid gap-5 sm:grid-cols-2">
                <label class="block">
                  <span class="text-sm font-bold text-slate-700">Starts</span>
                  <input v-model="blockForm.start_date" type="date" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400" />
                  <InputError :message="blockForm.errors.start_date" class="mt-2" />
                </label>
                <label class="block">
                  <span class="text-sm font-bold text-slate-700">Ends</span>
                  <input v-model="blockForm.end_date" type="date" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400" />
                  <InputError :message="blockForm.errors.end_date" class="mt-2" />
                </label>
              </div>

              <label class="block">
                <span class="text-sm font-bold text-slate-700">Reason</span>
                <input v-model="blockForm.reason" type="text" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400" placeholder="e.g. Private event, renovation" />
                <InputError :message="blockForm.errors.reason" class="mt-2" />
              </label>

              <label class="block">
                <span class="text-sm font-bold text-slate-700">Notes <span class="font-normal text-slate-400">(optional)</span></span>
                <textarea v-model="blockForm.notes" rows="2" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400" placeholder="Internal context for the operations team" />
                <InputError :message="blockForm.errors.notes" class="mt-2" />
              </label>

              <button
                type="submit"
                :disabled="blockForm.processing"
                class="inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
              >
                <Plus class="h-4 w-4" />
                {{ blockForm.processing ? 'Saving...' : 'Add availability block' }}
              </button>
            </div>
          </form>

          <section class="space-y-5">
            <article
              v-for="schedule in availabilitySchedules"
              :key="schedule.id"
              class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm"
            >
              <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-2">
                  <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-2xl font-black tracking-tight text-slate-900">{{ schedule.label }}</h3>
                    <span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-[0.18em]" :class="blockStatus(schedule).cls">
                      {{ blockStatus(schedule).label }}
                    </span>
                  </div>
                  <p class="text-sm font-medium text-slate-500">{{ schedule.reason }}</p>
                </div>

                <button
                  type="button"
                  @click="toggleBlock(schedule)"
                  class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                >
                  <Eye v-if="schedule.is_unavailable" class="h-4 w-4" />
                  <EyeOff v-else class="h-4 w-4" />
                  {{ schedule.is_unavailable ? 'Lift block' : 'Reinstate' }}
                </button>
              </div>

              <div v-if="schedule.notes" class="mt-4 text-sm leading-6 text-slate-500">{{ schedule.notes }}</div>

              <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div class="rounded-[1.5rem] bg-slate-50 p-4">
                  <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Blocked window</p>
                  <p class="mt-3 flex items-center gap-2 text-base font-black text-slate-900">
                    <CalendarOff class="h-4 w-4 text-slate-400" />
                    {{ schedule.start_date }} → {{ schedule.end_date }}
                  </p>
                </div>
                <div class="rounded-[1.5rem] bg-slate-50 p-4">
                  <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Affects</p>
                  <p class="mt-3 flex items-center gap-2 text-base font-black text-slate-900">
                    <BedDouble class="h-4 w-4 text-slate-400" />
                    {{ schedule.room_id ? schedule.label : schedule.label }}
                  </p>
                </div>
              </div>

              <div v-if="editingBlockId === schedule.id" class="mt-5 rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Edit block</p>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                  <label class="block">
                    <span class="text-sm font-bold text-slate-700">Starts</span>
                    <input v-model="blockEditForm.start_date" type="date" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400" />
                    <InputError :message="blockEditForm.errors.start_date" class="mt-2" />
                  </label>
                  <label class="block">
                    <span class="text-sm font-bold text-slate-700">Ends</span>
                    <input v-model="blockEditForm.end_date" type="date" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400" />
                    <InputError :message="blockEditForm.errors.end_date" class="mt-2" />
                  </label>
                  <label class="block">
                    <span class="text-sm font-bold text-slate-700">Reason</span>
                    <input v-model="blockEditForm.reason" type="text" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400" />
                    <InputError :message="blockEditForm.errors.reason" class="mt-2" />
                  </label>
                  <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3">
                    <input v-model="blockEditForm.is_unavailable" type="checkbox" class="rounded border-slate-300 text-slate-900 focus:ring-slate-400" />
                    <span class="text-sm font-semibold text-slate-700">Blocking availability</span>
                  </label>
                </div>
                <InputError :message="blockEditForm.errors.notes" class="mt-3" />
                <div class="mt-4 flex gap-3">
                  <button
                    type="button"
                    @click="saveBlockEdit(schedule)"
                    :disabled="blockEditForm.processing"
                    class="inline-flex items-center gap-2 rounded-2xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                  >
                    {{ blockEditForm.processing ? 'Saving...' : 'Save changes' }}
                  </button>
                  <button
                    type="button"
                    @click="editingBlockId = null"
                    class="inline-flex items-center rounded-2xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
                  >
                    Cancel
                  </button>
                </div>
              </div>

              <div class="mt-5 flex gap-3">
                <button
                  v-if="editingBlockId !== schedule.id"
                  type="button"
                  @click="openBlockEdit(schedule)"
                  class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                >
                  <Pencil class="h-4 w-4" />
                  Edit
                </button>
                <button
                  type="button"
                  @click="destroyBlock(schedule)"
                  :disabled="destroyForm.processing"
                  class="inline-flex items-center gap-2 rounded-2xl border border-rose-200 bg-white px-4 py-2.5 text-sm font-bold text-rose-700 transition hover:border-rose-300 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  <Trash2 class="h-4 w-4" />
                  {{ destroyForm.processing ? 'Deleting...' : 'Delete' }}
                </button>
              </div>
            </article>

            <div v-if="!availabilitySchedules.length" class="rounded-[2rem] border border-dashed border-slate-300 bg-white px-6 py-16 text-center shadow-sm">
              <CalendarClock class="mx-auto h-12 w-12 text-slate-300" />
              <h3 class="mt-4 text-xl font-black text-slate-900">No availability blocks yet</h3>
              <p class="mt-2 text-sm text-slate-500">Block a room or a whole room type for dates when it must not be booked.</p>
            </div>
          </section>
        </div>
      </section>
    </div>
  </ManagerLayout>
</template>