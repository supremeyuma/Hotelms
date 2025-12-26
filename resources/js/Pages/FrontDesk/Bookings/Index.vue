<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import BookingTable from '@/Components/FrontDesk/BookingTable.vue';
import FilterSearch from '@/Components/FrontDesk/FilterSearch.vue';
import Pagination from '@/Components/Pagination.vue';
import CheckInModal from '@/Components/FrontDesk/CheckInModal.vue';

const props = defineProps({
  bookings: Object,
  search: String,
  filter: String,
});

const search = ref(props.search || '');
const filter = ref(props.filter || '');
const filters = ['all', 'active', 'confirmed', 'past'];

const showCheckIn = ref(false);
const selectedBooking = ref(null);

function checkIn(booking) {
  selectedBooking.value = booking;
  showCheckIn.value = true;
}

function checkOut(booking) {
  router.post(`/frontdesk/bookings/${booking.id}/checkout`);
}

function editBooking(booking) {
  router.get(`/frontdesk/bookings/${booking.id}/edit`);
}


function fetchBookings(page = 1) {
  router.get(
    '/frontdesk/bookings',
    { search: search.value, filter: filter.value, page },
    { preserveState: true }
  );
}
</script>

<template>
  <div class="p-6 space-y-6">
    <h1 class="text-2xl font-bold">Bookings</h1>

    <FilterSearch
      :routeName="'frontdesk.bookings.index'"
      v-model:search="search"
      :filters="filters"
    />

    <BookingTable
      :bookings="bookings.data"
      @checkin="checkIn"
      @checkout="checkOut"
      @edit="editBooking"
    />

    <Pagination :links="bookings.links" @page-change="fetchBookings" />


    <CheckInModal
      v-if="selectedBooking"
      :show="showCheckIn"
      :booking="selectedBooking"
      @close="showCheckIn = false"
    />
  </div>
</template>
