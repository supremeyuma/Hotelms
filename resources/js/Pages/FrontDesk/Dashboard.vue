<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import FrontDeskLayout from '@/Layouts/Staff/FrontDeskLayout.vue'
import KPIWidget from '@/Components/FrontDesk/KPIWidget.vue'
import GuestRequestItem from '@/Components/FrontDesk/GuestRequestItem.vue'
import BookingItem from '@/Components/FrontDesk/BookingItem.vue'
import { 
  LayoutDashboard, 
  Bell, 
  AlertCircle, 
  ArrowUpRight, 
  Bed, 
  UserPlus, 
  UserMinus, 
  Wallet 
} from 'lucide-vue-next'

const props = defineProps({
    roomsOccupied: Number,
    roomsAvailable: Number,
    guestsArriving: Number,
    guestsDeparting: Number,
    recentRequests: Array,
    outstandingBookingList: Array,
})

const recentRequests = ref([...props.recentRequests])

// Filter logic for what "Front Desk" actually cares about in the feed
function isVisibleOnFrontDesk(request) {
    if (request.type === 'laundry') return request.status === 'requested'
    if (['kitchen', 'bar'].includes(request.type)) return request.status === 'pending'
    if (request.type === 'cleaning') return request.status === 'requested'
    return false
}

onMounted(() => {
    if (!window.Echo) return

    window.Echo.channel('laundry-orders')
        .listen('.LaundryOrderUpdated', (e) => {
            const order = e.order
            const guestRequest = {
                id: order.guest_request?.id ?? `laundry-${order.id}`,
                type: 'laundry',
                status: order.status,
                requestable: order,
                created_at: new Date()
            }

            recentRequests.value = recentRequests.value.filter(r => r.id !== guestRequest.id)
            if (isVisibleOnFrontDesk(guestRequest)) {
                recentRequests.value.unshift(guestRequest)
            }
        })
})

onBeforeUnmount(() => {
    window.Echo?.leave('laundry-orders')
})

function resolveRequestLink(request) {
    if (request.type === 'laundry' && request.requestable?.id) {
        return route('staff.laundry.show', request.requestable.id)
    }
    return '#'
}
</script>

<template>
    <FrontDeskLayout>
        <Head title="Front Desk Dashboard" />

        <div class="p-8 max-w-[1600px] mx-auto space-y-10">
            
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <div class="p-1.5 bg-indigo-100 text-indigo-600 rounded-lg">
                            <LayoutDashboard class="w-4 h-4" />
                        </div>
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Management Console</span>
                    </div>
                    <h1 class="text-4xl font-black text-slate-900 tracking-tight">Front Desk Dashboard</h1>
                </div>
                
                <Link 
                    :href="route('frontdesk.bookings.index')"
                    class="group flex items-center gap-3 px-6 py-3 bg-slate-900 text-white rounded-2xl font-black text-sm hover:bg-indigo-600 transition-all shadow-xl shadow-slate-200"
                >
                    View All Bookings
                    <ArrowUpRight class="w-4 h-4 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-transform" />
                </Link>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <KPIWidget
                    title="Occupancy"
                    :value="roomsOccupied"
                    label="Rooms In-Use"
                    :href="route('frontdesk.rooms.index', { status: 'occupied' })"
                    variant="indigo"
                />

                <KPIWidget
                    title="Availability"
                    :value="roomsAvailable"
                    label="Ready for Sale"
                    :href="route('frontdesk.rooms.index', { status: 'available' })"
                    variant="emerald"
                />

                <KPIWidget
                    title="Arrivals"
                    :value="guestsArriving"
                    label="Expected Today"
                    :href="route('frontdesk.bookings.index', { check_in_date: new Date().toISOString().slice(0, 10) })"
                    variant="amber"
                />

                <KPIWidget
                    title="Departures"
                    :value="guestsDeparting"
                    label="Scheduled Today"
                    :href="route('frontdesk.bookings.index', { check_out_date: new Date().toISOString().slice(0, 10) })"
                    variant="rose"
                />
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-10">
                
                <div class="xl:col-span-2 space-y-6">
                    <div class="flex items-center justify-between px-2">
                        <div class="flex items-center gap-3">
                            <h2 class="text-xl font-black text-slate-900 tracking-tight">Live Service Feed</h2>
                            <span class="px-3 py-1 bg-rose-100 text-rose-600 text-[10px] font-black uppercase rounded-full animate-pulse">Live</span>
                        </div>
                        <Link :href="route('frontdesk.guest-requests.index')" class="text-xs font-bold text-indigo-600 hover:underline">View All</Link>
                    </div>

                    <div v-if="recentRequests.length" class="space-y-4">
                        <GuestRequestItem
                            v-for="r in recentRequests"
                            :key="r.id"
                            :request="r"
                            :href="resolveRequestLink(r)"
                            layout="compact"
                        />
                    </div>
                    
                    <div v-else class="bg-white rounded-[2.5rem] border-2 border-dashed border-slate-100 py-20 text-center">
                        <div class="inline-flex p-5 bg-slate-50 text-slate-300 rounded-full mb-4">
                            <Bell class="w-8 h-8" />
                        </div>
                        <p class="text-slate-400 font-bold italic">No active service requests.</p>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="flex items-center gap-3 px-2">
                        <h2 class="text-xl font-black text-slate-900 tracking-tight">Financial Watchlist</h2>
                        <div class="p-1 bg-amber-100 text-amber-600 rounded">
                            <AlertCircle class="w-4 h-4" />
                        </div>
                    </div>

                    <div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden">
                        <div class="p-6 border-b border-slate-50 bg-slate-50/50">
                            <div class="flex items-center gap-2 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                <Wallet class="w-3 h-3" /> Outstanding Balances
                            </div>
                        </div>
                        
                        <div class="divide-y divide-slate-50">
                            <BookingItem
                                v-for="b in outstandingBookingList"
                                :key="b.id"
                                :booking="b"
                                :href="route('frontdesk.bookings.show', b.id)"
                                variant="sidebar"
                            />
                            
                            <div v-if="!outstandingBookingList.length" class="p-10 text-center">
                                <p class="text-sm text-slate-400 font-medium">All folios settled.</p>
                            </div>
                        </div>

                        <div class="p-4 bg-slate-50/80">
                            <p class="text-[9px] text-center text-slate-400 font-bold uppercase tracking-tighter italic">
                                Settle outstanding balances before checkout
                            </p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </FrontDeskLayout>
</template>

<style scoped>
/* Custom animations for live feeling */
.request-list-move,
.request-list-enter-active,
.request-list-leave-active {
  transition: all 0.5s ease;
}
</style>