<template>
  <div class="p-6 space-y-6">
    <h1 class="text-2xl font-bold">Bookings</h1>

    <!-- Search & Filter -->
    <FilterSearch
      v-model:search="search"
      :filters="filters"
      v-model:filter="filter"
      @search="fetchBookings"
    />

    <!-- Booking Table -->
    <BookingTable
      :bookings="bookings.data"
      @checkin="checkIn"
      @checkout="checkOut"
      @extend="extendStay"
      @edit="editBooking"
    />

    <!-- Pagination -->
    <Pagination :links="bookings.links" @page-change="fetchBookings" />
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import BookingTable from '@/Components/FrontDesk/BookingTable.vue';
import FilterSearch from '@/Components/FrontDesk/FilterSearch.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
  bookings: Object,
  search: String,
  filter: String,
});

const search = ref(props.search || '');
const filter = ref(props.filter || '');
const filters = ['all', 'active', 'confirmed', 'past'];

function fetchBookings(page = 1) {
  router.get('/frontdesk/bookings', { search: search.value, filter: filter.value, page }, { preserveState: true, replace: true });
}

function checkIn(bookingId) {
  router.post(`/frontdesk/bookings/${bookingId}/checkin`);
}

function checkOut(bookingId) {
  router.post(`/frontdesk/bookings/${bookingId}/checkout`);
}

function extendStay(bookingId, newDate) {
  router.post(`/frontdesk/bookings/${bookingId}/extend`, { new_checkout: newDate });
}

function editBooking(bookingId) {
  router.get(`/frontdesk/bookings/${bookingId}/edit`);
}
</script>
