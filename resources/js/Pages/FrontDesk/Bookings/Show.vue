<script setup>
import { computed } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import FrontDeskLayout from '@/Layouts/Staff/FrontDeskLayout.vue'
import StatusBadge from '@/Components/FrontDesk/StatusBadge.vue'
import { 
  User, 
  Calendar, 
  CreditCard, 
  BedDouble, 
  ChevronLeft, 
  Clock, 
  Plus, 
  AlertCircle,
  Receipt,
  LogOut,
  CheckCircle2,
  CalendarPlus
} from 'lucide-vue-next'

const props = defineProps({
  booking: Object,
})

/* ---------------- Computed ---------------- */

const totalCharges = computed(() =>
  Math.max(
    props.booking.charges.reduce((sum, c) => sum + Number(c.amount), 0),
    Number(props.booking.total_amount || 0)
  )
)

const totalPayments = computed(() =>
  props.booking.payments.reduce((sum, p) => sum + Number(p.amount_paid ?? p.amount), 0)
)

const balanceDue = computed(() => Math.max(totalCharges.value - totalPayments.value, 0))

/* ---------------- Actions ---------------- */

const isInHouse = computed(() =>
  ['active', 'checked_in'].includes(props.booking.status)
)

function checkIn() {
  router.post(route('frontdesk.bookings.checkIn', props.booking.id))
}

function checkOut() {
  if (balanceDue.value > 0) {
    alert('Outstanding balance must be cleared before checkout.')
    return
  }
  router.post(route('frontdesk.bookings.checkOut', props.booking.id))
}

function extendStay() {
  const newDate = prompt('Enter new checkout date (YYYY-MM-DD)')
  if (!newDate) return

  router.post(route('frontdesk.bookings.extendStay', props.booking.id), {
    new_checkout: newDate,
  })
}

function formatDate(dateString) {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleDateString('en-GB', {
    day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
  })
}
</script>

<template>
  <FrontDeskLayout>
    <Head :title="`Booking ${booking.booking_code}`" />

    <div class="p-8 max-w-7xl mx-auto space-y-8">
      
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div class="flex items-center gap-4">
          <Link 
            :href="route('frontdesk.bookings.index')" 
            class="p-2 hover:bg-slate-100 rounded-xl transition-colors text-slate-400 hover:text-slate-900"
          >
            <ChevronLeft class="w-6 h-6" />
          </Link>
          <div>
            <div class="flex items-center gap-3">
              <h1 class="text-3xl font-black text-slate-900 tracking-tight">
                Folio #{{ booking.booking_code }}
              </h1>
              <StatusBadge :status="booking.status" />
            </div>
            <p class="text-slate-500 font-medium">Created on {{ formatDate(booking.created_at) }}</p>
          </div>
        </div>

        <div class="flex flex-wrap gap-3">
          <button
            v-if="booking.status === 'confirmed'"
            @click="checkIn"
            class="flex items-center gap-2 px-6 py-3 bg-emerald-600 text-white rounded-2xl font-black hover:bg-emerald-700 transition-all shadow-lg shadow-emerald-100"
          >
            <CheckCircle2 class="w-5 h-5" /> Check In Guest
          </button>

          <button
            v-if="isInHouse"
            @click="extendStay"
            class="flex items-center gap-2 px-6 py-3 bg-amber-100 text-amber-700 rounded-2xl font-black hover:bg-amber-200 transition-all"
          >
            <CalendarPlus class="w-5 h-5" /> Extend Stay
          </button>

          <button
            v-if="isInHouse"
            @click="checkOut"
            class="flex items-center gap-2 px-6 py-3 bg-rose-600 text-white rounded-2xl font-black hover:bg-rose-700 transition-all shadow-lg shadow-rose-100"
          >
            <LogOut class="w-5 h-5" /> Finalize Checkout
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-2 space-y-8">
          
          <div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm p-8">
            <div class="flex items-center gap-3 mb-6">
              <div class="p-2 bg-indigo-50 text-indigo-600 rounded-xl"><User class="w-5 h-5" /></div>
              <h2 class="text-xl font-black text-slate-900">Guest Information</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
              <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Full Name</p>
                <p class="text-lg font-bold text-slate-800">{{ booking.guest_name }}</p>
              </div>
              <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Contact Details</p>
                <p class="font-bold text-slate-800">{{ booking.guest_email ?? 'No email provided' }}</p>
                <p class="text-slate-500 font-medium">{{ booking.guest_phone ?? 'No phone' }}</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm p-8">
             <div class="flex items-center gap-3 mb-6">
              <div class="p-2 bg-amber-50 text-amber-600 rounded-xl"><Calendar class="w-5 h-5" /></div>
              <h2 class="text-xl font-black text-slate-900">Stay Timeline</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div class="p-5 bg-slate-50 rounded-3xl border border-slate-100">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Check-In</p>
                <p class="text-lg font-black text-slate-800">{{ booking.check_in }}</p>
              </div>
              <div class="p-5 bg-slate-50 rounded-3xl border border-slate-100">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Check-Out</p>
                <p class="text-lg font-black text-slate-800">{{ booking.check_out }}</p>
              </div>
              <div class="p-5 bg-indigo-900 text-white rounded-3xl shadow-xl shadow-indigo-100">
                <p class="text-[10px] font-black text-indigo-300 uppercase tracking-widest mb-2">Inventory</p>
                <p class="text-lg font-black">{{ booking.checked_in_rooms_count }} Rooms Occupied</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden">
            <div class="p-8 border-b border-slate-50 flex justify-between items-center">
              <div class="flex items-center gap-3">
                <div class="p-2 bg-emerald-50 text-emerald-600 rounded-xl"><BedDouble class="w-5 h-5" /></div>
                <h2 class="text-xl font-black text-slate-900">Room Allocation</h2>
              </div>
            </div>
            <table class="w-full text-left">
              <thead class="bg-slate-50/50">
                <tr>
                  <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Room Number</th>
                  <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">In</th>
                  <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Out</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <tr v-for="room in booking.rooms" :key="room.id" class="hover:bg-slate-50/50 transition-colors">
                  <td class="px-8 py-5">
                    <div class="flex items-center gap-3">
                      <span class="w-10 h-10 bg-slate-100 flex items-center justify-center rounded-xl font-black text-slate-700">
                        {{ room.number }}
                      </span>
                      <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">{{ room.type?.name }}</span>
                    </div>
                  </td>
                  <td class="px-8 py-5 text-center text-sm font-bold text-slate-600">
                    {{ room.pivot.checked_in_at ? formatDate(room.pivot.checked_in_at) : '—' }}
                  </td>
                  <td class="px-8 py-5 text-center text-sm font-bold text-slate-600">
                    {{ room.pivot.checked_out_at ? formatDate(room.pivot.checked_out_at) : '—' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="space-y-8">
          
          <div class="bg-slate-900 rounded-[2.5rem] p-8 text-white shadow-2xl shadow-slate-200">
            <div class="flex items-center gap-3 mb-8">
              <Receipt class="w-6 h-6 text-indigo-400" />
              <h2 class="text-xl font-black tracking-tight">Financial Folio</h2>
            </div>
            
            <div class="space-y-4 mb-8">
              <div class="flex justify-between text-slate-400 text-sm font-medium">
                <span>Total Charges</span>
                <span class="text-white">₦{{ totalCharges.toLocaleString() }}</span>
              </div>
              <div class="flex justify-between text-slate-400 text-sm font-medium">
                <span>Total Payments</span>
                <span class="text-emerald-400">₦{{ totalPayments.toLocaleString() }}</span>
              </div>
              <div class="h-px bg-white/10 my-4"></div>
              <div class="flex justify-between items-end">
                <span class="text-xs font-black uppercase tracking-[0.2em] text-slate-400">Balance Due</span>
                <span class="text-3xl font-black" :class="balanceDue > 0 ? 'text-rose-400' : 'text-emerald-400'">
                  ₦{{ balanceDue.toLocaleString() }}
                </span>
              </div>
            </div>

            <div v-if="balanceDue > 0" class="flex items-start gap-3 p-4 bg-rose-500/10 border border-rose-500/20 rounded-2xl">
              <AlertCircle class="w-5 h-5 text-rose-400 shrink-0" />
              <p class="text-xs font-bold text-rose-200">Payment must be settled before the guest can be checked out.</p>
            </div>
          </div>

          <div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm p-8 space-y-8">
            <div>
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                  <Plus class="w-3 h-3" /> Recent Charges
                </h3>
                <button class="text-xs font-black text-indigo-600 hover:underline">Add Charge</button>
              </div>
              <div class="space-y-3">
                <div v-for="charge in booking.charges" :key="charge.id" class="flex justify-between items-center text-sm">
                  <span class="font-bold text-slate-700">{{ charge.description }}</span>
                  <span class="font-black text-slate-900">₦{{ Number(charge.amount).toLocaleString() }}</span>
                </div>
              </div>
            </div>

            <div>
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                  <CreditCard class="w-3 h-3" /> Payment History
                </h3>
                <button class="text-xs font-black text-indigo-600 hover:underline">New Payment</button>
              </div>
              <div class="space-y-3">
                <div v-for="payment in booking.payments" :key="payment.id" class="flex justify-between items-center text-sm">
                  <div class="flex flex-col">
                    <span class="font-bold text-slate-700">{{ payment.method }}</span>
                    <span class="text-[10px] text-slate-400 uppercase font-black tracking-tighter">Verified</span>
                  </div>
                  <span class="font-black text-emerald-600">₦{{ Number(payment.amount).toLocaleString() }}</span>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </FrontDeskLayout>
</template>
