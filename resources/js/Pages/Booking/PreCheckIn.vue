<script setup>
import { useForm } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Clock3, Mail, Phone, User, MessageSquare, CheckCircle2 } from 'lucide-vue-next';

const props = defineProps({
  booking: Object,
  pre_check_in: Object,
  can_submit: Boolean,
  signed_action: String,
});

const form = useForm({
  guest_name: props.booking.guest_name || '',
  guest_email: props.booking.guest_email || '',
  guest_phone: props.booking.guest_phone || '',
  estimated_arrival_time: props.pre_check_in?.estimated_arrival_time || '',
  arrival_notes: props.pre_check_in?.arrival_notes || '',
});

function submit() {
  form.post(props.signed_action);
}

function formatDate(dateString) {
  return new Date(dateString).toLocaleDateString('en-NG', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}
</script>

<template>
  <PublicLayout>
    <div class="min-h-screen bg-slate-50/50 py-12 px-4">
      <div class="max-w-3xl mx-auto">
        <div class="text-center mb-10">
          <div class="inline-flex items-center justify-center h-20 w-20 rounded-[2rem] bg-emerald-100 text-emerald-600 shadow-xl shadow-emerald-100 mb-5">
            <CheckCircle2 class="w-10 h-10" />
          </div>
          <h1 class="text-3xl font-black text-slate-900 tracking-tight mb-2">Complete Pre-Check-In</h1>
          <p class="text-slate-500 font-medium">
            Your booking is paid and confirmed for {{ formatDate(booking.check_in) }}. Final room access will still be issued by the front desk.
          </p>
        </div>

        <div class="bg-white rounded-[2.5rem] border border-slate-200 shadow-xl shadow-slate-200/50 overflow-hidden">
          <div class="border-b border-slate-100 px-8 py-6 bg-slate-50">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.25em] mb-1">Reservation</p>
            <h2 class="text-2xl font-black text-slate-900">{{ booking.booking_code }}</h2>
          </div>

          <div class="p-8 md:p-10">
            <div v-if="pre_check_in?.completed_at" class="mb-8 rounded-[2rem] border border-emerald-100 bg-emerald-50 p-5">
              <p class="text-sm font-bold text-emerald-800">
                Pre-check-in already completed.
              </p>
              <p class="mt-1 text-sm text-emerald-700">
                Estimated arrival: {{ pre_check_in.estimated_arrival_time || 'Not provided' }}.
              </p>
            </div>

            <form v-if="can_submit" @submit.prevent="submit" class="space-y-6">
              <div class="grid gap-6 md:grid-cols-2">
                <div class="space-y-2">
                  <label class="text-xs font-black uppercase tracking-widest text-slate-400 ml-1">Guest Name</label>
                  <div class="relative">
                    <User class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" />
                    <input v-model="form.guest_name" type="text" class="w-full rounded-2xl border-2 border-slate-100 bg-slate-50 py-4 pl-12 pr-4 font-bold text-slate-700 focus:border-indigo-600 focus:bg-white focus:ring-0" required />
                  </div>
                </div>

                <div class="space-y-2">
                  <label class="text-xs font-black uppercase tracking-widest text-slate-400 ml-1">Email</label>
                  <div class="relative">
                    <Mail class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" />
                    <input v-model="form.guest_email" type="email" class="w-full rounded-2xl border-2 border-slate-100 bg-slate-50 py-4 pl-12 pr-4 font-bold text-slate-700 focus:border-indigo-600 focus:bg-white focus:ring-0" required />
                  </div>
                </div>
              </div>

              <div class="grid gap-6 md:grid-cols-2">
                <div class="space-y-2">
                  <label class="text-xs font-black uppercase tracking-widest text-slate-400 ml-1">Phone</label>
                  <div class="relative">
                    <Phone class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" />
                    <input v-model="form.guest_phone" type="text" class="w-full rounded-2xl border-2 border-slate-100 bg-slate-50 py-4 pl-12 pr-4 font-bold text-slate-700 focus:border-indigo-600 focus:bg-white focus:ring-0" required />
                  </div>
                </div>

                <div class="space-y-2">
                  <label class="text-xs font-black uppercase tracking-widest text-slate-400 ml-1">Estimated Arrival Time</label>
                  <div class="relative">
                    <Clock3 class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" />
                    <input v-model="form.estimated_arrival_time" type="time" class="w-full rounded-2xl border-2 border-slate-100 bg-slate-50 py-4 pl-12 pr-4 font-bold text-slate-700 focus:border-indigo-600 focus:bg-white focus:ring-0" required />
                  </div>
                </div>
              </div>

              <div class="space-y-2">
                <label class="text-xs font-black uppercase tracking-widest text-slate-400 ml-1">Arrival Notes</label>
                <div class="relative">
                  <MessageSquare class="absolute left-4 top-4 w-5 h-5 text-slate-400" />
                  <textarea v-model="form.arrival_notes" rows="4" class="w-full rounded-2xl border-2 border-slate-100 bg-slate-50 py-4 pl-12 pr-4 font-medium text-slate-700 focus:border-indigo-600 focus:bg-white focus:ring-0" placeholder="Arrival time changes, luggage note, or anything the front desk should know."></textarea>
                </div>
              </div>

              <div class="rounded-[2rem] border border-slate-100 bg-slate-50 p-5 text-sm font-medium text-slate-600">
                This step prepares your arrival only. Identity checks, any remaining hotel verification, and room access are still completed at the front desk.
              </div>

              <button type="submit" :disabled="form.processing" class="w-full rounded-[2rem] bg-slate-900 py-5 text-sm font-black uppercase tracking-widest text-white transition hover:bg-indigo-600 disabled:opacity-50">
                {{ form.processing ? 'Saving...' : 'Complete Pre-Check-In' }}
              </button>
            </form>

            <div v-else class="rounded-[2rem] border border-slate-100 bg-slate-50 p-6 text-sm font-medium text-slate-600">
              This booking can no longer be updated through pre-check-in.
            </div>
          </div>
        </div>
      </div>
    </div>
  </PublicLayout>
</template>
