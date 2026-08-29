<script setup>
const props = defineProps({
  history: Array,
});
</script>

<template>
  <div class="space-y-4 border-l-2 border-slate-200 pl-4">
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
        <p class="text-slate-500">
          {{ new Date(event.created_at).toLocaleString() }}
          · {{ event.changer?.name || 'Guest' }}
        </p>
      </div>
    </div>
  </div>
</template>
