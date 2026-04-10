<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import BaseStaffLayout from '@/Layouts/Staff/BaseStaffLayout.vue'
import Pagination from '@/Components/Pagination.vue'

const props = defineProps({
  threads: { type: Object, required: true },
  summary: { type: Array, default: () => [] },
})

const cards = computed(() => props.summary ?? [])

function badgeClass(type) {
  return type === 'commendation'
    ? 'bg-emerald-100 text-emerald-700 ring-emerald-200'
    : 'bg-amber-100 text-amber-700 ring-amber-200'
}

function formatDate(value) {
  if (!value) return 'No activity yet'

  return new Date(value).toLocaleString([], {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function preview(thread) {
  return thread.latest_message?.message || 'Attachment only update'
}
</script>

<template>
  <BaseStaffLayout>
    <Head title="My Threads" />

    <div class="space-y-8">
      <section class="rounded-[2rem] bg-slate-900 px-6 py-7 text-white shadow-xl shadow-slate-200">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
          <div class="max-w-3xl">
            <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-300">Communication Desk</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight">Queries and commendations in one place.</h1>
            <p class="mt-3 text-sm text-slate-300">
              Start a conversation with leadership, follow replies, and keep your work communication in one visible thread.
              Internal notes are handled separately and are not shown here.
            </p>
          </div>

          <Link
            :href="route('staff.threads.create')"
            class="inline-flex items-center justify-center rounded-2xl bg-white px-5 py-3 text-sm font-bold text-slate-900 transition hover:bg-slate-100"
          >
            Start a thread
          </Link>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-3">
        <div
          v-for="item in cards"
          :key="item.label"
          class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm"
        >
          <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">{{ item.label }}</p>
          <p class="mt-4 text-3xl font-black text-slate-900">{{ item.value }}</p>
          <p class="mt-2 text-sm text-slate-500">{{ item.helper }}</p>
        </div>
      </section>

      <section class="rounded-[2rem] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Inbox</p>
            <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-900">My conversations</h2>
          </div>

          <Link
            :href="route('staff.threads.create')"
            class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
          >
            New conversation
          </Link>
        </div>

        <div v-if="threads.data.length" class="divide-y divide-slate-100">
          <Link
            v-for="thread in threads.data"
            :key="thread.id"
            :href="route('staff.threads.show', thread.id)"
            class="block px-6 py-5 transition hover:bg-slate-50"
          >
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-3">
                <span class="rounded-full px-3 py-1 text-xs font-bold ring-1" :class="badgeClass(thread.type)">
                  {{ thread.type }}
                </span>
                <p class="font-bold text-slate-900">{{ thread.title || 'Untitled conversation' }}</p>
              </div>

              <p class="mt-3 line-clamp-2 max-w-3xl text-sm text-slate-600">{{ preview(thread) }}</p>

              <div class="mt-3 flex flex-wrap gap-4 text-xs font-medium text-slate-400">
                <span>{{ thread.messages_count }} messages</span>
                <span>Last activity {{ formatDate(thread.updated_at) }}</span>
                <span v-if="thread.latest_message?.sender">Last reply by {{ thread.latest_message.sender.name }}</span>
              </div>
            </div>
          </Link>
        </div>

        <div v-else class="px-6 py-16 text-center">
          <h3 class="text-lg font-bold text-slate-900">No conversations yet</h3>
          <p class="mt-2 text-sm text-slate-500">Start a new query or commendation to open the shared record.</p>
          <Link
            :href="route('staff.threads.create')"
            class="mt-5 inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white transition hover:bg-slate-800"
          >
            Start your first thread
          </Link>
        </div>

        <div v-if="threads.links?.length" class="px-6 pb-6">
          <Pagination :links="threads.links" />
        </div>
      </section>
    </div>
  </BaseStaffLayout>
</template>
