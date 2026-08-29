<script setup>
import { ref, computed, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import ClubPosLayout from '@/Layouts/ClubPosLayout.vue'
import TopBar from '@/Components/ClubPos/TopBar.vue'
import ProductCatalog from '@/Components/ClubPos/ProductCatalog.vue'
import DocketPanel from '@/Components/ClubPos/DocketPanel.vue'
import PaymentModal from '@/Components/ClubPos/PaymentModal.vue'
import OpenDocketsBar from '@/Components/ClubPos/OpenDocketsBar.vue'
import StaffPinModal from '@/Components/ClubPos/StaffPinModal.vue'

defineOptions({ layout: ClubPosLayout })

const page = usePage()

const props = defineProps({
  device: { type: Object, default: null },
  openDockets: { type: Array, default: () => [] },
  currentSession: { type: Object, default: null },
  categories: { type: Array, default: () => [] },
  menuItems: { type: Array, default: () => [] },
  lowStockCount: { type: Number, default: 0 },
})

const authUser = computed(() => page.props.auth?.user)
const pinVerified = computed(() => page.props.club_pos?.pin_verified)
const currentStaff = ref(authUser.value)
const showPinModal = ref(!pinVerified.value)
const session = ref(props.currentSession)
const dockets = ref(props.openDockets)
const activeDocketId = ref(null)
const showPayment = ref(false)
const creatingDocket = ref(false)
const staffPinRef = ref(null)

const activeDocket = computed(() => {
  if (!activeDocketId.value) return null
  return dockets.value.find(d => d.id === activeDocketId.value) || null
})

async function handlePinConfirm(pin) {
  try {
    const res = await fetch(route('pos.staff.switch'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
      body: JSON.stringify({ pin }),
    })
    const data = await res.json()
    if (!res.ok) {
      alert(data.error || 'Invalid PIN')
      staffPinRef.value?.reset()
      return
    }
    window.location.href = route('club.pos.dashboard')
  } catch (e) {
    alert('Failed to verify PIN')
    staffPinRef.value?.reset()
  }
}

function handleLock() {
  currentStaff.value = null
  showPinModal.value = true
  activeDocketId.value = null
  showPayment.value = false
}

function handleAddItem(item) {
  if (!activeDocketId.value) {
    createDocket().then(() => {
      router.post(route('club.pos.dockets.items.store', { docket: activeDocketId.value }), {
        menu_item_id: item.id,
        quantity: 1,
      }, {
        preserveScroll: true,
        onSuccess: () => refreshDockets(),
      })
    })
    return
  }
  router.post(route('club.pos.dockets.items.add', { docket: activeDocketId.value }), {
    menu_item_id: item.id,
    quantity: 1,
  }, {
    preserveScroll: true,
    onSuccess: () => refreshDockets(),
  })
}

function createDocket() {
  if (!session.value) {
    alert('No open shift. Please open a shift first.')
    return Promise.reject()
  }
  creatingDocket.value = true
  return new Promise((resolve, reject) => {
    router.post(route('club.pos.dockets.store'), {
      session_id: session.value.id,
      table_identifier: null,
      customer_name: null,
    }, {
      preserveScroll: true,
      onSuccess: (resp) => {
        refreshDockets()
        resolve()
      },
      onError: () => reject(),
      onFinish: () => { creatingDocket.value = false },
    })
  })
}

function handleSelectDocket(id) {
  activeDocketId.value = id
  showPayment.value = false
}

function handlePay() {
  showPayment.value = true
}

function handlePaymentDone() {
  showPayment.value = false
  activeDocketId.value = null
  refreshDockets()
}

function refreshDockets() {
  router.reload({ only: ['openDockets', 'currentSession'], preserveScroll: true })
}

watch(() => props.openDockets, (val) => {
  dockets.value = val
  if (val.length > 0 && !activeDocketId.value) {
    activeDocketId.value = val[0].id
  }
}, { immediate: true })

watch(showPinModal, (val) => {
  if (!val && !currentStaff.value) {
    currentStaff.value = props.currentStaff
  }
})
</script>

<template>
  <div class="h-full flex flex-col">
    <TopBar :staff="currentStaff || authUser" :session="session" :device="device"
      @lock="handleLock" @switch-staff="showPinModal = true" />

    <div class="flex flex-col flex-1 min-h-0">
      <div class="flex flex-1 min-h-0">
        <div class="flex-[2] min-w-0 flex flex-col p-4 overflow-hidden">
          <ProductCatalog
            :menuItems="menuItems"
            :categories="categories"
            @add-item="handleAddItem" />
        </div>

        <div class="w-[380px] shrink-0 border-l border-slate-700 bg-slate-850 p-4 flex flex-col overflow-hidden">
          <PaymentModal
            v-if="showPayment && activeDocket"
            :docket="activeDocket"
            @close="showPayment = false"
            @done="handlePaymentDone" />
          <DocketPanel
            v-else
            :docket="activeDocket"
            @pay="handlePay"
            @void="refreshDockets"
            @close="activeDocketId = null" />
        </div>
      </div>

      <OpenDocketsBar
        :dockets="dockets"
        :activeDocketId="activeDocketId"
        @select="handleSelectDocket"
        @create="createDocket" />
    </div>

    <Teleport to="body">
      <div v-if="showPinModal"
        class="fixed inset-0 z-[100] bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
        <div
          role="dialog"
          aria-modal="true"
          aria-label="Staff login"
          class="w-full max-w-sm rounded-2xl bg-slate-800 border border-slate-700 shadow-2xl">
          <StaffPinModal ref="staffPinRef" @confirm="handlePinConfirm" @cancel="showPinModal = false" />
        </div>
      </div>
    </Teleport>
  </div>
</template>
