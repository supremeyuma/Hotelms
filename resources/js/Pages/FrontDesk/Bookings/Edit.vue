<script setup>
import FrontDeskLayout from '@/Layouts/Staff/FrontDeskLayout.vue'
import InputError from '@/Components/InputError.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import { ArrowLeft, CalendarDays, Save, ShieldCheck, UserRound } from 'lucide-vue-next'

const props = defineProps({
  booking: { type: Object, required: true },
  rooms: { type: Array, required: true },
  preCheckIn: { type: Object, default: null },
})

const form = useForm({
  room_id: props.booking.room_id ?? props.booking.assigned_room_options?.[0]?.id ?? '',
  guest_name: props.booking.guest_name ?? '',
  guest_email: props.booking.guest_email ?? '',
  guest_phone: props.booking.guest_phone ?? '',
  adults: String(props.booking.adults ?? 1),
  children: String(props.booking.children ?? 0),
  emergency_contact_name: props.booking.emergency_contact_name ?? '',
  emergency_contact_phone: props.booking.emergency_contact_phone ?? '',
  purpose_of_stay: props.booking.purpose_of_stay ?? '',
  special_requests: props.booking.special_requests ?? '',
  check_in: props.booking.check_in ?? '',
  check_out: props.booking.check_out ?? '',
  status: props.booking.status ?? 'confirmed',
})

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

function submit() {
  if (form.processing || invalidStay.value) return

  form.put(route('frontdesk.bookings.update', props.booking.id), {
    preserveScroll: true,
  })
}

function formatPreCheckInDate(value) {
  if (!value) return 'Not recorded'

  return new Date(value).toLocaleString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<template>
  <FrontDeskLayout>
    <Head :title="`Edit Reservation ${booking.booking_code}`" />

    <div class="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <Link
            :href="route('frontdesk.bookings.index')"
            class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 transition hover:text-slate-700"
          >
            <ArrowLeft class="h-4 w-4" />
            Back to reservations
          </Link>

          <div class="mt-4 flex flex-wrap items-center gap-3">
            <span class="rounded-full bg-slate-900 px-4 py-1.5 text-[11px] font-black uppercase tracking-[0.22em] text-white">
              {{ booking.booking_code }}
            </span>
            <span class="rounded-full bg-indigo-50 px-4 py-1.5 text-xs font-bold text-indigo-700">
              Front desk booking edit
            </span>
          </div>

          <h1 class="mt-4 text-3xl font-black tracking-tight text-slate-900">Edit reservation</h1>
          <p class="mt-2 text-sm text-slate-600">
            Update guest details, stay information, and reservation notes before arrival.
          </p>
        </div>
      </div>

      <form @submit.prevent="submit" class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_360px]">
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
                  <option value="pending_payment">Pending payment</option>
                  <option value="confirmed">Confirmed</option>
                  <option value="checked_in">Checked in</option>
                  <option value="checked_out">Checked out</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              <InputError :message="form.errors.status" />
            </label>

            <label class="space-y-2 md:col-span-2">
              <span class="text-sm font-bold text-slate-700">Guest full name</span>
              <input
                v-model="form.guest_name"
                type="text"
                class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
              />
              <InputError :message="form.errors.guest_name" />
            </label>

            <label class="space-y-2">
              <span class="text-sm font-bold text-slate-700">Guest email</span>
              <input
                v-model="form.guest_email"
                type="email"
                class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
              />
              <InputError :message="form.errors.guest_email" />
            </label>

            <label class="space-y-2">
              <span class="text-sm font-bold text-slate-700">Guest phone</span>
              <input
                v-model="form.guest_phone"
                type="text"
                class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
              />
              <InputError :message="form.errors.guest_phone" />
            </label>

            <label class="space-y-2">
              <span class="text-sm font-bold text-slate-700">Adults</span>
              <input
                v-model="form.adults"
                type="number"
                min="1"
                class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
              />
              <InputError :message="form.errors.adults" />
            </label>

            <label class="space-y-2">
              <span class="text-sm font-bold text-slate-700">Children</span>
              <input
                v-model="form.children"
                type="number"
                min="0"
                class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
              />
              <InputError :message="form.errors.children" />
            </label>

            <label class="space-y-2">
              <span class="text-sm font-bold text-slate-700">Emergency contact name</span>
              <input
                v-model="form.emergency_contact_name"
                type="text"
                class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
              />
              <InputError :message="form.errors.emergency_contact_name" />
            </label>

            <label class="space-y-2">
              <span class="text-sm font-bold text-slate-700">Emergency contact phone</span>
              <input
                v-model="form.emergency_contact_phone"
                type="text"
                class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
              />
              <InputError :message="form.errors.emergency_contact_phone" />
            </label>

            <label class="space-y-2">
              <span class="text-sm font-bold text-slate-700">Check-in date</span>
              <input
                v-model="form.check_in"
                type="date"
                class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
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
              />
              <InputError :message="form.errors.check_out" />
            </label>

            <label class="space-y-2 md:col-span-2">
              <span class="text-sm font-bold text-slate-700">Purpose of stay</span>
              <input
                v-model="form.purpose_of_stay"
                type="text"
                class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
              />
              <InputError :message="form.errors.purpose_of_stay" />
            </label>

            <label class="space-y-2 md:col-span-2">
              <span class="text-sm font-bold text-slate-700">Special requests</span>
              <textarea
                v-model="form.special_requests"
                rows="4"
                class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
              />
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

        <aside class="space-y-6">
          <section class="rounded-[2rem] bg-[linear-gradient(145deg,_#0f172a,_#1e293b)] p-6 text-white shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.24em] text-slate-300">Reservation summary</p>
            <h2 class="mt-3 text-2xl font-black tracking-tight">{{ form.guest_name || 'Guest details' }}</h2>

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
                  <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-300">Occupancy</p>
                  <p class="mt-1 text-sm font-bold text-white">
                    {{ form.adults || 1 }} adult<span v-if="Number(form.adults || 1) !== 1">s</span>,
                    {{ form.children || 0 }} child<span v-if="Number(form.children || 0) !== 1">ren</span>
                  </p>
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
              <p><span class="font-bold">Completed:</span> {{ formatPreCheckInDate(preCheckIn.completed_at) }}</p>
              <p><span class="font-bold">Estimated arrival:</span> {{ preCheckIn.estimated_arrival_time || 'Not provided' }}</p>
              <p><span class="font-bold">Arrival notes:</span> {{ preCheckIn.arrival_notes || 'None' }}</p>
            </div>
          </section>

          <section class="rounded-[1.5rem] border border-slate-200 bg-slate-50 px-5 py-5">
            <div class="flex items-start gap-3">
              <ShieldCheck class="mt-0.5 h-5 w-5 text-slate-500" />
              <div class="space-y-2 text-sm text-slate-600">
                <p class="font-bold text-slate-900">Before saving</p>
                <p>Guest details match the online booking flow so reception can complete missing information for walk-ins.</p>
                <p>The save button locks immediately to prevent double submission.</p>
              </div>
            </div>
          </section>

          <div class="flex flex-col gap-3">
            <button
              type="submit"
              :disabled="form.processing || invalidStay"
              class="inline-flex w-full items-center justify-center gap-3 rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <Save class="h-4 w-4" />
              {{ form.processing ? 'Saving changes...' : 'Save reservation changes' }}
            </button>

            <Link
              :href="route('frontdesk.bookings.index')"
              class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
            >
              Cancel
            </Link>
          </div>
        </aside>
      </form>
    </div>
  </FrontDeskLayout>
</template>
