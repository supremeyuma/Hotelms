<template>
    <div class="bg-white rounded shadow p-4">
        <div class="text-sm text-gray-500 uppercase tracking-wider font-semibold">
            {{ title }}
        </div>
        <div class="mt-2">
            <slot />
        </div>
    </div>
</template>

<script setup>
defineProps({
    title: String
})
</script>

