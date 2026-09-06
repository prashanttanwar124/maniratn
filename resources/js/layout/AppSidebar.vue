<script setup>
import { useLayout } from '@/layout/composables/layout';
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

import AppMenu from './AppMenu.vue';

const { layoutState, isDesktop } = useLayout();
const page = usePage();

const sidebarRef = ref(null);
let outsideClickListener = null;

watch(
    () => page.url,
    (newPath) => {
        if (isDesktop()) layoutState.activePath = null;
        else layoutState.activePath = newPath;

        layoutState.overlayMenuActive = false;
        layoutState.mobileMenuActive = false;
        layoutState.menuHoverActive = false;
    },
    { immediate: true },
);

const isMenuOpen = computed(() => Boolean(layoutState.mobileMenuActive || (isDesktop() && layoutState.overlayMenuActive)));

watch(isMenuOpen, (newVal) => {
    if (newVal) bindOutsideClickListener();
    else unbindOutsideClickListener();
});

const bindOutsideClickListener = () => {
    if (!outsideClickListener) {
        outsideClickListener = (event) => {
            if (isOutsideClicked(event)) {
                layoutState.overlayMenuActive = false;
                layoutState.mobileMenuActive = false;
            }
        };

        document.addEventListener('click', outsideClickListener);
        document.addEventListener('touchstart', outsideClickListener, { passive: true });
    }
};

const unbindOutsideClickListener = () => {
    if (outsideClickListener) {
        document.removeEventListener('click', outsideClickListener);
        document.removeEventListener('touchstart', outsideClickListener);
        outsideClickListener = null;
    }
};

const isOutsideClicked = (event) => {
    const sidebarEl = sidebarRef.value?.closest('.layout-sidebar') || sidebarRef.value;
    const topbarButtonEl = document.querySelector('.layout-menu-button');

    if (!sidebarEl) return false;

    return !(
        sidebarEl.isSameNode(event.target) ||
        sidebarEl.contains(event.target) ||
        topbarButtonEl?.isSameNode(event.target) ||
        topbarButtonEl?.contains(event.target)
    );
};

onBeforeUnmount(() => {
    unbindOutsideClickListener();
});
</script>

<template>
    <div ref="sidebarRef" class="layout-sidebar">
        <AppMenu />
    </div>
</template>
