<script setup>
import { computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import FrontDeskLayout from '@/Layouts/Staff/FrontDeskLayout.vue'
import StatusBadge from '@/Components/FrontDesk/StatusBadge.vue'
import {
  AlertCircle,
  BedDouble,
  Calendar,
  CalendarPlus,
  CheckCircle2,
  ChevronLeft,
  CreditCard,
  LogOut,
  Receipt,
  User,
} from 'lucide-vue-next'

const props = defineProps({
  booking: { type: Object, required: true },
  preCheckIn: { type: Object, default: null },
})

const totalCharges = computed(() =>
  Math.max(
    props.booking.charges.reduce((sum, charge) => sum + Number(charge.amount), 0),
    Number(props.booking.total_amount || 0)
  )
)

const totalPayments = computed(() =>
  props.booking.payments.reduce((sum, payment) => sum + Number(payment.amount), 0)
)

const balanceDue = computed(() => Math.max(totalCharges.value - totalPayments.value, 0))
const isInHouse = computed(() => ['active', 'checked_in'].includes(props.booking.status))

function checkIn() {
  router.post(route('frontdesk.bookings.checkIn', props.booking.id))
}

function checkOut() {
  if (balanceDue.value > 0) {
    alert('Outstanding balance must be cleared before checkout.')
    return
  }

  router.post(route('frontdesk.bookings.checkOut', props.booking.id))
}

function extendStay() {
  const newDate = prompt('Enter new checkout date (YYYY-MM-DD)')

  if (!newDate) return

  router.post(route('frontdesk.bookings.extendStay', props.booking.id), {
    new_checkout: newDate,
  })
}

function formatDate(dateString) {
  if (!dateString) return 'Not recorded'

  return new Date(dateString).toLocaleString('en-GB', {
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
</script>

<template>
  <FrontDeskLayout>
    <Head :title="`Booking ${booking.booking_code}`" />

    <div class="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
      <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-center gap-4">
          <Link
            :href="route('frontdesk.bookings.index')"
            class="rounded-2xl p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-900"
          >
            <ChevronLeft class="h-6 w-6" />
          </Link>

          <div>
            <div class="flex flex-wrap items-center gap-3">
              <h1 class="text-3xl font-black tracking-tight text-slate-900">{{ booking.booking_code }}</h1>
              <StatusBadge :status="booking.status" />
            </div>
            <p class="mt-2 text-sm font-medium text-slate-500">
              Created on {{ formatDate(booking.created_at) }}
            </p>
          </div>
        </div>

        <div class="flex flex-wrap gap-3">
          <button
            v-if="booking.status === 'confirmed' && !booking.has_pending_price_override_approval"
            type="button"
            @click="checkIn"
            class="inline-flex items-center gap-2 rounded-2xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-emerald-700"
          >
            <CheckCircle2 class="h-4 w-4" />
            Check in guest
          </button>

          <button
            v-if="isInHouse"
            type="button"
            @click="extendStay"
            class="inline-flex items-center gap-2 rounded-2xl bg-amber-100 px-5 py-3 text-sm font-bold text-amber-700 transition hover:bg-amber-200"
          >
            <CalendarPlus class="h-4 w-4" />
            Extend stay
          </button>

          <button
            v-if="isInHouse"
            type="button"
            @click="checkOut"
            class="inline-flex items-center gap-2 rounded-2xl bg-rose-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-rose-700"
          >
            <LogOut class="h-4 w-4" />
            Finalize checkout
          </button>
        </div>
      </div>

      <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
        <div class="space-y-8">
          <section
            v-if="booking.has_price_override"
            class="rounded-[2rem] border p-6 shadow-sm"
            :class="booking.has_pending_price_override_approval
              ? 'border-rose-200 bg-rose-50'
              : (booking.price_override?.approval_status === 'rejected' ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50')"
          >
            <div class="flex items-start gap-3">
              <AlertCircle
                class="mt-0.5 h-5 w-5"
                :class="booking.has_pending_price_override_approval
                  ? 'text-rose-600'
                  : (booking.price_override?.approval_status === 'rejected' ? 'text-amber-600' : 'text-emerald-600')"
              />
              <div>
                <h2 class="text-xl font-black text-slate-900">Price override</h2>
                <p class="mt-2 text-sm text-slate-700">
                  Original amount: NGN {{ Number(booking.price_override?.original_amount || 0).toLocaleString() }}.
                  Override amount: NGN {{ Number(booking.price_override?.override_amount || 0).toLocaleString() }}.
                </p>
                <p class="mt-2 text-sm text-slate-700">Note: {{ booking.price_override?.note || booking.price_override?.reason || 'No note recorded' }}</p>
                <p v-if="booking.has_pending_price_override_approval" class="mt-2 text-sm font-bold text-rose-700">
                  Manager approval is still pending. Check-in is locked until the override is reviewed.
                </p>
                <p v-else-if="booking.price_override?.approval_status === 'rejected'" class="mt-2 text-sm font-bold text-amber-700">
                  The override was rejected and the booking has been reset to the original calculated amount.
                </p>
              </div>
            </div>
          </section>

          <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center gap-3">
              <div class="rounded-2xl bg-indigo-50 p-2 text-indigo-600"><User class="h-5 w-5" /></div>
              <h2 class="text-xl font-black text-slate-900">Guest details</h2>
            </div>

            <div class="mt-6 grid gap-6 md:grid-cols-2">
              <div>
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Guest</p>
                <p class="mt-2 text-lg font-bold text-slate-900">{{ booking.guest_name || 'Guest name not provided' }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ booking.guest_email || 'No email provided' }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ booking.guest_phone || 'No phone provided' }}</p>
              </div>

              <div>
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Emergency contact</p>
                <p class="mt-2 text-sm font-bold text-slate-900">{{ booking.emergency_contact_name || 'Not provided' }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ booking.emergency_contact_phone || 'No emergency phone' }}</p>
              </div>

              <div>
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Purpose of stay</p>
                <p class="mt-2 text-sm text-slate-700">{{ booking.purpose_of_stay || 'Not provided' }}</p>
              </div>

              <div>
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Special requests</p>
                <p class="mt-2 text-sm text-slate-700">{{ booking.special_requests || 'None recorded' }}</p>
              </div>
            </div>
          </section>

          <section
            v-if="preCheckIn"
            class="rounded-[2rem] border border-emerald-200 bg-emerald-50 p-6 shadow-sm"
          >
            <h2 class="text-xl font-black text-emerald-900">Online pre-check-in</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-3">
              <div>
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">Completed</p>
                <p class="mt-2 text-sm text-emerald-900">{{ formatDate(preCheckIn.completed_at) }}</p>
              </div>
              <div>
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">Estimated arrival</p>
                <p class="mt-2 text-sm text-emerald-900">{{ preCheckIn.estimated_arrival_time || 'Not provided' }}</p>
              </div>
              <div>
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">Arrival notes</p>
                <p class="mt-2 text-sm text-emerald-900">{{ preCheckIn.arrival_notes || 'None' }}</p>
              </div>
            </div>
          </section>

          <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center gap-3">
              <div class="rounded-2xl bg-amber-50 p-2 text-amber-600"><Calendar class="h-5 w-5" /></div>
              <h2 class="text-xl font-black text-slate-900">Stay timeline</h2>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-3">
              <div class="rounded-[1.5rem] bg-slate-50 p-4">
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Check-in</p>
                <p class="mt-2 text-lg font-black text-slate-900">{{ booking.check_in }}</p>
              </div>
              <div class="rounded-[1.5rem] bg-slate-50 p-4">
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Check-out</p>
                <p class="mt-2 text-lg font-black text-slate-900">{{ booking.check_out }}</p>
              </div>
              <div class="rounded-[1.5rem] bg-slate-900 p-4 text-white">
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-300">Occupancy</p>
                <p class="mt-2 text-lg font-black">{{ booking.checked_in_rooms_count }} room<span v-if="booking.checked_in_rooms_count !== 1">s</span> checked in</p>
              </div>
            </div>
          </section>

          <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-5">
              <div class="flex items-center gap-3">
                <div class="rounded-2xl bg-emerald-50 p-2 text-emerald-600"><BedDouble class="h-5 w-5" /></div>
                <h2 class="text-xl font-black text-slate-900">Room allocation</h2>
              </div>
            </div>

            <div class="divide-y divide-slate-100">
              <div
                v-for="room in booking.rooms"
                :key="room.id"
                class="grid gap-4 px-6 py-5 md:grid-cols-[1fr_auto_auto]"
              >
                <div>
                  <p class="text-sm font-black text-slate-900">{{ room.room_label }}</p>
                  <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-400">Room reference #{{ room.room_number || room.id }}</p>
                </div>
                <div class="text-sm text-slate-600">
                  <p class="font-bold text-slate-900">Checked in</p>
                  <p>{{ formatDate(room.pivot.checked_in_at) }}</p>
                </div>
                <div class="text-sm text-slate-600">
                  <p class="font-bold text-slate-900">Checked out</p>
                  <p>{{ formatDate(room.pivot.checked_out_at) }}</p>
                </div>
              </div>
            </div>
          </section>
        </div>

        <div class="space-y-8">
          <section class="rounded-[2rem] bg-[linear-gradient(145deg,_#0f172a,_#1e293b)] p-6 text-white shadow-sm">
            <div class="flex items-center gap-3">
              <Receipt class="h-5 w-5 text-indigo-300" />
              <h2 class="text-xl font-black">Financial folio</h2>
            </div>

            <div class="mt-6 space-y-4">
              <div class="flex items-center justify-between text-sm text-slate-300">
                <span>Total charges</span>
                <span class="font-bold text-white">₦{{ totalCharges.toLocaleString() }}</span>
              </div>
              <div class="flex items-center justify-between text-sm text-slate-300">
                <span>Total payments</span>
                <span class="font-bold text-emerald-300">₦{{ totalPayments.toLocaleString() }}</span>
              </div>
              <div class="h-px bg-white/10" />
              <div class="flex items-end justify-between">
                <span class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Balance due</span>
                <span class="text-3xl font-black" :class="balanceDue > 0 ? 'text-rose-300' : 'text-emerald-300'">
                  ₦{{ balanceDue.toLocaleString() }}
                </span>
              </div>
            </div>

            <div
              v-if="balanceDue > 0"
              class="mt-6 flex items-start gap-3 rounded-[1.5rem] border border-rose-400/20 bg-rose-500/10 px-4 py-4 text-sm text-rose-100"
            >
              <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
              <p>Outstanding balance must be settled before checkout is finalized.</p>
            </div>
          </section>

          <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
              <h3 class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Charges</h3>
              <Link
                :href="route('frontdesk.billing.show', booking.id) + '#charge-form'"
                class="text-sm font-bold text-indigo-600 transition hover:text-indigo-700"
              >
                Add charge
              </Link>
            </div>

            <div class="mt-4 space-y-3">
              <div
                v-for="charge in booking.charges"
                :key="charge.id"
                class="rounded-[1.25rem] border border-slate-100 px-4 py-3"
              >
                <div class="flex items-center justify-between gap-4">
                  <div>
                    <p class="text-sm font-bold text-slate-900">{{ charge.description }}</p>
                    <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-400">{{ roomLabel(charge.room_id) }}</p>
                  </div>
                  <span class="text-sm font-black text-slate-900">₦{{ Number(charge.amount).toLocaleString() }}</span>
                </div>
              </div>
              <p v-if="booking.charges.length === 0" class="text-sm text-slate-500">No charges recorded yet.</p>
            </div>
          </section>

          <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
              <h3 class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Payments</h3>
              <Link
                :href="route('frontdesk.billing.show', booking.id) + '#payment-form'"
                class="text-sm font-bold text-indigo-600 transition hover:text-indigo-700"
              >
                New payment
              </Link>
            </div>

            <div class="mt-4 space-y-3">
              <div
                v-for="payment in booking.payments"
                :key="payment.id"
                class="rounded-[1.25rem] border border-slate-100 px-4 py-3"
              >
                <div class="flex items-center justify-between gap-4">
                  <div>
                    <p class="text-sm font-bold text-slate-900">{{ payment.method }}</p>
                    <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-400">{{ roomLabel(payment.room_id) }}</p>
                  </div>
                  <span class="text-sm font-black text-emerald-600">₦{{ Number(payment.amount).toLocaleString() }}</span>
                </div>
              </div>
              <p v-if="booking.payments.length === 0" class="text-sm text-slate-500">No payments recorded yet.</p>
            </div>
          </section>
        </div>
      </div>
    </div>
  </FrontDeskLayout>
</template>
