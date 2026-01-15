<script setup>
import { ref, computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Wysiwyg from '@/Components/Admin/Wysiwyg.vue'
import { 
  Image as ImageIcon, 
  FileText, 
  Code, 
  Search, 
  Upload, 
  ExternalLink,
  Loader2,
  CheckCircle2
} from 'lucide-vue-next'

const props = defineProps({
  contents: Object, // Format: { 'home.hero.title': { id, value, type, ... } }
})

/* ---------------- STATE ---------------- */
const activeKey = ref(null)
const searchQuery = ref('')
const isUploading = ref(false)

const form = useForm({
  key: '',
  value: '',
  type: 'text',
})

/* ---------------- DERIVED ---------------- */

// Filter and Group content
const groupedContent = computed(() => {
  const groups = {}
  
  const filtered = Object.entries(props.contents).filter(([key]) => 
    key.toLowerCase().includes(searchQuery.value.toLowerCase())
  )

  filtered.forEach(([key, item]) => {
    const section = key.split('.')[0]
    if (!groups[section]) groups[section] = []
    groups[section].push({ key, ...item })
  })

  return groups
})

/* ---------------- METHODS ---------------- */

const edit = (item) => {
  // 1. Set the active key first
  activeKey.value = item.key
  
  // 2. Clear and set the form data explicitly
  form.clearErrors() // Remove old validation errors
  form.key = item.key
  form.value = item.value
  form.type = item.type
  
  // 3. Force the form to not be "dirty" initially
  form.defaults({
    key: item.key,
    value: item.value,
    type: item.type
  })
  form.reset()
}

const formatKeyLabel = (key) => {
  return key.split('.').slice(1).join(' ').replace(/_/g, ' ')
}

const handleImageUpload = (e) => {
  const file = e.target.files[0]
  if (!file) return

  const imageForm = useForm({
    key: activeKey.value,
    image: file
  })

  isUploading.value = true
  imageForm.post('/admin/website/content/image'), {
    preserveScroll: true,
    onSuccess: () => {
      if (props.contents[activeKey.value]) {
        form.value = props.contents[activeKey.value].value
      }
      isUploading.value = false
    },
    onError: () => isUploading.value = false
  }
}

const save = () => {
  form.put(`/admin/website/content/${form.key}`, {
    preserveScroll: true,
    onSuccess: () => {
      activeKey.value = null
    },
  })
}
</script>

<template>
  <AdminLayout title="Website Content">
    <div class="max-w-[1600px] mx-auto p-4 md:p-8">
      
      <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
        <div>
          <h1 class="text-3xl font-black text-slate-900 tracking-tight">Website Content</h1>
          <p class="text-slate-500">Manage global text, media, and SEO configurations.</p>
        </div>
        
        <div class="relative w-full md:w-72">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <input 
            v-model="searchQuery"
            type="text" 
            placeholder="Search keys..." 
            class="w-full pl-10 pr-4 py-2 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 transition-all outline-none text-sm"
          />
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

        <div class="lg:col-span-4 xl:col-span-3 space-y-6 overflow-y-auto max-h-[calc(100vh-250px)] pr-2 custom-scrollbar">
          <div
            v-for="(items, section) in groupedContent"
            :key="section"
            class="space-y-1"
          >
            <h2 class="px-3 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mb-2 mt-4">
              {{ section }}
            </h2>

            <button
              v-for="item in items"
              :key="item.key"
              @click="edit(item)"
              class="w-full text-left px-3 py-2.5 rounded-xl flex items-center justify-between transition-all group"
              :class="activeKey === item.key 
                ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-200' 
                : 'hover:bg-white hover:shadow-sm text-slate-600'"
            >
              <span class="text-xs font-bold truncate pr-2 capitalize">
                {{ formatKeyLabel(item.key) }}
              </span>

              <span :class="activeKey === item.key ? 'text-white/60' : 'text-slate-300'">
                <ImageIcon v-if="item.type === 'image'" class="w-3.5 h-3.5" />
                <Code v-else-if="item.type === 'html'" class="w-3.5 h-3.5" />
                <FileText v-else class="w-3.5 h-3.5" />
              </span>
            </button>
          </div>
        </div>

        <div class="lg:col-span-8 xl:col-span-9">
          <div v-if="activeKey" class="bg-white border border-slate-200 rounded-3xl shadow-sm overflow-hidden">
            
            <div class="border-b border-slate-100 p-6 flex justify-between items-center bg-slate-50/50">
              <div>
                <span class="text-[10px] font-bold text-indigo-600 uppercase tracking-widest">{{ form.type }} content</span>
                <h3 class="font-mono text-sm text-slate-500">{{ activeKey }}</h3>
              </div>
              <a href="/" target="_blank" class="text-slate-400 hover:text-indigo-600 transition">
                <ExternalLink class="w-4 h-4" />
              </a>
            </div>

            <div class="p-8 space-y-8">
              <pre class="text-[10px] bg-black text-green-500 p-2">Editing: {{ activeKey }} | Form Value Length: {{ form.value?.length }}</pre>
              
              <div class="space-y-2">
                <label class="block text-sm font-bold text-slate-700">Content Value</label>
                
                <div v-if="form.type === 'html'" class="min-h-[400px]">
                  <Wysiwyg :key="activeKey" v-model="form.value" />
                </div>

                <textarea
                  v-else-if="form.type === 'text'"
                  v-model="form.value"
                  rows="8"
                  class="w-full border-slate-200 rounded-2xl p-4 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-slate-700 leading-relaxed"
                  placeholder="Enter text content here..."
                ></textarea>

                <div v-else-if="form.type === 'image'" class="space-y-4">
                  <div class="relative group aspect-video md:aspect-[21/9] bg-slate-100 rounded-3xl overflow-hidden border-2 border-dashed border-slate-200 flex items-center justify-center">
                    <img v-if="form.value" :src="form.value" class="w-full h-full object-cover transition duration-500 group-hover:scale-105" />
                    
                    <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center backdrop-blur-sm">
                      <label class="cursor-pointer bg-white text-slate-900 px-6 py-3 rounded-full font-bold flex items-center gap-2 shadow-xl hover:bg-indigo-50 transition-colors">
                        <Upload class="w-4 h-4" />
                        {{ isUploading ? 'Uploading...' : 'Replace Image' }}
                        <input type="file" class="hidden" @change="handleImageUpload" accept="image/*" />
                      </label>
                    </div>

                    <div v-if="!form.value" class="text-center p-6">
                       <ImageIcon class="w-12 h-12 text-slate-300 mx-auto mb-2" />
                       <p class="text-sm text-slate-400">No image uploaded yet</p>
                    </div>

                    <div v-if="isUploading" class="absolute inset-0 bg-white/80 flex items-center justify-center">
                      <Loader2 class="w-8 h-8 text-indigo-600 animate-spin" />
                    </div>
                  </div>
                  <p class="text-[10px] text-slate-400 font-mono italic">Path: {{ form.value }}</p>
                </div>
              </div>

              <div class="pt-6 border-t border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2 text-xs text-slate-400">
                  <CheckCircle2 v-if="!form.isDirty && !form.recentlySuccessful" class="w-4 h-4 text-emerald-500" />
                  <span v-if="form.isDirty" class="text-amber-500 font-medium italic underline decoration-amber-200 underline-offset-4">You have unsaved changes</span>
                  <span v-else-if="form.recentlySuccessful" class="text-emerald-600 font-bold">Changes saved successfully!</span>
                  <span v-else>All changes are up to date</span>
                </div>

                <div class="flex gap-4">
                  <button 
                    @click="activeKey = null"
                    class="px-6 py-2.5 text-sm font-bold text-slate-400 hover:text-slate-600 transition"
                  >
                    Cancel
                  </button>
                  <button
                    @click="save"
                    :disabled="form.processing || !form.isDirty"
                    class="px-8 py-2.5 bg-slate-900 text-white rounded-xl text-sm font-black shadow-lg shadow-slate-200 hover:bg-indigo-600 hover:shadow-indigo-100 disabled:opacity-30 disabled:pointer-events-none transition-all flex items-center gap-2"
                  >
                    <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
                    Save Changes
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div
            v-else
            class="h-full min-h-[500px] border-2 border-dashed border-slate-200 rounded-[40px] flex flex-col items-center justify-center text-center p-12 bg-slate-50/30"
          >
            <div class="w-20 h-20 bg-white rounded-3xl shadow-sm border border-slate-100 flex items-center justify-center mb-6">
              <FileText class="w-8 h-8 text-slate-300" />
            </div>
            <h3 class="text-xl font-black text-slate-800 mb-2">Editor Ready</h3>
            <p class="text-slate-500 max-w-xs mx-auto text-sm leading-relaxed">
              Select an item from the sidebar to begin editing website content, metadata, or media.
            </p>
          </div>
        </div>

      </div>
    </div>
  </AdminLayout>
</template>


<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #e2e8f0;
  border-radius: 10px;
}
</style>