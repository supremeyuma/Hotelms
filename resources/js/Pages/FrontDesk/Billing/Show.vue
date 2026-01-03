<script setup>
import FrontDeskLayout from '@/Layouts/Staff/FrontDeskLayout.vue'
import { ref, computed } from 'vue';
import { router, Head, Link } from '@inertiajs/vue3';
import { 
  Receipt, 
  CreditCard, 
  Wallet, 
  History, 
  PlusCircle, 
  ArrowLeft,
  CheckCircle2,
  AlertCircle,
  Banknote,
  FileText
} from 'lucide-vue-next';

const props = defineProps({
  booking: Object,
  billing: Object,
});

const form = ref({
  amount: '',
  method: 'Cash',
  notes: ''
});

const isOutstanding = computed(() => props.billing.outstanding > 0);

function submitPayment() {
  router.post(`/frontdesk/billing/${props.booking.id}/pay`, form.value, { 
    preserveState: true,
    onSuccess: () => {
        form.value.amount = '';
        form.value.notes = '';
    }
  });
}

function settleFull() {
  if(confirm(`Confirm full settlement of ₦${props.billing.outstanding.toLocaleString()}?`)) {
    router.post(`/frontdesk/billing/${props.booking.id}/settle-full`, {}, { preserveState: true });
  }
}

function formatDate(dateString) {
  return new Date(dateString).toLocaleDateString('en-GB', {
    day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit'
  });
}
</script>

<template>
  <FrontDeskLayout>
    <Head :title="`Billing - ${booking.guest_name}`" />

    <div class="p-8 max-w-6xl mx-auto space-y-8">
      
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
          <Link :href="route('frontdesk.bookings.show', booking.id)" class="p-2 hover:bg-slate-100 rounded-full transition-colors">
            <ArrowLeft class="w-6 h-6 text-slate-400" />
          </Link>
          <div>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Guest Ledger</h1>
            <p class="text-slate-500 font-medium">Account Statement for {{ booking.guest_name }}</p>
          </div>
        </div>

        <div 
          class="px-8 py-4 rounded-[2rem] border flex flex-col items-end justify-center"
          :class="isOutstanding ? 'bg-rose-50 border-rose-100' : 'bg-emerald-50 border-emerald-100'"
        >
          <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Current Outstanding</span>
          <span class="text-3xl font-black" :class="isOutstanding ? 'text-rose-600' : 'text-emerald-600'">
            ₦{{ props.billing.outstanding.toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
          </span>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-2 space-y-8">
          
          <div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-50 flex items-center gap-3">
              <div class="p-2 bg-slate-100 rounded-xl text-slate-600"><FileText class="w-5 h-5" /></div>
              <h2 class="text-lg font-black text-slate-900">Service Charges</h2>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-left">
                <thead class="bg-slate-50/50">
                  <tr>
                    <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Description</th>
                    <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Date</th>
                    <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Amount</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-for="charge in billing.charges" :key="charge.id" class="text-sm">
                    <td class="px-6 py-4 font-bold text-slate-700">{{ charge.description }}</td>
                    <td class="px-6 py-4 text-slate-500">{{ formatDate(charge.created_at) }}</td>
                    <td class="px-6 py-4 text-right font-black text-slate-900">₦{{ charge.amount.toLocaleString() }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-50 flex items-center gap-3">
              <div class="p-2 bg-emerald-50 rounded-xl text-emerald-600"><History class="w-5 h-5" /></div>
              <h2 class="text-lg font-black text-slate-900">Payment History</h2>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-left">
                <thead class="bg-slate-50/50">
                  <tr>
                    <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Method</th>
                    <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Notes</th>
                    <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Date</th>
                    <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Amount</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-for="payment in billing.payments" :key="payment.id" class="text-sm">
                    <td class="px-6 py-4">
                      <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-bold text-xs">
                        <CheckCircle2 class="w-3 h-3" /> {{ payment.method }}
                      </span>
                    </td>
                    <td class="px-6 py-4 text-slate-500 italic">{{ payment.notes || '—' }}</td>
                    <td class="px-6 py-4 text-slate-500">{{ formatDate(payment.created_at) }}</td>
                    <td class="px-6 py-4 text-right font-black text-emerald-600">₦{{ payment.amount.toLocaleString() }}</td>
                  </tr>
                  <tr v-if="billing.payments.length === 0">
                    <td colspan="4" class="px-6 py-12 text-center text-slate-400 font-medium">No payments recorded yet.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="space-y-6">
          <div class="bg-slate-900 rounded-[2.5rem] p-8 text-white shadow-xl shadow-slate-200">
            <div class="flex items-center gap-3 mb-8">
              <div class="p-2 bg-white/10 rounded-xl"><PlusCircle class="w-5 h-5 text-indigo-400" /></div>
              <h2 class="text-xl font-black tracking-tight">Record Payment</h2>
            </div>

            <form @submit.prevent="submitPayment" class="space-y-5">
              <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2 block">Amount to Collect</label>
                <div class="relative group">
                   <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none font-bold text-slate-500">₦</div>
                   <input 
                    type="number" 
                    v-model="form.amount" 
                    min="0.01" 
                    step="0.01" 
                    required 
                    class="block w-full pl-10 pr-4 py-4 bg-white/5 border border-white/10 rounded-2xl focus:bg-white/10 focus:border-indigo-500 focus:ring-0 transition-all font-bold text-white shadow-none"
                    placeholder="0.00"
                  />
                </div>
              </div>

              <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2 block">Payment Method</label>
                <select v-model="form.method" required class="block w-full px-4 py-4 bg-white/5 border border-white/10 rounded-2xl focus:bg-white/10 focus:border-indigo-500 focus:ring-0 transition-all font-bold text-white shadow-none appearance-none">
                  <option class="text-slate-900">Cash</option>
                  <option class="text-slate-900">Card</option>
                  <option class="text-slate-900">Online Transfer</option>
                  <option class="text-slate-900">POS</option>
                </select>
              </div>

              <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2 block">Reference / Notes</label>
                <input type="text" v-model="form.notes" placeholder="Transaction ref or remarks" class="block w-full px-4 py-4 bg-white/5 border border-white/10 rounded-2xl focus:bg-white/10 focus:border-indigo-500 focus:ring-0 transition-all font-bold text-white shadow-none" />
              </div>

              <div class="pt-4 space-y-3">
                <button type="submit" class="w-full py-4 bg-indigo-600 hover:bg-indigo-500 text-white rounded-2xl font-black text-sm transition-all shadow-lg shadow-indigo-900/20 flex items-center justify-center gap-2">
                  <Banknote class="w-4 h-4" /> Record Payment
                </button>
                
                <button 
                  v-if="isOutstanding"
                  type="button" 
                  @click="settleFull" 
                  class="w-full py-4 bg-white/5 hover:bg-emerald-600/20 border border-white/10 text-emerald-400 rounded-2xl font-black text-sm transition-all flex items-center justify-center gap-2"
                >
                  <CreditCard class="w-4 h-4" /> Settle Full Balance
                </button>
              </div>
            </form>
          </div>

          <div class="bg-amber-50 rounded-[2rem] p-6 border border-amber-100 flex items-start gap-4">
            <AlertCircle class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
            <p class="text-xs font-bold text-amber-800 leading-relaxed">
              Ensure all restaurant and laundry charges are posted before final settlement to avoid folio discrepancies.
            </p>
          </div>
        </div>
      </div>
    </div>
  </FrontDeskLayout>
</template>

<style scoped>
/* Standard boutique form fixes */
:deep(select) { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='white'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E"); background-position: right 1rem center; background-repeat: no-repeat; background-size: 1.5em; }
</style>