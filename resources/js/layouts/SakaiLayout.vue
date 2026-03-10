<script setup lang="ts">
import AppConfigurator from '@/layout/AppConfigurator.vue';
import AppFooter from '@/layout/AppFooter.vue';
import AppSidebar from '@/layout/AppSidebar.vue';
import AppTopbar from '@/layout/AppTopbar.vue';
import { useLayout } from '@/layout/composables/layout';
import type { BreadcrumbItemType } from '@/types';
import { usePage } from '@inertiajs/vue3';
import ConfirmDialog from 'primevue/confirmdialog';
import Toast from 'primevue/toast';
import { computed, watch } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const { layoutConfig, layoutState, isDesktop } = useLayout();

const containerClass = computed(() => ({
    'layout-overlay': layoutConfig.menuMode === 'overlay',
    'layout-static': layoutConfig.menuMode === 'static',
    'layout-static-inactive': layoutState.staticMenuInactive && layoutConfig.menuMode === 'static',
    'layout-overlay-active': layoutState.overlayMenuActive,
    'layout-mobile-active': layoutState.mobileMenuActive,
}));

// Close sidebar on Inertia navigation
const page = usePage();
watch(
    () => page.url,
    () => {
        layoutState.overlayMenuActive = false;
        layoutState.mobileMenuActive = false;
        layoutState.menuHoverActive = false;
    },
);
</script>

<template>
    <div class="layout-wrapper" :class="containerClass">
        <AppTopbar />
        <div class="layout-sidebar">
            <AppSidebar />
        </div>
        <div class="layout-main-container">
            <div class="layout-main">
                <Toast />
                <ConfirmDialog />
                <slot />
            </div>
            <AppFooter />
        </div>
        <AppConfigurator />
        <div class="layout-mask animate-fadein" />
    </div>
</template>
