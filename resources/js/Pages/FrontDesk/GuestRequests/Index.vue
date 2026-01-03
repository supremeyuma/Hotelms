<script setup>
import FrontDeskLayout from '@/Layouts/Staff/FrontDeskLayout.vue'
import { ref, onMounted } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import GuestRequestItem from '@/Components/FrontDesk/GuestRequestItem.vue';
import Pagination from '@/Components/Pagination.vue';
import { 
  Bell, 
  Clock, 
  CheckCircle2, 
  Zap, 
  Inbox, 
  Filter
} from 'lucide-vue-next';

const props = defineProps({
    guestRequests: Object,
});

// Use a local ref so Echo can update it in real-time
const localRequests = ref(props.guestRequests);

function fetchRequests(page = 1) {
    router.get('/frontdesk/guest-requests', { page }, { 
        preserveState: true, 
        replace: true,
        onSuccess: (page) => {
            localRequests.value = page.props.guestRequests;
        }
    });
}

function acknowledgeRequest(requestId) {
    router.post(`/frontdesk/guest-requests/${requestId}/acknowledge`, {}, { 
        preserveScroll: true,
        onSuccess: () => fetchRequests() 
    });
}

function completeRequest(requestId) {
    router.post(`/frontdesk/guest-requests/${requestId}/complete`, {}, { 
        preserveScroll: true,
        onSuccess: () => fetchRequests() 
    });
}

onMounted(() => {
    // Real-time listener for new requests
    if (window.Echo) {
        window.Echo.channel('frontdesk')
            .listen('GuestRequestCreated', (event) => {
                // Flash the browser tab or play a subtle sound if needed
                localRequests.value.data.unshift(event);
                if (localRequests.value.data.length > 20) {
                    localRequests.value.data.pop();
                }
            });
    }
});
</script>

<template>
    <FrontDeskLayout>
        <Head title="Live Guest Requests" />

        <div class="p-8 max-w-5xl mx-auto">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-10">
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-rose-500"></span>
                        </span>
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Live Service Feed</span>
                    </div>
                    <h1 class="text-4xl font-black text-slate-900 tracking-tight flex items-center gap-4">
                        Guest Requests
                        <span class="bg-slate-100 text-slate-500 text-sm px-4 py-1 rounded-2xl font-bold">
                            {{ localRequests.total }} total
                        </span>
                    </h1>
                </div>

                <div class="flex gap-3">
                    <button class="flex items-center gap-2 px-5 py-2.5 bg-white border border-slate-100 rounded-xl text-xs font-black text-slate-600 hover:bg-slate-50 transition-all">
                        <Filter class="w-4 h-4" /> Filter Feed
                    </button>
                    <button @click="fetchRequests(1)" class="flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white rounded-xl text-xs font-black hover:bg-indigo-700 shadow-lg shadow-indigo-100 transition-all">
                        <Zap class="w-4 h-4" /> Refresh
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm flex items-center gap-4">
                    <div class="p-4 bg-rose-50 text-rose-600 rounded-2xl"><Bell class="w-6 h-6" /></div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Pending</p>
                        <p class="text-2xl font-black text-slate-900">{{ localRequests.data.filter(r => r.status === 'pending').length }}</p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm flex items-center gap-4">
                    <div class="p-4 bg-amber-50 text-amber-600 rounded-2xl"><Clock class="w-6 h-6" /></div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">In Progress</p>
                        <p class="text-2xl font-black text-slate-900">{{ localRequests.data.filter(r => r.status === 'acknowledged').length }}</p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm flex items-center gap-4">
                    <div class="p-4 bg-emerald-50 text-emerald-600 rounded-2xl"><CheckCircle2 class="w-6 h-6" /></div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Completed</p>
                        <p class="text-2xl font-black text-slate-900">{{ localRequests.data.filter(r => r.status === 'completed').length }}</p>
                    </div>
                </div>
            </div>

            <div v-if="localRequests.data.length > 0" class="space-y-4">
                <GuestRequestItem 
                    v-for="request in localRequests.data" 
                    :key="request.id" 
                    :request="request" 
                    @acknowledge="acknowledgeRequest" 
                    @complete="completeRequest"
                />
            </div>

            <div v-else class="bg-white rounded-[3rem] border-2 border-dashed border-slate-100 py-20 text-center">
                <div class="inline-flex p-6 bg-slate-50 text-slate-300 rounded-full mb-6">
                    <Inbox class="w-12 h-12" />
                </div>
                <h3 class="text-xl font-black text-slate-900">Quiet for now</h3>
                <p class="text-slate-500 font-medium">New guest requests will appear here in real-time.</p>
            </div>

            <div class="mt-12 flex justify-center">
                <Pagination 
                    :links="localRequests.links" 
                    @page-change="fetchRequests" 
                    class="bg-white px-6 py-3 rounded-full border border-slate-100 shadow-sm"
                />
            </div>
        </div>
    </FrontDeskLayout>
</template>

<style scoped>
/* Smooth entry for real-time items */
.request-enter-active {
  transition: all 0.5s ease-out;
}
.request-enter-from {
  opacity: 0;
  transform: translateY(-20px);
}
</style>