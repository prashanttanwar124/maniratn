<script setup lang="ts">
import Message from 'primevue/message';

defineProps<{
    errors: string[];
}>();
</script>

<template>
    <Message v-for="(error, i) in errors" :key="i" severity="error" :closable="false">
        {{ error }}
    </Message>
</template>
