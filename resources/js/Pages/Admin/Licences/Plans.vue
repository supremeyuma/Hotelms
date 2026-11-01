<script setup>
import { computed } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import InputError from '@/Components/InputError.vue'
import { ArrowLeft, AlertTriangle, Loader2, Save, Tag } from 'lucide-vue-next'

const props = defineProps({
  plans: { type: Array, default: () => [] },
  termMonths: { type: Number, default: 12 },
  checkoutEnabled: { type: Boolean, default: false },
})

const page = usePage()

const form = useForm({
  plans: props.plans.map((plan) => ({
    id: plan.id,
    name: plan.name,
    description: plan.description ?? '',
    min_rooms: plan.min_rooms,
    max_rooms: plan.max_rooms,
    monthly_naira: Number(plan.monthly_naira),
    monthly_usd: Number(plan.monthly_usd),
    sort_order: plan.sort_order,
    is_active: Boolean(plan.is_active),
  })),
})

function format(amount, code) {
  return new Intl.NumberFormat(code === 'USD' ? 'en-US' : 'en-NG', {
    style: 'currency',
    currency: code,
    maximumFractionDigits: 0,
  }).format(Number(amount ?? 0))
}

const termTotal = (row) => Number(row.monthly_naira || 0) * props.termMonths
const termTotalUsd = (row) => Number(row.monthly_usd || 0) * props.termMonths

// Overlapping bands are not fatal (the first matching tier in sort order wins)
// but they are almost always a mistake, so surface them before saving.
const overlaps = computed(() => {
  const active = form.plans.filter((row) => row.is_active)
  const warnings = []

  for (let i = 0; i < active.length; i += 1) {
    for (let j = i + 1; j < active.length; j += 1) {
      const a = active[i]
      const b = active[j]
      const aMax = a.max_rooms === null || a.max_rooms === '' ? Infinity : Number(a.max_rooms)
      const bMax = b.max_rooms === null || b.max_rooms === '' ? Infinity : Number(b.max_rooms)

      if (Number(a.min_rooms) <= bMax && Number(b.min_rooms) <= aMax) {
        warnings.push(`${a.name} and ${b.name} cover the same room count.`)
      }
    }
  }

  return warnings
})

function submit() {
  form
    .transform((data) => ({
      plans: data.plans.map((row) => ({
        ...row,
        max_rooms: row.max_rooms === '' || row.max_rooms === null ? null : Number(row.max_rooms),
      })),
    }))
    .put(route('admin.licences.plans.update'), { preserveScroll: true })
}
</script>

<template>
  <ManagerLayout>
    <Head title="Licence pricing" />

    <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
      <Link :href="route('admin.licences.index')" class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 hover:text-slate-900">
        <ArrowLeft class="h-4 w-4" />
        Licence desk
      </Link>

      <div
        v-if="page.props.flash?.success"
        class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800"
      >
        {{ page.props.flash.success }}
      </div>

      <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-6">
          <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900 text-white">
              <Tag class="h-5 w-5" />
            </span>
            <div>
              <h1 class="text-2xl font-black tracking-tight text-slate-900">Licence pricing</h1>
              <p class="text-sm text-slate-500">
                Monthly rate per tier, charged once for {{ termMonths }} months. The buyer picks NGN or USD.
              </p>
            </div>
          </div>

          <p v-if="!checkoutEnabled" class="rounded-xl bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800">
            Online checkout is off; changes still apply to manual sales and the price list.
          </p>
        </div>

        <form class="p-6" @submit.prevent="submit">
          <div class="space-y-5">
            <div
              v-for="(row, index) in form.plans"
              :key="row.id"
              class="rounded-2xl border border-slate-200 bg-slate-50/60 p-5"
            >
              <div class="grid gap-4 lg:grid-cols-12">
                <div class="lg:col-span-4">
                  <label class="text-xs font-black uppercase tracking-[0.14em] text-slate-400">Tier name</label>
                  <input
                    v-model="row.name"
                    type="text"
                    required
                    maxlength="255"
                    class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                  />
                  <InputError :message="form.errors[`plans.${index}.name`]" />
                </div>

                <div class="lg:col-span-3">
                  <label class="text-xs font-black uppercase tracking-[0.14em] text-slate-400">Description</label>
                  <input
                    v-model="row.description"
                    type="text"
                    maxlength="255"
                    placeholder="Up to 25 rooms"
                    class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                  />
                  <InputError :message="form.errors[`plans.${index}.description`]" />
                </div>

                <div class="lg:col-span-1">
                  <label class="text-xs font-black uppercase tracking-[0.14em] text-slate-400">Min</label>
                  <input
                    v-model.number="row.min_rooms"
                    type="number"
                    min="1"
                    max="5000"
                    required
                    class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                  />
                  <InputError :message="form.errors[`plans.${index}.min_rooms`]" />
                </div>

                <div class="lg:col-span-1">
                  <label class="text-xs font-black uppercase tracking-[0.14em] text-slate-400">Max</label>
                  <input
                    v-model.number="row.max_rooms"
                    type="number"
                    min="1"
                    max="5000"
                    placeholder="∞"
                    class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                  />
                  <InputError :message="form.errors[`plans.${index}.max_rooms`]" />
                </div>

                <div class="lg:col-span-3">
                  <label class="text-xs font-black uppercase tracking-[0.14em] text-slate-400">Monthly (NGN)</label>
                  <input
                    v-model.number="row.monthly_naira"
                    type="number"
                    min="0"
                    step="0.01"
                    required
                    class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                  />
                  <p class="mt-1 text-xs font-semibold text-slate-500">
                    {{ format(termTotal(row), 'NGN') }} for {{ termMonths }} months
                  </p>
                  <InputError :message="form.errors[`plans.${index}.monthly_naira`]" />
                </div>

                <div class="lg:col-span-3">
                  <label class="text-xs font-black uppercase tracking-[0.14em] text-slate-400">Monthly (USD)</label>
                  <input
                    v-model.number="row.monthly_usd"
                    type="number"
                    min="0"
                    step="0.01"
                    required
                    class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                  />
                  <p class="mt-1 text-xs font-semibold text-slate-500">
                    {{ format(termTotalUsd(row), 'USD') }} for {{ termMonths }} months
                  </p>
                  <InputError :message="form.errors[`plans.${index}.monthly_usd`]" />
                </div>

                <div class="lg:col-span-2">
                  <label class="text-xs font-black uppercase tracking-[0.14em] text-slate-400">Sort</label>
                  <input
                    v-model.number="row.sort_order"
                    type="number"
                    min="0"
                    max="999"
                    required
                    class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                  />
                  <InputError :message="form.errors[`plans.${index}.sort_order`]" />
                </div>

                <div class="flex items-end lg:col-span-2">
                  <label class="flex items-center gap-2 text-sm font-bold text-slate-700">
                    <input v-model="row.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                    Active
                  </label>
                </div>
              </div>
            </div>
          </div>

          <div v-if="overlaps.length" class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <p class="flex items-center gap-2 text-sm font-black text-amber-900">
              <AlertTriangle class="h-4 w-4" />
              Overlapping bands
            </p>
            <ul class="mt-2 space-y-1 text-xs text-amber-800">
              <li v-for="warning in overlaps" :key="warning">{{ warning }}</li>
            </ul>
            <p class="mt-2 text-xs text-amber-800">
              These still save. The first matching tier in sort order is used, so keep bands continuous.
            </p>
          </div>

          <div class="mt-6 flex items-center gap-3">
            <button
              type="submit"
              :disabled="form.processing"
              class="inline-flex items-center gap-2 rounded-2xl bg-slate-900 px-6 py-3 text-sm font-bold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
              <Save v-else class="h-4 w-4" />
              {{ form.processing ? 'Saving…' : 'Save pricing' }}
            </button>
            <p class="text-xs text-slate-500">
              Existing licences keep the amount they were sold at; only new checkouts use these rates.
            </p>
          </div>
        </form>
      </section>
    </div>
  </ManagerLayout>
</template>
