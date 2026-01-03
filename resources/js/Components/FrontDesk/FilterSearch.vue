<script setup>
import { ref, watch } from 'vue';
import { Search, Filter, Calendar, XCircle, RotateCcw } from 'lucide-vue-next';

const props = defineProps({
    search: String,
    filter: String,
    date: String,
    filters: Array,
});

const emit = defineEmits(['change']);

const localSearch = ref(props.search || '');
const localFilter = ref(props.filter || 'all');
const localDate   = ref(props.date || '');

watch(() => props.search, v => localSearch.value = v);
watch(() => props.filter, v => localFilter.value = v);
watch(() => props.date,   v => localDate.value = v);

function emitChange() {
    emit('change', {
        search: localSearch.value,
        filter: localFilter.value,
        date: localDate.value,
    });
}

function clear() {
    localSearch.value = '';
    localFilter.value = 'all';
    localDate.value   = '';
    emitChange();
}

function capitalize(v) {
    return v.charAt(0).toUpperCase() + v.slice(1).replace('_', ' ');
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-4 w-full">
        <div class="flex-grow min-w-[280px]">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1 flex items-center gap-1.5">
                <Search class="w-3 h-3" />
                Find Reservation
            </label>
            <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <Search class="w-4 h-4 text-slate-300 group-focus-within:text-indigo-500 transition-colors" />
                </div>
                <input
                    v-model="localSearch"
                    @input="emitChange"
                    type="text"
                    placeholder="Guest name, code, or room..."
                    class="block w-full pl-10 pr-4 py-2.5 bg-slate-50 border-2 border-slate-50 rounded-xl focus:bg-white focus:border-indigo-600 focus:ring-0 transition-all font-bold text-slate-700 text-sm shadow-none"
                />
            </div>
        </div>

        <div class="w-48">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1 flex items-center gap-1.5">
                <Filter class="w-3 h-3" />
                Status
            </label>
            <div class="relative">
                <select
                    v-model="localFilter"
                    @change="emitChange"
                    class="block w-full border-2 border-slate-50 bg-slate-50 rounded-xl px-4 py-2.5 font-bold text-slate-700 focus:border-indigo-600 focus:ring-0 transition-all text-sm appearance-none shadow-none"
                >
                    <option value="all">All Records</option>
                    <option v-for="f in filters" :key="f" :value="f">
                        {{ capitalize(f) }}
                    </option>
                </select>
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                    <Filter class="w-3 h-3 text-slate-300" />
                </div>
            </div>
        </div>

        <div class="w-48">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1 flex items-center gap-1.5">
                <Calendar class="w-3 h-3" />
                Select Date
            </label>
            <div class="relative group">
                <input
                    v-model="localDate"
                    @change="emitChange"
                    type="date"
                    class="block w-full px-4 py-2.5 bg-slate-50 border-2 border-slate-50 rounded-xl focus:bg-white focus:border-indigo-600 focus:ring-0 transition-all font-bold text-slate-700 text-sm shadow-none"
                />
            </div>
        </div>

        <div v-if="localSearch || localFilter !== 'all' || localDate" class="pt-6">
            <button
                @click="clear"
                type="button"
                class="flex items-center gap-2 px-4 py-2.5 text-rose-500 hover:text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-xl transition-all font-black text-[10px] uppercase tracking-widest"
            >
                <RotateCcw class="w-3.5 h-3.5" />
                Reset
            </button>
        </div>
    </div>
</template>

<style scoped>
/* Scoped overrides to fix browser-specific select/date icons */
select {
    background-image: none;
}
input[type="date"]::-webkit-calendar-picker-indicator {
    opacity: 0.5;
    cursor: pointer;
}
input[type="date"]::-webkit-calendar-picker-indicator:hover {
    opacity: 1;
}
</style>