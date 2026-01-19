<script setup>
import { useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { 
  CalendarDays, 
  Users, 
  Baby, 
  Search, 
  ArrowRight,
  AlertCircle,
  RefreshCw
} from 'lucide-vue-next';

const form = useForm({
  check_in: '',
  check_out: '',
  adults: 1,
  children: 0,
});

function submit() {
  form.get(route('booking.rooms'), {
    preserveState: true,
    onError: (errors) => {
      console.log("Validation Failed:", errors);
    }
  });
}
</script>

<template>
  <PublicLayout>
    <div class="min-h-[80vh] flex items-center justify-center px-4 py-12 bg-slate-50">
      <div class="w-full max-w-xl">
        <div class="text-center mb-10">
          <div class="inline-flex p-3 bg-indigo-600 rounded-2xl text-white shadow-xl shadow-indigo-100 mb-4">
            <CalendarDays class="w-8 h-8" />
          </div>
          <h1 class="text-4xl font-black text-slate-900 tracking-tight mb-2">Book Your Stay</h1>
          <p class="text-slate-500 font-medium">Experience luxury tailored to your schedule</p>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-xl shadow-slate-200/60 border border-slate-100 overflow-hidden">
          <form @submit.prevent="submit" class="p-8 md:p-10 space-y-8">
            
            <transition enter-active-class="animate-in fade-in slide-in-from-top-4">
              <div v-if="form.errors.availability" class="flex items-center gap-3 p-4 bg-rose-50 border border-rose-100 text-rose-700 rounded-2xl">
                <AlertCircle class="w-5 h-5 shrink-0" />
                <p class="text-sm font-bold">{{ form.errors.availability }}</p>
              </div>
            </transition>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="space-y-2">
                <label class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] ml-1">Check-in</label>
                <div class="relative group">
                  <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-600 transition-colors">
                    <CalendarDays class="w-5 h-5" />
                  </div>
                  <input 
                    type="date" 
                    v-model="form.check_in" 
                    required 
                    class="block w-full pl-12 pr-4 py-4 bg-slate-50 border-2 border-slate-50 rounded-2xl focus:bg-white focus:border-indigo-600 focus:ring-0 transition-all font-bold text-slate-700"
                  />
                </div>
              </div>

              <div class="space-y-2">
                <label class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] ml-1">Check-out</label>
                <div class="relative group">
                  <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-600 transition-colors">
                    <CalendarDays class="w-5 h-5" />
                  </div>
                  <input 
                    type="date" 
                    v-model="form.check_out" 
                    required 
                    class="block w-full pl-12 pr-4 py-4 bg-slate-50 border-2 border-slate-50 rounded-2xl focus:bg-white focus:border-indigo-600 focus:ring-0 transition-all font-bold text-slate-700"
                  />
                </div>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="space-y-2">
                <label class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] ml-1">Adults</label>
                <div class="relative group">
                  <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-600 transition-colors">
                    <Users class="w-5 h-5" />
                  </div>
                  <input 
                    type="number" 
                    v-model="form.adults" 
                    min="1" 
                    required 
                    class="block w-full pl-12 pr-4 py-4 bg-slate-50 border-2 border-slate-50 rounded-2xl focus:bg-white focus:border-indigo-600 focus:ring-0 transition-all font-bold text-slate-700"
                  />
                </div>
              </div>

              <div class="space-y-2">
                <label class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] ml-1">Children</label>
                <div class="relative group">
                  <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-600 transition-colors">
                    <Baby class="w-5 h-5" />
                  </div>
                  <input 
                    type="number" 
                    v-model="form.children" 
                    min="0" 
                    class="block w-full pl-12 pr-4 py-4 bg-slate-50 border-2 border-slate-50 rounded-2xl focus:bg-white focus:border-indigo-600 focus:ring-0 transition-all font-bold text-slate-700"
                  />
                </div>
              </div>
            </div>

            <button 
              type="submit" 
              :disabled="form.processing"
              class="w-full group flex items-center justify-center gap-3 py-5 bg-slate-900 text-white rounded-[2rem] font-black text-lg hover:bg-indigo-600 transition-all shadow-xl shadow-slate-200 disabled:opacity-50 active:scale-[0.98]"
            >
              <Search v-if="!form.processing" class="w-5 h-5" />
              <RefreshCw v-else class="w-5 h-5 animate-spin" />
              {{ form.processing ? 'Searching...' : 'Search Available Rooms' }}
              <ArrowRight class="w-5 h-5 group-hover:translate-x-1 transition-transform" />
            </button>
          </form>
          
          <div class="h-2 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500"></div>
        </div>

        <p class="text-center mt-8 text-slate-400 text-sm font-medium">
          Best price guarantee when booking directly with us.
        </p>
      </div>
    </div>
  </PublicLayout>
</template>

<style scoped>
/* Custom styling for date inputs to look consistent across browsers */
input[type="date"]::-webkit-calendar-picker-indicator {
  cursor: pointer;
  opacity: 0;
  position: absolute;
  right: 1rem;
  width: 100%;
}
</style>