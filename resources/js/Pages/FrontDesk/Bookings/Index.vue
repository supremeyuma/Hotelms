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
  date: String,
});

const search = ref(props.search || '');
const filter = ref(props.filter || 'all');
//const filters = ['all', 'active', 'confirmed', 'past'];
const date   = ref(props.date || '');
const dateType = ref(props.dateType || 'check_in');



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

function applyFilters(payload) {
  search.value = payload.search;
  filter.value = payload.filter;
  date.value   = payload.date;

  fetchBookings();
}

function fetchBookings(page = 1) {
  const params = {
    search: search.value || undefined,
    filter: filter.value !== 'all' ? filter.value : undefined,
    page,
  };

  if (date.value) {
    if (dateType.value === 'check_in') {
      params.check_in_date = date.value;
    } else {
      params.check_out_date = date.value;
    }
  }

  router.get('/frontdesk/bookings', params, {
    preserveState: true,
    replace: true,
  });
}

</script>

<template>
  <div class="p-6 space-y-6">
    <h1 class="text-2xl font-bold">Bookings</h1>
    <div class="flex items-end gap-4">
  <div>
      <label class="block text-sm font-medium text-gray-600 mb-1">
        Filter date by
      </label>
      <select
        v-model="dateType"
        class="border rounded px-3 py-2 w-40"
      >
        <option value="check_in">Check-in date</option>
        <option value="check_out">Check-out date</option>
      </select>
    </div>

  <FilterSearch
    :search="search"
    :filter="filter"
    :date="date"
    :filters="['active', 'confirmed', 'past']"
    @change="applyFilters"
  />

</div>


    <!--<FilterSearch
      :routeName="'frontdesk.bookings.index'"
      v-model:search="search"
      v-model:filter="filter"
      v-model:date="date"
      :filters="['active', 'confirmed', 'past']"
    />-->

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
