<script setup lang="ts">
import SakaiLayout from '@/layouts/SakaiLayout.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <SakaiLayout :breadcrumbs="breadcrumbs">
        <slot />
    </SakaiLayout>
</template>
