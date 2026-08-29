<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
  staff: { type: Object, required: true },
  staffId: { type: Number, required: true },
  routePrefix: { type: String, required: true },
})

const form = useForm({
  type: 'query',
  title: '',
  message: '',
  attachments: [],
})

const typeOptions = [
  {
    value: 'query',
    label: 'Query',
    description: 'Use this for clarifications, requests, blockers, or issues that need response.',
  },
  {
    value: 'commendation',
    label: 'Commendation',
    description: 'Use this to document praise, recognition, or positive coaching feedback.',
  },
]

function handleFiles(event) {
  form.attachments = Array.from(event.target.files || [])
}

function submit() {
  form.post(route(`${props.routePrefix}.threads.store`, props.staffId), {
    forceFormData: true,
  })
}
</script>

<template>
  <ManagerLayout>
    <Head :title="`Start Thread - ${staff.name}`" />

    <div class="mx-auto max-w-4xl space-y-6">
      <div class="flex items-center justify-between gap-4">
        <div>
          <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Staff Communications</p>
          <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-900">Start a conversation for {{ staff.name }}</h1>
          <p class="mt-2 text-sm text-slate-500">Create a documented query or commendation and keep future follow-up in the same thread.</p>
        </div>

        <Link
          :href="route(`${routePrefix}.threads.index`, staffId)"
          class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
        >
          Back to threads
        </Link>
      </div>

      <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
          <div class="space-y-4 rounded-2xl bg-slate-50 p-5">
            <p class="text-sm font-bold text-slate-900">Thread type</p>
            <div
              v-for="option in typeOptions"
              :key="option.value"
              class="rounded-xl border p-4"
              :class="form.type === option.value ? 'border-slate-900 bg-white' : 'border-slate-200 bg-slate-50'"
            >
              <div class="flex items-center justify-between gap-4">
                <p class="font-bold text-slate-900">{{ option.label }}</p>
                <input v-model="form.type" :value="option.value" type="radio" class="h-4 w-4" />
              </div>
              <p class="mt-2 text-sm text-slate-500">{{ option.description }}</p>
            </div>
          </div>

          <form @submit.prevent="submit" class="space-y-5">
            <div>
              <label class="text-sm font-semibold text-slate-700">Title</label>
              <input
                v-model="form.title"
                type="text"
                maxlength="191"
                placeholder="Short summary"
                class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-slate-900 focus:outline-none focus:ring-0"
              />
              <InputError :message="form.errors.title" class="mt-2" />
            </div>

            <div>
              <label class="text-sm font-semibold text-slate-700">Opening message</label>
              <textarea
                v-model="form.message"
                rows="8"
                placeholder="Write the details clearly so future replies stay easy to follow."
                class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-slate-900 focus:outline-none focus:ring-0"
              />
              <InputError :message="form.errors.message" class="mt-2" />
            </div>

            <div>
              <label class="text-sm font-semibold text-slate-700">Attachments</label>
              <input
                type="file"
                multiple
                accept="image/*,.pdf,.doc,.docx"
                @change="handleFiles"
                class="mt-2 block w-full rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-sm text-slate-600"
              />
              <p class="mt-2 text-xs text-slate-400">Upload images or documents up to 8MB each.</p>
            </div>

            <div class="flex justify-end border-t border-slate-100 pt-4">
              <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex items-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
              >
                {{ form.processing ? 'Sending...' : 'Start conversation' }}
              </button>
            </div>
          </form>
        </div>
      </section>
    </div>
  </ManagerLayout>
</template>
