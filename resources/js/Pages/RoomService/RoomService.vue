<template>
  <GuestLayout>
    <div class="max-w-4xl mx-auto">
      <h2 class="text-2xl mb-4">Room Service — Room {{ room.name }}</h2>

      <div class="mb-4">
        <Tabs :tabs="tabs" v-model:active="activeTab" />
      </div>

      <div>
        <div v-if="activeTab === 'kitchen'">
          <OrderForm section="Kitchen" @placed="onPlaced"/>
        </div>
        <div v-if="activeTab === 'laundry'">
          <OrderForm section="Laundry" @placed="onPlaced"/>
        </div>
        <div v-if="activeTab === 'housekeeping'">
          <OrderForm section="Housekeeping" @placed="onPlaced"/>
        </div>
        <div v-if="activeTab === 'maintenance'">
          <OrderForm section="Maintenance" @placed="onPlaced"/>
        </div>
      </div>
    </div>
  </GuestLayout>
</template>

<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { ref } from 'vue';
//import Tabs from '@/Components/Ui/Tabs.vue';
//import OrderForm from '@/Pages/RoomService/Components/OrderForm.vue'; // small order form component

const props = usePage().props;
const room = props.room;

// Tabs with friendly labels
const tabs = [
  { key: 'kitchen', label: 'Kitchen' },
  { key: 'laundry', label: 'Laundry' },
  { key: 'housekeeping', label: 'Housekeeping' },
  { key: 'maintenance', label: 'Maintenance' },
];

const activeTab = ref('kitchen');

function onPlaced(payload) {
  // called when order is placed
  // payload = { order }
  window.flash = window.flash || {};
  // could use useToast composable here
  alert('Order placed successfully');
}
</script>
