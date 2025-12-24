<template>
  <div class="p-6 space-y-6">
    <h1 class="text-2xl font-bold">Guest Requests</h1>

    <div class="grid gap-4">
      <GuestRequestItem 
        v-for="request in guestRequests.data" 
        :key="request.id" 
        :request="request" 
        @acknowledge="acknowledgeRequest" 
        @complete="completeRequest"
      />
    </div>

    <Pagination :links="guestRequests.links" @page-change="fetchRequests" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  guestRequests: Object,
});

const guestRequests = ref(props.guestRequests);

function fetchRequests(page = 1) {
  router.get('/frontdesk/guest-requests', { page }, { preserveState: true, replace: true });
}

function acknowledgeRequest(requestId) {
  router.post(`/frontdesk/guest-requests/${requestId}/acknowledge`, {}, { onSuccess: () => fetchRequests() });
}

function completeRequest(requestId) {
  router.post(`/frontdesk/guest-requests/${requestId}/complete`, {}, { onSuccess: () => fetchRequests() });
}

onMounted(() => {
  window.Echo.channel('frontdesk')
    .listen('GuestRequestCreated', (event) => {
      guestRequests.value.data.unshift(event);
      // Optional: limit number of items shown
      if (guestRequests.value.data.length > 20) {
        guestRequests.value.data.pop();
      }
    });
});
</script>

