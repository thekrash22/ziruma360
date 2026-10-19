<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <p v-if="message" class="mt-1 text-sm text-error-500">
        {{ message }}
    </p>
</template>
