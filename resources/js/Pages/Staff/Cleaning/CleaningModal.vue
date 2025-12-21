<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  room: Object
})

const emit = defineEmits(['close'])

const action = ref('clean')
const actionCode = ref('')
const error = ref(null)

const cleaningId = computed(() => props.room.latest_cleaning?.id)

const submit = () => {
  router.patch(
    `/cleaning/${cleaningId.value ?? 'create'}`,
    {
      room_id: props.room.id,
      action: action.value,
      action_code: actionCode.value
    },
    {
      onError: (e) => error.value = e.message,
      onSuccess: () => emit('close')
    }
  )
}
</script>

<template>
<div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
  <div class="bg-white p-6 rounded w-96">
    <h2 class="font-bold text-lg mb-4">
      Room {{ room.number }}
    </h2>

    <select v-model="action" class="w-full mb-3 border rounded px-2 py-1">
      <option value="cleaning">Start Cleaning</option>
      <option value="clean">Mark as Clean</option>
    </select>

    <input
      v-model="actionCode"
      type="password"
      placeholder="Action Code"
      class="w-full border rounded px-2 py-1 mb-3"
    />

    <p v-if="error" class="text-red-600 text-sm">{{ error }}</p>

    <div class="flex justify-end gap-2">
      <button @click="$emit('close')" class="px-3 py-1 border rounded">
        Cancel
      </button>
      <button @click="submit" class="px-3 py-1 bg-green-600 text-white rounded">
        Confirm
      </button>
    </div>
  </div>
</div>
</template>
