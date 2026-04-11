<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import Pagination from '@/Components/Pagination.vue'

const props = defineProps({
  threads: { type: Object, required: true },
  staff: { type: Object, required: true },
  staffId: { type: Number, required: true },
  summary: { type: Array, default: () => [] },
  routePrefix: { type: String, required: true },
})

function isSuspended(staff) {
  return Boolean(staff?.is_suspended ?? staff?.suspended_at)
}

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

function suspendStaff(id) {
  if (!confirm('Suspend this staff member?')) return

  router.post(route(`${props.routePrefix}.suspend`, id))
}

function reinstateStaff(id) {
  if (!confirm('Reactivate this staff member?')) return

  router.post(route(`${props.routePrefix}.reinstate`, id))
}
</script>

<template>
  <ManagerLayout>
    <Head :title="`Threads - ${staff.name}`" />

    <div class="space-y-8">
      <section
        class="rounded-[2rem] px-6 py-7 text-white shadow-xl shadow-slate-200"
        :class="isSuspended(staff) ? 'bg-gradient-to-br from-rose-900 via-rose-800 to-slate-900' : 'bg-slate-900'"
      >
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
          <div class="max-w-3xl">
            <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-300">Staff Communications</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight">{{ staff.name }}</h1>
            <p class="mt-3 text-sm text-slate-300">
              Review queries and commendations for this staff member, reply in context, or start a new conversation.
            </p>
          </div>

          <div class="flex flex-wrap gap-3">
            <Link
              :href="route(`${routePrefix}.threads.create`, staffId)"
              class="inline-flex items-center justify-center rounded-2xl bg-white px-5 py-3 text-sm font-bold text-slate-900 transition hover:bg-slate-100"
            >
              Start conversation
            </Link>
            <Link
              :href="route(`${routePrefix}.edit`, staffId)"
              class="inline-flex items-center justify-center rounded-2xl border border-white/15 px-5 py-3 text-sm font-bold text-white transition hover:bg-white/10"
            >
              Open staff record
            </Link>
          </div>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-3">
        <div
          v-for="item in summary"
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
            <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Conversation Log</p>
            <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-900">Queries and commendations</h2>
          </div>

          <button
            v-if="!isSuspended(staff)"
            type="button"
            class="inline-flex items-center rounded-xl border border-rose-200 px-4 py-2 text-sm font-bold text-rose-600 transition hover:bg-rose-50"
            @click="suspendStaff(staffId)"
          >
            Suspend staff
          </button>
          <button
            v-else
            type="button"
            class="inline-flex items-center rounded-xl border border-emerald-200 px-4 py-2 text-sm font-bold text-emerald-700 transition hover:bg-emerald-50"
            @click="reinstateStaff(staffId)"
          >
            Reactivate staff
          </button>
        </div>

        <div v-if="threads.data.length" class="divide-y divide-slate-100">
          <div v-for="thread in threads.data" :key="thread.id" class="px-6 py-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-3">
                  <span class="rounded-full px-3 py-1 text-xs font-bold ring-1" :class="badgeClass(thread.type)">
                    {{ thread.type }}
                  </span>
                  <p class="font-bold text-slate-900">{{ thread.title || 'Untitled conversation' }}</p>
                </div>

                <p class="mt-3 line-clamp-2 max-w-3xl text-sm text-slate-600">{{ preview(thread) }}</p>

                <div class="mt-3 flex flex-wrap gap-4 text-xs font-medium text-slate-400">
                  <span>{{ thread.messages_count }} messages</span>
                  <span>Updated {{ formatDate(thread.updated_at) }}</span>
                  <span v-if="thread.latest_message?.sender">Last reply by {{ thread.latest_message.sender.name }}</span>
                </div>
              </div>

              <div class="flex flex-wrap gap-3">
                <Link
                  :href="route(`${routePrefix}.threads.show`, thread.id)"
                  class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white transition hover:bg-slate-800"
                >
                  View thread
                </Link>
                <Link
                  :href="route(`${routePrefix}.edit`, thread.staff.id)"
                  class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
                >
                  Staff record
                </Link>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="px-6 py-16 text-center">
          <h3 class="text-lg font-bold text-slate-900">No conversations recorded yet</h3>
          <p class="mt-2 text-sm text-slate-500">Start a query or commendation to keep future follow-up in one place.</p>
          <Link
            :href="route(`${routePrefix}.threads.create`, staffId)"
            class="mt-5 inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white transition hover:bg-slate-800"
          >
            Start first conversation
          </Link>
        </div>

        <div v-if="threads.links?.length" class="px-6 pb-6">
          <Pagination :links="threads.links" />
        </div>
      </section>
    </div>
  </ManagerLayout>
</template>
