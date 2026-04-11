<script setup>
import FrontDeskLayout from '@/Layouts/Staff/FrontDeskLayout.vue'
import InputError from '@/Components/InputError.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import { ArrowLeft, CalendarDays, Save, ShieldCheck, UserRound } from 'lucide-vue-next'

const props = defineProps({
  rooms: {
    type: Array,
    default: () => [],
  },
  roomDiscountCodeHint: {
    type: String,
    default: '',
  },
})

const form = useForm({
  room_id: '',
  guest_name: '',
  guest_email: '',
  guest_phone: '',
  adults: '1',
  children: '0',
  emergency_contact_name: '',
  emergency_contact_phone: '',
  purpose_of_stay: '',
  special_requests: '',
  check_in: '',
  check_out: '',
  discount_code: '',
})

const selectedRoom = computed(() =>
  props.rooms.find((room) => Number(room.id) === Number(form.room_id)) ?? null
)

const nights = computed(() => {
  if (!form.check_in || !form.check_out) return 0

  const diff = Math.round((new Date(form.check_out) - new Date(form.check_in)) / 86400000)

  return Number.isFinite(diff) && diff > 0 ? diff : 0
})

const estimatedTotal = computed(() => {
  if (!selectedRoom.value || !nights.value) return 0
  return Number(selectedRoom.value.room_type?.base_price || 0) * nights.value
})

function submit() {
  if (form.processing) return

  form.post(route('frontdesk.bookings.store'), {
    preserveScroll: true,
  })
}
</script>

<template>
  <FrontDeskLayout>
    <Head title="Create Reservation" />

    <div class="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
      <div>
        <Link
          :href="route('frontdesk.bookings.index')"
          class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 transition hover:text-slate-700"
        >
          <ArrowLeft class="h-4 w-4" />
          Back to reservations
        </Link>

        <h1 class="mt-4 text-3xl font-black tracking-tight text-slate-900">New reservation</h1>
        <p class="mt-2 text-sm text-slate-600">
          Create a front desk booking with the same guest details captured during the online booking flow.
        </p>
      </div>

      <form @submit.prevent="submit" class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="space-y-6 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
          <div class="grid gap-6 md:grid-cols-2">
            <label class="space-y-2 md:col-span-2">
              <span class="text-sm font-bold text-slate-700">Assign room</span>
              <select
                v-model="form.room_id"
                class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
              >
                <option value="" disabled>Select room</option>
                <option v-for="room in rooms" :key="room.id" :value="room.id">
                  {{ room.room_type?.title }} - {{ room.name || room.room_number }}
                </option>
              </select>
              <InputError :message="form.errors.room_id" />
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

            <label class="space-y-2 md:col-span-2">
              <span class="text-sm font-bold text-slate-700">Discount code</span>
              <input v-model="form.discount_code" type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm uppercase text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" placeholder="Optional" />
              <p class="text-xs font-medium text-slate-500">
                Use a manager-issued {{ roomDiscountCodeHint.toLowerCase() }} code if this reservation qualifies.
              </p>
              <InputError :message="form.errors.discount_code" />
            </label>
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
                  <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-300">Room</p>
                  <p class="mt-1 text-sm font-bold text-white">
                    {{ selectedRoom ? `${selectedRoom.room_type?.title} - ${selectedRoom.name || selectedRoom.room_number}` : 'Select room' }}
                  </p>
                  <p class="mt-1 text-xs text-slate-300">
                    Estimated room value: ₦{{ estimatedTotal.toLocaleString() }}
                  </p>
                </div>
              </div>

              <div class="flex items-start gap-3 rounded-[1.5rem] bg-white/10 px-4 py-4">
                <ShieldCheck class="mt-0.5 h-4 w-4 text-slate-200" />
                <div>
                  <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-300">Discount</p>
                  <p class="mt-1 text-sm font-bold text-white">{{ form.discount_code || 'No code applied' }}</p>
                  <p class="mt-1 text-xs text-slate-300">
                    Room discounts are validated when the reservation is saved.
                  </p>
                </div>
              </div>
            </div>
          </section>

          <section class="rounded-[1.5rem] border border-slate-200 bg-slate-50 px-5 py-5">
            <div class="flex items-start gap-3">
              <ShieldCheck class="mt-0.5 h-5 w-5 text-slate-500" />
              <div class="space-y-2 text-sm text-slate-600">
                <p class="font-bold text-slate-900">Front desk workflow</p>
                <p>This creates a confirmed booking with the assigned room already reserved for the guest.</p>
                <p>Payments and extra charges can then be posted against the booking and room from the folio screen.</p>
              </div>
            </div>
          </section>

          <div class="flex flex-col gap-3">
            <button
              type="submit"
              :disabled="form.processing"
              class="inline-flex w-full items-center justify-center gap-3 rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <Save class="h-4 w-4" />
              {{ form.processing ? 'Creating reservation...' : 'Create reservation' }}
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
