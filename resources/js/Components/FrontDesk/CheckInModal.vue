<template>
  <Modal :show="show" @close="$emit('close')">
    <template #title>
      <h2 class="text-lg font-bold">Check-in Guest</h2>
    </template>

    <template #content>
      <p class="mb-3">
        <strong>{{ booking.guest_name }}</strong><br />
        {{ booking.checked_in_rooms }} of {{ booking.quantity }} rooms checked-in
      </p>

      <div class="mb-4">
        <label class="block text-sm mb-1">Rooms to check-in</label>
        <input
          type="number"
          v-model.number="rooms"
          :max="remaining"
          min="1"
          class="w-full border rounded px-3 py-2"
        />
        <p v-if="remaining === 0" class="text-red-500 text-sm mt-1">
          All rooms already checked-in
        </p>
      </div>

      <div class="flex justify-end gap-3">
        <button class="btn-secondary" @click="$emit('close')">Cancel</button>
        <button
          class="btn-primary"
          :disabled="rooms < 1 || rooms > remaining"
          @click="submit"
        >
          Confirm Check-in
        </button>
      </div>
    </template>
  </Modal>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
  show: Boolean,
  booking: Object,
});

const emit = defineEmits(['checked-in','close']); // ✅ define emits

const rooms = ref(1);

const remaining = computed(() =>
  Math.max(props.booking.quantity - props.booking.checked_in_rooms, 0)
);

// Reset input when modal opens
watch(
  () => props.show,
  (newVal) => {
    if (newVal) rooms.value = 1;
  }
);

function submit() {
  if (rooms.value < 1 || rooms.value > remaining.value) return;

  router.post(
    `/frontdesk/bookings/${props.booking.id}/checkin`,
    { rooms: rooms.value },
    {
      preserveScroll: true,
      onSuccess: () => emit('close'),
    }
  );
}

function onSuccess(bookingId) {
    emit('checked-in', bookingId); // ✅ use the emit function
}
</script>
