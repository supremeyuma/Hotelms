<script setup>
const props = defineProps({
  history: Array,
});
console.log(props.history);
</script>

<template>
  <div class="space-y-4 border-l-2 border-gray-200 pl-4">
    <div
      v-for="event in history"
      :key="event.id"
      class="relative"
    >
      <div class="absolute -left-[9px] top-1.5 w-4 h-4 rounded-full bg-blue-500"></div>

      <div class="text-sm">
        <p class="font-semibold">
          {{ event.from_status || 'Created' }}
          →
          {{ event.to_status }}
        </p>
        <p class="text-gray-500">
          {{ new Date(event.created_at).toLocaleString() }}
          · {{ event.changer?.name || 'Guest' }}
        </p>
      </div>
    </div>
  </div>
</template>
