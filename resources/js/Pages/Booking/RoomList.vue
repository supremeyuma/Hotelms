<script setup>
import RoomSelection from './RoomSelection.vue';

defineProps({
  roomTypes: Array,
  check_in: String,
  check_out: String,
  adults: Number,
  children: Number,
});
</script>

<template>
  <RoomSelection
    :room-types="roomTypes"
    :check_in="check_in"
    :check_out="check_out"
    :adults="adults"
    :children="children"
  />
</template>
