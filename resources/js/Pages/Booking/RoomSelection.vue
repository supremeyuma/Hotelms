<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import ImageLightbox from '@/Components/Booking/ImageLightbox.vue';
import {
  BedDouble,
  Users,
  Baby,
  ChevronRight,
  Info,
  Calendar,
  Image as ImageIcon,
  CheckCircle2,
  Layers3,
} from 'lucide-vue-next';

const props = defineProps({
  roomTypes: Array,
  check_in: String,
  check_out: String,
  adults: Number,
  children: Number,
  imageSettings: {
    type: Object,
    default: () => ({
      show_room_images: true,
      show_room_type_images: true,
    }),
  },
});

const expandedRoomTypeId = ref(null);
const selectedRoomIdsByType = reactive({});
const galleryImages = ref([]);
const galleryTitle = ref('');
const galleryIndex = ref(0);
const isGalleryOpen = ref(false);

props.roomTypes.forEach((roomType) => {
  selectedRoomIdsByType[roomType.id] = [];
});

const formatDate = (dateStr) =>
  new Date(dateStr).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });

const formatCurrency = (amount) => new Intl.NumberFormat('en-NG').format(amount || 0);

const roomLimitText = (roomType) => {
  if (roomType.available_quantity === 0) return 'Sold out';
  if (roomType.available_quantity === 1) return '1 room available';
  return `${roomType.available_quantity} rooms available`;
};

const selectedCount = (roomTypeId) => selectedRoomIdsByType[roomTypeId]?.length ?? 0;

const isSelected = (roomTypeId, roomId) => selectedRoomIdsByType[roomTypeId]?.includes(roomId);

function toggleRoom(roomTypeId, roomId) {
  const selected = selectedRoomIdsByType[roomTypeId] ?? [];

  if (selected.includes(roomId)) {
    selectedRoomIdsByType[roomTypeId] = selected.filter((id) => id !== roomId);
    return;
  }

  selectedRoomIdsByType[roomTypeId] = [...selected, roomId];
}

function handleRoomCardKeydown(event, roomTypeId, roomId) {
  if (event.key !== 'Enter' && event.key !== ' ') {
    return;
  }

  event.preventDefault();
  toggleRoom(roomTypeId, roomId);
}

function submitSelection(roomType) {
  router.post('/booking/select-room', {
    room_type_id: roomType.id,
    selected_room_ids: selectedRoomIdsByType[roomType.id] ?? [],
    check_in: props.check_in,
    check_out: props.check_out,
    adults: props.adults,
    children: props.children,
  }, {
    preserveScroll: true,
  });
}

function primaryImage(item) {
  return item.primary_image_url || item.images?.[0]?.url || null;
}

function hasImages(item) {
  return Array.isArray(item?.images) && item.images.length > 0;
}

function openGallery(images, title, index = 0) {
  if (!Array.isArray(images) || images.length === 0) {
    return;
  }

  galleryImages.value = images;
  galleryTitle.value = title;
  galleryIndex.value = index;
  isGalleryOpen.value = true;
}

function closeGallery() {
  isGalleryOpen.value = false;
}

function roomSubtitle(room) {
  const bits = [];

  if (room.floor) bits.push(`Floor ${room.floor}`);
  if (room.code) bits.push(room.code);
  if (room.meta?.view) bits.push(room.meta.view);

  return bits.join(' - ');
}

const occupancyText = computed(() => {
  const bits = [`${props.adults} adult${props.adults > 1 ? 's' : ''}`];
  if (props.children > 0) bits.push(`${props.children} child${props.children > 1 ? 'ren' : ''}`);
  return bits.join(', ');
});

const roomImageVisibilityEnabled = computed(() => Boolean(props.imageSettings?.show_room_images));
const roomTypeImageVisibilityEnabled = computed(() => Boolean(props.imageSettings?.show_room_type_images));
</script>

<template>
  <PublicLayout>
    <div class="min-h-screen bg-slate-50/50 pb-20">
      <div class="bg-white border-b border-slate-200 sticky top-0 z-10 shadow-sm">
        <div class="max-w-6xl mx-auto px-4 py-4 flex flex-wrap items-center justify-between gap-4">
          <div class="flex items-center gap-6">
            <div class="hidden md:flex items-center gap-2 text-slate-400">
              <Calendar class="w-5 h-5" />
              <span class="text-sm font-bold uppercase tracking-wider">Your Stay</span>
            </div>
            <div class="flex items-center gap-3">
              <div class="text-sm">
                <p class="text-[10px] font-black text-indigo-600 uppercase tracking-widest">Check-in</p>
                <p class="font-bold text-slate-900">{{ formatDate(check_in) }}</p>
              </div>
              <ChevronRight class="w-4 h-4 text-slate-300" />
              <div class="text-sm">
                <p class="text-[10px] font-black text-indigo-600 uppercase tracking-widest">Check-out</p>
                <p class="font-bold text-slate-900">{{ formatDate(check_out) }}</p>
              </div>
            </div>
          </div>
          <div class="flex items-center gap-4 text-sm font-bold text-slate-600 bg-slate-100 px-4 py-2 rounded-2xl">
            <span class="flex items-center gap-1.5"><Users class="w-4 h-4" /> {{ occupancyText }}</span>
          </div>
        </div>
      </div>

      <div class="max-w-6xl mx-auto px-4 pt-12">
        <div class="mb-10">
          <h1 class="text-4xl font-black text-slate-900 tracking-tight mb-2">Choose Your Room</h1>
          <p class="text-slate-500 font-medium">
            Pick the room you want in one step. Your selection will be reserved for this booking while payment is pending.
          </p>
        </div>

        <div class="space-y-8">
          <div
            v-for="roomType in roomTypes"
            :key="roomType.id"
            class="bg-white rounded-[2.5rem] border border-slate-200 overflow-hidden shadow-sm"
          >
            <div class="grid lg:grid-cols-[1.1fr,1fr] gap-0">
              <div
                class="relative min-h-[280px]"
                :class="roomTypeImageVisibilityEnabled
                  ? ['bg-slate-100', hasImages(roomType) ? 'cursor-zoom-in' : '']
                  : 'bg-slate-950'"
                @click="roomTypeImageVisibilityEnabled && hasImages(roomType) ? openGallery(roomType.images, `${roomType.name} photos`) : null"
              >
                <img
                  v-if="roomTypeImageVisibilityEnabled && primaryImage(roomType)"
                  :src="primaryImage(roomType)"
                  :alt="roomType.name"
                  class="absolute inset-0 h-full w-full object-cover"
                />
                <div v-else-if="roomTypeImageVisibilityEnabled" class="absolute inset-0 flex items-center justify-center text-slate-300">
                  <ImageIcon class="w-16 h-16" />
                </div>
                <div
                  v-if="roomTypeImageVisibilityEnabled && hasImages(roomType)"
                  class="pointer-events-none absolute left-6 top-6 z-10 inline-flex items-center gap-2 rounded-full border border-white/30 bg-slate-950/45 px-4 py-2 text-[11px] font-black uppercase tracking-[0.22em] text-white backdrop-blur"
                >
                  <ImageIcon class="h-4 w-4" />
                  View Photos
                  <span class="text-white/70">{{ roomType.images.length }}</span>
                </div>
                <div
                  class="absolute inset-0"
                  :class="roomTypeImageVisibilityEnabled
                    ? 'bg-gradient-to-t from-slate-950/75 via-slate-950/10 to-transparent'
                    : 'bg-gradient-to-r from-slate-950 via-slate-900 to-slate-800'"
                ></div>
                <div class="absolute left-6 right-6 bottom-6 flex items-end justify-between gap-4">
                  <div>
                    <p class="text-[10px] font-black text-white/70 uppercase tracking-[0.3em] mb-2">Room Type</p>
                    <h2 class="text-3xl font-black text-white tracking-tight">{{ roomType.name }}</h2>
                    <p class="text-sm text-white/75 font-semibold mt-1">{{ roomLimitText(roomType) }}</p>
                  </div>
                  <div class="rounded-2xl bg-white/90 backdrop-blur px-4 py-3 text-right">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">From</p>
                    <p class="text-2xl font-black text-slate-900">₦{{ formatCurrency(roomType.price_per_night) }}</p>
                  </div>
                </div>
              </div>

              <div class="p-8 lg:p-10">
                <div class="flex flex-wrap gap-3 text-xs font-bold text-slate-500 mb-5">
                  <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1.5">
                    <Users class="w-4 h-4 text-indigo-500" /> Max {{ roomType.max_adults }} Guests
                  </span>
                  <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1.5">
                    <Layers3 class="w-4 h-4 text-indigo-500" /> {{ roomType.available_rooms?.length || 0 }} rooms shown
                  </span>
                  <span v-if="children > 0" class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1.5">
                    <Baby class="w-4 h-4 text-indigo-500" /> Family stay
                  </span>
                </div>

                <p class="text-slate-500 font-medium leading-relaxed mb-8">
                  {{ roomType.description || 'Comfortable accommodation prepared for your selected dates.' }}
                </p>

                <button
                  type="button"
                  @click="expandedRoomTypeId = expandedRoomTypeId === roomType.id ? null : roomType.id"
                  class="inline-flex items-center gap-2 rounded-2xl bg-slate-900 px-5 py-3 text-sm font-black uppercase tracking-widest text-white transition hover:bg-indigo-600"
                >
                  {{ expandedRoomTypeId === roomType.id ? 'Hide rooms' : 'Choose a room' }}
                  <ChevronRight class="w-4 h-4 transition-transform" :class="expandedRoomTypeId === roomType.id ? 'rotate-90' : ''" />
                </button>
              </div>
            </div>

            <div v-if="expandedRoomTypeId === roomType.id" class="border-t border-slate-200 bg-slate-50/70 p-6 md:p-8">
              <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-6">
                <div>
                  <h3 class="text-xl font-black text-slate-900 tracking-tight">Available Rooms</h3>
                  <p class="text-sm text-slate-500 font-medium">
                    Select the exact room you would like us to hold for this reservation.
                  </p>
                </div>
                <div class="rounded-2xl bg-white px-4 py-3 border border-slate-200">
                  <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Selected</p>
                  <p class="text-lg font-black text-slate-900">{{ selectedCount(roomType.id) }} room<span v-if="selectedCount(roomType.id) !== 1">s</span></p>
                </div>
              </div>

              <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <div
                  v-for="room in roomType.available_rooms"
                  :key="room.id"
                  role="button"
                  tabindex="0"
                  @click="toggleRoom(roomType.id, room.id)"
                  @keydown="handleRoomCardKeydown($event, roomType.id, room.id)"
                  class="group overflow-hidden rounded-[2rem] border text-left transition-all"
                  :class="isSelected(roomType.id, room.id)
                    ? 'border-indigo-500 bg-white shadow-xl shadow-indigo-100'
                    : 'border-slate-200 bg-white hover:border-slate-300 hover:shadow-lg'"
                >
                  <div
                    v-if="roomImageVisibilityEnabled"
                    class="relative h-48 bg-slate-100"
                    :class="hasImages(room) ? 'cursor-zoom-in' : ''"
                    @click.stop="hasImages(room) ? openGallery(room.images, `${room.name} photos`) : null"
                  >
                    <img
                      v-if="primaryImage(room)"
                      :src="primaryImage(room)"
                      :alt="room.name"
                      class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                    />
                    <div v-else class="absolute inset-0 flex items-center justify-center text-slate-300">
                      <BedDouble class="w-14 h-14" />
                    </div>
                    <div
                      v-if="hasImages(room)"
                      class="pointer-events-none absolute left-4 top-4 z-10 inline-flex items-center gap-2 rounded-full border border-white/70 bg-white/90 px-3 py-2 text-[10px] font-black uppercase tracking-[0.18em] text-slate-700 backdrop-blur"
                    >
                      <ImageIcon class="h-4 w-4" />
                      View
                    </div>
                    <div class="absolute right-4 top-4">
                      <div
                        class="flex h-10 w-10 items-center justify-center rounded-2xl border backdrop-blur"
                        :class="isSelected(roomType.id, room.id)
                          ? 'border-indigo-500 bg-indigo-600 text-white'
                          : 'border-white/60 bg-white/90 text-slate-500'"
                      >
                        <CheckCircle2 class="w-5 h-5" />
                      </div>
                    </div>
                  </div>

                  <div class="p-5" :class="roomImageVisibilityEnabled ? '' : 'relative'">
                    <div v-if="!roomImageVisibilityEnabled" class="absolute right-5 top-5">
                      <div
                        class="flex h-10 w-10 items-center justify-center rounded-2xl border backdrop-blur"
                        :class="isSelected(roomType.id, room.id)
                          ? 'border-indigo-500 bg-indigo-600 text-white'
                          : 'border-slate-200 bg-slate-50 text-slate-500'"
                      >
                        <CheckCircle2 class="w-5 h-5" />
                      </div>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                      <div :class="!roomImageVisibilityEnabled ? 'pr-14' : ''">
                        <h4 class="text-lg font-black text-slate-900">{{ room.name }}</h4>
                        <p v-if="roomSubtitle(room)" class="mt-1 text-xs font-bold uppercase tracking-widest text-slate-400">
                          {{ roomSubtitle(room) }}
                        </p>
                      </div>
                    </div>

                    <p class="mt-4 text-sm text-slate-500 font-medium min-h-[40px]">
                      {{ room.meta?.description || roomType.description || 'Room details available for your selected stay.' }}
                    </p>
                  </div>
                </div>
              </div>

              <div class="mt-8 flex flex-col gap-4 border-t border-slate-200 pt-6 md:flex-row md:items-center md:justify-between">
                <p class="text-sm font-medium text-slate-500">
                  Your selected room<span v-if="selectedCount(roomType.id) !== 1">s</span> will appear in the review step before payment.
                </p>

                <button
                  type="button"
                  :disabled="selectedCount(roomType.id) === 0"
                  @click="submitSelection(roomType)"
                  class="inline-flex items-center justify-center gap-3 rounded-[1.5rem] bg-slate-900 px-8 py-4 text-sm font-black uppercase tracking-widest text-white transition hover:bg-indigo-600 disabled:cursor-not-allowed disabled:opacity-40"
                >
                  Continue with Selected Room
                  <ChevronRight class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
        </div>

        <div v-if="roomTypes.length === 0" class="mt-12 text-center py-20 bg-white rounded-[3rem] border border-slate-200">
          <div class="p-6 bg-slate-50 rounded-full inline-flex mb-6 text-slate-300">
            <Info class="w-12 h-12" />
          </div>
          <h2 class="text-2xl font-black text-slate-900 mb-2">No availability for these dates</h2>
          <p class="text-slate-500 font-medium mb-8">Try adjusting your check-in dates or guest count.</p>
          <button @click="router.visit('/booking')" class="text-indigo-600 font-black flex items-center gap-2 mx-auto hover:gap-4 transition-all">
            Modify Search <ChevronRight class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>
    <ImageLightbox
      :show="isGalleryOpen"
      :images="galleryImages"
      :start-index="galleryIndex"
      :title="galleryTitle"
      @close="closeGallery"
      @update:start-index="galleryIndex = $event"
    />
  </PublicLayout>
</template>
