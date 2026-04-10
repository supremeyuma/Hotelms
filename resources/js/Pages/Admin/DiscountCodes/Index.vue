<script setup>
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import InputError from '@/Components/InputError.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { CalendarClock, Percent, Plus, Tag, TicketSlash } from 'lucide-vue-next'

const props = defineProps({
  codes: Array,
  scopeOptions: Array,
  discountTypeOptions: Array,
})

const form = useForm({
  name: '',
  code: '',
  description: '',
  applies_to: 'room_rate',
  discount_type: 'percentage',
  discount_value: '',
  valid_from: '',
  valid_until: '',
  max_rooms: '',
  is_active: true,
})

function submit() {
  form.post(route('admin.discount-codes.store'), {
    preserveScroll: true,
    onSuccess: () => form.reset('name', 'code', 'description', 'discount_value', 'valid_from', 'valid_until', 'max_rooms'),
  })
}

function toggle(code) {
  router.patch(route('admin.discount-codes.toggle', code.id), {}, {
    preserveScroll: true,
  })
}

function formatValue(code) {
  return code.discount_type === 'percentage'
    ? `${Number(code.discount_value).toLocaleString()}%`
    : `NGN ${Number(code.discount_value).toLocaleString()}`
}

function statusClass(code) {
  if (code.is_active && code.is_currently_valid) return 'bg-emerald-100 text-emerald-700'
  if (code.is_active) return 'bg-amber-100 text-amber-700'
  return 'bg-slate-100 text-slate-600'
}
</script>

<template>
  <ManagerLayout>
    <Head title="Discount Codes" />

    <div class="space-y-8">
      <section class="overflow-hidden rounded-[2rem] bg-slate-900 text-white shadow-2xl shadow-slate-200">
        <div class="grid gap-6 px-6 py-8 sm:px-8 xl:grid-cols-[1.2fr_0.8fr]">
          <div class="space-y-4">
            <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-[11px] font-black uppercase tracking-[0.22em] text-slate-200">
              <Tag class="h-3.5 w-3.5" />
              Commercial Controls
            </div>
            <h1 class="max-w-3xl text-3xl font-black tracking-tight sm:text-4xl">
              Create room, food, bar, and club discount codes.
            </h1>
            <p class="max-w-2xl text-sm leading-6 text-slate-300">
              Define what each code applies to, when it can be used, and how many rooms can consume it.
            </p>
          </div>

          <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-1">
            <div class="rounded-[1.75rem] border border-white/10 bg-white/10 p-5">
              <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-300">Total codes</p>
              <p class="mt-3 text-3xl font-black">{{ codes.length }}</p>
            </div>
            <div class="rounded-[1.75rem] border border-white/10 bg-white/10 p-5">
              <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-300">Room-booking ready</p>
              <p class="mt-3 text-3xl font-black">{{ codes.filter(code => code.applies_to === 'room_rate').length }}</p>
            </div>
          </div>
        </div>
      </section>

      <section class="grid gap-8 xl:grid-cols-[0.95fr_1.05fr]">
        <form @submit.prevent="submit" class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
          <div class="flex items-center gap-3">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-700">
              <Plus class="h-5 w-5" />
            </div>
            <div>
              <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">New code</p>
              <h2 class="mt-1 text-2xl font-black tracking-tight text-slate-900">Create discount code</h2>
            </div>
          </div>

          <div class="mt-6 grid gap-5">
            <div class="grid gap-5 md:grid-cols-2">
              <label class="block">
                <span class="text-sm font-bold text-slate-700">Name</span>
                <input v-model="form.name" type="text" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400" placeholder="Weekend saver" />
                <InputError :message="form.errors.name" class="mt-2" />
              </label>

              <label class="block">
                <span class="text-sm font-bold text-slate-700">Code</span>
                <input v-model="form.code" type="text" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm uppercase text-slate-700 outline-none transition focus:border-slate-400" placeholder="SAVE10" />
                <InputError :message="form.errors.code" class="mt-2" />
              </label>
            </div>

            <label class="block">
              <span class="text-sm font-bold text-slate-700">Description</span>
              <textarea v-model="form.description" rows="3" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400" placeholder="Optional notes for the operations team." />
              <InputError :message="form.errors.description" class="mt-2" />
            </label>

            <div class="grid gap-5 md:grid-cols-2">
              <label class="block">
                <span class="text-sm font-bold text-slate-700">Applies to</span>
                <select v-model="form.applies_to" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400">
                  <option v-for="option in scopeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
                <InputError :message="form.errors.applies_to" class="mt-2" />
              </label>

              <label class="block">
                <span class="text-sm font-bold text-slate-700">Discount type</span>
                <select v-model="form.discount_type" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400">
                  <option v-for="option in discountTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
                <InputError :message="form.errors.discount_type" class="mt-2" />
              </label>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
              <label class="block">
                <span class="text-sm font-bold text-slate-700">Discount value</span>
                <input v-model="form.discount_value" type="number" min="0" step="0.01" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400" :placeholder="form.discount_type === 'percentage' ? '10' : '5000'" />
                <InputError :message="form.errors.discount_value" class="mt-2" />
              </label>

              <label class="block">
                <span class="text-sm font-bold text-slate-700">Room usage cap</span>
                <input v-model="form.max_rooms" type="number" min="1" step="1" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400" placeholder="Optional" />
                <InputError :message="form.errors.max_rooms" class="mt-2" />
              </label>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
              <label class="block">
                <span class="text-sm font-bold text-slate-700">Valid from</span>
                <input v-model="form.valid_from" type="datetime-local" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400" />
                <InputError :message="form.errors.valid_from" class="mt-2" />
              </label>

              <label class="block">
                <span class="text-sm font-bold text-slate-700">Valid until</span>
                <input v-model="form.valid_until" type="datetime-local" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400" />
                <InputError :message="form.errors.valid_until" class="mt-2" />
              </label>
            </div>

            <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
              <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-slate-900 focus:ring-slate-400" />
              <span class="text-sm font-semibold text-slate-700">Activate this code immediately</span>
            </label>

            <button
              type="submit"
              :disabled="form.processing"
              class="inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <Plus class="h-4 w-4" />
              {{ form.processing ? 'Saving...' : 'Create discount code' }}
            </button>
          </div>
        </form>

        <section class="space-y-5">
          <article
            v-for="code in codes"
            :key="code.id"
            class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm"
          >
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
              <div class="space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="text-2xl font-black tracking-tight text-slate-900">{{ code.name }}</h3>
                  <span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-[0.18em]" :class="statusClass(code)">
                    {{ code.is_active ? (code.is_currently_valid ? 'Live' : 'Scheduled') : 'Paused' }}
                  </span>
                </div>
                <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-2 text-sm font-black tracking-[0.18em] text-slate-700">
                  <Tag class="h-4 w-4" />
                  {{ code.code }}
                </div>
                <p v-if="code.description" class="max-w-2xl text-sm leading-6 text-slate-500">{{ code.description }}</p>
              </div>

              <button
                type="button"
                @click="toggle(code)"
                class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
              >
                {{ code.is_active ? 'Pause code' : 'Activate code' }}
              </button>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
              <div class="rounded-[1.5rem] bg-slate-50 p-4">
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Offer</p>
                <p class="mt-3 text-base font-black text-slate-900">{{ formatValue(code) }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ code.discount_type_label }}</p>
              </div>

              <div class="rounded-[1.5rem] bg-slate-50 p-4">
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Usage target</p>
                <p class="mt-3 text-base font-black text-slate-900">{{ code.applies_to_label }}</p>
                <p class="mt-1 text-sm text-slate-500">Selected by manager</p>
              </div>

              <div class="rounded-[1.5rem] bg-slate-50 p-4">
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Room capacity</p>
                <p class="mt-3 text-base font-black text-slate-900">
                  {{ code.max_rooms === null ? 'Unlimited' : `${code.remaining_rooms} left` }}
                </p>
                <p class="mt-1 text-sm text-slate-500">
                  {{ code.max_rooms === null ? 'No room cap set' : `${code.active_redemptions_count} active booking use(s)` }}
                </p>
              </div>

              <div class="rounded-[1.5rem] bg-slate-50 p-4">
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Window</p>
                <p class="mt-3 text-base font-black text-slate-900">{{ code.valid_from ? 'Scheduled' : 'Immediate' }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ code.valid_until ? 'Ends automatically' : 'No expiry set' }}</p>
              </div>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-2">
              <div class="rounded-[1.5rem] border border-slate-200 p-4">
                <div class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">
                  <CalendarClock class="h-3.5 w-3.5" />
                  Active dates
                </div>
                <p class="mt-3 text-sm text-slate-700">
                  {{ code.valid_from || 'Starts immediately' }}
                </p>
                <p class="mt-1 text-sm text-slate-500">
                  {{ code.valid_until || 'Runs until manually paused' }}
                </p>
              </div>

              <div class="rounded-[1.5rem] border border-slate-200 p-4">
                <div class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">
                  <Percent class="h-3.5 w-3.5" />
                  Tracking
                </div>
                <p class="mt-3 text-sm text-slate-700">
                  Created {{ code.created_at }}
                </p>
                <p class="mt-1 text-sm text-slate-500">
                  {{ code.creator || 'System user not recorded' }}
                </p>
              </div>
            </div>
          </article>

          <div v-if="!codes.length" class="rounded-[2rem] border border-dashed border-slate-300 bg-white px-6 py-16 text-center shadow-sm">
            <TicketSlash class="mx-auto h-12 w-12 text-slate-300" />
            <h2 class="mt-4 text-xl font-black text-slate-900">No discount codes yet</h2>
            <p class="mt-2 text-sm text-slate-500">Create the first code so managers can control booking and outlet promotions from one place.</p>
          </div>
        </section>
      </section>
    </div>
  </ManagerLayout>
</template>
