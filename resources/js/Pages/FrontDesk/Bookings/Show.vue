<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import FrontDeskLayout from '@/Layouts/Staff/FrontDeskLayout.vue'

const props = defineProps({
  booking: Object,
})

/* ---------------- Computed ---------------- */

const totalCharges = computed(() =>
  props.booking.charges.reduce((sum, c) => sum + Number(c.amount), 0)
)

const totalPayments = computed(() =>
  props.booking.payments.reduce((sum, p) => sum + Number(p.amount), 0)
)

const balanceDue = computed(() => totalCharges.value - totalPayments.value)

/* ---------------- Actions ---------------- */

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
</script>

<template>
  <FrontDeskLayout>
    <div class="p-6 space-y-6">

      <!-- Header -->
      <div class="flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-bold">
            Booking {{ booking.booking_code }}
          </h1>
          <p class="text-sm text-gray-500">
            Status: <span class="font-semibold">{{ booking.status }}</span>
          </p>
        </div>

        <div class="space-x-2">
          <button
            v-if="booking.status === 'confirmed'"
            @click="checkIn"
            class="px-4 py-2 bg-green-600 text-white rounded"
          >
            Check In
          </button>

          <button
            v-if="booking.status === 'active'"
            @click="extendStay"
            class="px-4 py-2 bg-yellow-600 text-white rounded"
          >
            Extend Stay
          </button>

          <button
            v-if="booking.status === 'active'"
            @click="checkOut"
            class="px-4 py-2 bg-red-600 text-white rounded"
          >
            Check Out
          </button>
        </div>
      </div>

      <!-- Guest Info -->
      <div class="bg-white rounded shadow p-4">
        <h2 class="font-semibold mb-2">Guest Information</h2>
        <p><strong>Name:</strong> {{ booking.guest_name }}</p>
        <p><strong>Email:</strong> {{ booking.guest_email ?? '—' }}</p>
        <p><strong>Phone:</strong> {{ booking.guest_phone ?? '—' }}</p>
      </div>

      <!-- Stay Info -->
      <div class="bg-white rounded shadow p-4 grid grid-cols-2 gap-4">
        <p><strong>Check-in:</strong> {{ booking.check_in }}</p>
        <p><strong>Check-out:</strong> {{ booking.check_out }}</p>
        <p><strong>Rooms Checked In:</strong> {{ booking.checked_in_rooms_count }}</p>
      </div>

      <!-- Rooms -->
      <div class="bg-white rounded shadow p-4">
        <h2 class="font-semibold mb-2">Rooms</h2>
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b">
              <th class="text-left py-2">Room</th>
              <th>Status</th>
              <th>Checked In</th>
              <th>Checked Out</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="room in booking.rooms"
              :key="room.id"
              class="border-b"
            >
              <td class="py-2">{{ room.number }}</td>
              <td>{{ room.pivot.status }}</td>
              <td>{{ room.pivot.checked_in_at ?? '—' }}</td>
              <td>{{ room.pivot.checked_out_at ?? '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Charges -->
      <div class="bg-white rounded shadow p-4">
        <h2 class="font-semibold mb-2">Charges</h2>

        <table class="w-full text-sm">
          <thead>
            <tr class="border-b">
              <th class="text-left py-2">Description</th>
              <th class="text-right">Amount</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="charge in booking.charges"
              :key="charge.id"
              class="border-b"
            >
              <td class="py-2">{{ charge.description }}</td>
              <td class="text-right">
                ₦{{ Number(charge.amount).toFixed(2) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Payments -->
      <div class="bg-white rounded shadow p-4">
        <h2 class="font-semibold mb-2">Payments</h2>

        <table class="w-full text-sm">
          <thead>
            <tr class="border-b">
              <th class="text-left py-2">Method</th>
              <th class="text-right">Amount</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="payment in booking.payments"
              :key="payment.id"
              class="border-b"
            >
              <td class="py-2">{{ payment.method }}</td>
              <td class="text-right">
                ₦{{ Number(payment.amount).toFixed(2) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Summary -->
      <div class="bg-gray-100 rounded p-4 text-right">
        <p><strong>Total Charges:</strong> ₦{{ totalCharges.toFixed(2) }}</p>
        <p><strong>Total Payments:</strong> ₦{{ totalPayments.toFixed(2) }}</p>
        <p class="text-lg font-bold">
          Balance Due:
          <span :class="balanceDue > 0 ? 'text-red-600' : 'text-green-600'">
            ₦{{ balanceDue.toFixed(2) }}
          </span>
        </p>
      </div>

    </div>
  </FrontDeskLayout>
</template>
