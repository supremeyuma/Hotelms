<script setup>
import { ref, computed, watch, onUnmounted, nextTick } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import ClubPosLayout from '@/Layouts/ClubPosLayout.vue'
import TopBar from '@/Components/ClubPos/TopBar.vue'
import OfflineBanner from '@/Components/ClubPos/OfflineBanner.vue'
import PosToast from '@/Components/ClubPos/PosToast.vue'
import ProductCatalog from '@/Components/ClubPos/ProductCatalog.vue'
import DocketPanel from '@/Components/ClubPos/DocketPanel.vue'
import PaymentModal from '@/Components/ClubPos/PaymentModal.vue'
import OpenDocketsBar from '@/Components/ClubPos/OpenDocketsBar.vue'
import StaffPinModal from '@/Components/ClubPos/StaffPinModal.vue'
import CloseShiftModal from '@/Components/ClubPos/CloseShiftModal.vue'
import BookingSearchModal from '@/Components/ClubPos/BookingSearchModal.vue'

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
const showCloseShift = ref(false)
const showBookingSearch = ref(false)
const staffPinRef = ref(null)
const addingItem = ref(false)
const addingItemId = ref(null)
const notice = ref(null)
let noticeTimer = null

function showNotice(message, type = 'info', ttl = 5000) {
  notice.value = { message, type }
  clearTimeout(noticeTimer)
  noticeTimer = setTimeout(() => { notice.value = null }, ttl)
}

function showError(message) {
  showNotice(message, 'error', 6000)
}

onUnmounted(() => clearTimeout(noticeTimer))

const activeDocket = computed(() => {
  if (!activeDocketId.value) return null
  return dockets.value.find(d => d.id === activeDocketId.value) || null
})

async function handlePinConfirm(pin) {
  try {
    // The meta CSRF token goes stale as soon as the session rotates (login,
    // session regenerate, another tab). Laravel keeps the XSRF-TOKEN cookie
    // current on every response, so the unlock must use that — sending the
    // stale meta token answered 419 and locked the till.
    const xsrfCookie = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
    const csrf = xsrfCookie
      ? decodeURIComponent(xsrfCookie[1])
      : (document.querySelector('meta[name=csrf-token]')?.content ?? '')

    const res = await fetch(route('pos.staff.switch'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': csrf,
      },
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
  if (addingItem.value) return

  addingItemId.value = item.id

  if (!activeDocketId.value) {
    createDocket().then((created) => {
      if (!created) {
        addingItemId.value = null
        return
      }
      addItem(item)
    })
    return
  }

  addItem(item)
}

function addItem(item) {
  if (addingItem.value || !activeDocketId.value) return
  addingItem.value = true
  addingItemId.value = item.id
  router.post(route('club.pos.dockets.items.store', { docket: activeDocketId.value }), {
    menu_item_id: item.id,
    quantity: 1,
  }, {
    preserveScroll: true,
    onSuccess: () => refreshDockets(),
    onError: (errors) => {
      showError(errors.docket || errors.menu_item_id || 'Could not add item to the docket.')
    },
    onFinish: () => {
      addingItem.value = false
      addingItemId.value = null
    },
  })
}

let createPromise = null

function createDocket() {
  if (createPromise) return createPromise
  creatingDocket.value = true
  createPromise = new Promise((resolve) => {
    router.post(route('club.pos.dockets.store'), {
      table_identifier: null,
      customer_name: null,
    }, {
      preserveScroll: true,
      onSuccess: async () => {
        refreshDockets()
        // Wait for the openDockets watcher to assign activeDocketId before
        // the caller posts an item to the docket it just opened.
        await nextTick()
        resolve(true)
      },
      onError: (errors) => {
        showError(errors.docket || 'Could not open a docket.')
        resolve(false)
      },
      onFinish: () => {
        creatingDocket.value = false
        createPromise = null
      },
    })
  })
  return createPromise
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

function onShiftClosed() {
  showCloseShift.value = false
  session.value = null
  activeDocketId.value = null
  refreshDockets()
}

function onBookingAttached() {
  showBookingSearch.value = false
  refreshDockets()
}

watch(() => props.openDockets, (val) => {
  dockets.value = val
  if (val.length > 0 && !activeDocketId.value) {
    activeDocketId.value = val[0].id
  }
}, { immediate: true })

watch(() => props.currentSession, (val) => {
  session.value = val
}, { immediate: true })

watch(showPinModal, (val) => {
  if (!val && !currentStaff.value) {
    currentStaff.value = props.currentStaff
  }
})

// Task 2 acknowledgement surfaced by DocketController::pay: tells the cashier
// whether the sale only queued locally (no peer has it yet) or already synced.
watch(() => page.props.flash?.queued_for_sync, (val) => {
  if (val === true) {
    showNotice('Sale recorded — will sync automatically.', 'queued')
  } else if (val === false) {
    showNotice('Sale recorded and synced.', 'success')
  }
}, { immediate: true })
</script>

<template>
  <div class="h-full flex flex-col">
    <OfflineBanner />
    <PosToast
      :message="notice?.message"
      :type="notice?.type" />

    <TopBar :staff="currentStaff || authUser" :session="session" :device="device"
      @lock="handleLock" @switch-staff="showPinModal = true"
      @close-shift="showCloseShift = true" />

    <div v-if="showCloseShift && session"
      class="fixed inset-0 z-[90] bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
      <div role="dialog" aria-modal="true" aria-label="Close shift"
        class="w-full max-w-sm rounded-2xl bg-slate-800 border border-slate-700 shadow-2xl">
        <CloseShiftModal :session="session" @close="showCloseShift = false" @closed="onShiftClosed" />
      </div>
    </div>

    <div class="flex flex-col flex-1 min-h-0">
      <div class="flex flex-1 min-h-0">
        <div class="flex-[2] min-w-0 flex flex-col p-4 overflow-hidden">
          <ProductCatalog
            :menuItems="menuItems"
            :categories="categories"
            :busy="addingItem || creatingDocket"
            :busyItemId="addingItemId"
            @add-item="handleAddItem" />
        </div>

        <div class="w-[380px] shrink-0 border-l border-slate-700 bg-slate-800 p-4 flex flex-col overflow-hidden">
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
            @attach-room="showBookingSearch = true"
            @close="activeDocketId = null" />
        </div>
      </div>

      <OpenDocketsBar
        :dockets="dockets"
        :activeDocketId="activeDocketId"
        :creating="creatingDocket"
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

    <Teleport to="body">
      <div v-if="showBookingSearch && activeDocket"
        class="fixed inset-0 z-[100] bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
        <div role="dialog" aria-modal="true" aria-label="Charge to room"
          class="w-full max-w-md rounded-2xl bg-slate-800 border border-slate-700 shadow-2xl p-4">
          <BookingSearchModal :docket="activeDocket" @close="showBookingSearch = false" @attached="onBookingAttached" />
        </div>
      </div>
    </Teleport>
  </div>
</template>
