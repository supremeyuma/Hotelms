<script setup>
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'

const props = defineProps({
  event: Object
})

// Helper to extract time from datetime string
const extractTimeFromDateTime = (dateTimeString) => {
  if (!dateTimeString) return ''
  const date = new Date(dateTimeString)
  if (isNaN(date.getTime())) return ''
  // Returns HH:mm in 24-hour format
  return date.getHours().toString().padStart(2, '0') + ':' + 
         date.getMinutes().toString().padStart(2, '0')
}

// Add this helper to format any date/time string for the datetime-local input
const formatForDateTimeInput = (dateTimeString) => {
  if (!dateTimeString) return ''
  const date = new Date(dateTimeString)
  if (isNaN(date.getTime())) return ''
  
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  
  return `${year}-${month}-${day}T${hours}:${minutes}`
}

// Helper to format date for date input
const formatDateForInput = (dateString) => {
  if (!dateString) return ''
  const date = new Date(dateString)
  if (isNaN(date.getTime())) return ''
  return date.toISOString().split('T')[0] // YYYY-MM-DD format
}

const form = useForm({
  title: props.event.title,
  description: props.event.description,
  event_date: formatDateForInput(props.event.event_date),
  start_datetime: formatForDateTimeInput(props.event.start_datetime || props.event.event_date + ' ' + props.event.start_time),
  end_datetime: formatForDateTimeInput(props.event.end_datetime || props.event.end_time),
  venue: props.event.venue || '',
  capacity: props.event.capacity || '',
  is_active: props.event.is_active,
  is_featured: props.event.is_featured,
  ticket_sales_start: (formatDateForInput(props.event.ticket_sales_start) && extractTimeFromDateTime(props.event.ticket_sales_start)) 
      ? formatDateForInput(props.event.ticket_sales_start) + 'T' + extractTimeFromDateTime(props.event.ticket_sales_start)
      : '',

  ticket_sales_end: (formatDateForInput(props.event.ticket_sales_end) && extractTimeFromDateTime(props.event.ticket_sales_end)) 
      ? formatDateForInput(props.event.ticket_sales_end) + 'T' + extractTimeFromDateTime(props.event.ticket_sales_end)
      : '',
  max_tickets_per_person: props.event.max_tickets_per_person || 10,
  has_table_reservations: props.event.has_table_reservations || false,
  table_capacity: props.event.table_capacity || 0,
  table_price: props.event.table_price || 0,
  image: null,
  promotional_media: [],
  ticket_types: props.event.ticket_types?.length > 0 ? props.event.ticket_types.map(type => ({
    id: type.id,
    name: type.name,
    description: type.description || '',
    price: type.price,
    quantity_available: type.quantity_available,
    max_per_person: type.max_per_person || 10,
    color_code: type.color_code || '#3B82F6'
  })) : [
    {
      name: '',
      description: '',
      price: 0,
      quantity_available: 100,
      max_per_person: 10,
      color_code: '#3B82F6'
    }
  ],
  table_types: props.event.table_types?.length > 0 ? props.event.table_types.map(type => ({
    id: type.id,
    name: type.name,
    description: type.description || '',
    price: type.price,
    capacity: type.capacity || 10,
    color_code: type.color_code || '#3B82F6'
  })) : [
    {
      name: '',
      description: '',
      price: 0,
      capacity: 10,
      color_code: '#3B82F6'
    }
  ]
})

const imagePreview = ref(props.event.image ? `/storage/${props.event.image}` : null)
const mediaFiles = ref([])
const mainImageIndex = ref(0)
const existingMedia = ref(props.event.promotional_media || [])
const removedMedia = ref([])

// Initialize existing media
existingMedia.value.forEach((media, index) => {
  if (media.is_main_image) {
    mainImageIndex.value = index
  }
})

const handleImageUpload = (event) => {
  const file = event.target.files[0]
  if (file) {
    form.image = file
    const reader = new FileReader()
    reader.onload = (e) => {
      imagePreview.value = e.target.result
    }
    reader.readAsDataURL(file)
  }
}

const handleMediaUpload = (event) => {
  const files = Array.from(event.target.files)
  files.forEach(file => {
    const reader = new FileReader()
    reader.onload = (e) => {
      mediaFiles.value.push({
        file: file,
        preview: e.target.result,
        media_type: file.type.startsWith('video/') ? 'video' : 'image',
        title: '',
        description: '',
        is_main_image: false
      })
    }
    reader.readAsDataURL(file)
  })
}

const removeExistingMedia = (index) => {
  const media = existingMedia.value[index]
  removedMedia.value.push(media.id)
  existingMedia.value.splice(index, 1)
  if (mainImageIndex.value >= existingMedia.value.length + mediaFiles.value.length) {
    mainImageIndex.value = 0
  }
}

const removeMedia = (index) => {
  mediaFiles.value.splice(index, 1)
  if (mainImageIndex.value >= existingMedia.value.length + mediaFiles.value.length) {
    mainImageIndex.value = 0
  }
}

const setMainImage = (index, isExisting = false) => {
  mainImageIndex.value = index
  
  // Reset all to false
  existingMedia.value.forEach((media, i) => {
    media.is_main_image = i === index && isExisting
  })
  
  mediaFiles.value.forEach((media, i) => {
    media.is_main_image = i === index && !isExisting
  })
}

const addTicketType = () => {
  form.ticket_types.push({
    name: '',
    description: '',
    price: 0,
    quantity_available: 100,
    max_per_person: 10,
    color_code: '#' + Math.floor(Math.random()*16777215).toString(16)
  })
}

const removeTicketType = (index) => {
  if (form.ticket_types.length > 1) {
    form.ticket_types.splice(index, 1)
  }
}

const addTableType = () => {
  form.table_types.push({
    name: '',
    description: '',
    price: 0,
    capacity: 10,
    color_code: '#' + Math.floor(Math.random()*16777215).toString(16)
  })
}

const removeTableType = (index) => {
  if (form.table_types.length > 1) {
    form.table_types.splice(index, 1)
  }
}

const submit = () => {
  // 1. Prepare media arrays
  form.media = mediaFiles.value.map(m => ({
    file: m.file,
    media_type: m.media_type,
    title: m.title,
    description: m.description,
    is_main_image: m.is_main_image
  }))

  form.existing_media = existingMedia.value.map(m => ({
    id: m.id,
    title: m.title || '',
    description: m.description || '',
    is_main_image: m.is_main_image ? 1 : 0
  }))
  
  form.removed_media = removedMedia.value

  // 2. Handle Table Reservations Logic
  let processedTableTypes = []
  if (form.has_table_reservations) {
    processedTableTypes = form.table_types.filter(t => t.name && t.name.trim() !== '')
  }

  // 3. POST with Method Spoofing
  form.transform((data) => ({
    ...data,
    table_types: processedTableTypes, // Send filtered tables
    _method: 'PUT', 
    // Explicitly remove old keys if they still haunt the form object
    event_date: undefined, 
    start_time: undefined,
    end_date: undefined,
    end_time: undefined,
  })).post(`/admin/events/${props.event.id}`, {
    forceFormData: true,
    preserveScroll: true,
    onError: (errors) => {
        console.error("Validation Errors:", errors);
    }
  })
}

const isDateSequenceValid = computed(() => {
  if (!form.start_datetime || !form.end_datetime) return true
  return new Date(form.start_datetime) < new Date(form.end_datetime)
})

// Then update your main validation
const isFormValid = computed(() => {
  return (
    form.title && 
    form.description && 
    form.start_datetime && 
    form.end_datetime &&
    isDateSequenceValid.value
  )
})


// Update existing media when main image changes
watch(mainImageIndex, (newIndex) => {
  const totalMedia = existingMedia.value.length + mediaFiles.value.length
  if (newIndex < totalMedia) {
    existingMedia.value.forEach((media, i) => {
      media.is_main_image = i === newIndex
    })
    mediaFiles.value.forEach((media, i) => {
      media.is_main_image = (existingMedia.value.length + i) === newIndex
    })
  }
})
</script>

<template>
  <ManagerLayout>
    <div class="max-w-6xl mx-auto p-6 space-y-8">
      <div class="flex items-center justify-between">
        <h1 class="text-3xl font-black text-slate-900">Edit Event: {{ event.title }}</h1>
        <Link 
          href="/admin/events" 
          class="text-slate-600 hover:text-slate-900 transition-colors"
        >
          ← Back to Events
        </Link>
      </div>

      <form @submit.prevent="submit" class="space-y-8">
        <!-- Basic Information -->
        <section class="bg-white rounded-lg border border-slate-200 p-6">
          <h2 class="text-xl font-semibold text-slate-900 mb-6">Basic Information</h2>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-slate-700 mb-2">Event Title *</label>
              <input 
                v-model="form.title" 
                type="text" 
                class="input w-full" 
                placeholder="Enter event title"
                required
              />
              <p v-if="form.errors.title" class="text-red-500 text-sm mt-1">{{ form.errors.title }}</p>
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-slate-700 mb-2">Description *</label>
              <textarea 
                v-model="form.description" 
                class="input w-full h-32 resize-none" 
                placeholder="Describe your event..."
                required
              ></textarea>
              <p v-if="form.errors.description" class="text-red-500 text-sm mt-1">{{ form.errors.description }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Venue</label>
              <input 
                v-model="form.venue" 
                type="text" 
                class="input w-full" 
                placeholder="Event venue"
              />
              <p v-if="form.errors.venue" class="text-red-500 text-sm mt-1">{{ form.errors.venue }}</p>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Event Starts *</label>
              <input 
                v-model="form.start_datetime" 
                type="datetime-local" 
                class="input w-full"
                required
              />
              <p v-if="form.errors.start_datetime" class="text-red-500 text-sm mt-1">{{ form.errors.start_datetime }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Event Ends *</label>
              <input 
                v-model="form.end_datetime" 
                type="datetime-local" 
                class="input w-full"
                required
              />
              <p v-if="form.errors.end_datetime" class="text-red-500 text-sm mt-1">{{ form.errors.end_datetime }}</p>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Capacity</label>
              <input 
                v-model.number="form.capacity" 
                type="number" 
                min="1"
                class="input w-full" 
                placeholder="Maximum attendees"
              />
              <p v-if="form.errors.capacity" class="text-red-500 text-sm mt-1">{{ form.errors.capacity }}</p>
            </div>

            <div class="flex items-center space-x-6">
              <label class="flex items-center">
                <input 
                  v-model="form.is_active" 
                  type="checkbox" 
                  class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                />
                <span class="ml-2 text-sm font-medium text-slate-700">Active</span>
              </label>

              <label class="flex items-center">
                <input 
                  v-model="form.is_featured" 
                  type="checkbox" 
                  class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                />
                <span class="ml-2 text-sm font-medium text-slate-700">Featured</span>
              </label>
            </div>
          </div>
        </section>

        <!-- Event Image -->
        <section class="bg-white rounded-lg border border-slate-200 p-6">
          <h2 class="text-xl font-semibold text-slate-900 mb-6">Event Cover Image</h2>
          
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Current Image</label>
              <div v-if="event.image" class="mb-4">
                <img 
                  :src="imagePreview" 
                  alt="Current event image" 
                  class="h-48 w-full object-cover rounded-lg border border-slate-200"
                />
                <p class="text-sm text-slate-500 mt-2">Current: {{ event.image }}</p>
              </div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Upload New Image</label>
              <input 
                type="file" 
                @input="handleImageUpload"
                accept="image/*"
                class="input w-full"
              />
              <p class="text-sm text-slate-500 mt-1">Recommended: 1920x1080px, Max 2MB</p>
            </div>
          </div>
        </section>

        <!-- Promotional Media -->
        <section class="bg-white rounded-lg border border-slate-200 p-6">
          <h2 class="text-xl font-semibold text-slate-900 mb-6">Promotional Media</h2>
          
          <div class="space-y-6">
            <!-- Existing Media -->
            <div v-if="existingMedia.length > 0">
              <h3 class="text-lg font-medium text-slate-800 mb-4">Current Media</h3>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div 
                  v-for="(media, index) in existingMedia" 
                  :key="media.id"
                  class="relative group"
                >
                  <div class="aspect-square rounded-lg overflow-hidden border-2 transition-colors"
                       :class="media.is_main_image ? 'border-blue-500' : 'border-slate-200'">
                    <img 
                      v-if="media.media_type === 'image'" 
                      :src="`/storage/${media.media_url}`" 
                      :alt="media.title || `Media ${index + 1}`"
                      class="w-full h-full object-cover"
                    />
                    <video 
                      v-else 
                      :src="`/storage/${media.media_url}`" 
                      class="w-full h-full object-cover"
                      controls
                    ></video>
                  </div>

                  <button 
                    type="button"
                    @click="removeExistingMedia(index)"
                    class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                  >
                    ×
                  </button>

                  <button 
                    type="button"
                    v-if="media.media_type === 'image'"
                    @click="setMainImage(index, true)"
                    class="absolute bottom-2 left-2 bg-blue-500 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity"
                  >
                    {{ media.is_main_image ? 'Main' : 'Set Main' }}
                  </button>

                  <div v-if="media.media_type === 'image'" class="mt-2">
                    <label class="block text-xs font-medium text-slate-700 mb-1">Title</label>
                    <input 
                      v-model="media.title" 
                      type="text" 
                      class="input w-full text-xs"
                      placeholder="Media title"
                    />
                  </div>
                </div>
              </div>
            </div>

            <!-- New Media Upload -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Upload Additional Images & Videos</label>
              <input 
                type="file" 
                @input="handleMediaUpload"
                accept="image/*,video/*"
                multiple
                class="input w-full"
              />
              <p class="text-sm text-slate-500 mt-1">You can upload multiple images and videos.</p>
            </div>

            <!-- New Media Preview -->
            <div v-if="mediaFiles.length > 0" class="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div 
                v-for="(media, index) in mediaFiles" 
                :key="`new-${index}`"
                class="relative group"
              >
                <div class="aspect-square rounded-lg overflow-hidden border-2 transition-colors"
                     :class="media.is_main_image ? 'border-blue-500' : 'border-slate-200'">
                  <img 
                    v-if="media.media_type === 'image'" 
                    :src="media.preview" 
                    :alt="`New Media ${index + 1}`"
                    class="w-full h-full object-cover"
                  />
                  <video 
                    v-else 
                    :src="media.preview" 
                    class="w-full h-full object-cover"
                    controls
                  ></video>
                </div>

                <button 
                  type="button"
                  @click="removeMedia(index)"
                  class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                >
                  ×
                </button>

                <button 
                  type="button"
                  v-if="media.media_type === 'image'"
                  @click="setMainImage(existingMedia.length + index, false)"
                  class="absolute bottom-2 left-2 bg-blue-500 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity"
                >
                  {{ media.is_main_image ? 'Main' : 'Set Main' }}
                </button>

                <div v-if="media.media_type === 'image'" class="mt-2">
                  <label class="block text-xs font-medium text-slate-700 mb-1">Title</label>
                  <input 
                    v-model="media.title" 
                    type="text" 
                    class="input w-full text-xs"
                    placeholder="Media title"
                  />
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- Ticket Sales Settings -->
        <section class="bg-white rounded-lg border border-slate-200 p-6">
          <h2 class="text-xl font-semibold text-slate-900 mb-6">Ticket Sales Settings</h2>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Sales Start Date</label>
              <input 
                v-model="form.ticket_sales_start" 
                type="datetime-local" 
                class="input w-full"
              />
              <p v-if="form.errors.ticket_sales_start" class="text-red-500 text-sm mt-1">{{ form.errors.ticket_sales_start }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Sales End Date</label>
              <input 
                v-model="form.ticket_sales_end" 
                type="datetime-local" 
                class="input w-full"
              />
              <p v-if="form.errors.ticket_sales_end" class="text-red-500 text-sm mt-1">{{ form.errors.ticket_sales_end }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Max Tickets Per Person</label>
              <input 
                v-model.number="form.max_tickets_per_person" 
                type="number" 
                min="1" 
                max="20"
                class="input w-full"
              />
              <p v-if="form.errors.max_tickets_per_person" class="text-red-500 text-sm mt-1">{{ form.errors.max_tickets_per_person }}</p>
            </div>
          </div>
        </section>

        <!-- Ticket Types -->
        <section class="bg-white rounded-lg border border-slate-200 p-6">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-semibold text-slate-900">Ticket Types</h2>
            <button 
              type="button"
              @click="addTicketType"
              class="btn-secondary"
            >
              + Add Ticket Type
            </button>
          </div>
          
          <div class="space-y-6">
            <div 
              v-for="(ticket, index) in form.ticket_types" 
              :key="ticket.id || `new-${index}`"
              class="border border-slate-200 rounded-lg p-6"
            >
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-slate-900">Ticket Type {{ index + 1 }}</h3>
                <button 
                  v-if="form.ticket_types.length > 1"
                  type="button"
                  @click="removeTicketType(index)"
                  class="text-red-500 hover:text-red-700 text-sm"
                >
                  Remove
                </button>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-2">Name *</label>
                  <input 
                    v-model="ticket.name" 
                    type="text" 
                    class="input w-full"
                    placeholder="e.g., Regular Admission"
                    required
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-2">Price (₦) *</label>
                  <input 
                    v-model.number="ticket.price" 
                    type="number" 
                    min="0" 
                    step="0.01"
                    class="input w-full"
                    placeholder="0.00"
                    required
                  />
                </div>

                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-slate-700 mb-2">Description</label>
                  <textarea 
                    v-model="ticket.description" 
                    class="input w-full h-20 resize-none"
                    placeholder="Describe this ticket type..."
                  ></textarea>
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-2">Quantity Available *</label>
                  <input 
                    v-model.number="ticket.quantity_available" 
                    type="number" 
                    min="1"
                    class="input w-full"
                    required
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-2">Max Per Person</label>
                  <input 
                    v-model.number="ticket.max_per_person" 
                    type="number" 
                    min="1" 
                    max="20"
                    class="input w-full"
                  />
                </div>

                

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-2">Color Code</label>
                  <input 
                    v-model="ticket.color_code" 
                    type="color" 
                    class="input w-full h-10"
                  />
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- Table Reservations -->
        <section class="bg-white rounded-lg border border-slate-200 p-6">
          <h2 class="text-xl font-semibold text-slate-900 mb-6">Table Reservations</h2>
          
          <div class="space-y-4">
            <label class="flex items-center">
              <input 
                v-model="form.has_table_reservations" 
                type="checkbox" 
                class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
              />
              <span class="ml-2 text-sm font-medium text-slate-700">Enable Table Reservations</span>
            </label>

            <!--<div v-if="form.has_table_reservations" class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Table Capacity</label>
                <input 
                  v-model.number="form.table_capacity" 
                  type="number" 
                  min="1"
                  class="input w-full"
                  placeholder="Number of tables available"
                />
                <p v-if="form.errors.table_capacity" class="text-red-500 text-sm mt-1">{{ form.errors.table_capacity }}</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Table Price (₦)</label>
                <input 
                  v-model.number="form.table_price" 
                  type="number" 
                  min="0" 
                  step="0.01"
                  class="input w-full"
                  placeholder="0.00"
                />
                <p v-if="form.errors.table_price" class="text-red-500 text-sm mt-1">{{ form.errors.table_price }}</p>
              </div>
            </div>-->
          </div>
        </section>

        <!-- Table Types -->
        <section class="bg-white rounded-lg border border-slate-200 p-6">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-semibold text-slate-900">Table Types</h2>
            <button 
              type="button"
              @click="addTableType"
              class="btn-secondary"
            >
              + Add Table Type
            </button>
          </div>
          
          <div class="space-y-6">
            <div 
              v-for="(tableType, index) in form.table_types" 
              :key="tableType.id || `new-${index}`"
              class="border border-slate-200 rounded-lg p-6"
            >
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-slate-900">Table Type {{ index + 1 }}</h3>
                <button 
                  v-if="form.table_types.length > 1"
                  type="button"
                  @click="removeTableType(index)"
                  class="text-red-500 hover:text-red-700 text-sm"
                >
                  Remove
                </button>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-2">Name *</label>
                  <input 
                    v-model="tableType.name" 
                    type="text" 
                    class="input w-full"
                    placeholder="e.g., VIP Table, Premium Booth"
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-2">Price (₦) *</label>
                  <input 
                    v-model.number="tableType.price" 
                    type="number" 
                    min="0" 
                    step="0.01"
                    class="input w-full"
                    placeholder="0.00"
                  />
                </div>

                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-slate-700 mb-2">Description</label>
                  <textarea 
                    v-model="tableType.description" 
                    class="input w-full h-20 resize-none"
                    placeholder="Describe this table type..."
                  ></textarea>
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-2">Capacity *</label>
                  <input 
                    v-model.number="tableType.capacity" 
                    type="number" 
                    min="1"
                    class="input w-full"
                    placeholder="Number of seats"
                  />
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- Submit Buttons -->
        <div class="flex items-center justify-end space-x-4">
          <Link 
            href="/admin/events"
            class="btn-secondary"
          >
            Cancel
          </Link>
          <button 
            type="submit"
            :disabled="form.processing || !isFormValid"
            class="btn-primary"
          >
            {{ form.processing ? 'Updating...' : 'Update Event' }}
          </button>
        </div>
      </form>
    </div>
  </ManagerLayout>
</template>