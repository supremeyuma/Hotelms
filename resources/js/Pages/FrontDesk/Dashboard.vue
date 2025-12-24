<template>
  <div class="space-y-6 p-6">
    <h1 class="text-2xl font-bold">FrontDesk Dashboard</h1>

    <!-- KPIs -->
    <div class="grid grid-cols-4 gap-4">
      <KPIWidget title="Rooms Occupied" :value="roomsOccupied" />
      <KPIWidget title="Rooms Available" :value="roomsAvailable" />
      <KPIWidget title="Guests Arriving Today" :value="guestsArriving" />
      <KPIWidget title="Guests Departing Today" :value="guestsDeparting" />
    </div>

    <!-- Outstanding Bookings -->
    <div class="mt-6">
      <h2 class="font-semibold text-lg mb-2">Bookings with Outstanding Balance</h2>
      <span>{{ outstandingBookings }} bookings need attention</span>
    </div>

    <!-- Recent Guest Requests -->
    <div class="mt-6">
      <h2 class="font-semibold text-lg mb-2">Recent Guest Requests</h2>
      <div class="space-y-2">
        <GuestRequestItem 
          v-for="request in recentRequests" 
          :key="request.id" 
          :request="request" 
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import KPIWidget from '@/Components/FrontDesk/KPIWidget.vue';
import GuestRequestItem from '@/Components/FrontDesk/GuestRequestItem.vue';

const props = defineProps({
  roomsOccupied: Number,
  roomsAvailable: Number,
  guestsArriving: Number,
  guestsDeparting: Number,
  outstandingBookings: Number,
  recentRequests: Array,
});
</script>
