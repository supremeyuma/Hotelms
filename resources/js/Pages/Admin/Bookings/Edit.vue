<script setup>
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import InputError from '@/Components/InputError.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import {
  ArrowLeft,
  BedDouble,
  CalendarDays,
  CreditCard,
  Mail,
  Phone,
  Save,
  ShieldCheck,
  Users,
} from 'lucide-vue-next'

const props = defineProps({
  booking: { type: Object, required: true },
  rooms: { type: Array, required: true },
  statusOptions: { type: Array, default: () => [] },
})

const form = useForm({
  room_id: props.booking.room_id,
  check_in: props.booking.check_in ?? '',
  check_out: props.booking.check_out ?? '',
  status: props.booking.status,
  guests: String(props.booking.guests ?? 1),
})

const roomOptions = computed(() => props.rooms.map((room) => ({
  label: [
    room.room_type?.title,
    room.name || room.room_number,
    room.status ? `(${String(room.status).replaceAll('_', ' ')})` : null,
  ].filter(Boolean).join(' - '),
  value: room.id,
})))

const selectedRoom = computed(() =>
  props.rooms.find((room) => Number(room.id) === Number(form.room_id)) ?? null
)

const nights = computed(() => {
  if (!form.check_in || !form.check_out) {
    return 0
  }

  const checkIn = new Date(form.check_in)
  const checkOut = new Date(form.check_out)
  const diff = Math.round((checkOut - checkIn) / 86400000)

  return Number.isFinite(diff) && diff > 0 ? diff : 0
})

const invalidStay = computed(() => !!form.check_in && !!form.check_out && nights.value === 0)
const statusLabel = computed(() =>
  props.statusOptions.find((option) => option.value === form.status)?.label ?? form.status
)

function formatCurrency(amount) {
  return new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    maximumFractionDigits: 0,
  }).format(Number(amount || 0))
}

function formatStatus(value) {
  return String(value || '')
    .replaceAll('_', ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase())
}

function submit() {
  if (invalidStay.value || form.processing) {
    return
  }

  form.put(route('admin.bookings.update', props.booking.id), {
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
              {{ formatStatus(booking.payment_status) }}
            </span>
          </div>

          <h1 class="mt-4 text-3xl font-black tracking-tight text-slate-900">Edit booking</h1>
          <p class="mt-2 text-sm text-slate-600">
            Update room assignment, stay dates, guest count, and reservation status.
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

      <form @submit.prevent="submit" class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="space-y-6 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
          <div class="grid gap-6 md:grid-cols-2">
            <label class="space-y-2 md:col-span-2">
              <span class="text-sm font-bold text-slate-700">Assigned room</span>
              <select
                v-model="form.room_id"
                class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
                required
              >
                <option v-for="room in roomOptions" :key="room.value" :value="room.value">
                  {{ room.label }}
                </option>
              </select>
              <InputError :message="form.errors.room_id" />
            </label>

            <label class="space-y-2">
              <span class="text-sm font-bold text-slate-700">Check-in date</span>
              <input
                v-model="form.check_in"
                type="date"
                class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
                required
              />
              <InputError :message="form.errors.check_in" />
            </label>

            <label class="space-y-2">
              <span class="text-sm font-bold text-slate-700">Check-out date</span>
              <input
                v-model="form.check_out"
                type="date"
                :min="form.check_in || undefined"
                class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
                required
              />
              <InputError :message="form.errors.check_out" />
            </label>

            <label class="space-y-2">
              <span class="text-sm font-bold text-slate-700">Guest count</span>
              <input
                v-model="form.guests"
                type="number"
                min="1"
                inputmode="numeric"
                class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
              />
              <InputError :message="form.errors.guests" />
            </label>

            <label class="space-y-2">
              <span class="text-sm font-bold text-slate-700">Booking status</span>
              <select
                v-model="form.status"
                class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
                required
              >
                <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
              <InputError :message="form.errors.status" />
            </label>
          </div>

          <div
            v-if="invalidStay"
            class="rounded-[1.5rem] border border-rose-200 bg-rose-50 px-4 py-4 text-sm font-medium text-rose-700"
          >
            Check-out must be after check-in.
          </div>

          <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 px-5 py-5">
            <div class="flex items-start gap-3">
              <ShieldCheck class="mt-0.5 h-5 w-5 text-slate-500" />
              <div class="space-y-2 text-sm text-slate-600">
                <p class="font-bold text-slate-900">Before saving</p>
                <p>Room availability is checked again when dates or room assignment change.</p>
                <p>The save button locks immediately to prevent double submission.</p>
              </div>
            </div>
          </div>
        </section>

        <aside class="space-y-6">
          <section class="rounded-[2rem] bg-[linear-gradient(145deg,_#0f172a,_#1e293b)] p-6 text-white shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.24em] text-slate-300">Live summary</p>
            <h2 class="mt-3 text-2xl font-black tracking-tight">{{ booking.guest_name }}</h2>
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
                <BedDouble class="mt-0.5 h-4 w-4 text-slate-200" />
                <div>
                  <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-300">Room</p>
                  <p class="mt-1 text-sm font-bold text-white">
                    {{ selectedRoom?.room_type?.title || 'Room type' }} - {{ selectedRoom?.name || selectedRoom?.room_number || 'Select room' }}
                  </p>
                  <p class="mt-1 text-xs text-slate-300">{{ selectedRoom ? formatStatus(selectedRoom.status) : 'No room selected' }}</p>
                </div>
              </div>

              <div class="flex items-start gap-3 rounded-[1.5rem] bg-white/10 px-4 py-4">
                <Users class="mt-0.5 h-4 w-4 text-slate-200" />
                <div>
                  <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-300">Guest count</p>
                  <p class="mt-1 text-sm font-bold text-white">{{ form.guests || '1' }} guest<span v-if="Number(form.guests || 1) !== 1">s</span></p>
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

          <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Guest contact</p>
            <div class="mt-4 space-y-3 text-sm text-slate-600">
              <div class="flex items-center gap-3">
                <Mail class="h-4 w-4 text-slate-400" />
                <span>{{ booking.guest_email || 'No email provided' }}</span>
              </div>
              <div class="flex items-center gap-3">
                <Phone class="h-4 w-4 text-slate-400" />
                <span>{{ booking.guest_phone || 'No phone provided' }}</span>
              </div>
            </div>

            <div v-if="booking.assigned_rooms?.length" class="mt-6">
              <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">All assigned rooms</p>
              <div class="mt-3 flex flex-wrap gap-2">
                <span
                  v-for="room in booking.assigned_rooms"
                  :key="room"
                  class="rounded-full bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700"
                >
                  {{ room }}
                </span>
              </div>
            </div>

            <div v-if="booking.special_requests" class="mt-6 rounded-[1.5rem] border border-amber-200 bg-amber-50 px-4 py-4">
              <p class="text-[11px] font-black uppercase tracking-[0.18em] text-amber-700">Special requests</p>
              <p class="mt-2 text-sm leading-6 text-amber-900">{{ booking.special_requests }}</p>
            </div>
          </section>

          <div class="flex flex-col gap-3">
            <button
              type="submit"
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
      </form>
    </div>
  </ManagerLayout>
</template>
