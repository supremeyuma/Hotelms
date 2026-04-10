<script setup>
import Pagination from '@/Components/Pagination.vue'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, reactive, watch } from 'vue'
import {
  BedDouble,
  CircleDot,
  DoorOpen,
  ImagePlus,
  PenSquare,
  Search,
  ShieldAlert,
  Sparkles,
  Trash2,
  Wrench,
} from 'lucide-vue-next'

const props = defineProps({
  rooms: Object,
  filters: Object,
  overview: Object,
  statusOptions: Array,
})

const filters = reactive({
  search: props.filters?.search ?? '',
  status: props.filters?.status ?? 'all',
})

watch(
  () => [filters.search, filters.status],
  ([search, status]) => {
    router.get(route('admin.rooms.index'), { search, status }, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    })
  }
)

const metricCards = computed(() => [
  {
    key: 'occupied',
    label: 'Occupied',
    filterValue: 'occupied',
    value: props.overview?.occupied ?? 0,
    helper: 'Currently in use',
    tone: 'indigo',
    icon: DoorOpen,
  },
  {
    key: 'available',
    label: 'Available',
    filterValue: 'available',
    value: props.overview?.available ?? 0,
    helper: 'Ready to assign',
    tone: 'emerald',
    icon: Sparkles,
  },
  {
    key: 'dirty',
    label: 'Dirty',
    filterValue: 'dirty',
    value: props.overview?.dirty ?? 0,
    helper: 'Needs housekeeping',
    tone: 'amber',
    icon: CircleDot,
  },
  {
    key: 'clean',
    label: 'Clean',
    filterValue: 'clean',
    value: props.overview?.clean ?? 0,
    helper: 'Housekeeping cleared',
    tone: 'sky',
    icon: BedDouble,
  },
  {
    key: 'maintenance',
    label: 'Maintenance',
    filterValue: 'maintenance',
    value: props.overview?.maintenance ?? 0,
    helper: 'Out of order',
    tone: 'rose',
    icon: Wrench,
  },
  {
    key: 'unavailable',
    label: 'Unavailable',
    filterValue: 'unavailable',
    value: props.overview?.unavailable ?? 0,
    helper: 'Manually blocked',
    tone: 'slate',
    icon: ShieldAlert,
  },
])

function palette(tone) {
  const palettes = {
    emerald: 'from-emerald-500/15 via-emerald-500/5 to-white text-emerald-700 border-emerald-100',
    indigo: 'from-indigo-500/15 via-indigo-500/5 to-white text-indigo-700 border-indigo-100',
    amber: 'from-amber-500/15 via-amber-500/5 to-white text-amber-700 border-amber-100',
    sky: 'from-sky-500/15 via-sky-500/5 to-white text-sky-700 border-sky-100',
    rose: 'from-rose-500/15 via-rose-500/5 to-white text-rose-700 border-rose-100',
    slate: 'from-slate-500/15 via-slate-500/5 to-white text-slate-700 border-slate-200',
  }

  return palettes[tone] ?? palettes.slate
}

function isMetricCardActive(card) {
  return filters.status === card.filterValue
}

function selectMetricCard(card) {
  filters.status = isMetricCardActive(card) ? 'all' : card.filterValue
}

function roomStatusConfig(status) {
  const map = {
    available: { label: 'Available', classes: 'bg-emerald-50 text-emerald-700 border-emerald-100' },
    occupied: { label: 'Occupied', classes: 'bg-indigo-50 text-indigo-700 border-indigo-100' },
    dirty: { label: 'Dirty', classes: 'bg-amber-50 text-amber-700 border-amber-100' },
    reserved: { label: 'Reserved', classes: 'bg-orange-50 text-orange-700 border-orange-100' },
    maintenance: { label: 'Maintenance', classes: 'bg-rose-50 text-rose-700 border-rose-100' },
    unavailable: { label: 'Unavailable', classes: 'bg-slate-100 text-slate-700 border-slate-200' },
  }

  return map[status] ?? { label: status || 'Unknown', classes: 'bg-slate-100 text-slate-700 border-slate-200' }
}

function cleaningStatusConfig(status) {
  const map = {
    clean: { label: 'Clean', classes: 'bg-sky-50 text-sky-700 border-sky-100' },
    dirty: { label: 'Dirty', classes: 'bg-amber-50 text-amber-700 border-amber-100' },
    cleaning: { label: 'Cleaning', classes: 'bg-violet-50 text-violet-700 border-violet-100' },
    cleaner_requested: { label: 'Cleaner requested', classes: 'bg-fuchsia-50 text-fuchsia-700 border-fuchsia-100' },
    unknown: { label: 'Not tracked', classes: 'bg-slate-100 text-slate-600 border-slate-200' },
  }

  return map[status] ?? map.unknown
}

function quickUpdateStatus(room, status) {
  if (room.status === status) return

  router.patch(route('admin.rooms.update-status', room.id), { status }, {
    preserveScroll: true,
  })
}

function deleteRoom(room) {
  if (!window.confirm(`Delete ${room.name}?`)) return

  router.delete(route('admin.rooms.destroy', room.id), {
    preserveScroll: true,
  })
}
</script>

<template>
  <ManagerLayout>
    <Head title="Rooms" />

    <div class="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
      <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(15,23,42,0.06),_transparent_32%),linear-gradient(135deg,_#ffffff,_#f8fafc_50%,_#eef2ff)] p-8 shadow-sm">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
          <div class="max-w-3xl">
            <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white/80 px-3 py-1 text-[11px] font-black uppercase tracking-[0.24em] text-slate-500">
              <CircleDot class="h-3.5 w-3.5" />
              Room Control Centre
            </div>
            <h1 class="text-4xl font-black tracking-tight text-slate-900">Manager rooms board</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">
              View occupancy, housekeeping condition, and room merchandising in one place. Primary room images here are what guests see during room selection.
            </p>
          </div>

          <div class="flex flex-col gap-3 sm:flex-row">
            <div class="relative">
              <Search class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
              <input
                v-model="filters.search"
                type="text"
                placeholder="Search room, code, or type"
                class="w-full rounded-2xl border border-slate-200 bg-white pl-11 pr-4 py-3 text-sm font-medium text-slate-700 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 sm:w-72"
              />
            </div>

            <select
              v-model="filters.status"
              class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
            >
              <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>

            <Link
              :href="route('admin.rooms.create')"
              class="inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800"
            >
              <ImagePlus class="h-4 w-4" />
              New Room
            </Link>
          </div>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <button
          v-for="card in metricCards"
          :key="card.key"
          type="button"
          @click="selectMetricCard(card)"
          :class="[
            'rounded-[1.75rem] border bg-gradient-to-br p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-offset-2',
            palette(card.tone),
            isMetricCardActive(card) ? 'ring-2 ring-slate-900/15 border-slate-300' : '',
          ]"
        >
          <div class="flex items-start justify-between">
            <div>
              <div class="flex items-center gap-2">
                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">{{ card.label }}</p>
                <span
                  v-if="isMetricCardActive(card)"
                  class="rounded-full bg-slate-900 px-2 py-0.5 text-[10px] font-black uppercase tracking-[0.16em] text-white"
                >
                  Active
                </span>
              </div>
              <p class="mt-3 text-3xl font-black tracking-tight text-slate-900">{{ card.value }}</p>
              <p class="mt-1 text-sm text-slate-600">{{ card.helper }}</p>
              <p class="mt-3 text-xs font-semibold text-slate-500">
                {{ isMetricCardActive(card) ? 'Click to clear this filter' : 'Click to filter rooms' }}
              </p>
            </div>
            <div class="rounded-2xl bg-white/80 p-3 shadow-sm">
              <component :is="card.icon" class="h-5 w-5" :class="card.tone === 'slate' ? 'text-slate-600' : ''" />
            </div>
          </div>
        </button>
      </section>

      <section v-if="rooms.data.length" class="grid gap-6 xl:grid-cols-2">
        <article
          v-for="room in rooms.data"
          :key="room.id"
          class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg"
        >
          <div class="grid gap-0 md:grid-cols-[220px_minmax(0,1fr)]">
            <div class="relative min-h-[220px] bg-slate-100">
              <img
                v-if="room.primary_image_url"
                :src="room.primary_image_url"
                :alt="room.name"
                class="absolute inset-0 h-full w-full object-cover"
              />
              <div v-else class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-[linear-gradient(135deg,_#f8fafc,_#e2e8f0)] text-slate-400">
                <BedDouble class="h-10 w-10" />
                <span class="text-xs font-bold uppercase tracking-[0.24em]">No primary image</span>
              </div>

              <div class="absolute left-4 top-4 inline-flex items-center gap-2 rounded-full bg-white/90 px-3 py-1 text-[11px] font-black uppercase tracking-[0.18em] text-slate-600 shadow-sm">
                <ImagePlus class="h-3.5 w-3.5" />
                {{ room.images.length }} image{{ room.images.length === 1 ? '' : 's' }}
              </div>
            </div>

            <div class="p-6">
              <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                  <p class="text-[11px] font-black uppercase tracking-[0.24em] text-slate-400">
                    {{ room.room_type?.title || 'Room' }}
                    <span v-if="room.property_name">· {{ room.property_name }}</span>
                  </p>
                  <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-900">{{ room.name }}</h2>
                  <p class="mt-2 text-sm text-slate-500">
                    Room {{ room.room_number || 'N/A' }}
                    <span v-if="room.code">· {{ room.code }}</span>
                    <span v-if="room.floor !== null">· Floor {{ room.floor }}</span>
                  </p>
                </div>

                <div class="flex flex-wrap gap-2">
                  <span :class="['rounded-full border px-3 py-1 text-xs font-bold', roomStatusConfig(room.status).classes]">
                    {{ roomStatusConfig(room.status).label }}
                  </span>
                  <span :class="['rounded-full border px-3 py-1 text-xs font-bold', cleaningStatusConfig(room.housekeeping?.status).classes]">
                    {{ cleaningStatusConfig(room.housekeeping?.status).label }}
                  </span>
                </div>
              </div>

              <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                  <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Stay context</p>
                  <template v-if="room.current_booking">
                    <p class="mt-3 font-bold text-slate-900">{{ room.current_booking.guest_name || 'Guest assigned' }}</p>
                    <p class="mt-1 text-sm text-slate-600">
                      {{ room.current_booking.booking_code }}
                      <span v-if="room.current_booking.check_in && room.current_booking.check_out">
                        · {{ room.current_booking.check_in }} to {{ room.current_booking.check_out }}
                      </span>
                    </p>
                  </template>
                  <p v-else class="mt-3 text-sm text-slate-500">No active or upcoming stay linked right now.</p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                  <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Guest listing image</p>
                  <p class="mt-3 text-sm text-slate-600">
                    <span v-if="room.primary_image_url">Primary image is set and ready for booking flow display.</span>
                    <span v-else>Add a primary image so guests see a curated room photo during selection.</span>
                  </p>
                </div>
              </div>

              <div class="mt-5 flex flex-col gap-3 border-t border-slate-100 pt-5 lg:flex-row lg:items-center lg:justify-between">
                <label class="flex items-center gap-3 text-sm font-semibold text-slate-700">
                  Quick status
                  <select
                    :value="room.status"
                    @change="quickUpdateStatus(room, $event.target.value)"
                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
                  >
                    <option
                      v-for="option in statusOptions.filter((option) => option.value !== 'all')"
                      :key="option.value"
                      :value="option.value"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                </label>

                <div class="flex flex-wrap gap-2">
                  <Link
                    :href="route('admin.rooms.edit', room.id)"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                  >
                    <PenSquare class="h-4 w-4" />
                    Edit room
                  </Link>
                  <button
                    type="button"
                    @click="deleteRoom(room)"
                    class="inline-flex items-center gap-2 rounded-xl border border-rose-200 px-4 py-2 text-sm font-bold text-rose-700 transition hover:bg-rose-50"
                  >
                    <Trash2 class="h-4 w-4" />
                    Delete
                  </button>
                </div>
              </div>
            </div>
          </div>
        </article>
      </section>

      <section v-else class="rounded-[2rem] border border-dashed border-slate-300 bg-white px-6 py-16 text-center shadow-sm">
        <DoorOpen class="mx-auto h-12 w-12 text-slate-300" />
        <h2 class="mt-4 text-xl font-black text-slate-900">No rooms match this view</h2>
        <p class="mt-2 text-sm text-slate-500">Try a different status filter or search term to bring rooms back into focus.</p>
      </section>

      <Pagination :links="rooms.links" />
    </div>
  </ManagerLayout>
</template>
