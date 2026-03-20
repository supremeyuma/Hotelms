<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Draggable from 'vuedraggable/dist/vuedraggable.umd.js'
import KitchenLayout from '@/Layouts/Staff/KitchenLayout.vue'

const draggable = Draggable

const props = defineProps({
  categories: Array,
  area: String
})

/* =========================
   UI STATE
========================= */
const showCategory = ref(false)
const showSubcategory = ref(false)
const showItem = ref(false)

const editingCategory = ref(null)
const editingSubcategory = ref(null)
const editingItem = ref(null)

const expandedCategories = ref([]) 
const expandedSubcategories = ref([])
const search = ref('')
const showUnavailable = ref(true)

/* =========================
   FORMS (Original Fields Preserved)
========================= */
const categoryForm = reactive({
  name: '',
  type: props.area
})

const subcategoryForm = reactive({
  name: '',
  menu_category_id: ''
})

const itemForm = reactive({
  menu_category_id: '',
  menu_subcategory_id: null,
  name: '',
  description: '',
  price: '',
  prep_time_adjustment: '',
  service_area: props.area,
  is_available: true,
  images: []
})

/* =========================
   COMPUTED
========================= */
const filteredCategories = computed(() => {
  return props.categories.map(c => ({
    ...c,
    items: c.items.filter(filterItem),
    subcategories: c.subcategories.map(s => ({
      ...s,
      items: s.items.filter(filterItem)
    }))
  }))
})

const filteredSubcategories = computed(() => {
  const c = props.categories.find(c => c.id === itemForm.menu_category_id)
  return c ? c.subcategories : []
})

function filterItem(item) {
  if (!showUnavailable.value && !item.is_available) return false
  return item.name.toLowerCase().includes(search.value.toLowerCase())
}

/* =========================
   HELPERS
========================= */
function toggleCategory(id) {
  const index = expandedCategories.value.indexOf(id)
  if (index > -1) expandedCategories.value.splice(index, 1)
  else expandedCategories.value.push(id)
}

function toggleSubcategory(id) {
  const index = expandedSubcategories.value.indexOf(id)
  if (index > -1) expandedSubcategories.value.splice(index, 1)
  else expandedSubcategories.value.push(id)
}

function resetItemForm() {
  Object.assign(itemForm, {
    menu_category_id: '',
    menu_subcategory_id: null,
    name: '',
    description: '',
    price: '',
    prep_time_adjustment: '',
    is_available: true,
    images: []
  })
  editingItem.value = null
}

function handleImages(e) {
  itemForm.images = Array.from(e.target.files)
}

/* =========================
   CREATE / UPDATE / DELETE
========================= */
function createCategory() {
  const url = editingCategory.value ? `/staff/menu/categories/${editingCategory.value.id}` : '/staff/menu/categories'
  const method = editingCategory.value ? 'patch' : 'post'
  router[method](url, categoryForm, {
    onSuccess: () => {
      categoryForm.name = ''
      showCategory.value = false
      editingCategory.value = null
    }
  })
}

function editCategory(cat) {
  editingCategory.value = cat
  categoryForm.name = cat.name
  showCategory.value = true
}

function createSubcategory() {
  const url = editingSubcategory.value ? `/staff/menu/subcategories/${editingSubcategory.value.id}` : '/staff/menu/subcategories'
  const method = editingSubcategory.value ? 'patch' : 'post'
  router[method](url, subcategoryForm, {
    onSuccess: () => {
      subcategoryForm.name = ''
      subcategoryForm.menu_category_id = ''
      showSubcategory.value = false
      editingSubcategory.value = null
    }
  })
}

function editSubcategory(sub) {
  editingSubcategory.value = sub
  subcategoryForm.name = sub.name
  subcategoryForm.menu_category_id = sub.menu_category_id
  showSubcategory.value = true
}

function submitItem() {
  const data = new FormData()
  Object.entries(itemForm).forEach(([k, v]) => {
    if (k === 'images') {
      v.forEach((f, i) => data.append(`images[${i}]`, f))
    } else if (v !== null && v !== '') {
      data.append(k, typeof v === 'boolean' ? (v ? 1 : 0) : v)
    }
  })

  if (editingItem.value) {
    router.post(`/staff/menu/items/${editingItem.value.id}?_method=PATCH`, data, {
      forceFormData: true,
      onSuccess: () => {
        resetItemForm()
        showItem.value = false
      }
    })
  } else {
    router.post('/staff/menu/items', data, {
      forceFormData: true,
      onSuccess: () => {
        resetItemForm()
        showItem.value = false
      }
    })
  }
}

function editItem(item) {
  editingItem.value = item
  showItem.value = true
  Object.assign(itemForm, {
    menu_category_id: item.menu_category_id,
    menu_subcategory_id: item.menu_subcategory_id,
    name: item.name,
    description: item.description,
    price: item.price,
    prep_time_adjustment: '',
    is_available: item.is_available,
    images: []
  })
}

function destroy(url, label) {
  if (confirm(`Delete this ${label}?`)) router.delete(url)
}

function toggle(url) {
  router.patch(url, {}, { preserveScroll: true, showProgress: false })
}

/* =========================
   REORDER
========================= */
function saveCategoryOrder() {
  router.post('/staff/menu/reorder', {
    categories: props.categories.map((c, i) => ({ id: c.id, sort_order: i }))
  }, { showProgress: false })
}

function saveItemOrder(category) {
  router.post('/staff/menu/reorder-items', {
    category_id: category.id,
    items: category.items.map((i, idx) => ({ id: i.id, sort_order: idx }))
  }, { showProgress: false })
}

watch(() => itemForm.menu_category_id, () => {
  itemForm.menu_subcategory_id = null
})
</script>

<template>
  <KitchenLayout>
  <div class="p-6 space-y-6 max-w-7xl mx-auto">

    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold uppercase tracking-tight">
        {{ area === 'kitchen' ? 'Kitchen' : 'Bar' }} Menu
      </h1>

      <div class="flex gap-3">
        <input v-model="search" placeholder="Search items..." class="border rounded-lg px-3 py-1 text-sm focus:ring-1 focus:ring-black outline-none" />
        <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
          <input type="checkbox" v-model="showUnavailable" class="rounded" />
          Show unavailable
        </label>
      </div>
    </div>

    <div class="flex gap-4">
      <button class="btn" @click="showCategory = !showCategory; editingCategory = null; categoryForm.name = ''">
        {{ editingCategory ? 'Edit Category' : 'Add Category' }}
      </button>
      <button class="btn" @click="showSubcategory = !showSubcategory; editingSubcategory = null; subcategoryForm.name = ''">
        {{ editingSubcategory ? 'Edit Subcategory' : 'Add Subcategory' }}
      </button>
      <button class="btn-primary" @click="showItem = !showItem; editingItem = null; resetItemForm()">
        {{ editingItem ? 'Edit Item' : 'Add Item' }}
      </button>
    </div>

    <div v-if="showCategory" class="panel shadow-sm">
      <form @submit.prevent="createCategory" class="space-y-3">
        <input v-model="categoryForm.name" class="input" placeholder="Category name" required />
        <div class="flex gap-2">
            <button class="btn-primary">{{ editingCategory ? 'Update' : 'Save' }}</button>
            <button type="button" class="btn" @click="showCategory = false">Cancel</button>
        </div>
      </form>
    </div>

    <div v-if="showSubcategory" class="panel shadow-sm">
      <form @submit.prevent="createSubcategory" class="space-y-3">
        <select v-model="subcategoryForm.menu_category_id" class="input" required>
          <option value="">Select Category</option>
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <input v-model="subcategoryForm.name" class="input" placeholder="Subcategory name" required />
        <div class="flex gap-2">
            <button class="btn-primary">{{ editingSubcategory ? 'Update' : 'Save' }}</button>
            <button type="button" class="btn" @click="showSubcategory = false">Cancel</button>
        </div>
      </form>
    </div>

    <div v-if="showItem" class="modal">
      <div class="modal-card">
        <h2 class="font-bold mb-3 text-lg uppercase tracking-wide">
          {{ editingItem ? 'Edit Item' : 'Add Item' }}
        </h2>

        <form @submit.prevent="submitItem" class="grid grid-cols-2 gap-3">
          <select v-model="itemForm.menu_category_id" class="input col-span-2">
            <option value="">Category</option>
            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>

          <select v-model="itemForm.menu_subcategory_id" class="input col-span-2" :disabled="!itemForm.menu_category_id">
            <option :value="null">No Subcategory</option>
            <option v-for="s in filteredSubcategories" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>

          <input v-model="itemForm.name" class="input col-span-2" placeholder="Name" />
          <input v-model="itemForm.price" type="number" class="input" placeholder="Price" />
          <input v-model="itemForm.prep_time_adjustment" type="number" class="input" placeholder="± Prep time" />

          <textarea v-model="itemForm.description" class="input col-span-2" placeholder="Description" rows="2" />

          <label class="col-span-2 flex justify-between items-center bg-gray-50 p-2 rounded border border-dashed">
            <span class="text-sm font-medium">Available</span>
            <label class="switch small">
                <input type="checkbox" v-model="itemForm.is_available" />
                <span class="slider"></span>
            </label>
          </label>

          <input type="file" multiple @change="handleImages" class="col-span-2 text-xs" />

          <div class="col-span-2 flex justify-end gap-3 pt-4 border-t">
            <button type="button" @click="showItem=false; resetItemForm()" class="btn">Cancel</button>
            <button class="btn-primary">Save Item</button>
          </div>
        </form>
      </div>
    </div>

    <draggable
      v-model="props.categories"
      item-key="id"
      class="space-y-4"
      @end="saveCategoryOrder"
      handle=".drag-cat"
    >
      <template #item="{ element: category }">
        <div class="border rounded-xl bg-white overflow-hidden shadow-sm">
          
          <div class="flex justify-between items-center p-4 bg-gray-50">
            <div class="flex items-center gap-4">
                <span class="drag-cat cursor-grab text-gray-300">☰</span>
                <button @click="toggleCategory(category.id)" class="flex items-center gap-2 font-bold uppercase text-sm">
                    <span class="transform transition-transform text-[10px]" :class="{'rotate-180': expandedCategories.includes(category.id)}">▼</span>
                    {{ category.name }}
                </button>
            </div>

            <div class="flex gap-4 items-center">
                <button @click="editCategory(category)" class="text-xs font-bold text-blue-600 uppercase">Edit</button>
                <label class="switch">
                    <input type="checkbox" :checked="category.is_active" @change="toggle(`/staff/menu/categories/${category.id}/toggle`)" />
                    <span class="slider"></span>
                </label>
                <button @click="destroy(`/staff/menu/categories/${category.id}`, 'category')" class="text-red-400 text-xl font-light hover:text-red-600">&times;</button>
            </div>
          </div>

          <div v-show="expandedCategories.includes(category.id)" class="p-4 border-t">
              
              <div v-for="sub in category.subcategories" :key="sub.id" class="mb-2 last:mb-0">
                  <div class="flex justify-between items-center py-2 px-3 bg-gray-100 rounded-lg">
                      <div class="flex items-center gap-2">
                          <button @click="toggleSubcategory(sub.id)" class="text-gray-400 hover:text-black">
                              <span class="text-[8px] transform inline-block transition-transform" :class="{'rotate-180': expandedSubcategories.includes(sub.id)}">▼</span>
                          </button>
                          <span class="text-xs font-black text-gray-700 uppercase tracking-widest">{{ sub.name }}</span>
                      </div>
                      <div class="flex items-center gap-4">
                          <button @click="editSubcategory(sub)" class="text-[10px] font-bold text-blue-600 uppercase">Edit</button>
                          <label class="switch small">
                            <input type="checkbox" :checked="sub.is_active" @change="toggle(`/staff/menu/subcategories/${sub.id}/toggle`)" />
                            <span class="slider"></span>
                          </label>
                          <button @click="destroy(`/staff/menu/subcategories/${sub.id}`, 'subcategory')" class="text-red-400">&times;</button>
                      </div>
                  </div>

                  <div v-show="expandedSubcategories.includes(sub.id)" class="grid grid-cols-1 md:grid-cols-2 gap-2 ml-6 mt-2 pb-2">
                      <div v-for="item in sub.items" :key="item.id" class="row shadow-sm" :class="{'opacity-50': !item.is_available}">
                        <div>
                            <p class="font-bold text-sm">{{ item.name }}</p>
                            <p class="text-[10px] text-gray-400">₦{{ item.price }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <button @click="editItem(item)" class="text-xs font-bold text-indigo-600 uppercase">Edit</button>
                            <label class="switch small">
                                <input type="checkbox" :checked="item.is_available" @change="toggle(`/staff/menu/items/${item.id}/toggle`)" />
                                <span class="slider"></span>
                            </label>
                        </div>
                      </div>
                  </div>
              </div>

              <div v-if="category.items.length" class="mt-4 pt-4 border-t border-dashed">
                  <p class="text-[10px] font-bold text-gray-400 uppercase mb-2 px-2">General Items</p>
                  <draggable v-model="category.items" item-key="id" @end="saveItemOrder(category)" class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <template #item="{ element: item }">
                        <div class="row shadow-sm" :class="{'opacity-50': !item.is_available}">
                            <div>
                                <p class="font-bold text-sm">{{ item.name }}</p>
                                <p class="text-[10px] text-gray-400">₦{{ item.price }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <button @click="editItem(item)" class="text-xs font-bold text-indigo-600 uppercase">Edit</button>
                                <label class="switch small">
                                    <input type="checkbox" :checked="item.is_available" @change="toggle(`/staff/menu/items/${item.id}/toggle`)" />
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                    </template>
                  </draggable>
              </div>
          </div>
        </div>
      </template>
    </draggable>
  </div>
  </KitchenLayout>
</template>

<style scoped>
.btn { @apply px-4 py-2 border border-gray-300 rounded-lg text-xs font-bold uppercase hover:bg-gray-50 transition bg-white }
.btn-primary { @apply px-4 py-2 bg-black text-white rounded-lg text-xs font-bold uppercase hover:bg-gray-800 transition shadow-sm }
.input { @apply border border-gray-200 rounded-lg p-2 w-full text-sm focus:ring-1 focus:ring-black outline-none transition-all }
.panel { @apply border rounded-xl p-4 bg-white max-w-md border-gray-200 }
.row { @apply flex justify-between items-center border border-gray-100 rounded-lg p-3 bg-white }
.modal { @apply fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4 backdrop-blur-[1px] }
.modal-card { @apply bg-white rounded-2xl p-6 w-full max-w-lg shadow-2xl }

/* SLIDER STYLING */
.switch { position: relative; display: inline-block; width: 36px; height: 18px }
.switch input { opacity: 0; width: 0; height: 0 }
.slider {
  position: absolute; cursor: pointer; inset: 0;
  background-color: #cbd5e1; border-radius: 99px; transition: .3s;
}
.slider:before {
  position: absolute; content: ""; height: 14px; width: 14px;
  left: 2px; bottom: 2px; background-color: white; border-radius: 50%; transition: .3s;
}
input:checked + .slider { background-color: #10b981 }
input:checked + .slider:before { transform: translateX(18px) }

.small { width: 30px; height: 16px }
.small.slider:before { height: 12px; width: 12px; left: 2px; bottom: 2px }
input:checked + .small.slider:before { transform: translateX(14px) }
</style>