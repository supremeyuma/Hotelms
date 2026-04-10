<script setup>
import { computed, watch } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import Checkbox from '@/Components/Checkbox.vue'
import InputError from '@/Components/InputError.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import Textarea from '@/Components/Textarea.vue'

const props = defineProps({
  config: {
    type: Object,
    required: true,
  },
  title: {
    type: String,
    required: true,
  },
  subtitle: {
    type: String,
    required: true,
  },
  helper: {
    type: String,
    default: '',
  },
})

const page = usePage()

const form = useForm({
  category: 'service',
  subject: '',
  message: '',
  rating: '',
  is_anonymous: props.config.prefill?.is_anonymous ?? true,
  allow_follow_up: props.config.prefill?.allow_follow_up ?? false,
  contact_name: props.config.prefill?.contact_name ?? '',
  contact_email: props.config.prefill?.contact_email ?? '',
  contact_phone: props.config.prefill?.contact_phone ?? '',
})

watch(() => form.is_anonymous, (value) => {
  if (value) {
    form.allow_follow_up = false
    form.contact_name = ''
    form.contact_email = ''
    form.contact_phone = ''
  } else {
    form.contact_name = props.config.prefill?.contact_name ?? ''
    form.contact_email = props.config.prefill?.contact_email ?? ''
  }
})

const flashSuccess = computed(() => page.props.flash?.success)

function submit() {
  form.post(props.config.action, {
    preserveScroll: true,
    onSuccess: () => {
      form.reset('category', 'subject', 'message', 'rating')
      form.category = 'service'
      form.is_anonymous = props.config.prefill?.is_anonymous ?? true
      form.allow_follow_up = props.config.prefill?.allow_follow_up ?? false
      form.contact_name = props.config.prefill?.contact_name ?? ''
      form.contact_email = props.config.prefill?.contact_email ?? ''
      form.contact_phone = props.config.prefill?.contact_phone ?? ''
    },
  })
}
</script>

<template>
  <section class="mx-auto max-w-4xl">
    <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
      <div class="border-b border-slate-100 bg-slate-900 px-6 py-7 text-white sm:px-8">
        <p class="text-[11px] font-black uppercase tracking-[0.24em] text-slate-300">{{ config.audience }} feedback</p>
        <h1 class="mt-3 text-3xl font-black tracking-tight">{{ title }}</h1>
        <p class="mt-3 max-w-2xl text-sm text-slate-200">{{ subtitle }}</p>
      </div>

      <div class="space-y-8 px-6 py-8 sm:px-8">
        <div v-if="flashSuccess" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
          {{ flashSuccess }}
        </div>

        <div class="grid gap-4 rounded-[1.5rem] bg-slate-50 p-5 md:grid-cols-2">
          <div>
            <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Anonymous by default</p>
            <p class="mt-2 text-sm text-slate-600">When anonymity stays on, identity and stay context are not stored with the submission.</p>
          </div>
          <div v-if="config.context" class="rounded-[1.25rem] border border-slate-200 bg-white p-4">
            <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Current context</p>
            <p class="mt-2 text-sm font-semibold text-slate-900" v-if="config.context.guest_name">{{ config.context.guest_name }}</p>
            <p class="mt-1 text-sm text-slate-600" v-if="config.context.room_name">Room: {{ config.context.room_name }}</p>
          </div>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
          <div class="grid gap-6 md:grid-cols-2">
            <div>
              <label class="mb-2 block text-sm font-semibold text-slate-700">Category</label>
              <select v-model="form.category" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option v-for="option in config.category_options" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
              <InputError class="mt-2" :message="form.errors.category" />
            </div>

            <div>
              <label class="mb-2 block text-sm font-semibold text-slate-700">Rating</label>
              <select v-model="form.rating" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">No rating</option>
                <option value="5">5 - Excellent</option>
                <option value="4">4 - Good</option>
                <option value="3">3 - Fair</option>
                <option value="2">2 - Poor</option>
                <option value="1">1 - Very poor</option>
              </select>
              <InputError class="mt-2" :message="form.errors.rating" />
            </div>
          </div>

          <div>
            <label class="mb-2 block text-sm font-semibold text-slate-700">Subject</label>
            <TextInput v-model="form.subject" class="block w-full" placeholder="Short summary" />
            <InputError class="mt-2" :message="form.errors.subject" />
          </div>

          <div>
            <label class="mb-2 block text-sm font-semibold text-slate-700">Feedback</label>
            <Textarea v-model="form.message" placeholder="Tell us what happened, what worked well, or what we should improve." class="min-h-36" />
            <InputError class="mt-2" :message="form.errors.message" />
          </div>

          <div class="grid gap-4 rounded-[1.5rem] border border-slate-200 p-5 md:grid-cols-2">
            <label class="flex items-start gap-3 rounded-2xl bg-slate-50 p-4">
              <Checkbox v-model:checked="form.is_anonymous" :checked="form.is_anonymous" />
              <span>
                <span class="block text-sm font-semibold text-slate-900">Keep this anonymous</span>
                <span class="mt-1 block text-sm text-slate-600">No user identity or stay link will be saved with this submission.</span>
              </span>
            </label>

            <label class="flex items-start gap-3 rounded-2xl bg-slate-50 p-4" :class="form.is_anonymous ? 'opacity-60' : ''">
              <Checkbox v-model:checked="form.allow_follow_up" :checked="form.allow_follow_up" :disabled="form.is_anonymous" />
              <span>
                <span class="block text-sm font-semibold text-slate-900">Allow follow-up</span>
                <span class="mt-1 block text-sm text-slate-600">Only available when the feedback is not anonymous.</span>
              </span>
            </label>
          </div>

          <div v-if="!form.is_anonymous" class="grid gap-6 md:grid-cols-3">
            <div>
              <label class="mb-2 block text-sm font-semibold text-slate-700">Name</label>
              <TextInput v-model="form.contact_name" class="block w-full" placeholder="Your name" />
              <InputError class="mt-2" :message="form.errors.contact_name" />
            </div>

            <div>
              <label class="mb-2 block text-sm font-semibold text-slate-700">Email</label>
              <TextInput v-model="form.contact_email" class="block w-full" type="email" placeholder="name@example.com" />
              <InputError class="mt-2" :message="form.errors.contact_email" />
            </div>

            <div>
              <label class="mb-2 block text-sm font-semibold text-slate-700">Phone</label>
              <TextInput v-model="form.contact_phone" class="block w-full" placeholder="Optional phone number" />
              <InputError class="mt-2" :message="form.errors.contact_phone" />
            </div>
          </div>

          <div v-if="helper" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
            {{ helper }}
          </div>

          <div class="flex items-center justify-between gap-4 border-t border-slate-100 pt-6">
            <p class="text-sm text-slate-500">Submissions are reviewed by hotel management.</p>
            <PrimaryButton type="submit" :disabled="form.processing">
              {{ form.processing ? 'Submitting...' : 'Submit feedback' }}
            </PrimaryButton>
          </div>
        </form>
      </div>
    </div>
  </section>
</template>
