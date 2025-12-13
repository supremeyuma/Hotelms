<template>
  <select
    :id="id"
    :disabled="disabled"
    :value="modelValue"
    @change="$emit('update:modelValue', $event.target.value)"
    class="border rounded px-2 py-1 w-full"
  >
    <option value="">Select...</option>
    <option v-for="opt in options" :key="opt.value" :value="opt.value">
      {{ opt.label }}
    </option>
  </select>
</template>

<script setup>
defineProps({
  modelValue: [String, Number],
  options: {
    type: Array,
    default: () => [],
  },
  id: String,
  disabled: Boolean
});

defineEmits(['update:modelValue']);
</script>
