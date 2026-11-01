<script setup>
import { Link } from '@inertiajs/vue3'
import LicenceLayout from '@/Layouts/LicenceLayout.vue'
import { ArrowLeft, LifeBuoy, XCircle } from 'lucide-vue-next'

defineProps({
  message: { type: String, default: 'The payment did not complete.' },
})
</script>

<template>
  <LicenceLayout>
    <section class="mx-auto max-w-2xl px-4 py-16 sm:px-6 lg:px-8">
      <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-rose-100 text-rose-600">
        <XCircle class="h-6 w-6" />
      </span>

      <h1 class="mt-5 text-3xl font-black tracking-tight text-slate-900">Payment not completed</h1>
      <p class="mt-3 text-sm leading-6 text-slate-600">{{ message }}</p>

      <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-600">
        <p class="flex items-start gap-2">
          <LifeBuoy class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
          <span>
            If your bank shows a charge but you did not reach the success page, do not pay again.
            Contact support with the transaction reference and we will trace it and issue your key.
          </span>
        </p>
      </div>

      <div class="mt-8 flex flex-wrap gap-3">
        <Link
          :href="route('licence.checkout.create')"
          class="inline-flex items-center gap-2 rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800"
        >
          <ArrowLeft class="h-4 w-4" />
          Try again
        </Link>
        <Link
          :href="route('licence.pricing')"
          class="rounded-2xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-white"
        >
          View pricing
        </Link>
      </div>
    </section>
  </LicenceLayout>
</template>
