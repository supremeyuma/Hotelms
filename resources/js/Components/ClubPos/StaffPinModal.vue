<script setup>
import { ref } from 'vue'

const emit = defineEmits(['confirm', 'cancel'])

const pin = ref('')
const error = ref('')
const loading = ref(false)

const rows = [
  ['1', '2', '3'],
  ['4', '5', '6'],
  ['7', '8', '9'],
  ['', '0', 'del'],
]

function press(key) {
  if (key === 'del') {
    pin.value = pin.value.slice(0, -1)
    error.value = ''
    return
  }
  if (pin.value.length >= 6) return
  pin.value += key
  error.value = ''
}

async function submit() {
  if (pin.value.length === 0) return
  loading.value = true
  error.value = ''
  emit('confirm', pin.value)
}

function reset() {
  pin.value = ''
  error.value = ''
  loading.value = false
}

defineExpose({ reset })
</script>

<template>
  <div class="flex flex-col items-center justify-center gap-6 py-8 px-6">
    <div class="text-center">
      <div class="w-16 h-16 mx-auto rounded-full bg-indigo-500/20 flex items-center justify-center mb-3">
        <svg class="w-8 h-8 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
        </svg>
      </div>
      <h2 class="text-xl font-bold text-white">Staff Login</h2>
      <p class="text-sm text-slate-400 mt-1">Enter your PIN to access the POS</p>
    </div>

    <div class="flex gap-3">
      <div v-for="i in 6" :key="i"
        class="w-4 h-4 rounded-full border-2 transition-all duration-100"
        :class="pin.length >= i ? 'bg-indigo-400 border-indigo-400 scale-110' : 'border-slate-500'">
      </div>
    </div>

    <p v-if="error" class="text-sm text-red-400">{{ error }}</p>

    <div class="grid grid-cols-3 gap-3 w-64">
      <template v-for="row in rows" :key="row.join('')">
        <button v-for="key in row" :key="key"
          @click="press(key)"
          :disabled="loading"
          class="h-14 rounded-xl text-lg font-bold transition-all active:scale-90 disabled:opacity-50"
          :class="key === 'del'
            ? 'bg-slate-700 text-slate-300 hover:bg-slate-600 col-span-1'
            : key === ''
              ? 'bg-transparent pointer-events-none'
              : 'bg-slate-700 text-white hover:bg-slate-600'
          ">
          <span v-if="key === 'del'" class="text-sm">DEL</span>
          <span v-else>{{ key }}</span>
        </button>
      </template>
    </div>

    <div class="flex gap-3 w-full max-w-xs">
      <button @click="$emit('cancel')"
        class="flex-1 py-3 rounded-xl bg-slate-700 text-slate-300 font-medium hover:bg-slate-600 transition-all active:scale-95">
        Cancel
      </button>
      <button @click="submit" :disabled="pin.length === 0 || loading"
        class="flex-1 py-3 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-500 transition-all active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed">
        {{ loading ? 'Checking...' : 'Enter' }}
      </button>
    </div>
  </div>
</template>
