<script setup>
import { onMounted, ref } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    label: { type: String, default: '' },
    id: { type: String, default: '' },
});

const model = defineModel({
    type: [String, Number],
    required: true,
});

const input = ref(null);

const inputId = props.id || `text-input-${Math.random().toString(36).slice(2, 9)}`;

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <div>
        <label
            v-if="label"
            :for="inputId"
            class="block text-sm font-medium text-gray-700"
        >{{ label }}</label>
        <input
            v-bind="$attrs"
            :id="inputId"
            :class="[
                'rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500',
                $attrs.class,
            ]"
            v-model="model"
            ref="input"
        />
    </div>
</template>
