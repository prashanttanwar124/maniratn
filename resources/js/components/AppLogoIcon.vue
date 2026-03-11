<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <div
        :class="className"
        v-bind="$attrs"
        class="inline-flex items-center justify-center rounded-md bg-sidebar-primary font-semibold tracking-wide text-sidebar-primary-foreground"
    >
        JF
    </div>
</template>
