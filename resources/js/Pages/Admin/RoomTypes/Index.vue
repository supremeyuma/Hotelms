<script setup>
import Pagination from '@/Components/Pagination.vue'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { BedDouble, Pencil, Plus, Trash2, Users } from 'lucide-vue-next'

defineProps({
  types: Object,
})

function formatMoney(value) {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
  }).format(Number(value ?? 0))
}

function deleteType(type) {
  if (!window.confirm(`Delete ${type.title}?`)) return

  router.delete(route('admin.room-types.destroy', type.id), {
    preserveScroll: true,
  })
}
</script>

<template>
  <ManagerLayout>
    <Head title="Room Types" />

    <div class="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
      <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(15,23,42,0.06),_transparent_32%),linear-gradient(135deg,_#ffffff,_#f8fafc_50%,_#eef2ff)] p-8 shadow-sm">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
          <div class="max-w-3xl">
            <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white/80 px-3 py-1 text-[11px] font-black uppercase tracking-[0.24em] text-slate-500">
              <BedDouble class="h-3.5 w-3.5" />
              Room Catalogue
            </div>
            <h1 class="text-4xl font-black tracking-tight text-slate-900">Manage room types</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">
              Define the room categories your hotel sells, the standard rate, occupancy, and what guests should expect from each type.
            </p>
          </div>

          <Link
            :href="route('admin.room-types.create')"
            class="inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800"
          >
            <Plus class="h-4 w-4" />
            New room type
          </Link>
        </div>
      </section>

      <section v-if="types.data.length" class="grid gap-6 xl:grid-cols-2">
        <article
          v-for="type in types.data"
          :key="type.id"
          class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg"
        >
          <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-[11px] font-black uppercase tracking-[0.24em] text-slate-400">
                {{ type.property?.name || 'Property not linked' }}
              </p>
              <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-900">{{ type.title }}</h2>
            </div>

            <div class="rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-right">
              <p class="text-[11px] font-black uppercase tracking-[0.2em] text-emerald-600">Base rate</p>
              <p class="mt-1 text-xl font-black text-emerald-700">{{ formatMoney(type.base_price) }}</p>
            </div>
          </div>

          <div class="mt-5 grid gap-3 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
              <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Occupancy</p>
              <p class="mt-3 inline-flex items-center gap-2 text-sm font-bold text-slate-900">
                <Users class="h-4 w-4 text-slate-500" />
                {{ type.max_occupancy }} guest{{ Number(type.max_occupancy) === 1 ? '' : 's' }}
              </p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
              <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Rooms using type</p>
              <p class="mt-3 text-sm font-bold text-slate-900">{{ type.rooms_count ?? 0 }}</p>
            </div>
          </div>

          <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Features</p>
            <div v-if="type.features?.length" class="mt-3 flex flex-wrap gap-2">
              <span
                v-for="feature in type.features"
                :key="feature"
                class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-bold text-slate-600"
              >
                {{ feature }}
              </span>
            </div>
            <p v-else class="mt-3 text-sm text-slate-500">No guest-facing features listed yet.</p>
          </div>

          <div class="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-5">
            <Link
              :href="route('admin.room-types.edit', type.id)"
              class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
            >
              <Pencil class="h-4 w-4" />
              Edit
            </Link>

            <button
              type="button"
              @click="deleteType(type)"
              class="inline-flex items-center gap-2 rounded-xl border border-rose-200 px-4 py-2 text-sm font-bold text-rose-700 transition hover:bg-rose-50"
            >
              <Trash2 class="h-4 w-4" />
              Delete
            </button>
          </div>
        </article>
      </section>

      <section v-else class="rounded-[2rem] border border-dashed border-slate-300 bg-white px-6 py-16 text-center shadow-sm">
        <BedDouble class="mx-auto h-12 w-12 text-slate-300" />
        <h2 class="mt-4 text-xl font-black text-slate-900">No room types available</h2>
        <p class="mt-2 text-sm text-slate-500">Create the first room category so rooms can be grouped correctly in operations and booking flow.</p>
      </section>

      <Pagination :links="types.links" />
    </div>
  </ManagerLayout>
</template>
