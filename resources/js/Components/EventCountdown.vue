<template>
  <div v-if="!isExpired" class="flex gap-2 text-center">
    <div v-for="(value, label) in timeLeft" :key="label" class="flex flex-col">
      <span class="text-lg font-bold leading-none">{{ value }}</span>
      <span class="text-[10px] uppercase opacity-75">{{ label.charAt(0) }}</span>
    </div>
  </div>
  <div v-else class="text-xs font-bold text-red-400 animate-pulse">
    HAPPENING NOW
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';

const props = defineProps(['targetDate']);
const now = ref(new Date());
let timer = null;

const timeLeft = computed(() => {
  // Ensure the date string is in a format JS likes: YYYY-MM-DDTHH:MM:SS
  const formattedDate = props.targetDate.replace(/\s/, 'T');
  const target = new Date(formattedDate);
  const diff = target - now.value;
  
  if (isNaN(target) || diff <= 0) return null;

  return {
    days: Math.floor(diff / (1000 * 60 * 60 * 24)),
    hrs: Math.floor((diff / (1000 * 60 * 60)) % 24),
    mins: Math.floor((diff / 1000 / 60) % 60),
    secs: Math.floor((diff / 1000) % 60),
  };
});

const isExpired = computed(() => !timeLeft.value);

onMounted(() => {
  timer = setInterval(() => { now.value = new Date(); }, 1000);
});

onUnmounted(() => clearInterval(timer));
</script>