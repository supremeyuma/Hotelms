<template>
  <div class="space-y-4">
    <div class="flex flex-col">
      <div class="flex items-baseline gap-1">
        <span class="text-3xl font-black tracking-tight text-slate-900">
          ₦{{ outstanding.toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
        </span>
      </div>
      
      <div v-if="outstanding === 0" class="flex items-center gap-1.5 mt-1 text-emerald-600 font-black text-[10px] uppercase tracking-widest">
        <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
        Account Settled
      </div>
      <div v-else class="flex items-center gap-1.5 mt-1 text-rose-500 font-black text-[10px] uppercase tracking-widest">
        <div class="w-1.5 h-1.5 rounded-full bg-rose-500"></div>
        Balance Due
      </div>
    </div>

    <button
      v-if="outstanding > 0 && showPayButton"
      @click="mockPay"
      class="group w-full bg-slate-900 text-white p-4 rounded-2xl font-black text-xs uppercase tracking-[0.2em] hover:bg-indigo-600 transition-all shadow-xl shadow-slate-200 flex items-center justify-center gap-3 active:scale-[0.98]"
    >
      <span>Settle Balance</span>
      <div class="w-5 h-5 bg-white/10 rounded-lg flex items-center justify-center group-hover:bg-white/20">
         <ChevronRight class="w-3 h-3 text-white" />
      </div>
    </button>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { ChevronRight } from 'lucide-vue-next'

const props = defineProps({
  accessToken: { type: String, required: true },
  showPayButton: { type: Boolean, default: false } // Controlled by parent
})

const outstanding = ref(0)

async function fetchBill() {
  try {
    const { data } = await axios.get(
      `/guest/room/${props.accessToken}/bill-history`
    )
    outstanding.value = Number(data.outstanding)
  } catch (e) {
    console.error("Failed to fetch bill status")
  }
}

async function mockPay() {
  try {
    await axios.post(`/guest/room/${props.accessToken}/payment`, {
      amount: outstanding.value,
      method: 'Online'
    })
    await fetchBill()
    // Refresh the whole page state if needed
    window.location.reload() 
  } catch (e) {
    alert("Payment processing failed. Please contact the front desk.")
  }
}

onMounted(() => {
  if (props.accessToken) {
    fetchBill()
  }
})
</script>