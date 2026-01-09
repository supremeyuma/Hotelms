<script setup>
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import LaundryLayout from '@/Layouts/Staff/LaundryLayout.vue';
import LaundryStatusBadge from '@/Components/Laundry/StatusBadge.vue';
import LaundryTimeline from '@/Components/Laundry/Timeline.vue';
import { 
  Camera, 
  ChevronLeft, 
  Ban, 
  ListChecks, 
  History, 
  Image as ImageIcon,
  ArrowUpCircle,
  RefreshCw,
  ChevronDown
} from 'lucide-vue-next';

const props = defineProps({
  order: Object,
  statuses: Array,
});

const openPayModal = ref(false)
const paymentMethod = ref('pos')

function format(date) {
  return new Date(date).toLocaleString([], { 
    month: 'short', 
    day: 'numeric', 
    hour: '2-digit', 
    minute: '2-digit' 
  });
}

function updateStatus(status) {
  if (!status || status === props.order.status) return;
  router.post(route('staff.laundry.updateStatus', props.order.id), { status }, {
    preserveScroll: true
  });
}

function cancelOrder() {
  if (confirm('Are you sure you want to cancel this order? This action cannot be undone.')) {
    router.post(route('staff.laundry.cancel', props.order.id));
  }
}

function uploadImages(e) {
  const data = new FormData();
  [...e.target.files].forEach(f => data.append('images[]', f));
  router.post(route('staff.laundry.addImages', props.order.id), data, {
    forceFormData: true,
    preserveScroll: true
  });
}

function canProcessOrder() {
  if (!props.order.charge) return true

  return !(
    props.order.charge.payment_mode === 'prepaid' &&
    props.order.charge.status === 'unpaid'
  )
}

function markChargePaid() {
  router.post(
    route('staff.charges.markPaid', props.order.charge.id),
    { method: paymentMethod.value },
    {
      preserveScroll: true,
      onSuccess: () => {
        openPayModal.value = false
      }
    }
  )
}

</script>

<template>
  <LaundryLayout>
    <div class="max-w-4xl mx-auto px-4 py-6 md:py-10 space-y-8">
      
      <div class="space-y-4">
        <button 
          @click="router.visit(route('staff.laundry.dashboard'))"
          class="flex items-center gap-2 text-slate-500 font-bold text-sm hover:text-indigo-600 transition-colors group"
        >
          <ChevronLeft class="w-4 h-4 group-hover:-translate-x-1 transition-transform" />
          Back to Dashboard
        </button>

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-[2.5rem] border border-slate-200 shadow-sm">
          <div class="flex items-center gap-4">
            <div class="w-16 h-16 bg-slate-900 rounded-2xl flex flex-col items-center justify-center text-white shrink-0 shadow-lg shadow-slate-200">
              <span class="text-[10px] font-black opacity-50 uppercase tracking-tighter">Room</span>
              <span class="text-xl font-black leading-none">{{ order.room.name }}</span>
            </div>
            <div>
              <h1 class="text-2xl font-black text-slate-900 tracking-tight">{{ order.order_code }}</h1>
              <p class="text-slate-500 text-sm font-medium flex items-center gap-1.5">
                <History class="w-3.5 h-3.5" /> Requested {{ format(order.created_at) }}
              </p>
            </div>
          </div>
          <LaundryStatusBadge :status="order.status" class="scale-110 md:self-center self-start" />
          <!-- PAYMENT STATUS -->
          <div
            v-if="order.charge"
            class="flex items-center gap-2 mt-2"
          >
            <span
              class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border"
              :class="
                order.charge.status === 'paid'
                  ? 'bg-emerald-50 text-emerald-700 border-emerald-100'
                  : order.charge.payment_mode === 'postpaid'
                    ? 'bg-amber-50 text-amber-700 border-amber-100'
                    : 'bg-rose-50 text-rose-700 border-rose-100 animate-pulse'
              "
            >
              <template v-if="order.charge.status === 'paid'">
                Paid
              </template>

              <template v-else-if="order.charge.payment_mode === 'postpaid'">
                Pay on Delivery
              </template>

              <template v-else>
                Awaiting Payment
              </template>
            </span>

            <span class="text-xs text-slate-400 font-bold">
              ₦{{ order.charge.amount }}
            </span>
          </div>

        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-2 space-y-8">
          <section class="bg-white rounded-[2rem] border border-slate-200 p-8 shadow-sm relative overflow-hidden">
            <h2 class="flex items-center gap-2 text-xs font-black text-slate-400 uppercase tracking-[0.2em] mb-6">
              <ArrowUpCircle class="w-4 h-4 text-indigo-500" /> Management Actions
            </h2>
            
            <div class="flex flex-wrap items-center gap-4 relative z-10">
              <div class="relative flex-1 min-w-[240px]">
                <select
                  class="w-full pl-5 pr-12 py-4 bg-slate-50 border-2 border-slate-100 rounded-2xl text-sm font-bold text-slate-700 focus:bg-white focus:border-indigo-600 focus:ring-0 transition-all appearance-none cursor-pointer"
                  @change="updateStatus($event.target.value)"
                >
                  <option disabled selected>Change order status...</option>
                  <option
                    v-for="s in statuses"
                    :key="s"
                    :value="s"
                    :disabled="s === order.status"
                  >
                    {{ s.replace('_', ' ').toUpperCase() }}
                  </option>
                </select>
                <p
                  v-if="!canProcessOrder()"
                  class="text-xs text-rose-600 font-bold mt-2"
                >
                  Payment required before processing
                </p>

                <div class="absolute inset-y-0 right-0 pr-5 flex items-center pointer-events-none text-slate-400">
                  <ChevronDown class="w-5 h-5" />
                </div>
              </div>

              <button
                class="px-8 py-4 bg-rose-50 text-rose-600 rounded-2xl font-bold text-sm hover:bg-rose-100 transition-all flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed active:scale-95"
                @click="cancelOrder"
                :disabled="['cancelled', 'delivered'].includes(order.status)"
              >
                <Ban class="w-4 h-4" />
                Cancel Order
              </button>

              <!-- CHARGE STATUS -->
              <div
                v-if="order.charge"
                class="mt-6 p-6 rounded-[2rem] border flex items-center justify-between"
                :class="
                  order.charge.status === 'paid'
                    ? 'bg-emerald-50 border-emerald-200'
                    : 'bg-amber-50 border-amber-200'
                "
              >
                <div>
                  <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mb-1">
                    Payment Status
                  </p>

                  <p
                    class="text-lg font-black"
                    :class="
                      order.charge.status === 'paid'
                        ? 'text-emerald-700'
                        : 'text-amber-700'
                    "
                  >
                    <template v-if="order.charge.status === 'paid'">
                      Paid
                    </template>

                    <template v-else-if="order.charge.payment_mode === 'postpaid'">
                      Pay on Delivery
                    </template>

                    <template v-else>
                      Awaiting Payment
                    </template>
                  </p>

                  <p class="text-xs text-slate-500 font-medium mt-1">
                    Method: {{ order.charge.payment_mode.replace('_', ' ') }}
                  </p>
                </div>

                <!-- ACTION -->
                <div v-if="order.charge.status === 'unpaid'">
                  <button
                    @click="openPayModal = true"
                    class="px-6 py-3 bg-emerald-600 text-white rounded-xl font-black text-xs uppercase tracking-widest hover:bg-emerald-700 active:scale-95 shadow-lg shadow-emerald-200"
                  >
                    Mark as Paid
                  </button>
                </div>
              </div>

            </div>
          </section>

          <section class="bg-white rounded-[2rem] border border-slate-200 p-8 shadow-sm">
            <h2 class="flex items-center gap-2 text-xs font-black text-slate-400 uppercase tracking-[0.2em] mb-6">
              <ListChecks class="w-4 h-4 text-indigo-500" /> Order Details
            </h2>
            
            <div class="space-y-4">
              <div 
                v-for="item in order.items" 
                :key="item.id"
                class="flex justify-between items-center p-5 bg-slate-50/50 rounded-2xl border border-slate-100 transition-colors hover:bg-slate-50"
              >
                <div class="flex items-center gap-4">
                  <span class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-sm font-black text-slate-900 shadow-sm">
                    {{ item.quantity }}
                  </span>
                  <span class="font-bold text-slate-700 text-lg">{{ item.item.name }}</span>
                </div>
                <span class="font-black text-slate-900 text-lg">₦{{ item.subtotal }}</span>
              </div>
              
              <div class="flex justify-between items-center p-8 bg-slate-900 rounded-[2rem] text-white mt-8 shadow-xl shadow-slate-200">
                <div>
                  <p class="text-[10px] font-black uppercase tracking-[0.2em] opacity-50 mb-1">Total Amount</p>
                  <p class="text-3xl font-black">₦{{ order.total_amount }}</p>
                </div>
                <div class="p-3 bg-white/10 rounded-2xl">
                   <ListChecks class="w-8 h-8 opacity-50" />
                </div>
              </div>
            </div>
          </section>
        </div>

        <div class="space-y-8">
          <section class="bg-white rounded-[2.5rem] border border-slate-200 p-6 shadow-sm">
            <h2 class="flex items-center gap-2 text-xs font-black text-slate-400 uppercase tracking-[0.2em] mb-6">
              <ImageIcon class="w-4 h-4 text-indigo-500" /> Photo Evidence
            </h2>
            
            <div class="grid grid-cols-2 gap-3 mb-6">
              <div
                v-for="img in order.images"
                :key="img.id"
                class="relative aspect-square rounded-2xl overflow-hidden border border-slate-100 group shadow-sm bg-slate-50"
              >
                <img :src="`/storage/${img.path}`" class="h-full w-full object-cover group-hover:scale-110 transition-transform duration-700" />
                <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity"></div>
              </div>
              
              <label class="relative aspect-square rounded-2xl border-2 border-dashed border-slate-200 flex flex-col items-center justify-center cursor-pointer hover:bg-indigo-50 hover:border-indigo-400 hover:text-indigo-600 transition-all text-slate-400 group">
                <Camera class="w-7 h-7 mb-2 group-hover:scale-110 transition-transform" />
                <span class="text-[10px] font-black uppercase tracking-widest">Add Photo</span>
                <input type="file" multiple class="hidden" @change="uploadImages" />
              </label>
            </div>
            <p class="text-[10px] text-slate-400 text-center font-medium italic px-4">Upload condition photos before pickup and after delivery.</p>
          </section>

          <section class="bg-white rounded-[2.5rem] border border-slate-200 p-6 shadow-sm">
            <h2 class="flex items-center gap-2 text-xs font-black text-slate-400 uppercase tracking-[0.2em] mb-6">
              <History class="w-4 h-4 text-indigo-500" /> Activity Log
            </h2>
            <div class="px-1 overflow-hidden">
              <LaundryTimeline :history="order.status_histories" />
            </div>
          </section>
        </div>
      </div>
    </div>

    <!-- MARK AS PAID MODAL -->
    <div
      v-if="openPayModal"
      class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center"
    >
      <div class="bg-white rounded-[2rem] p-8 w-full max-w-sm shadow-xl">
        <h3 class="text-lg font-black mb-4">Confirm Payment</h3>

        <p class="text-sm text-slate-500 mb-6">
          Select payment method used to settle this charge.
        </p>

        <select
          v-model="paymentMethod"
          class="w-full mb-6 px-4 py-3 border border-slate-200 rounded-xl font-bold"
        >
          <option value="cash">Cash</option>
          <option value="pos">POS</option>
          <option value="transfer">Transfer</option>
        </select>

        <div class="flex gap-3">
          <button
            @click="openPayModal = false"
            class="flex-1 py-3 rounded-xl bg-slate-100 font-black text-xs uppercase"
          >
            Cancel
          </button>

          <button
            @click="markChargePaid"
            class="flex-1 py-3 rounded-xl bg-emerald-600 text-white font-black text-xs uppercase"
          >
            Confirm
          </button>
        </div>
      </div>
    </div>

  </LaundryLayout>
</template>

<style scoped>
/* Scannability and mobile touch targets */
select {
  -webkit-tap-highlight-color: transparent;
}

@media (max-width: 640px) {
  .rounded-\[2rem\] {
    border-radius: 1.5rem;
  }
}
</style>