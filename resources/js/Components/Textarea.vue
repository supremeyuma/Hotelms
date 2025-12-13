<template>
  <textarea
    :id="id"
    v-model="internalValue"
    :placeholder="placeholder"
    rows="4"
    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
    :disabled="disabled"
  ></textarea>
</template>

<script setup>
import { defineProps, defineEmits, ref, watch } from 'vue';

const props = defineProps({
  id: String,
  modelValue: String,
  placeholder: String,
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

// Internal ref to avoid v-model overwriting issues
const internalValue = ref(props.modelValue);

// Sync internalValue -> parent
watch(internalValue, (val) => {
  emit('update:modelValue', val);
});

// Sync parent -> internalValue
watch(() => props.modelValue, (val) => {
  internalValue.value = val;
});
</script>
