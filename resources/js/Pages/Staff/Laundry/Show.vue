<script setup>
import { router } from '@inertiajs/vue3';
import LaundryStatusBadge from '@/Components/Laundry/StatusBadge.vue';
import LaundryTimeline from '@/Components/Laundry/Timeline.vue';

const props = defineProps({
  order: Object,
  statuses: Array, // ['requested', 'washing', ...]
});

//console.log(props.order.status_histories);

function format(date) {
  return new Date(date).toLocaleString();
}

function updateStatus(status) {
  if (!status || status === props.order.status) return;
  router.post(route('staff.laundry.updateStatus', props.order.id), { status });
}

function cancelOrder() {
  if (confirm('Cancel this order?')) {
    router.post(route('staff.laundry.cancel', props.order.id));
  }
}

function uploadImages(e) {
  const data = new FormData();
  [...e.target.files].forEach(f => data.append('images[]', f));
  router.post(route('staff.laundry.addImages', props.order.id), data);
}
</script>

<template>
  <div class="p-6 space-y-8 max-w-5xl mx-auto">
    <!-- HEADER -->
    <div class="flex justify-between items-start">
      <div>
        <h1 class="text-2xl font-bold">{{ order.order_code }}</h1>
        <p class="text-gray-500">
          Room {{ order.room.room_number }} · Requested {{ format(order.created_at) }}
        </p>
      </div>

      <LaundryStatusBadge :status="order.status" />
    </div>

    <!-- STATUS ACTIONS (STAFF ONLY) -->
    <div class="flex items-center gap-4">
      <select
        class="border rounded px-3 py-2"
        @change="updateStatus($event.target.value)"
      >
        <option disabled selected>Update status</option>

        <option
          v-for="s in statuses"
          :key="s"
          :value="s"
          :disabled="s === order.status"
        >
          {{ s.replace('_', ' ') }}
        </option>
      </select>

      <button
        class="btn-danger"
        @click="cancelOrder"
        :disabled="order.status === 'cancelled'"
      >
        Cancel Order
      </button>
    </div>

    <!-- ITEMS -->
    <div>
      <h2 class="font-semibold mb-2">Items</h2>
      <ul class="list-disc pl-6">
        <li v-for="item in order.items" :key="item.id">
          {{ item.quantity }} × {{ item.item.name }} — ₦{{ item.subtotal }}
        </li>
      </ul>
    </div>

    <!-- IMAGES -->
    <div>
      <h2 class="font-semibold mb-2">Uploaded Images</h2>
      <div class="flex gap-3 flex-wrap">
        <img
          v-for="img in order.images"
          :key="img.id"
          :src="`/storage/${img.path}`"
          class="h-28 w-28 object-cover rounded border"
        />
      </div>

      <input
        type="file"
        multiple
        class="mt-3"
        @change="uploadImages"
      />
    </div>

    <!-- TIMELINE -->
    <div>
      <h2 class="font-semibold mb-4">Order Timeline</h2>
      <LaundryTimeline :history="order.status_histories" />
    </div>
  </div>
</template>
