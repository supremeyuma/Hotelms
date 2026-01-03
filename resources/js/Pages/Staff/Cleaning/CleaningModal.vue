<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { 
  X, 
  KeyRound, 
  PlayCircle, 
  CheckCircle, 
  DoorOpen,
  AlertCircle 
} from 'lucide-vue-next'

const props = defineProps({
  room: Object
})

const emit = defineEmits(['close'])

const action = ref('cleaning')
const actionCode = ref('')
const error = ref(null)

const cleaningId = computed(() => props.room.latest_cleaning?.id)

const submit = () => {
  error.value = null
  router.patch(
    `/cleaning/${cleaningId.value ?? 'create'}`,
    {
      room_id: props.room.id,
      action: action.value,
      action_code: actionCode.value
    },
    {
      preserveScroll: true,
      onError: (e) => {
        error.value = Object.values(e)[0] || 'Verification failed'
      },
      onSuccess: () => emit('close')
    }
  )
}
</script>

<template>
  <div class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center p-4">
    <div 
      class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" 
      @click="$emit('close')"
    ></div>

    <div class="relative bg-white w-full max-w-md rounded-[2.5rem] shadow-2xl overflow-hidden transform transition-all animate-in fade-in slide-in-from-bottom-8 duration-300">
      
      <div class="px-8 pt-8 pb-4 flex justify-between items-center">
        <div class="flex items-center gap-3">
          <div class="p-3 bg-indigo-50 text-indigo-600 rounded-2xl">
            <DoorOpen class="w-6 h-6" />
          </div>
          <div>
            <h2 class="text-2xl font-black text-slate-900 leading-none">
              Room {{ room.room_number || room.number }}
            </h2>
            <p class="text-sm text-slate-500 font-medium mt-1">Update cleaning status</p>
          </div>
        </div>
        <button 
          @click="$emit('close')" 
          class="p-2 rounded-full hover:bg-slate-100 text-slate-400 transition-colors"
        >
          <X class="w-6 h-6" />
        </button>
      </div>

      <div class="px-8 py-4 space-y-6">
        <div class="grid grid-cols-2 gap-3">
          <button 
            @click="action = 'cleaning'"
            type="button"
            :class="[
              'flex flex-col items-center gap-2 p-4 rounded-3xl border-2 transition-all',
              action === 'cleaning' 
                ? 'border-indigo-600 bg-indigo-50 text-indigo-700' 
                : 'border-slate-100 bg-slate-50 text-slate-500 hover:border-slate-200'
            ]"
          >
            <PlayCircle class="w-6 h-6" />
            <span class="font-bold text-xs uppercase tracking-wider">Start Prep</span>
          </button>

          <button 
            @click="action = 'clean'"
            type="button"
            :class="[
              'flex flex-col items-center gap-2 p-4 rounded-3xl border-2 transition-all',
              action === 'clean' 
                ? 'border-emerald-600 bg-emerald-50 text-emerald-700' 
                : 'border-slate-100 bg-slate-50 text-slate-500 hover:border-slate-200'
            ]"
          >
            <CheckCircle class="w-6 h-6" />
            <span class="font-bold text-xs uppercase tracking-wider">Finish Job</span>
          </button>
        </div>

        <div class="space-y-2">
          <label class="text-xs font-black text-slate-400 uppercase tracking-widest ml-1">Staff Access Code</label>
          <div class="relative group">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-600 transition-colors">
              <KeyRound class="w-5 h-5" />
            </div>
            <input
              v-model="actionCode"
              type="password"
              inputmode="numeric"
              placeholder="••••"
              class="block w-full pl-12 pr-4 py-4 bg-slate-50 border-2 border-slate-100 rounded-2xl focus:bg-white focus:border-indigo-600 focus:ring-0 transition-all text-xl tracking-[0.5em] font-mono"
            />
          </div>
        </div>

        <transition enter-active-class="animate-shake">
          <div v-if="error" class="flex items-center gap-2 p-3 rounded-xl bg-rose-50 text-rose-600 border border-rose-100">
            <AlertCircle class="w-4 h-4" />
            <p class="text-xs font-bold">{{ error }}</p>
          </div>
        </transition>
      </div>

      <div class="p-8 pt-4 flex flex-col gap-3">
        <button 
          @click="submit" 
          class="w-full py-4 bg-slate-900 text-white rounded-2xl font-bold text-lg hover:bg-indigo-600 shadow-xl shadow-slate-200 active:scale-[0.98] transition-all"
        >
          Confirm Update
        </button>
        <button 
          @click="$emit('close')" 
          class="w-full py-3 text-slate-400 font-bold text-sm hover:text-slate-600 transition-colors"
        >
          Dismiss
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes shake {
  0%, 100% { transform: translateX(0); }
  25% { transform: translateX(-4px); }
  75% { transform: translateX(4px); }
}
.animate-shake {
  animation: shake 0.2s ease-in-out 0s 2;
}
</style>