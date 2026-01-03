<script setup>
import FrontDeskLayout from '@/Layouts/Staff/FrontDeskLayout.vue'
import { ref, onMounted } from 'vue';
import { Link, router, Head } from '@inertiajs/vue3';
import { 
  ArrowLeft, 
  Receipt, 
  CreditCard, 
  History, 
  PlusCircle, 
  Banknote,
  FileText,
  Clock
} from 'lucide-vue-next';

const props = defineProps({
  room: Object,
  billing: Object
});

const form = ref({
  booking_id: props.billing.charges[0]?.booking_id ?? null,
  amount: '',
  method: 'Cash',
  notes: ''
});

onMounted(() => {
  if (window.Echo) {
    window.Echo.channel(`room.${props.room.id}.billing`)
      .listen('.billing.updated', () => {
        router.reload({ preserveScroll: true })
      })
  }
})

function submitPayment() {
  router.post(
    `/frontdesk/rooms/${props.room.id}/billing/pay`,
    form.value,
    { 
      preserveScroll: true,
      onSuccess: () => {
        form.value.amount = '';
        form.value.notes = '';
      }
    }
  );
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('en-GB', {
    day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit'
  });
}
</script>

<template>
  <FrontDeskLayout>
    <Head :title="`Room ${room.number} Billing`" />

    <div class="p-8 max-w-6xl mx-auto space-y-10">
      
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div class="flex items-center gap-4">
          <Link href="/frontdesk/rooms" class="p-2 hover:bg-slate-100 rounded-full transition-colors text-slate-400">
            <ArrowLeft class="w-6 h-6" />
          </Link>
          <div>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Room {{ room.number }}</h1>
            <p class="text-slate-500 font-medium">Guest Statement & Folio Management</p>
          </div>
        </div>

        <div class="px-8 py-4 rounded-[2rem] border bg-white shadow-sm flex flex-col items-end justify-center min-w-[240px]">
          <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Total Outstanding</span>
          <span 
            class="text-3xl font-black" 
            :class="billing.outstanding > 0 ? 'text-rose-600' : 'text-emerald-600'"
          >
            ₦{{ billing.outstanding.toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
          </span>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
        
        <div class="lg:col-span-2 space-y-8">
          
          <div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-50 flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="p-2 bg-slate-100 rounded-xl text-slate-600"><FileText class="w-5 h-5" /></div>
                <h2 class="text-lg font-black text-slate-900">Charges</h2>
              </div>
              <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest bg-slate-50 px-3 py-1 rounded-full">
                {{ billing.charges.length }} Items
              </span>
            </div>
            
            <div class="overflow-x-auto">
              <table class="w-full text-left">
                <thead class="bg-slate-50/50">
                  <tr>
                    <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Description</th>
                    <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Amount</th>
                    <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Date</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-for="charge in billing.charges" :key="charge.id" class="text-sm">
                    <td class="px-6 py-4">
                      <p class="font-bold text-slate-700">{{ charge.description }}</p>
                    </td>
                    <td class="px-6 py-4 text-right font-black text-slate-900 italic">₦{{ charge.amount.toLocaleString() }}</td>
                    <td class="px-6 py-4 text-right text-slate-500 font-medium">
                      <div class="flex items-center justify-end gap-2">
                        <Clock class="w-3 h-3" /> {{ formatDate(charge.created_at) }}
                      </div>
                    </td>
                  </tr>
                  <tr v-if="billing.charges.length === 0">
                    <td colspan="3" class="px-6 py-12 text-center text-slate-400 italic font-medium">No charges recorded for this room.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-50 flex items-center gap-3">
              <div class="p-2 bg-emerald-50 rounded-xl text-emerald-600"><History class="w-5 h-5" /></div>
              <h2 class="text-lg font-black text-slate-900">Transaction History</h2>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-left">
                <thead class="bg-slate-50/50">
                  <tr>
                    <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Method</th>
                    <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Amount</th>
                    <th class="px-6 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Date</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-for="payment in billing.payments" :key="payment.id" class="text-sm">
                    <td class="px-6 py-4">
                      <div class="flex flex-col">
                        <span class="font-bold text-emerald-700 uppercase text-xs tracking-widest">{{ payment.method }}</span>
                        <span class="text-[11px] text-slate-400 italic">{{ payment.notes || 'Payment Received' }}</span>
                      </div>
                    </td>
                    <td class="px-6 py-4 text-right font-black text-emerald-600">₦{{ payment.amount.toLocaleString() }}</td>
                    <td class="px-6 py-4 text-right text-slate-500 font-medium">{{ formatDate(payment.created_at) }}</td>
                  </tr>
                  <tr v-if="billing.payments.length === 0">
                    <td colspan="3" class="px-6 py-12 text-center text-slate-400 italic font-medium">No payment history found.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="space-y-6">
          <div class="bg-slate-900 rounded-[2.5rem] p-8 text-white shadow-2xl shadow-slate-200 sticky top-8">
            <div class="flex items-center gap-3 mb-8">
              <div class="p-2 bg-white/10 rounded-xl text-indigo-400"><PlusCircle class="w-5 h-5" /></div>
              <h2 class="text-xl font-black tracking-tight">Record Payment</h2>
            </div>

            <form @submit.prevent="submitPayment" class="space-y-6">
              <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2 block">Amount to Post</label>
                <div class="relative">
                  <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none font-bold text-slate-500">₦</div>
                  <input 
                    type="number" 
                    v-model.number="form.amount" 
                    step="0.01" 
                    min="0.01" 
                    required 
                    class="block w-full pl-10 pr-4 py-4 bg-white/5 border border-white/10 rounded-2xl focus:bg-white/10 focus:border-indigo-500 focus:ring-0 transition-all font-bold text-white text-lg"
                    placeholder="0.00"
                  />
                </div>
              </div>

              <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2 block">Payment Method</label>
                <select 
                  v-model="form.method" 
                  class="block w-full px-4 py-4 bg-white/5 border border-white/10 rounded-2xl focus:bg-white/10 focus:border-indigo-500 focus:ring-0 transition-all font-bold text-white appearance-none"
                >
                  <option class="text-slate-900">Cash</option>
                  <option class="text-slate-900">Card</option>
                  <option class="text-slate-900">Transfer</option>
                  <option class="text-slate-900">Online</option>
                </select>
              </div>

              <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2 block">Notes / Reference</label>
                <input 
                  type="text" 
                  v-model="form.notes" 
                  placeholder="e.g. POS Transaction Ref" 
                  class="block w-full px-4 py-4 bg-white/5 border border-white/10 rounded-2xl focus:bg-white/10 focus:border-indigo-500 focus:ring-0 transition-all font-bold text-white text-sm"
                />
              </div>

              <button 
                type="submit" 
                class="w-full py-4 bg-indigo-600 hover:bg-indigo-500 text-white rounded-2xl font-black text-sm transition-all shadow-lg shadow-indigo-900/40 flex items-center justify-center gap-2"
              >
                <Banknote class="w-4 h-4" /> Finalize Payment
              </button>
            </form>
          </div>

          <div class="p-6 bg-slate-50 border border-slate-100 rounded-[2rem] flex items-start gap-4">
            <Receipt class="w-5 h-5 text-slate-400 shrink-0 mt-0.5" />
            <p class="text-xs font-bold text-slate-500 leading-relaxed italic">
              Posting a payment updates the room balance immediately via real-time sync.
            </p>
          </div>
        </div>

      </div>
    </div>
  </FrontDeskLayout>
</template>

<style scoped>
/* Custom select styling for the dark theme */
:deep(select) { 
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='white'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E"); 
  background-position: right 1rem center; 
  background-repeat: no-repeat; 
  background-size: 1.5em; 
}
</style>