<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import BaseStaffLayout from '@/Layouts/Staff/BaseStaffLayout.vue'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
  thread: { type: Object, required: true },
})

const form = useForm({
  message: '',
  attachments: [],
})

const isQuery = computed(() => props.thread.type === 'query')

function handleFiles(event) {
  form.attachments = Array.from(event.target.files || [])
}

function sendMessage() {
  form.post(route('staff.threads.messages.store', props.thread.id), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => form.reset('message', 'attachments'),
  })
}

function isImage(path) {
  return /\.(png|jpe?g|gif|webp|bmp|svg)$/i.test(path || '')
}

function filename(path) {
  return String(path || '').split('/').pop()
}

function formatDate(value) {
  if (!value) return ''

  return new Date(value).toLocaleString([], {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<template>
  <BaseStaffLayout>
    <Head :title="thread.title || 'Conversation'" />

    <div class="mx-auto max-w-5xl space-y-6">
      <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <div class="flex flex-wrap items-center gap-3">
              <span
                class="rounded-full px-3 py-1 text-xs font-bold"
                :class="isQuery ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'"
              >
                {{ thread.type }}
              </span>
              <span class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                {{ thread.messages_count }} messages
              </span>
            </div>

            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-900">
              {{ thread.title || 'Untitled conversation' }}
            </h1>

            <p class="mt-2 text-sm text-slate-500">
              Started {{ formatDate(thread.created_at) }}. Keep follow-up in this thread so updates stay together.
            </p>
          </div>

          <Link
            :href="route('staff.threads.index')"
            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
          >
            Back to inbox
          </Link>
        </div>
      </section>

      <section class="space-y-4">
        <article
          v-for="message in thread.messages"
          :key="message.id"
          class="rounded-[1.5rem] border border-slate-200 p-5 shadow-sm"
          :class="message.sender_id === $page.props.auth.user.id ? 'bg-slate-900 text-white' : 'bg-white'"
        >
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="font-bold" :class="message.sender_id === $page.props.auth.user.id ? 'text-white' : 'text-slate-900'">
                {{ message.sender?.name || 'Unknown sender' }}
              </p>
              <p class="text-xs" :class="message.sender_id === $page.props.auth.user.id ? 'text-slate-300' : 'text-slate-400'">
                {{ formatDate(message.created_at) }}
              </p>
            </div>
          </div>

          <p
            v-if="message.message"
            class="mt-4 whitespace-pre-line text-sm leading-6"
            :class="message.sender_id === $page.props.auth.user.id ? 'text-slate-100' : 'text-slate-700'"
          >
            {{ message.message }}
          </p>

          <div v-if="message.attachments?.length" class="mt-4 grid gap-3 md:grid-cols-2">
            <div
              v-for="attachment in message.attachments"
              :key="attachment"
              class="rounded-xl border p-3"
              :class="message.sender_id === $page.props.auth.user.id ? 'border-white/15 bg-white/5' : 'border-slate-200 bg-slate-50'"
            >
              <img
                v-if="isImage(attachment)"
                :src="`/storage/${attachment}`"
                :alt="filename(attachment)"
                class="mb-3 max-h-64 w-full rounded-lg object-cover"
              />
              <a
                :href="`/storage/${attachment}`"
                target="_blank"
                class="text-sm font-semibold underline underline-offset-4"
                :class="message.sender_id === $page.props.auth.user.id ? 'text-white' : 'text-slate-700'"
              >
                {{ filename(attachment) }}
              </a>
            </div>
          </div>
        </article>
      </section>

      <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-xl font-black tracking-tight text-slate-900">Reply in this thread</h2>
        <p class="mt-2 text-sm text-slate-500">Keep the discussion in one place so leadership can follow the full context.</p>

        <form @submit.prevent="sendMessage" class="mt-5 space-y-4">
          <div>
            <textarea
              v-model="form.message"
              rows="5"
              placeholder="Write your reply..."
              class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-slate-900 focus:outline-none focus:ring-0"
            />
            <InputError :message="form.errors.message" class="mt-2" />
          </div>

          <div>
            <input
              type="file"
              multiple
              accept="image/*,.pdf,.doc,.docx"
              @change="handleFiles"
              class="block w-full rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-sm text-slate-600"
            />
            <InputError :message="form.errors.attachments" class="mt-2" />
          </div>

          <div class="flex justify-end">
            <button
              type="submit"
              :disabled="form.processing"
              class="inline-flex items-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
            >
              {{ form.processing ? 'Sending...' : 'Send reply' }}
            </button>
          </div>
        </form>
      </section>
    </div>
  </BaseStaffLayout>
</template>
