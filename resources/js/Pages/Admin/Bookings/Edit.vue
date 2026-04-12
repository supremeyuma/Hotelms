<script setup>
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import InputError from '@/Components/InputError.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import {
  ArrowLeft,
  CalendarDays,
  CreditCard,
  Mail,
  Phone,
  Save,
  ShieldCheck,
  UserRound,
} from 'lucide-vue-next'

const props = defineProps({
  booking: { type: Object, required: true },
  rooms: { type: Array, required: true },
  statusOptions: { type: Array, default: () => [] },
  preCheckIn: { type: Object, default: null },
})

const form = useForm({
  room_id: props.booking.room_id ?? props.booking.assigned_room_options?.[0]?.id ?? '',
  check_in: props.booking.check_in ?? '',
  check_out: props.booking.check_out ?? '',
  status: props.booking.status,
  guests: String(props.booking.guests ?? 1),
  adults: String(props.booking.adults ?? 1),
  children: String(props.booking.children ?? 0),
  guest_name: props.booking.guest_name ?? '',
  guest_email: props.booking.guest_email ?? '',
  guest_phone: props.booking.guest_phone ?? '',
  emergency_contact_name: props.booking.emergency_contact_name ?? '',
  emergency_contact_phone: props.booking.emergency_contact_phone ?? '',
  purpose_of_stay: props.booking.purpose_of_stay ?? '',
  special_requests: props.booking.special_requests ?? '',
})

const chargeForm = useForm({
  room_id: props.booking.has_multiple_rooms ? '' : props.booking.assigned_room_options?.[0]?.id ?? props.booking.room_id ?? '',
  description: '',
  amount: '',
})

const paymentForm = useForm({
  room_id: props.booking.has_multiple_rooms ? '' : props.booking.assigned_room_options?.[0]?.id ?? props.booking.room_id ?? '',
  amount: '',
  method: 'Cash',
  reference: '',
  notes: '',
})

const approveOverrideForm = useForm({})
const rejectOverrideForm = useForm({})
const checkInForm = useForm({})
const checkOutForm = useForm({})

const roomOptions = computed(() => {
  const current = (props.booking.assigned_room_options ?? []).map((room) => ({
    id: room.id,
    label: `${room.label} (currently assigned)`,
  }))

  const available = props.rooms.map((room) => ({
    id: room.id,
    label: [
      room.room_type?.title,
      room.name || room.room_number,
      room.status ? `(${String(room.status).replaceAll('_', ' ')})` : null,
    ].filter(Boolean).join(' - '),
  }))

  return [...current, ...available].filter(
    (room, index, items) => items.findIndex((item) => Number(item.id) === Number(room.id)) === index
  )
})

const nights = computed(() => {
  if (!form.check_in || !form.check_out) return 0

  const diff = Math.round((new Date(form.check_out) - new Date(form.check_in)) / 86400000)

  return Number.isFinite(diff) && diff > 0 ? diff : 0
})

const invalidStay = computed(() => Boolean(form.check_in && form.check_out && !nights.value))
const statusLabel = computed(() => props.statusOptions.find((option) => option.value === form.status)?.label ?? form.status)

function formatCurrency(amount) {
  return new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    maximumFractionDigits: 0,
  }).format(Number(amount || 0))
}

function formatDate(value) {
  if (!value) return 'Not recorded'

  return new Date(value).toLocaleString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function roomLabel(roomId) {
  return props.booking.assigned_room_options.find((room) => Number(room.id) === Number(roomId))?.label || 'Room not specified'
}

function submit() {
  if (form.processing || invalidStay.value) return

  form.put(route('admin.bookings.update', props.booking.id), {
    preserveScroll: true,
  })
}

function submitCharge() {
  chargeForm.post(route('admin.bookings.charges.store', props.booking.id), {
    preserveScroll: true,
    onSuccess: () => {
      chargeForm.reset('description', 'amount')
      chargeForm.room_id = props.booking.has_multiple_rooms ? '' : props.booking.assigned_room_options?.[0]?.id ?? props.booking.room_id ?? ''
    },
  })
}

function submitPayment() {
  paymentForm.post(route('admin.bookings.payments.store', props.booking.id), {
    preserveScroll: true,
    onSuccess: () => {
      paymentForm.reset('amount', 'reference', 'notes')
      paymentForm.method = 'Cash'
      paymentForm.room_id = props.booking.has_multiple_rooms ? '' : props.booking.assigned_room_options?.[0]?.id ?? props.booking.room_id ?? ''
    },
  })
}

function approvePriceOverride() {
  approveOverrideForm.post(route('admin.bookings.price-override.approve', props.booking.id), {
    preserveScroll: true,
  })
}

function rejectPriceOverride() {
  rejectOverrideForm.post(route('admin.bookings.price-override.reject', props.booking.id), {
    preserveScroll: true,
  })
}

function handleCheckIn() {
  if (checkInForm.processing) return
  
  if (!['confirmed', 'checked_in'].includes(props.booking.status)) {
    alert('Booking must be confirmed before check-in')
    return
  }

  checkInForm.post(route('admin.bookings.check-in', props.booking.id), {
    preserveScroll: true,
  })
}

function handleCheckOut() {
  if (checkOutForm.processing) return
  
  if (!['checked_in', 'checked_out'].includes(props.booking.status)) {
    alert('Booking must be checked in before check-out')
    return
  }

  if (!confirm('Mark all rooms as checked out and ready for cleaning?')) {
    return
  }

  checkOutForm.post(route('admin.bookings.check-out', props.booking.id), {
    preserveScroll: true,
  })
}
</script>

<template>
  <ManagerLayout>
    <Head :title="`Edit Booking ${booking.booking_code || `#${booking.id}`}`" />

    <div class="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <Link
            :href="route('admin.bookings.index')"
            class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 transition hover:text-slate-700"
          >
            <ArrowLeft class="h-4 w-4" />
            Back to bookings
          </Link>

          <div class="mt-4 flex flex-wrap items-center gap-3">
            <span class="rounded-full bg-slate-900 px-4 py-1.5 text-[11px] font-black uppercase tracking-[0.22em] text-white">
              {{ booking.booking_code || `Booking #${booking.id}` }}
            </span>
            <span class="rounded-full bg-slate-100 px-4 py-1.5 text-xs font-bold text-slate-600">
              {{ booking.payment_status }}
            </span>
          </div>

          <h1 class="mt-4 text-3xl font-black tracking-tight text-slate-900">Edit booking</h1>
          <p class="mt-2 text-sm text-slate-600">
            Manage guest details, stay dates, room assignment, and room-specific billing from one screen.
          </p>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
          <div class="rounded-[1.5rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Guest</p>
            <p class="mt-2 text-sm font-black text-slate-900">{{ booking.guest_name }}</p>
          </div>
          <div class="rounded-[1.5rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Current room</p>
            <p class="mt-2 text-sm font-black text-slate-900">{{ booking.room_label }}</p>
          </div>
          <div class="rounded-[1.5rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Booking value</p>
            <p class="mt-2 text-sm font-black text-slate-900">{{ formatCurrency(booking.total_amount) }}</p>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div v-if="['confirmed', 'checked_in'].includes(booking.status)" class="rounded-[2rem] border border-emerald-200 bg-gradient-to-br from-emerald-50 to-emerald-50/50 p-6 shadow-sm">
        <p class="text-sm font-bold text-emerald-700">Guest check-in</p>
        <p class="mt-1 text-sm text-emerald-600">Mark rooms as occupied and record check-in timestamps for the reporting system.</p>
        <div class="mt-4 flex gap-3">
          <button
            type="button"
            @click="handleCheckIn"
            :disabled="checkInForm.processing"
            class="inline-flex items-center justify-center rounded-2xl bg-emerald-600 px-6 py-3 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
          >
            {{ checkInForm.processing ? 'Checking in...' : 'Check in guest now' }}
          </button>
        </div>
      </div>

      <div v-if="['checked_in', 'checked_out'].includes(booking.status)" class="rounded-[2rem] border border-rose-200 bg-gradient-to-br from-rose-50 to-rose-50/50 p-6 shadow-sm">
        <p class="text-sm font-bold text-rose-700">Guest check-out</p>
        <p class="mt-1 text-sm text-rose-600">Mark rooms as checked out and ready for cleaning. Finalizes all room charges.</p>
        <div class="mt-4 flex gap-3">
          <button
            type="button"
            @click="handleCheckOut"
            :disabled="checkOutForm.processing"
            class="inline-flex items-center justify-center rounded-2xl bg-rose-600 px-6 py-3 text-sm font-bold text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-60"
          >
            {{ checkOutForm.processing ? 'Checking out...' : 'Check out guest now' }}
          </button>
        </div>
      </div>

      <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="space-y-8">
          <section
            v-if="booking.has_price_override"
            class="rounded-[2rem] border p-6 shadow-sm"
            :class="booking.has_pending_price_override_approval
              ? 'border-rose-200 bg-rose-50'
              : (booking.price_override?.approval_status === 'rejected' ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50')"
          >
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
              <div>
                <p
                  class="text-[11px] font-black uppercase tracking-[0.18em]"
                  :class="booking.has_pending_price_override_approval
                    ? 'text-rose-700'
                    : (booking.price_override?.approval_status === 'rejected' ? 'text-amber-700' : 'text-emerald-700')"
                >
                  Price override
                </p>
                <h2 class="mt-2 text-2xl font-black text-slate-900">
                  {{
                    booking.has_pending_price_override_approval
                      ? 'Manager review required'
                      : (booking.price_override?.approval_status === 'rejected' ? 'Override rejected' : 'Override recorded')
                  }}
                </h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                  Original amount {{ formatCurrency(booking.price_override?.original_amount) }}.
                  Override amount {{ formatCurrency(booking.price_override?.override_amount) }}.
                </p>
                <p class="mt-2 text-sm text-slate-600">
                  Note: {{ booking.price_override?.note || booking.price_override?.reason || 'No note recorded' }}
                </p>
                <p class="mt-2 text-sm text-slate-500">
                  Requested by {{ booking.price_override?.requested_by_name || 'Front desk' }} on {{ formatDate(booking.price_override?.requested_at) }}.
                </p>
                <p v-if="booking.price_override?.approval_status === 'rejected'" class="mt-2 text-sm font-bold text-amber-700">
                  The booking total has been reset to the original calculated amount.
                </p>
              </div>

              <div v-if="booking.has_pending_price_override_approval" class="flex flex-wrap gap-3">
                <button
                  type="button"
                  @click="approvePriceOverride"
                  :disabled="approveOverrideForm.processing || rejectOverrideForm.processing"
                  class="inline-flex items-center justify-center rounded-2xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  {{ approveOverrideForm.processing ? 'Approving...' : 'Approve override' }}
                </button>
                <button
                  type="button"
                  @click="rejectPriceOverride"
                  :disabled="approveOverrideForm.processing || rejectOverrideForm.processing"
                  class="inline-flex items-center justify-center rounded-2xl border border-rose-300 px-5 py-3 text-sm font-bold text-rose-700 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  {{ rejectOverrideForm.processing ? 'Rejecting...' : 'Reject override' }}
                </button>
              </div>
            </div>
          </section>

          <section class="space-y-6 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="grid gap-6 md:grid-cols-2">
              <label class="space-y-2">
                <span class="text-sm font-bold text-slate-700">Assigned room</span>
                <select
                  v-model="form.room_id"
                  class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
                >
                  <option v-for="room in roomOptions" :key="room.id" :value="room.id">
                    {{ room.label }}
                  </option>
                </select>
                <InputError :message="form.errors.room_id" />
              </label>

              <label class="space-y-2">
                <span class="text-sm font-bold text-slate-700">Booking status</span>
                <select
                  v-model="form.status"
                  class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
                >
                  <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </option>
                </select>
                <InputError :message="form.errors.status" />
              </label>

              <label class="space-y-2 md:col-span-2">
                <span class="text-sm font-bold text-slate-700">Guest full name</span>
                <input v-model="form.guest_name" type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" />
                <InputError :message="form.errors.guest_name" />
              </label>

              <label class="space-y-2">
                <span class="text-sm font-bold text-slate-700">Guest email</span>
                <input v-model="form.guest_email" type="email" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" />
                <InputError :message="form.errors.guest_email" />
              </label>

              <label class="space-y-2">
                <span class="text-sm font-bold text-slate-700">Guest phone</span>
                <input v-model="form.guest_phone" type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" />
                <InputError :message="form.errors.guest_phone" />
              </label>

              <label class="space-y-2">
                <span class="text-sm font-bold text-slate-700">Adults</span>
                <input v-model="form.adults" type="number" min="1" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" />
                <InputError :message="form.errors.adults" />
              </label>

              <label class="space-y-2">
                <span class="text-sm font-bold text-slate-700">Children</span>
                <input v-model="form.children" type="number" min="0" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" />
                <InputError :message="form.errors.children" />
              </label>

              <label class="space-y-2">
                <span class="text-sm font-bold text-slate-700">Emergency contact name</span>
                <input v-model="form.emergency_contact_name" type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" />
                <InputError :message="form.errors.emergency_contact_name" />
              </label>

              <label class="space-y-2">
                <span class="text-sm font-bold text-slate-700">Emergency contact phone</span>
                <input v-model="form.emergency_contact_phone" type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" />
                <InputError :message="form.errors.emergency_contact_phone" />
              </label>

              <label class="space-y-2">
                <span class="text-sm font-bold text-slate-700">Check-in date</span>
                <input v-model="form.check_in" type="date" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" />
                <InputError :message="form.errors.check_in" />
              </label>

              <label class="space-y-2">
                <span class="text-sm font-bold text-slate-700">Check-out date</span>
                <input v-model="form.check_out" type="date" :min="form.check_in || undefined" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" />
                <InputError :message="form.errors.check_out" />
              </label>

              <label class="space-y-2 md:col-span-2">
                <span class="text-sm font-bold text-slate-700">Purpose of stay</span>
                <input v-model="form.purpose_of_stay" type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" />
                <InputError :message="form.errors.purpose_of_stay" />
              </label>

              <label class="space-y-2 md:col-span-2">
                <span class="text-sm font-bold text-slate-700">Special requests</span>
                <textarea v-model="form.special_requests" rows="4" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" />
                <InputError :message="form.errors.special_requests" />
              </label>
            </div>

            <div
              v-if="invalidStay"
              class="rounded-[1.5rem] border border-rose-200 bg-rose-50 px-4 py-4 text-sm font-medium text-rose-700"
            >
              Check-out must be after check-in.
            </div>
          </section>

          <section class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
              <div class="flex items-center gap-3">
                <div class="rounded-2xl bg-indigo-50 p-2 text-indigo-600"><CreditCard class="h-5 w-5" /></div>
                <h2 class="text-xl font-black text-slate-900">Add charge</h2>
              </div>

              <form @submit.prevent="submitCharge" class="mt-6 space-y-4">
                <label class="space-y-2">
                  <span class="text-sm font-bold text-slate-700">Room</span>
                  <select v-model="chargeForm.room_id" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                    <option value="" disabled>Select room</option>
                    <option v-for="room in booking.assigned_room_options" :key="room.id" :value="room.id">
                      {{ room.label }}
                    </option>
                  </select>
                  <InputError :message="chargeForm.errors.room_id" />
                </label>

                <label class="space-y-2">
                  <span class="text-sm font-bold text-slate-700">Description</span>
                  <input v-model="chargeForm.description" type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" />
                  <InputError :message="chargeForm.errors.description" />
                </label>

                <label class="space-y-2">
                  <span class="text-sm font-bold text-slate-700">Amount</span>
                  <input v-model="chargeForm.amount" type="number" min="0.01" step="0.01" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" />
                  <InputError :message="chargeForm.errors.amount" />
                </label>

                <button type="submit" :disabled="chargeForm.processing" class="inline-flex w-full items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800 disabled:opacity-60">
                  {{ chargeForm.processing ? 'Posting charge...' : 'Post charge' }}
                </button>
              </form>
            </div>

            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
              <div class="flex items-center gap-3">
                <div class="rounded-2xl bg-emerald-50 p-2 text-emerald-600"><CreditCard class="h-5 w-5" /></div>
                <h2 class="text-xl font-black text-slate-900">Record payment</h2>
              </div>

              <form @submit.prevent="submitPayment" class="mt-6 space-y-4">
                <label class="space-y-2">
                  <span class="text-sm font-bold text-slate-700">Room</span>
                  <select v-model="paymentForm.room_id" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                    <option value="" disabled>Select room</option>
                    <option v-for="room in booking.assigned_room_options" :key="room.id" :value="room.id">
                      {{ room.label }}
                    </option>
                  </select>
                  <InputError :message="paymentForm.errors.room_id" />
                </label>

                <label class="space-y-2">
                  <span class="text-sm font-bold text-slate-700">Amount</span>
                  <input v-model="paymentForm.amount" type="number" min="0.01" step="0.01" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" />
                  <InputError :message="paymentForm.errors.amount" />
                </label>

                <label class="space-y-2">
                  <span class="text-sm font-bold text-slate-700">Method</span>
                  <select v-model="paymentForm.method" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                    <option>Cash</option>
                    <option>Card</option>
                    <option>Online Transfer</option>
                    <option>POS</option>
                  </select>
                  <InputError :message="paymentForm.errors.method" />
                </label>

                <label class="space-y-2">
                  <span class="text-sm font-bold text-slate-700">Reference</span>
                  <input v-model="paymentForm.reference" type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" />
                  <InputError :message="paymentForm.errors.reference" />
                </label>

                <label class="space-y-2">
                  <span class="text-sm font-bold text-slate-700">Notes</span>
                  <input v-model="paymentForm.notes" type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" />
                  <InputError :message="paymentForm.errors.notes" />
                </label>

                <button type="submit" :disabled="paymentForm.processing" class="inline-flex w-full items-center justify-center rounded-2xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-emerald-500 disabled:opacity-60">
                  {{ paymentForm.processing ? 'Recording payment...' : 'Record payment' }}
                </button>
              </form>
            </div>
          </section>
        </div>

        <aside class="space-y-6">
          <section class="rounded-[2rem] bg-[linear-gradient(145deg,_#0f172a,_#1e293b)] p-6 text-white shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.24em] text-slate-300">Live summary</p>
            <h2 class="mt-3 text-2xl font-black tracking-tight">{{ form.guest_name }}</h2>
            <p class="mt-2 text-sm text-slate-300">{{ statusLabel }}</p>

            <div class="mt-6 space-y-4">
              <div class="flex items-start gap-3 rounded-[1.5rem] bg-white/10 px-4 py-4">
                <CalendarDays class="mt-0.5 h-4 w-4 text-slate-200" />
                <div>
                  <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-300">Stay</p>
                  <p class="mt-1 text-sm font-bold text-white">{{ form.check_in || 'Select date' }} to {{ form.check_out || 'Select date' }}</p>
                  <p class="mt-1 text-xs text-slate-300">
                    {{ nights ? `${nights} night${nights === 1 ? '' : 's'}` : 'Waiting for valid dates' }}
                  </p>
                </div>
              </div>

              <div class="flex items-start gap-3 rounded-[1.5rem] bg-white/10 px-4 py-4">
                <UserRound class="mt-0.5 h-4 w-4 text-slate-200" />
                <div>
                  <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-300">Guest contact</p>
                  <p class="mt-1 text-sm font-bold text-white">{{ form.guest_email || 'No email provided' }}</p>
                  <p class="mt-1 text-xs text-slate-300">{{ form.guest_phone || 'No phone provided' }}</p>
                </div>
              </div>

              <div class="flex items-start gap-3 rounded-[1.5rem] bg-white/10 px-4 py-4">
                <CreditCard class="mt-0.5 h-4 w-4 text-slate-200" />
                <div>
                  <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-300">Payment</p>
                  <p class="mt-1 text-sm font-bold text-white">{{ formatCurrency(booking.total_amount) }}</p>
                  <p class="mt-1 text-xs text-slate-300">{{ booking.payment_method }}</p>
                </div>
              </div>
            </div>
          </section>

          <section
            v-if="preCheckIn"
            class="rounded-[2rem] border border-emerald-200 bg-emerald-50 p-6 shadow-sm"
          >
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">Online pre-check-in</p>
            <div class="mt-4 space-y-2 text-sm text-emerald-900">
              <p><span class="font-bold">Completed:</span> {{ formatDate(preCheckIn.completed_at) }}</p>
              <p><span class="font-bold">Estimated arrival:</span> {{ preCheckIn.estimated_arrival_time || 'Not provided' }}</p>
              <p><span class="font-bold">Arrival notes:</span> {{ preCheckIn.arrival_notes || 'None' }}</p>
            </div>
          </section>

          <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Recent charges</p>
            <div class="mt-4 space-y-3">
              <div v-for="charge in booking.charges" :key="charge.id" class="rounded-[1.25rem] border border-slate-100 px-4 py-3">
                <p class="text-sm font-bold text-slate-900">{{ charge.description }}</p>
                <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-400">{{ roomLabel(charge.room_id) }}</p>
                <p class="mt-2 text-sm font-black text-slate-900">{{ formatCurrency(charge.amount) }}</p>
              </div>
              <p v-if="booking.charges.length === 0" class="text-sm text-slate-500">No charges recorded yet.</p>
            </div>
          </section>

          <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Recent payments</p>
            <div class="mt-4 space-y-3">
              <div v-for="payment in booking.payments" :key="payment.id" class="rounded-[1.25rem] border border-slate-100 px-4 py-3">
                <p class="text-sm font-bold text-slate-900">{{ payment.method }}</p>
                <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-400">{{ roomLabel(payment.room_id) }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ payment.notes || 'Payment recorded' }}</p>
                <p class="mt-2 text-sm font-black text-emerald-600">{{ formatCurrency(payment.amount) }}</p>
              </div>
              <p v-if="booking.payments.length === 0" class="text-sm text-slate-500">No payments recorded yet.</p>
            </div>
          </section>

          <section class="rounded-[1.5rem] border border-slate-200 bg-slate-50 px-5 py-5">
            <div class="flex items-start gap-3">
              <ShieldCheck class="mt-0.5 h-5 w-5 text-slate-500" />
              <div class="space-y-2 text-sm text-slate-600">
                <p class="font-bold text-slate-900">Before saving</p>
                <p>Room availability is checked again when dates or room assignment change.</p>
                <p>Charges and payments are tied to the selected room to keep shared bookings accurate.</p>
              </div>
            </div>
          </section>

          <div class="flex flex-col gap-3">
            <button
              type="button"
              @click="submit"
              :disabled="form.processing || invalidStay"
              class="inline-flex w-full items-center justify-center gap-3 rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <Save class="h-4 w-4" />
              {{ form.processing ? 'Saving changes...' : 'Save booking changes' }}
            </button>

            <Link
              :href="route('admin.bookings.index')"
              class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
            >
              Cancel
            </Link>
          </div>
        </aside>
      </div>
    </div>
  </ManagerLayout>
</template>
