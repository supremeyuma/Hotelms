<template>
<GuestLayout>
  <div class="max-w-xl mx-auto py-12">
    <h1 class="text-2xl font-bold mb-6">Guest Details</h1>

    <form @submit.prevent="submit" class="space-y-4">
      <div>
        <label>Name</label>
        <input type="text" v-model="form.guest_name" required class="input"/>
      </div>

      <div>
        <label>Email</label>
        <input type="email" v-model="form.guest_email" required class="input"/>
      </div>

      <div>
        <label>Phone</label>
        <input type="text" v-model="form.guest_phone" required class="input"/>
      </div>

      <div>
        <label>Special Requests</label>
        <textarea v-model="form.special_requests" class="input"></textarea>
      </div>

      <button type="submit" class="btn btn-primary">Continue to Review</button>
    </form>
  </div>
</GuestLayout>
</template>

<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { router,useForm } from '@inertiajs/vue3';
import { defineProps } from 'vue';

const props = defineProps({
  booking: Object,
});

const form = useForm({
  guest_name: props.booking.guest_name || '',
  guest_email: props.booking.guest_email || '',
  guest_phone: props.booking.guest_phone || '',
  special_requests: props.booking.special_requests || '',
});

function submit() {
  router.post('/booking/guest', form);
}
</script>
