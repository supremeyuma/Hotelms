<script setup>
import { ref, watch } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import FrontDeskLayout from '@/Layouts/Staff/FrontDeskLayout.vue'
import BookingTable from '@/Components/FrontDesk/BookingTable.vue';
import FilterSearch from '@/Components/FrontDesk/FilterSearch.vue';
import Pagination from '@/Components/Pagination.vue';
import CheckInModal from '@/Components/FrontDesk/CheckInModal.vue';
import { CalendarDays, Filter, Plus, Search } from 'lucide-vue-next';

const props = defineProps({
  bookings: Object,
  search: String,
  filter: String,
  date: String,
  dateType: String,
});

const search = ref(props.search || '');
const filter = ref(props.filter || 'all');
const date = ref(props.date || '');
const dateType = ref(props.dateType || 'check_in');

const showCheckIn = ref(false);
const selectedBooking = ref(null);

function checkIn(booking) {
  selectedBooking.value = booking;
  showCheckIn.value = true;
}

function checkOut(booking) {
  if (confirm('Are you sure you want to check out this guest?')) {
    router.post(`/frontdesk/bookings/${booking.id}/checkout`);
  }
}

function editBooking(booking) {
  router.get(`/frontdesk/bookings/${booking.id}/edit`);
}

function applyFilters(payload) {
  search.value = payload.search;
  filter.value = payload.filter;
  date.value = payload.date;
  fetchBookings();
}

function fetchBookings(page = 1) {
  const params = {
    search: search.value || undefined,
    filter: filter.value !== 'all' ? filter.value : undefined,
    dateType: dateType.value,
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

// Re-fetch if the user toggles between Check-in/Check-out date types
watch(dateType, () => {
  if (date.value) fetchBookings();
});

</script>

<template>
  <FrontDeskLayout>
    <Head title="Booking Management" />

    <div class="p-8 max-w-[1600px] mx-auto space-y-8">
      
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 class="text-3xl font-black text-slate-900 tracking-tight">Bookings</h1>
          <p class="text-slate-500 font-medium">Manage arrivals, departures, and guest stays.</p>
        </div>
        
        <button 
          @click="router.visit('/frontdesk/bookings/create')"
          class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-indigo-600 text-white rounded-2xl font-bold hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-100 active:scale-95"
        >
          <Plus class="w-5 h-5" />
          New Reservation
        </button>
      </div>

      <div class="bg-white p-6 rounded-[2rem] border border-slate-200 shadow-sm">
        <div class="flex flex-wrap items-end gap-6">
          
          <div class="flex-none">
            <label class="flex items-center gap-2 text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">
              <CalendarDays class="w-3 h-3" />
              Date Reference
            </label>
            <select
              v-model="dateType"
              class="block w-full border-2 border-slate-100 bg-slate-50 rounded-xl px-4 py-2.5 font-bold text-slate-700 focus:border-indigo-500 focus:ring-0 transition-all text-sm"
            >
              <option value="check_in">Check-in Date</option>
              <option value="check_out">Check-out Date</option>
            </select>
          </div>

          <div class="flex-grow">
            <FilterSearch
              :search="search"
              :filter="filter"
              :date="date"
              :filters="['active', 'confirmed', 'past']"
              @change="applyFilters"
            />
          </div>

        </div>
      </div>

      <div class="bg-white rounded-[2rem] border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <BookingTable
            :bookings="bookings.data"
            @checkin="checkIn"
            @checkout="checkOut"
            @edit="editBooking"
          />
        </div>
        
        <div v-if="bookings.data.length === 0" class="py-20 text-center">
          <div class="inline-flex p-4 bg-slate-50 rounded-full text-slate-300 mb-4">
            <Search class="w-10 h-10" />
          </div>
          <p class="text-slate-500 font-bold">No reservations found matching your criteria.</p>
        </div>

        <div class="p-6 border-t border-slate-50 bg-slate-50/50">
          <Pagination :links="bookings.links" @page-change="fetchBookings" />
        </div>
      </div>
    </div>

    <CheckInModal
      v-if="selectedBooking"
      :show="showCheckIn"
      :booking="selectedBooking"
      @close="showCheckIn = false"
    />
  </FrontDeskLayout>
</template>

<style scoped>
/* Scoped overrides to ensure the staff UI feels tighter than the guest UI */
:deep(select), :deep(input) {
  @apply shadow-none !important;
}
</style>