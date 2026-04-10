<script setup>
import { reactive, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import Pagination from '@/Components/Pagination.vue'

const props = defineProps({
  feedback: { type: Object, required: true },
  filters: { type: Object, required: true },
  stats: { type: Object, required: true },
  statusOptions: { type: Array, required: true },
  sourceOptions: { type: Array, required: true },
})

const filterForm = reactive({
  status: props.filters.status ?? '',
  source: props.filters.source ?? '',
  search: props.filters.search ?? '',
  anonymous: props.filters.anonymous ?? false,
})

const savingId = ref(null)

const cards = [
  { key: 'new', label: 'New', valueKey: 'new' },
  { key: 'in_review', label: 'In Review', valueKey: 'in_review' },
  { key: 'resolved', label: 'Resolved', valueKey: 'resolved' },
  { key: 'anonymous', label: 'Anonymous', valueKey: 'anonymous', special: true },
]

function applyFilters() {
  router.get(route('admin.feedback.index'), {
    status: filterForm.status || undefined,
    source: filterForm.source || undefined,
    search: filterForm.search || undefined,
    anonymous: filterForm.anonymous ? 1 : undefined,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

function cardHref(card) {
  if (card.special) {
    return route('admin.feedback.index', {
      anonymous: props.filters.anonymous ? undefined : 1,
      status: filterForm.status || undefined,
      source: filterForm.source || undefined,
      search: filterForm.search || undefined,
    })
  }

  return props.filters.status === card.key && !props.filters.anonymous
    ? route('admin.feedback.index', {
        anonymous: undefined,
        source: filterForm.source || undefined,
        search: filterForm.search || undefined,
      })
    : route('admin.feedback.index', {
        status: card.key,
        anonymous: undefined,
        source: filterForm.source || undefined,
        search: filterForm.search || undefined,
      })
}

function formatLabel(value) {
  return String(value || '').replaceAll('_', ' ')
}

function updateFeedback(item, payload) {
  savingId.value = item.id

  router.patch(route('admin.feedback.update', item.id), payload, {
    preserveScroll: true,
    onFinish: () => {
      savingId.value = null
    },
  })
}
</script>

<template>
  <ManagerLayout>
    <div class="space-y-6">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight text-slate-900">Feedback Queue</h1>
          <p class="mt-2 text-sm text-slate-500">Review anonymous and identified submissions from guests, staff, and public visitors.</p>
        </div>
        <Link :href="route('feedback.create')" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
          Open public form
        </Link>
      </div>

      <div class="grid gap-4 md:grid-cols-4">
        <Link
          v-for="card in cards"
          :key="card.key"
          :href="cardHref(card)"
          class="rounded-2xl bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
          :class="(card.special ? props.filters.anonymous : props.filters.status === card.key && !props.filters.anonymous) ? 'ring-2 ring-slate-900/10 border border-slate-300' : 'border border-transparent'"
        >
          <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ card.label }}</div>
          <div class="mt-2 text-3xl font-bold text-slate-900">{{ stats[card.valueKey] }}</div>
          <div class="mt-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
            {{ props.filters.status === card.key ? 'Showing this queue' : 'Filter queue' }}
          </div>
        </Link>
      </div>

      <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
        <div class="grid gap-4 lg:grid-cols-[1fr_220px_220px_auto]">
          <input
            v-model="filterForm.search"
            type="text"
            class="rounded-xl border border-slate-300 px-4 py-3"
            placeholder="Search subject, message, or contact"
            @keyup.enter="applyFilters"
          />

          <select v-model="filterForm.source" class="rounded-xl border border-slate-300 px-4 py-3" @change="applyFilters">
            <option v-for="option in sourceOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>

          <select v-model="filterForm.status" class="rounded-xl border border-slate-300 px-4 py-3" @change="applyFilters">
            <option value="">All statuses</option>
            <option v-for="option in statusOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>

          <button type="button" class="rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white" @click="applyFilters">
            Apply filters
          </button>
        </div>
        <label class="mt-4 inline-flex items-center gap-2 text-sm text-slate-600">
          <input v-model="filterForm.anonymous" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @change="applyFilters" />
          Anonymous submissions only
        </label>
      </div>

      <div class="space-y-4">
        <article
          v-for="item in feedback.data"
          :key="item.id"
          class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm"
        >
          <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <div class="space-y-4 xl:max-w-3xl">
              <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase text-slate-600">{{ item.source }}</span>
                <span class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold uppercase text-indigo-700">{{ formatLabel(item.category) }}</span>
                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold uppercase text-amber-700">{{ formatLabel(item.status) }}</span>
                <span v-if="item.is_anonymous" class="rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold uppercase text-rose-700">Anonymous</span>
                <span v-if="item.rating" class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase text-emerald-700">{{ item.rating }}/5</span>
              </div>

              <div>
                <h2 class="text-xl font-bold text-slate-900">{{ item.subject || 'Untitled feedback' }}</h2>
                <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600">{{ item.message }}</p>
              </div>

              <div class="grid gap-4 text-sm text-slate-600 md:grid-cols-3">
                <div>
                  <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Submitted</p>
                  <p class="mt-2">{{ item.created_at }}</p>
                </div>
                <div>
                  <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Contact</p>
                  <p class="mt-2">{{ item.is_anonymous ? 'Not stored' : (item.contact_name || item.contact_email || item.contact_phone || 'Not provided') }}</p>
                </div>
                <div>
                  <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Context</p>
                  <p class="mt-2">
                    <span v-if="item.room">{{ item.room.name }}</span>
                    <span v-else-if="item.booking">{{ item.booking.booking_code }}</span>
                    <span v-else>No linked stay</span>
                  </p>
                </div>
              </div>
            </div>

            <div class="w-full space-y-3 xl:max-w-sm">
              <select
                class="w-full rounded-xl border border-slate-300 px-4 py-3"
                :value="item.status"
                :disabled="savingId === item.id"
                @change="updateFeedback(item, { status: $event.target.value, internal_notes: item.internal_notes || '' })"
              >
                <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>

              <textarea
                :value="item.internal_notes || ''"
                rows="4"
                class="w-full rounded-xl border border-slate-300 px-4 py-3"
                placeholder="Internal review notes"
                :disabled="savingId === item.id"
                @change="updateFeedback(item, { status: item.status, internal_notes: $event.target.value })"
              ></textarea>

              <p class="text-xs text-slate-400">
                <span v-if="item.reviewer">Last reviewed by {{ item.reviewer.name }}</span>
                <span v-else>Not reviewed yet</span>
                <span v-if="item.reviewed_at"> on {{ item.reviewed_at }}</span>
              </p>
            </div>
          </div>
        </article>

        <div v-if="!feedback.data.length" class="rounded-[1.75rem] border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-slate-500">
          No feedback matches the current filters.
        </div>
      </div>

      <Pagination :links="feedback.links" />
    </div>
  </ManagerLayout>
</template>
