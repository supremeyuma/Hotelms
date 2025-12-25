<template>
  <div class="space-y-6 p-6">
    <h1 class="text-2xl font-bold">FrontDesk Dashboard</h1>

    <!-- KPIs -->
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
        :href="route('frontdesk.bookings.index', { filter: 'arrivals' })"
        icon="user-plus"
      />
      <KPIWidget 
        title="Guests Departing Today" 
        :value="guestsDeparting" 
        :href="route('frontdesk.bookings.index', { filter: 'departures' })"
        icon="user-minus"
      />
    </div>

    <!-- Outstanding Bookings -->
    <div class="mt-6">
      <h2 class="font-semibold text-lg mb-2">Bookings with Outstanding Balance</h2>
      <div class="space-y-2">
        <BookingItem 
          v-for="booking in outstandingBookingList" 
          :key="booking.id" 
          :booking="booking" 
          :href="`/frontdesk/bookings/${booking.id}`"
        />
        <span v-if="outstandingBookingList.length === 0">No outstanding bookings.</span>
      </div>
    </div>

    <!-- Recent Guest Requests -->
    <div class="mt-6">
      <h2 class="font-semibold text-lg mb-2">Recent Guest Requests</h2>
      <div class="space-y-2">
        <GuestRequestItem 
          v-for="request in recentRequests" 
          :key="request.id" 
          :request="request" 
          :href="`/frontdesk/guest-requests/${request.id}`"
        />
        <span v-if="recentRequests.length === 0">No pending requests.</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import KPIWidget from '@/Components/FrontDesk/KPIWidget.vue';
import GuestRequestItem from '@/Components/FrontDesk/GuestRequestItem.vue';
import BookingItem from '@/Components/FrontDesk/BookingItem.vue';

const props = defineProps({
  roomsOccupied: Number,
  roomsAvailable: Number,
  guestsArriving: Number,
  guestsDeparting: Number,
  outstandingBookings: Number,
  recentRequests: Array,
  outstandingBookingList: Array,
});
</script>
