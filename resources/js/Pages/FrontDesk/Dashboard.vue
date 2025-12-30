<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import KPIWidget from '@/Components/FrontDesk/KPIWidget.vue'
import GuestRequestItem from '@/Components/FrontDesk/GuestRequestItem.vue'
import BookingItem from '@/Components/FrontDesk/BookingItem.vue'

const props = defineProps({
  roomsOccupied: Number,
  roomsAvailable: Number,
  guestsArriving: Number,
  guestsDeparting: Number,
  recentRequests: Array,
  outstandingBookingList: Array,
})

const recentRequests = ref([...props.recentRequests])

function isVisibleOnFrontDesk(request) {
  if (request.type === 'laundry') {
    return request.status === 'requested'
  }

  if (['kitchen', 'bar'].includes(request.type)) {
    return request.status === 'pending'
  }

  if (request.type === 'cleaning') {
    return request.status === 'requested'
  }

  return false
}

onMounted(() => {
  if (!window.Echo) return

  window.Echo.channel('laundry-orders')
    .listen('.LaundryOrderUpdated', (e) => {
      const order = e.order

      const guestRequest = {
        id: order.guest_request?.id ?? `laundry-${order.id}`,
        type: 'laundry',
        status: order.status,
        requestable: order,
      }

      // Remove existing
      recentRequests.value = recentRequests.value.filter(r => r.id !== guestRequest.id)

      // Only add if still frontdesk-visible
      if (isVisibleOnFrontDesk(guestRequest)) {
        recentRequests.value.unshift(guestRequest)
      }
    })
})

onBeforeUnmount(() => {
  window.Echo?.leave('laundry-orders')
})

function resolveRequestLink(request) {
  if (request.type === 'laundry' && request.requestable?.id) {
    return route('staff.laundry.show', request.requestable.id)
  }

  // future-proof
  if (request.type === 'kitchen') {
    return '#'
  }

  if (request.type === 'cleaning') {
    return '#'
  }

  return '#'
}
</script>

<template>
  <div class="space-y-6 p-6">
    <h1 class="text-2xl font-bold">FrontDesk Dashboard</h1>

    <div class="grid grid-cols-4 gap-4">
      <KPIWidget
        title="Rooms Occupied"
        :value="roomsOccupied"
        :href="route('frontdesk.rooms.index', { status: 'occupied' })"
        icon="bed"
      />

      <KPIWidget
        title="Rooms Available"
        :value="roomsAvailable"
        :href="route('frontdesk.rooms.index', { status: 'available' })"
        icon="bed-outline"
      />

      <KPIWidget
        title="Guests Arriving Today"
        :value="guestsArriving"
        :href="route('frontdesk.bookings.index', {
          check_in_date: new Date().toISOString().slice(0, 10)
        })"
        icon="user-plus"
      />

      <KPIWidget
        title="Guests Departing Today"
        :value="guestsDeparting"
        :href="route('frontdesk.bookings.index', {
          check_out_date: new Date().toISOString().slice(0, 10)
        })"
        icon="user-minus"
      />
    </div>

    <KPIWidget
      title="All Bookings"
      :value="'View'"
      :href="route('frontdesk.bookings.index')"
      icon="list"
    />


    <!-- Outstanding -->
    <div>
      <h2 class="font-semibold text-lg mb-2">
        Bookings with Outstanding Balance
      </h2>

      <BookingItem
        v-for="b in outstandingBookingList"
        :key="b.id"
        :booking="b"
        :href="route('frontdesk.bookings.show', b.id)"
      />

      <p v-if="!outstandingBookingList.length" class="text-gray-500">
        No outstanding bookings.
      </p>
    </div>

    <!-- Guest Requests -->
    <div>
      <h2 class="font-semibold text-lg mb-2">
        Recent Guest Requests
      </h2>

    <GuestRequestItem
      v-for="r in recentRequests"
      :key="r.id"
      :request="r"
      :href="resolveRequestLink(r)"
    />


      <p v-if="!recentRequests.length" class="text-gray-500">
        No pending requests.
      </p>
    </div>
  </div>
</template>

