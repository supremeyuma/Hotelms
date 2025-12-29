<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  request: { type: Object, required: true },
  href: { type: String, required: true },
})
console.log(props.request.room_id);

const label = computed(() =>
  props.request.type
    ? props.request.type.replace('_', ' ').toUpperCase()
    : 'REQUEST'
)

const roomNumber = computed(() =>
  props.request.room?.room_number ?? '—'
)
</script>

<template>
  <Link
    :href="href"
    class="block p-3 rounded border hover:bg-gray-50"
  >
    <div class="flex justify-between items-center">
      <div>
        <p class="font-semibold">
          {{ label }} — Room {{ roomNumber }}
        </p>
        <p class="text-sm text-gray-500">
          Status: {{ request.status }}
        </p>
      </div>

      <span class="text-xs text-gray-400">
        {{ new Date(request.created_at).toLocaleTimeString() }}
      </span>
    </div>
  </Link>
</template>
