<!-- resources/js/Pages/Admin/WebsiteContent/Gallery.vue -->
<script setup>
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import draggable from 'vuedraggable'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  Image as ImageIcon,
  Upload,
  Trash2,
  Search,
  Loader2,
  GripVertical,
  Eye,
  EyeOff,
} from 'lucide-vue-next'

/* ================= PROPS ================= */
const props = defineProps({
  items: {
    type: Array,
    required: true,
  },
})

/* ================= STATE ================= */
const activeId = ref(null)
const search = ref('')

const form = useForm({
  id: null,
  category: '',
  caption: '',
  image: null,
})

/* ================= DERIVED ================= */
const groupedGallery = computed(() => {
  const groups = {}

  props.items
    .filter(item => {
      const q = search.value.toLowerCase()
      return (
        item.category.toLowerCase().includes(q) ||
        (item.caption ?? '').toLowerCase().includes(q)
      )
    })
    .forEach(item => {
      const section = item.category || 'general'
      if (!groups[section]) groups[section] = []
      groups[section].push(item)
    })

  return groups
})

const activeItem = computed(() =>
  props.items.find(i => i.id === activeId.value) ?? null
)

/* ================= METHODS ================= */
const edit = (item) => {
  activeId.value = item.id
  form.id = item.id
  form.category = item.category
  form.caption = item.caption
  form.image = null
}

const resetEditor = () => {
  activeId.value = null
  form.reset()
}

const save = () => {
  form.put(`/admin/website/gallery/${form.id}`, {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: resetEditor,
  })
}

const create = () => {
  form.post('/admin/website/gallery', {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: resetEditor,
  })
}

const remove = (id) => {
  if (!confirm('Delete this image?')) return
  form.delete(`/admin/website/gallery/${id}`, {
    preserveScroll: true,
    onSuccess: resetEditor,
  })
}

const toggleActive = (item) => {
  router.patch(
    `/admin/website/gallery/${item.id}/toggle`,
    {},
    { preserveScroll: true }
  )
}

const saveOrder = (items) => {
  router.post(
    '/admin/website/gallery/reorder',
    { order: items.map(i => i.id) },
    { preserveScroll: true }
  )
}
</script>

<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto p-8 space-y-10">

      <!-- HEADER -->
      <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
        <div>
          <h1 class="text-3xl font-black tracking-tight">Gallery</h1>
          <p class="text-sm text-slate-500">
            Manage public gallery images
          </p>
        </div>

        <div class="relative w-full md:w-72">
          <Search class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <input
            v-model="search"
            type="text"
            placeholder="Search gallery..."
            class="w-full pl-11 pr-4 py-3 rounded-2xl border border-slate-200 text-sm font-bold shadow-sm"
          />
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">

        <!-- LIST -->
        <div class="lg:col-span-1 space-y-6">
          <button
            @click="resetEditor"
            class="w-full px-4 py-3 rounded-2xl bg-indigo-600 text-white font-black flex items-center justify-center gap-2 shadow"
          >
            <Upload class="w-4 h-4" />
            Upload New Image
          </button>

          <div
            v-for="(items, section) in groupedGallery"
            :key="section"
            class="bg-white border rounded-2xl p-4"
          >
            <h2 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-3">
              {{ section }}
            </h2>

            <draggable
              :list="items"
              item-key="id"
              handle=".drag"
              @end="saveOrder(items)"
              class="space-y-1"
            >
              <template #item="{ element }">
                <li
                  @click="edit(element)"
                  class="cursor-pointer px-3 py-2 rounded-lg flex items-center justify-between hover:bg-slate-50"
                  :class="[
                    activeId === element.id
                      ? 'bg-indigo-50 text-indigo-700'
                      : '',
                    !element.is_active ? 'opacity-50' : '',
                  ]"
                >
                  <div class="flex items-center gap-2 min-w-0">
                    <GripVertical class="w-4 h-4 text-slate-400 drag cursor-grab" />
                    <span class="text-sm truncate">
                      {{ element.caption || 'Untitled image' }}
                    </span>
                  </div>

                  <div class="flex items-center gap-2">
                    <button
                      @click.stop="toggleActive(element)"
                      class="p-1 rounded hover:bg-slate-100"
                    >
                      <Eye v-if="element.is_active" class="w-4 h-4 text-emerald-600" />
                      <EyeOff v-else class="w-4 h-4 text-slate-400" />
                    </button>
                    <ImageIcon class="w-4 h-4 opacity-50" />
                  </div>
                </li>
              </template>
            </draggable>
          </div>
        </div>

        <!-- EDITOR -->
        <div class="lg:col-span-2">
          <div
            v-if="activeItem"
            class="bg-white border rounded-3xl p-6 space-y-6"
          >
            <img
              :src="`/storage/${activeItem.image_path.replace('public/', '')}`"
              class="rounded-2xl border max-h-80 object-cover"
            />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <input
                v-model="form.category"
                placeholder="Category"
                class="border rounded-xl px-4 py-3"
              />
              <input
                v-model="form.caption"
                placeholder="Caption"
                class="border rounded-xl px-4 py-3"
              />
            </div>

            <input
              type="file"
              @change="e => form.image = e.target.files[0]"
            />

            <div class="flex justify-between pt-4 border-t">
              <button
                @click="remove(activeItem.id)"
                class="flex items-center gap-2 text-rose-600 font-bold"
              >
                <Trash2 class="w-4 h-4" />
                Delete
              </button>

              <button
                @click="save"
                :disabled="form.processing"
                class="px-6 py-2 rounded-xl bg-slate-900 text-white text-sm font-black flex items-center gap-2"
              >
                <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
                Save Changes
              </button>
            </div>
          </div>

          <!-- CREATE -->
          <div
            v-else
            class="border-2 border-dashed rounded-3xl p-12 space-y-6"
          >
            <h3 class="text-xl font-black">
              Upload New Gallery Image
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
              <input
                v-model="form.category"
                placeholder="Category"
                class="border rounded-xl px-4 py-3"
              />
              <input
                v-model="form.caption"
                placeholder="Caption"
                class="border rounded-xl px-4 py-3"
              />
              <input
                type="file"
                @change="e => form.image = e.target.files[0]"
              />
            </div>

            <button
              @click="create"
              class="px-8 py-3 rounded-xl bg-indigo-600 text-white font-black"
            >
              Upload
            </button>
          </div>
        </div>

      </div>
    </div>
  </AdminLayout>
</template>

<style scoped>
.drag {
  cursor: grab;
}
</style>
