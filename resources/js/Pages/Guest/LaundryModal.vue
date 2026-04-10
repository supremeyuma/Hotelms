<template>
  <Modal :show="show" @close="$emit('close')">
    <template #title>
      <div class="flex items-center gap-3">
        <div class="p-2 bg-purple-50 text-purple-600 rounded-xl">
          <WashingMachine class="w-6 h-6" />
        </div>
        <div>
          <h2 class="text-xl font-black text-slate-900 tracking-tight">Laundry Service</h2>
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Professional Care</p>
        </div>
      </div>
    </template>

    <template #content>
      <div
        v-if="successMessage"
        class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl p-4 text-sm font-bold"
      >
        {{ successMessage }}
      </div>

      <form @submit.prevent="submitOrder" class="space-y-8 py-2">
        <div class="space-y-4 max-h-[40vh] overflow-y-auto pr-2 custom-scrollbar">
          <div
            v-for="item in items"
            :key="item.id"
            class="group flex items-center justify-between p-4 rounded-[1.5rem] border border-slate-100 bg-slate-50/50 hover:bg-white hover:border-purple-200 hover:shadow-md transition-all duration-300"
          >
            <div class="flex-1">
              <p class="font-bold text-slate-800">{{ item.name }}</p>
              <p class="text-xs font-black text-purple-600 italic">N{{ Number(item.price).toLocaleString() }}</p>
            </div>

            <div class="flex items-center bg-white border border-slate-200 rounded-xl p-1 shadow-sm">
              <button
                type="button"
                @click="quantities[item.id] > 0 ? quantities[item.id]-- : null"
                class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-purple-600 transition-colors"
              >
                <Minus class="w-4 h-4" />
              </button>
              <input
                type="number"
                v-model.number="quantities[item.id]"
                readonly
                class="w-8 text-center border-none focus:ring-0 text-sm font-black text-slate-900 bg-transparent p-0"
              />
              <button
                type="button"
                @click="quantities[item.id]++"
                class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-purple-600 transition-colors"
              >
                <Plus class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>

        <div class="space-y-3">
          <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Condition Photos (Optional)</label>
          <div class="flex flex-wrap gap-3">
            <label class="w-20 h-20 flex flex-col items-center justify-center border-2 border-dashed border-slate-200 rounded-[1.25rem] text-slate-400 hover:border-purple-400 hover:text-purple-500 cursor-pointer transition-all bg-slate-50/50">
              <Camera class="w-6 h-6 mb-1" />
              <span class="text-[8px] font-black uppercase">Add</span>
              <input type="file" multiple @change="handleFiles" accept="image/*" class="hidden" />
            </label>

            <div v-for="(file, idx) in filePreviews" :key="idx" class="relative group">
              <img :src="file" class="h-20 w-20 object-cover rounded-[1.25rem] border border-slate-100 shadow-sm" />
              <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 rounded-[1.25rem] transition-opacity flex items-center justify-center">
                <button @click="removeFile(idx)" type="button" class="text-white">
                  <X class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="space-y-2">
          <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
            Payment Option
          </p>

          <div class="grid grid-cols-1 gap-3">
            <button
              type="button"
              @click="paymentMode = 'postpaid'"
              class="p-4 rounded-xl border text-left transition-all"
              :class="paymentMode === 'postpaid'
                ? 'border-purple-600 bg-purple-50'
                : 'border-slate-200 bg-white hover:border-purple-300'"
            >
              <p class="font-black text-sm">Pay on Delivery</p>
              <p class="text-xs text-slate-500">Pay when laundry is returned</p>
            </button>
          </div>

          <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-medium text-amber-800">
            Online payment for laundry requests is not enabled yet. Laundry charges are added to the room bill for now.
          </div>
        </div>

        <div class="bg-slate-900 rounded-[2rem] p-6 text-white flex justify-between items-center shadow-xl shadow-slate-200">
          <div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Estimated Total</p>
            <p class="text-2xl font-black">N{{ total.toLocaleString() }}</p>
          </div>
          <button
            type="submit"
            :disabled="total === 0"
            class="px-8 py-3 bg-purple-600 disabled:bg-slate-700 text-white rounded-xl font-black text-xs uppercase tracking-widest transition-all active:scale-95 shadow-lg shadow-purple-900/20"
          >
            Place Request
          </button>
        </div>
      </form>
    </template>
  </Modal>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Modal from '@/Components/Modal.vue'
import { WashingMachine, Plus, Minus, Camera, X } from 'lucide-vue-next'

const props = defineProps({
  room: Object,
  booking: Object,
  items: { type: Array, default: () => [] },
  accessToken: String,
  show: Boolean,
})

const quantities = ref({})
const files = ref([])
const filePreviews = ref([])
const paymentMode = ref('postpaid')
const submitting = ref(false)
const successMessage = ref(null)

props.items.forEach((item) => {
  quantities.value[item.id] = 0
})

const total = computed(() =>
  props.items.reduce((sum, item) => sum + (quantities.value[item.id] || 0) * item.price, 0)
)

watch(files, () => {
  filePreviews.value = files.value.map((file) => URL.createObjectURL(file))
})

function handleFiles(event) {
  files.value = [...files.value, ...Array.from(event.target.files)]
}

function removeFile(index) {
  files.value.splice(index, 1)
}

function submitOrder() {
  if (submitting.value) return

  submitting.value = true
  successMessage.value = null

  const formData = new FormData()
  formData.append('booking_id', props.booking.id)
  formData.append('payment_mode', paymentMode.value)

  for (const id in quantities.value) {
    if (quantities.value[id] > 0) {
      formData.append(`items[${id}][laundry_item_id]`, id)
      formData.append(`items[${id}][quantity]`, quantities.value[id])
    }
  }

  files.value.forEach((file) => formData.append('images[]', file))

  router.post(route('guest.laundry.store', props.accessToken), formData, {
    onSuccess: () => {
      submitting.value = false
      successMessage.value = 'Laundry request sent successfully.'
      resetForm()
    },
    onError: () => {
      submitting.value = false
    },
  })
}

function resetForm() {
  quantities.value = {}
  props.items.forEach((item) => {
    quantities.value[item.id] = 0
  })
  files.value = []
  filePreviews.value = []
}
</script>
