<template>
  <Modal :show="show" @close="show = false">
    <template #title>
      <h2 class="text-xl font-bold">Laundry Service</h2>
    </template>

    <template #content>
      <form @submit.prevent="submitOrder" class="space-y-4">
        <!-- Laundry Items -->
        <div v-for="item in items" :key="item.id" class="flex items-center space-x-2">
          <div class="flex-1">
            <p class="font-semibold">{{ item.name }} — ₦{{ item.price }}</p>
            <p class="text-sm text-gray-500" v-if="item.description">{{ item.description }}</p>
          </div>
          <input 
            type="number" 
            min="0" 
            v-model.number="quantities[item.id]" 
            class="w-16 border rounded px-2 py-1" 
          />
        </div>

        <!-- Selected Items Summary -->
        <div v-if="selectedItems.length" class="border-t pt-2 text-sm">
          <p class="font-semibold">Selected Items:</p>
          <ul class="list-disc pl-5">
            <li v-for="i in selectedItems" :key="i.id">
              {{ i.quantity }} × {{ i.name }} — ₦{{ i.subtotal }}
            </li>
          </ul>
        </div>

        <!-- Image Upload -->
        <div>
          <label class="block font-semibold">Photos (optional)</label>
          <input type="file" multiple @change="handleFiles" accept="image/*" class="mt-1" />
          <div class="flex mt-2 space-x-2">
            <div v-for="(file, idx) in filePreviews" :key="idx">
              <img :src="file" class="h-20 w-20 object-cover rounded border" />
            </div>
          </div>
        </div>

        <!-- Total -->
        <div class="text-right font-bold mt-2">
          Total: ₦{{ total }}
        </div>

        <!-- Actions -->
        <div class="flex justify-end space-x-2">
          <button type="button" class="btn-secondary" @click="show = false">Cancel</button>
          <button type="submit" class="btn-primary" :disabled="total === 0">Submit</button>
        </div>
      </form>
    </template>
    </Modal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Modal from '@/Components/Modal.vue'

const props = defineProps({
  room: Object,
  booking: Object,
  items: {
    type: Array,
    default: () => []
  },
  accessToken: String,
})

console.log(props.booking.id)

const show = ref(true)
const quantities = ref({})
const files = ref([])
const filePreviews = ref([])

// Initialize quantities
props.items.forEach(i => quantities.value[i.id] = 0)

// Compute total
const total = computed(() =>
  props.items.reduce((sum, i) => sum + (quantities.value[i.id] || 0) * i.price, 0)
)

// Selected items for summary
const selectedItems = computed(() => 
  props.items
    .map(i => ({ ...i, quantity: quantities.value[i.id] || 0, subtotal: (quantities.value[i.id] || 0) * i.price }))
    .filter(i => i.quantity > 0)
)

// Watch files to generate previews
watch(files, () => {
  filePreviews.value = files.value.map(f => URL.createObjectURL(f))
})

function handleFiles(event) {
  files.value = Array.from(event.target.files)
}

function submitOrder() {
  const formData = new FormData()
  
  // Append booking ID
  formData.append('booking_id', props.booking.id) // <-- must include this

  // Append selected items
  for (const id in quantities.value) {
    if (quantities.value[id] > 0) {
      formData.append(`items[${id}][laundry_item_id]`, id)
      formData.append(`items[${id}][quantity]`, quantities.value[id])
    }
  }

  // Append uploaded images
  files.value.forEach(f => formData.append('images[]', f))

  // Send request using the guest token in the URL
  router.post(route('guest.laundry.store', props.accessToken), formData, {
    onSuccess: () => {
      show.value = false // close modal on success
      quantities.value = {} // reset quantities
      files.value = []
      filePreviews.value = []
    },
    onError: (errors) => {
      console.error('Validation errors:', errors)
    }
  })
}

</script>
