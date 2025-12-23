<template>
  <div class="p-4 border rounded flex justify-between items-center">
    <span class="font-semibold">Outstanding Balance:</span>
    <span class="text-lg font-bold">₦{{ total }}</span>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
  accessToken: String
});

const total = ref(0);

async function fetchBill() {
  try {
    const response = await axios.get(`/guest/room/${props.accessToken}/bill-history`);
    const charges = response.data.billHistory.filter(h => h.type === 'charge')
      .reduce((sum, h) => sum + parseFloat(h.amount), 0);
    const payments = response.data.billHistory.filter(h => h.type === 'payment')
      .reduce((sum, h) => sum + parseFloat(h.amount), 0);
    total.value = charges - payments;
  } catch (error) {
    console.error(error);
  }
}

onMounted(() => {
  fetchBill();
  setInterval(fetchBill, 50000); // refresh every 5s
});
</script>
