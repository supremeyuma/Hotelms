<template>
<GuestLayout>
  <div class="max-w-xl mx-auto py-12">
    <h1 class="text-2xl font-bold mb-6">Book Your Stay</h1>

    <form @submit.prevent="submit" class="space-y-4">
      <div>
        <label>Check-in</label>
        <input type="date" v-model="form.check_in" required class="input"/>
      </div>

      <div v-if="form.errors.availability" class="p-3 bg-red-100 text-red-700 rounded mt-4">
            {{ form.errors.availability }}
        </div>

      <div>
        <label>Check-out</label>
        <input type="date" v-model="form.check_out" required class="input"/>
      </div>

      <div>
        <label>Adults</label>
        <input type="number" v-model="form.adults" min="1" required class="input"/>
      </div>

      <div>
        <label>Children</label>
        <input type="number" v-model="form.children" min="0" class="input"/>
      </div>

      <button type="submit" class="btn btn-primary">Search Rooms</button>
    </form>
  </div>
</GuestLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const form = useForm({
  check_in: '',
  check_out: '',
  adults: 1,
  children: 0,
});

function submit() {
  form.get(route('booking.rooms'), {
    preserveState: true,
    onError: (errors) => {
        console.log("Validation Failed:", errors);
    }
  });
}
</script>

<style scoped>
.input { 
  width: 100%; 
  padding: 0.5rem; 
  border: 1px solid #ccc; 
  border-radius: 0.375rem;
}
.btn { 
  padding: 0.5rem 1rem; 
  border-radius: 0.375rem; 
  cursor: pointer;
}
</style>
