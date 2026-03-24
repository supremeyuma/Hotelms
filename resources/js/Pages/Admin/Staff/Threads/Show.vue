<template>
  <ManagerLayout>
    <div class="max-w-4xl mx-auto">

      <h2 class="text-xl font-semibold mb-4">
        {{ thread.title || thread.type.toUpperCase() }}
      </h2>

      <!-- Messages -->
      <div class="space-y-4 mb-6">
        <div
          v-for="msg in thread.messages"
          :key="msg.id"
          class="border rounded p-3"
          :class="msg.sender_id === $page.props.auth.user.id ? 'bg-indigo-50' : 'bg-gray-50'"
        >
          <div class="text-sm text-gray-600 mb-1">
            {{ msg.sender.name }} ·
            {{ new Date(msg.created_at).toLocaleString() }}
          </div>

          <p v-if="msg.message" class="mb-2">{{ msg.message }}</p>

          <!-- Images -->
          <div v-if="msg.attachments?.length" class="grid grid-cols-3 gap-2">
            <img
              v-for="(img, i) in msg.attachments"
              :key="i"
              :src="`/storage/${img}`"
              class="rounded border object-cover"
            />
          </div>
        </div>
      </div>

      <!-- Reply -->

    <form @submit.prevent="sendMessage" class="mt-6">
        <textarea
            v-model="form.message"
            class="w-full border rounded p-2"
            rows="3"
            placeholder="Type your reply..."
        />

        <!-- Attachments -->
        <div class="mt-2">
        <input
            type="file"
            multiple
            accept="image/*,.pdf,.doc,.docx"
            @change="handleFiles"
            class="block w-full text-sm text-gray-600"
        />
        </div>


        <div class="mt-2 flex justify-end">
            <button
            type="submit"
            class="bg-indigo-600 text-white px-4 py-2 rounded"
            :disabled="form.processing"
            >
            Send
            </button>
        </div>
    </form>

  </div>
  </ManagerLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'

const props = defineProps({
  thread: Object,
  routePrefix: String,
})

const form = useForm({
  message: '',
  attachments: [],
})

function handleFiles(e) {
  form.attachments = Array.from(e.target.files)
}

function sendMessage() {
  form.post(
    route(`${props.routePrefix}.threads.messages.store`, props.thread.id),
    {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        form.reset('message', 'attachments')
      },
    }
  )
}
</script>
