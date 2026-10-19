<script setup lang="ts">
defineProps<{
    value?: string;
}>();
</script>

<template>
    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
    </label>
</template>
