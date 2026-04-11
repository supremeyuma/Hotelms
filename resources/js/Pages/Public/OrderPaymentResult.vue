<template>
  <PublicLayout>
    <Head :title="status === 'success' ? 'Payment Confirmed' : 'Payment Pending'" />

    <div class="min-h-screen bg-slate-50 py-10">
      <div class="mx-auto max-w-3xl px-4">
        <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-xl shadow-slate-200/60">
          <div :class="status === 'success' ? 'bg-emerald-600' : 'bg-amber-500'" class="p-8 text-white">
            <p class="text-xs font-black uppercase tracking-[0.22em]">
              Public Order Payment
            </p>
            <h1 class="mt-3 text-3xl font-black tracking-tight">
              {{ status === 'success' ? 'Payment Confirmed' : 'Payment Awaiting Confirmation' }}
            </h1>
            <p class="mt-2 text-sm font-medium text-white/80">
              {{ message }}
            </p>
          </div>

          <div class="space-y-6 p-8">
            <div class="grid gap-4 sm:grid-cols-2">
              <div class="rounded-2xl bg-slate-50 p-4">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Order Code</p>
                <p class="mt-2 text-sm font-bold text-slate-900">{{ order.order_code }}</p>
              </div>
              <div class="rounded-2xl bg-slate-50 p-4">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Reference</p>
                <p class="mt-2 text-sm font-bold text-slate-900">{{ reference || 'Pending' }}</p>
              </div>
            </div>

            <div class="rounded-2xl border border-slate-100 p-5">
              <div class="flex items-center justify-between">
                <p class="text-sm font-black text-slate-900">Order Summary</p>
                <p class="text-sm font-bold text-slate-500">{{ order.items.length }} items</p>
              </div>
              <div class="mt-4 space-y-3">
                <div v-for="item in order.items" :key="item.id" class="flex items-center justify-between text-sm">
                  <span class="font-medium text-slate-700">{{ item.item_name || item.name }}</span>
                  <span class="font-bold text-slate-900">NGN {{ Number(item.price || 0).toLocaleString() }}</span>
                </div>
              </div>
              <div class="mt-5 border-t border-slate-100 pt-4 text-right">
                <p class="text-xs font-black uppercase tracking-[0.2em] text-slate-400">Total</p>
                <p class="mt-1 text-xl font-black text-slate-900">NGN {{ Number(order.total || 0).toLocaleString() }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </PublicLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import PublicLayout from '@/Layouts/PublicLayout.vue'

defineProps({
  order: Object,
  status: String,
  reference: String,
  message: String,
})
</script>
