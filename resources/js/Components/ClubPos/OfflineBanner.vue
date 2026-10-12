<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import axios from 'axios'

const status = ref({ online: true, peers: 0, failed: [] })
let timer = null

async function refresh() {
  try {
    const { data } = await axios.get(route('club.pos.connectivity'), { params: { probe: 1 } })
    status.value = data
  } catch {
    status.value = { online: false, peers: 0, failed: ['unreachable'] }
  }
}

onMounted(async () => {
  await refresh()
  timer = setInterval(refresh, 15000)
})

onUnmounted(() => clearInterval(timer))
</script>

<template>
  <div
    v-if="!status.online"
    role="alert"
    aria-live="assertive"
    class="sticky top-0 z-50 flex items-center gap-3 bg-amber-500 px-4 py-3 text-amber-950 shrink-0"
  >
    <svg class="h-5 w-5 shrink-0 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
    </svg>
    <div class="text-sm font-semibold">
      Offline — sales are being recorded on this terminal and will sync automatically.
      <span v-if="status.peers > 0" class="font-normal">
        {{ status.failed.length }} of {{ status.peers }} servers unreachable.
      </span>
    </div>
  </div>
</template>
