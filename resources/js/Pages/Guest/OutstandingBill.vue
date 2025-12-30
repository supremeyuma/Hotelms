<template>
  <div class="p-4 border rounded space-y-3">
    <div class="flex justify-between items-center">
      <span class="font-semibold">Outstanding Balance</span>
      <span class="text-lg font-bold">₦{{ outstanding.toLocaleString() }}</span>
    </div>

    <button
      v-if="outstanding > 0"
      @click="mockPay"
      class="w-full bg-green-600 text-white py-2 rounded hover:bg-green-700"
    >
      Pay ₦{{ outstanding.toLocaleString() }} (Mock)
    </button>

    <p v-else class="text-green-600 font-semibold">
      Fully Paid ✔
    </p>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  accessToken: { type: String, required: true }
})
//console.log(props)

const outstanding = ref(0)

async function fetchBill() {
  const { data } = await axios.get(
    `/guest/room/${props.accessToken}/bill-history`
  )
  outstanding.value = data.outstanding
}

async function mockPay() {
  await axios.post(`/guest/room/${props.accessToken}/payment`, {
    amount: outstanding.value
  })

  await fetchBill()
}

onMounted(() => {
  if (props.accessToken) {
    fetchBill()
  } else {
    console.error("No token provided to OutstandingBill component")
  }
})
</script>
