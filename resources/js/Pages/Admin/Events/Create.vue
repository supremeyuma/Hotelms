<script setup>
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const form = useForm({
  title: '',
  description: '',
  event_date: '',
  start_time: '',
  end_date: '',
  end_time: '',
  venue: '',
  capacity: '',
  is_active: true,
  is_featured: false,
  ticket_sales_start: '',
  ticket_sales_end: '',
  max_tickets_per_person: 10,
  has_table_reservations: false,
  table_capacity: 0,
  table_price: 0,
  image: null,
  promotional_media: [],
  ticket_types: [
    {
      name: '',
      description: '',
      price: 0,
      quantity_available: 100,
      max_per_person: 10,
      sales_start: '',
      sales_end: '',
      color_code: '#3B82F6'
    }
  ],
  table_types: [
    {
      name: '',
      description: '',
      price: 0,
      capacity: 10,
      sales_start: '',
      sales_end: '',
      color_code: '#3B82F6'
    }
  ]
})

const imagePreview = ref(null)
const mediaFiles = ref([])
const mainImageIndex = ref(0)

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

const removeMedia = (index) => {
  mediaFiles.value.splice(index, 1)
  if (mainImageIndex.value >= mediaFiles.value.length) {
    mainImageIndex.value = 0
  }
}

const setMainImage = (index) => {
  mainImageIndex.value = index
  mediaFiles.value.forEach((media, i) => {
    media.is_main_image = i === index
  })
}

const addTicketType = () => {
  form.ticket_types.push({
    name: '',
    description: '',
    price: 0,
    quantity_available: 100,
    max_per_person: 10,
    sales_start: '',
    sales_end: '',
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
    sales_start: '',
    sales_end: '',
    color_code: '#' + Math.floor(Math.random()*16777215).toString(16)
  })
}

const removeTableType = (index) => {
  if (form.table_types.length > 1) {
    form.table_types.splice(index, 1)
  }
}

const addTableType = () => {
  form.table_types.push({
    name: '',
    description: '',
    price: 0,
    capacity: 10,
    sales_start: '',
    sales_end: '',
    color_code: '#' + Math.floor(Math.random()*16777215).toString(16)
  })
}

const removeTableType = (index) => {
  if (form.table_types.length > 1) {
    form.table_types.splice(index, 1)
  }
}

const submit = () => {
  // Convert media files to form data
  const formData = new FormData()
  
  // Combine end_date and end_time for backend compatibility
  if (form.end_date && form.end_time) {
    formData.append('end_time', new Date(`${form.end_date}T${form.end_time}`).toISOString())
  } else if (form.end_time) {
    // If only end_time is provided, assume same day as event_date
    formData.append('end_time', new Date(`${form.event_date}T${form.end_time}`).toISOString())
  }
  
  // Add all form fields
  Object.keys(form.data()).forEach(key => {
    if (key !== 'promotional_media' && key !== 'ticket_types' && key !== 'image' && key !== 'end_date' && key !== 'end_time') {
      formData.append(key, form[key])
    }
  })

  // Add image if exists
  if (form.image) {
    formData.append('image', form.image)
  }

  // Add promotional media
  mediaFiles.value.forEach((media, index) => {
    formData.append(`media[${index}][file]`, media.file)
    formData.append(`media[${index}][media_type]`, media.media_type)
    formData.append(`media[${index}][title]`, media.title)
    formData.append(`media[${index}][description]`, media.description)
    if (media.is_main_image) {
      formData.append(`media[${index}][is_main_image]`, true)
    }
  })

  // Add ticket types
  form.ticket_types.forEach((ticket, index) => {
    Object.keys(ticket).forEach(key => {
      formData.append(`ticket_types[${index}][${key}]`, ticket[key])
    })
  })

  form.post('/admin/events', {
    forceFormData: true
  })
}

const isFormValid = computed(() => {
  return form.title && form.event_date && form.description
})
</script>

<template>
  <ManagerLayout>
    <div class="max-w-6xl mx-auto p-6 space-y-8">
      <div class="flex items-center justify-between">
        <h1 class="text-3xl font-black text-slate-900">Create Event</h1>
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
              <label class="block text-sm font-medium text-slate-700 mb-2">Event Date *</label>
              <input 
                v-model="form.event_date" 
                type="date" 
                class="input w-full"
                :min="new Date().toISOString().split('T')[0]"
                required
              />
              <p v-if="form.errors.event_date" class="text-red-500 text-sm mt-1">{{ form.errors.event_date }}</p>
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
              <label class="block text-sm font-medium text-slate-700 mb-2">Start Time</label>
              <input 
                v-model="form.start_time" 
                type="time" 
                class="input w-full"
              />
              <p v-if="form.errors.start_time" class="text-red-500 text-sm mt-1">{{ form.errors.start_time }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">End Date</label>
              <input 
                v-model="form.end_date" 
                type="date" 
                class="input w-full"
                :min="form.event_date"
              />
              <p v-if="form.errors.end_date" class="text-red-500 text-sm mt-1">{{ form.errors.end_date }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">End Time</label>
              <input 
                v-model="form.end_time" 
                type="time" 
                class="input w-full"
              />
              <p v-if="form.errors.end_time" class="text-red-500 text-sm mt-1">{{ form.errors.end_time }}</p>
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
              <label class="block text-sm font-medium text-slate-700 mb-2">Cover Image</label>
              <input 
                type="file" 
                @input="handleImageUpload"
                accept="image/*"
                class="input w-full"
              />
              <p class="text-sm text-slate-500 mt-1">Recommended: 1920x1080px, Max 2MB</p>
            </div>

            <div v-if="imagePreview" class="mt-4">
              <img 
                :src="imagePreview" 
                alt="Event image preview" 
                class="h-48 w-full object-cover rounded-lg border border-slate-200"
              />
            </div>
          </div>
        </section>

        <!-- Promotional Media -->
        <section class="bg-white rounded-lg border border-slate-200 p-6">
          <h2 class="text-xl font-semibold text-slate-900 mb-6">Promotional Media</h2>
          
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Upload Images & Videos</label>
              <input 
                type="file" 
                @input="handleMediaUpload"
                accept="image/*,video/*"
                multiple
                class="input w-full"
              />
              <p class="text-sm text-slate-500 mt-1">You can upload multiple images and videos. The first image will be set as main image.</p>
            </div>

            <div v-if="mediaFiles.length > 0" class="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div 
                v-for="(media, index) in mediaFiles" 
                :key="index"
                class="relative group"
              >
                <div class="aspect-square rounded-lg overflow-hidden border-2 transition-colors"
                     :class="media.is_main_image ? 'border-blue-500' : 'border-slate-200'">
                  <img 
                    v-if="media.media_type === 'image'" 
                    :src="media.preview" 
                    :alt="`Media ${index + 1}`"
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
                  @click="setMainImage(index)"
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
              <label class="block text-sm font-medium text-slate-700 mb-2">Sales Start Date & Time</label>
              <input 
                v-model="form.ticket_sales_start" 
                type="datetime-local" 
                class="input w-full"
              />
              <p v-if="form.errors.ticket_sales_start" class="text-red-500 text-sm mt-1">{{ form.errors.ticket_sales_start }}</p>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Sales End Date & Time</label>
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
              :key="index"
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
                  <label class="block text-sm font-medium text-slate-700 mb-2">Sales Start</label>
                  <input 
                    v-model="ticket.sales_start" 
                    type="date" 
                    class="input w-full"
                  />
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-2">Sales End</label>
                  <input 
                    v-model="ticket.sales_end" 
                    type="date" 
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

            <div v-if="form.has_table_reservations" class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
            </div>
          </div>
        </section>

        <!-- Table Types -->
        <section class="bg-white rounded-lg border border-slate-200 p-6">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-semibold text-slate-900 mb-6">Table Types</h2>
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
                <label class="block text-sm font-medium text-slate-700 mb-2">Description</label>
                <textarea 
                  v-model="tableType.description" 
                  class="input w-full h-20 resize-none"
                  placeholder="Describe this table type..."
                ></textarea>
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
            {{ form.processing ? 'Creating...' : 'Create Event' }}
          </button>
        </div>
      </form>
    </div>
  </ManagerLayout>
</template>