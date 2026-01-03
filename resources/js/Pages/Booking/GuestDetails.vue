<script setup>
import { defineProps } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { 
  User, 
  Mail, 
  Phone, 
  MessageSquare, 
  ArrowRight, 
  ShieldCheck,
  ClipboardList
} from 'lucide-vue-next';

const props = defineProps({
  booking: Object,
});

const form = useForm({
  guest_name: props.booking.guest_name || '',
  guest_email: props.booking.guest_email || '',
  guest_phone: props.booking.guest_phone || '',
  special_requests: props.booking.special_requests || '',
});

function submit() {
  router.post('/booking/guest', form, {
    preserveScroll: true,
  });
}
</script>

<template>
  <GuestLayout>
    <div class="min-h-screen bg-slate-50/50 py-12 px-4">
      <div class="max-w-2xl mx-auto">
        
        <div class="flex items-center justify-center gap-4 mb-10">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center shadow-lg shadow-emerald-100">
              <CheckCircle2 class="w-5 h-5" v-if="false" /> <span class="text-xs font-black">1</span>
            </div>
            <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Select Room</span>
          </div>
          <div class="w-12 h-px bg-slate-200"></div>
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center shadow-lg shadow-indigo-100">
              <span class="text-xs font-black">2</span>
            </div>
            <span class="text-xs font-bold text-slate-900 uppercase tracking-widest">Guest Info</span>
          </div>
          <div class="w-12 h-px bg-slate-200"></div>
          <div class="flex items-center gap-2 opacity-40">
            <div class="w-8 h-8 rounded-full bg-slate-300 text-white flex items-center justify-center">
              <span class="text-xs font-black">3</span>
            </div>
            <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Review</span>
          </div>
        </div>

        <div class="text-center mb-10">
          <h1 class="text-3xl font-black text-slate-900 tracking-tight mb-3">Who's Staying?</h1>
          <p class="text-slate-500 font-medium">Please provide your contact details for the reservation.</p>
        </div>

        <div class="bg-white rounded-[2.5rem] border border-slate-200 shadow-xl shadow-slate-200/50 overflow-hidden">
          <form @submit.prevent="submit" class="p-8 md:p-12 space-y-8">
            
            <div class="space-y-6">
              <div class="space-y-2">
                <label class="text-xs font-black text-slate-400 uppercase tracking-widest ml-1">Full Name</label>
                <div class="relative group">
                  <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-600 transition-colors">
                    <User class="w-5 h-5" />
                  </div>
                  <input
                    type="text"
                    v-model="form.guest_name"
                    required
                    placeholder="John Doe"
                    class="block w-full pl-12 pr-5 py-4 bg-slate-50 border-2 border-slate-50 rounded-2xl focus:bg-white focus:border-indigo-600 focus:ring-0 transition-all font-bold text-slate-700"
                  />
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                  <label class="text-xs font-black text-slate-400 uppercase tracking-widest ml-1">Email Address</label>
                  <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-600 transition-colors">
                      <Mail class="w-5 h-5" />
                    </div>
                    <input
                      type="email"
                      v-model="form.guest_email"
                      required
                      placeholder="john@example.com"
                      class="block w-full pl-12 pr-5 py-4 bg-slate-50 border-2 border-slate-50 rounded-2xl focus:bg-white focus:border-indigo-600 focus:ring-0 transition-all font-bold text-slate-700"
                    />
                  </div>
                </div>

                <div class="space-y-2">
                  <label class="text-xs font-black text-slate-400 uppercase tracking-widest ml-1">Phone Number</label>
                  <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-600 transition-colors">
                      <Phone class="w-5 h-5" />
                    </div>
                    <input
                      type="text"
                      v-model="form.guest_phone"
                      required
                      placeholder="+234 ..."
                      class="block w-full pl-12 pr-5 py-4 bg-slate-50 border-2 border-slate-50 rounded-2xl focus:bg-white focus:border-indigo-600 focus:ring-0 transition-all font-bold text-slate-700"
                    />
                  </div>
                </div>
              </div>

              <div class="space-y-2">
                <label class="text-xs font-black text-slate-400 uppercase tracking-widest ml-1 flex items-center justify-between">
                  Special Requests
                  <span class="text-[10px] text-slate-300 normal-case italic">Optional</span>
                </label>
                <div class="relative group">
                  <div class="absolute top-4 left-5 pointer-events-none text-slate-400 group-focus-within:text-indigo-600 transition-colors">
                    <MessageSquare class="w-5 h-5" />
                  </div>
                  <textarea
                    v-model="form.special_requests"
                    rows="4"
                    placeholder="Late arrival, extra towels, airport pickup..."
                    class="block w-full pl-12 pr-5 py-4 bg-slate-50 border-2 border-slate-50 rounded-2xl focus:bg-white focus:border-indigo-600 focus:ring-0 transition-all font-medium text-slate-700"
                  ></textarea>
                </div>
              </div>
            </div>

            <div class="pt-6 border-t border-slate-100">
              <button
                type="submit"
                class="w-full group flex items-center justify-center gap-3 py-5 bg-slate-900 text-white rounded-3xl font-black text-lg hover:bg-indigo-600 transition-all shadow-xl shadow-slate-200 active:scale-[0.98]"
              >
                Continue to Review
                <ArrowRight class="w-5 h-5 group-hover:translate-x-1 transition-transform" />
              </button>
              
              <div class="flex items-center justify-center gap-4 mt-6 text-slate-400">
                <div class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest">
                  <ShieldCheck class="w-4 h-4 text-emerald-500" /> Secure Encryption
                </div>
                <div class="w-1.5 h-1.5 rounded-full bg-slate-200"></div>
                <div class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest">
                  <ClipboardList class="w-4 h-4 text-indigo-400" /> Instant Confirmation
                </div>
              </div>
            </div>
          </form>
        </div>
        
        <button 
          @click="window.history.back()" 
          class="mt-8 mx-auto flex items-center gap-2 text-slate-400 font-bold text-sm hover:text-slate-600 transition-colors"
        >
          Change room selection
        </button>
      </div>
    </div>
  </GuestLayout>
</template>

<style scoped>
/* Standardize focus and spacing */
textarea {
  resize: none;
}
</style>