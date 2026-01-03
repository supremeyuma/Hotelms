<script setup>
import { ref, reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import LaundryLayout from '@/Layouts/Staff/LaundryLayout.vue'
import { 
  Plus, 
  Pencil, 
  Trash2, 
  Shirt, 
  X, 
  Banknote, 
  AlignLeft,
  ChevronLeft
} from 'lucide-vue-next'

const props = defineProps({
  items: Array,
})

/* -------------------------
  MODAL STATE
-------------------------- */
const showModal = ref(false)
const editingItem = ref(null)

/* -------------------------
  FORM
-------------------------- */
const form = reactive({
  name: '',
  price: '',
  description: '',
})

function openCreate() {
  editingItem.value = null
  form.name = ''
  form.price = ''
  form.description = ''
  showModal.value = true
}

function openEdit(item) {
  editingItem.value = item
  form.name = item.name
  form.price = item.price
  form.description = item.description ?? ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editingItem.value = null
}

/* -------------------------
  ACTIONS
-------------------------- */
function submit() {
  if (editingItem.value) {
    router.put(
      route('staff.laundry-items.update', editingItem.value.id),
      form,
      { onSuccess: closeModal }
    )
  } else {
    router.post(route('staff.laundry-items.store'), form, {
      onSuccess: closeModal,
    })
  }
}

function destroy(id) {
  if (confirm('Delete this laundry item? This will remove it from the guest menu.')) {
    router.delete(route('staff.laundry-items.destroy', id))
  }
}
</script>

<template>
  <LaundryLayout>
    <div class="max-w-5xl mx-auto px-4 py-6 md:py-10">
      
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
        <div>
          <button 
            @click="router.visit(route('staff.laundry.dashboard'))"
            class="flex items-center gap-2 text-slate-500 font-bold text-sm hover:text-indigo-600 transition-colors mb-4 group"
          >
            <ChevronLeft class="w-4 h-4 group-hover:-translate-x-1 transition-transform" />
            Back to Dashboard
          </button>
          <div class="flex items-center gap-4">
            <div class="p-3 bg-indigo-100 text-indigo-600 rounded-2xl">
              <Shirt class="w-8 h-8" />
            </div>
            <div>
              <h1 class="text-3xl font-black text-slate-900 tracking-tight">Laundry Items</h1>
              <p class="text-slate-500 font-medium">Configure services and pricing for guests</p>
            </div>
          </div>
        </div>

        <button
          @click="openCreate"
          class="inline-flex items-center justify-center gap-2 px-6 py-4 bg-slate-900 text-white rounded-2xl font-bold hover:bg-indigo-600 transition-all shadow-xl shadow-slate-200 active:scale-95"
        >
          <Plus class="w-5 h-5" />
          Add New Item
        </button>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div
          v-for="item in items"
          :key="item.id"
          class="group bg-white rounded-[2rem] border border-slate-200 p-6 flex justify-between items-center hover:shadow-xl hover:border-indigo-100 transition-all duration-300"
        >
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-slate-50 rounded-xl flex items-center justify-center text-slate-400 group-hover:bg-indigo-50 group-hover:text-indigo-600 transition-colors">
              <Shirt class="w-6 h-6" />
            </div>
            <div>
              <p class="font-bold text-slate-900 text-lg leading-tight">{{ item.name }}</p>
              <p class="text-sm font-medium text-slate-500 mt-1">
                <span class="text-indigo-600 font-black">₦{{ item.price }}</span> 
                <span class="mx-2 opacity-30">•</span>
                {{ item.description || 'No description provided' }}
              </p>
            </div>
          </div>

          <div class="flex gap-2">
            <button
              @click="openEdit(item)"
              class="p-3 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-xl transition-all"
              title="Edit Item"
            >
              <Pencil class="w-5 h-5" />
            </button>

            <button
              @click="destroy(item.id)"
              class="p-3 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition-all"
              title="Delete Item"
            >
              <Trash2 class="w-5 h-5" />
            </button>
          </div>
        </div>

        <div v-if="items.length === 0" class="col-span-full py-20 bg-slate-50 rounded-[3rem] border-2 border-dashed border-slate-200 flex flex-col items-center justify-center text-slate-400">
           <Shirt class="w-12 h-12 mb-4 opacity-20" />
           <p class="font-bold">No items found. Click "Add New Item" to start.</p>
        </div>
      </div>

      <transition 
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
      >
        <div
          v-if="showModal"
          class="fixed inset-0 z-[100] flex items-center justify-center p-4"
        >
          <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="closeModal"></div>
          
          <div class="relative bg-white rounded-[2.5rem] shadow-2xl w-full max-w-lg overflow-hidden transform transition-all">
            <div class="px-8 pt-8 pb-4 flex justify-between items-center">
              <h2 class="text-2xl font-black text-slate-900 tracking-tight">
                {{ editingItem ? 'Edit Item' : 'Create Item' }}
              </h2>
              <button @click="closeModal" class="p-2 hover:bg-slate-100 rounded-full text-slate-400 transition-colors">
                <X class="w-6 h-6" />
              </button>
            </div>

            <form @submit.prevent="submit" class="p-8 space-y-6">
              <div class="space-y-2">
                <label class="text-xs font-black text-slate-400 uppercase tracking-widest ml-1">Service Name</label>
                <div class="relative group">
                  <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-600 transition-colors">
                    <Shirt class="w-5 h-5" />
                  </div>
                  <input
                    v-model="form.name"
                    placeholder="e.g. Suit Dry Cleaning"
                    class="block w-full pl-12 pr-4 py-4 bg-slate-50 border-2 border-slate-100 rounded-2xl focus:bg-white focus:border-indigo-600 focus:ring-0 transition-all font-bold"
                    required
                  />
                </div>
              </div>

              <div class="space-y-2">
                <label class="text-xs font-black text-slate-400 uppercase tracking-widest ml-1">Price (₦)</label>
                <div class="relative group">
                  <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-600 transition-colors">
                    <Banknote class="w-5 h-5" />
                  </div>
                  <input
                    v-model="form.price"
                    type="number"
                    placeholder="0.00"
                    class="block w-full pl-12 pr-4 py-4 bg-slate-50 border-2 border-slate-100 rounded-2xl focus:bg-white focus:border-indigo-600 focus:ring-0 transition-all font-bold"
                    required
                  />
                </div>
              </div>

              <div class="space-y-2">
                <label class="text-xs font-black text-slate-400 uppercase tracking-widest ml-1">Description</label>
                <div class="relative group">
                  <div class="absolute top-4 left-4 pointer-events-none text-slate-400 group-focus-within:text-indigo-600 transition-colors">
                    <AlignLeft class="w-5 h-5" />
                  </div>
                  <textarea
                    v-model="form.description"
                    placeholder="Brief details about the service..."
                    rows="3"
                    class="block w-full pl-12 pr-4 py-4 bg-slate-50 border-2 border-slate-100 rounded-2xl focus:bg-white focus:border-indigo-600 focus:ring-0 transition-all font-medium"
                  />
                </div>
              </div>

              <div class="flex flex-col gap-3 pt-4">
                <button
                  class="w-full py-4 bg-slate-900 text-white rounded-2xl font-bold text-lg hover:bg-indigo-600 shadow-xl shadow-slate-200 active:scale-95 transition-all"
                >
                  {{ editingItem ? 'Save Changes' : 'Create Item' }}
                </button>
                <button
                  type="button"
                  @click="closeModal"
                  class="w-full py-3 text-slate-400 font-bold text-sm hover:text-slate-600 transition-colors"
                >
                  Cancel
                </button>
              </div>
            </form>
          </div>
        </div>
      </transition>
    </div>
  </LaundryLayout>
</template>