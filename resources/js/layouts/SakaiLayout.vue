<script setup lang="ts">
import AppFooter from '@/layout/AppFooter.vue';
import AppSidebar from '@/layout/AppSidebar.vue';
import AppTopbar from '@/layout/AppTopbar.vue';
import { useLayout } from '@/layout/composables/layout';
import type { BreadcrumbItemType } from '@/types';
import { useForm, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import ConfirmDialog from 'primevue/confirmdialog';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import Toast from 'primevue/toast';
import { computed, watch } from 'vue';
import { route } from 'ziggy-js';

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
const dayStatus = computed(() => page.props.dayStatus ?? { is_open: true });
const auth = computed(() => page.props.auth ?? {});
const canManageVault = computed(() => Boolean(auth.value.can?.manage_vault));
const showOpenDayModal = computed(() => !dayStatus.value.is_open);
const isInitialSetup = computed(() => Boolean(dayStatus.value.is_initial_setup));
const expectedOpeningCash = computed(() => Number(dayStatus.value.expected_opening_cash || 0));
const expectedOpeningGold = computed(() => Number(dayStatus.value.expected_opening_gold || 0));
const expectedOpeningSilver = computed(() => Number(dayStatus.value.expected_opening_silver || 0));
const hasExpectedOpening = computed(() => expectedOpeningCash.value > 0 || expectedOpeningGold.value > 0 || expectedOpeningSilver.value > 0);
const openDayForm = useForm({
    opening_cash: 0,
    opening_gold: 0,
    opening_silver: 0,
    mismatch_reason: '',
    reopen_reason: '',
});

const openingMismatch = computed(() => {
    return hasExpectedOpening.value && (
        Math.abs(Number(openDayForm.opening_cash || 0) - expectedOpeningCash.value) > 0.0001 ||
        Math.abs(Number(openDayForm.opening_gold || 0) - expectedOpeningGold.value) > 0.0001 ||
        Math.abs(Number(openDayForm.opening_silver || 0) - expectedOpeningSilver.value) > 0.0001
    );
});

watch(
    dayStatus,
    (status) => {
        if (!status?.is_open) {
            openDayForm.defaults({
                opening_cash: expectedOpeningCash.value,
                opening_gold: expectedOpeningGold.value,
                opening_silver: expectedOpeningSilver.value,
                mismatch_reason: '',
            });
            openDayForm.reset();
            openDayForm.clearErrors();
        }
    },
    { immediate: true },
);

const submitOpenDay = () => {
    if (!canManageVault.value) {
        return;
    }

    openDayForm.post(route('dashboard.open-day'), {
        preserveScroll: true,
    });
};

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
                <div v-if="!dayStatus.is_open" class="mb-4 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    Shop day is closed. Enter opening cash, gold, and silver on the dashboard before creating or updating business records.
                </div>
                <slot />
            </div>
            <AppFooter />
        </div>
        <div class="layout-mask animate-fadein" />
        <Dialog
            :visible="showOpenDayModal"
            modal
            :closable="false"
            :dismissableMask="false"
            header="Open Shop Day"
            class="w-full max-w-md"
        >
            <div class="space-y-4 pt-2">
                <div class="border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    {{ isInitialSetup
                        ? 'This is the first time the software is being opened. Enter the business opening cash, gold, and silver to initialize the system.'
                        : "Record today's counted opening balances before using billing, ledger, order, expense, or recovery actions." }}
                </div>

                <template v-if="canManageVault">
                    <div class="border border-surface-200 bg-surface-50 px-4 py-3 text-sm text-surface-700">
                        {{ isInitialSetup
                            ? 'First-time setup will create the initial vault balances from these counted values and store an audit entry.'
                            : 'This does not add funds to the vault. It only records the counted opening snapshot for the day.' }}
                    </div>

                    <div v-if="hasExpectedOpening" class="border border-surface-200 bg-surface-50 px-4 py-3 text-sm text-surface-700">
                        Expected from last closed day
                        <div class="mt-1 flex items-center justify-between gap-4 text-xs text-surface-500">
                            <span>Cash: {{ expectedOpeningCash.toLocaleString('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }) }}</span>
                            <span>Gold: {{ expectedOpeningGold.toFixed(3) }} g</span>
                            <span>Silver: {{ expectedOpeningSilver.toFixed(3) }} g</span>
                        </div>
                        <div v-if="dayStatus.expected_opening_date" class="mt-1 text-xs text-surface-400">
                            Based on close of {{ dayStatus.expected_opening_date }}
                        </div>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Counted Opening Cash</label>
                        <InputNumber v-model="openDayForm.opening_cash" mode="currency" currency="INR" locale="en-IN" class="w-full" />
                        <small class="mt-1 block text-xs text-surface-500">
                            {{ isInitialSetup
                                ? 'Enter the business cash balance available when starting the software.'
                                : 'Enter the physically counted opening cash. Zero is not allowed.' }}
                        </small>
                        <small v-if="openDayForm.errors.opening_cash" class="mt-1 block text-xs text-red-500">
                            {{ openDayForm.errors.opening_cash }}
                        </small>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Counted Opening Gold</label>
                        <InputNumber v-model="openDayForm.opening_gold" :minFractionDigits="3" suffix=" g" class="w-full" />
                        <small class="mt-1 block text-xs text-surface-500">
                            {{ isInitialSetup
                                ? 'Enter the loose gold physically available in the business when starting the software.'
                                : 'Enter the physically counted opening gold. Zero is not allowed.' }}
                        </small>
                        <small v-if="openDayForm.errors.opening_gold" class="mt-1 block text-xs text-red-500">
                            {{ openDayForm.errors.opening_gold }}
                        </small>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Counted Opening Silver</label>
                        <InputNumber v-model="openDayForm.opening_silver" :minFractionDigits="3" suffix=" g" class="w-full" />
                        <small class="mt-1 block text-xs text-surface-500">
                            {{ isInitialSetup
                                ? 'Enter the loose silver physically available in the business when starting the software.'
                                : 'Enter the physically counted opening silver. Zero is not allowed.' }}
                        </small>
                        <small v-if="openDayForm.errors.opening_silver" class="mt-1 block text-xs text-red-500">
                            {{ openDayForm.errors.opening_silver }}
                        </small>
                    </div>

                    <div v-if="openingMismatch">
                        <label class="mb-2 block text-sm font-medium text-surface-700">Mismatch Reason</label>
                        <Textarea v-model="openDayForm.mismatch_reason" rows="3" class="w-full" placeholder="Why does today's counted opening differ from the last closed day?" />
                        <small class="mt-1 block text-xs text-amber-700">Required because the counted opening differs from the previous closing balance.</small>
                        <small v-if="openDayForm.errors.mismatch_reason" class="mt-1 block text-xs text-red-500">
                            {{ openDayForm.errors.mismatch_reason }}
                        </small>
                    </div>

                    <div v-if="dayStatus.has_register">
                        <label class="mb-2 block text-sm font-medium text-surface-700">Reopen Reason</label>
                        <Textarea v-model="openDayForm.reopen_reason" rows="3" class="w-full" placeholder="Why are you reopening the shop day for the same date?" />
                        <small class="mt-1 block text-xs text-amber-700">Required because a register already exists for today.</small>
                        <small v-if="openDayForm.errors.reopen_reason" class="mt-1 block text-xs text-red-500">
                            {{ openDayForm.errors.reopen_reason }}
                        </small>
                    </div>

                    <Button label="Open Day" icon="pi pi-check" class="w-full" @click="submitOpenDay" :loading="openDayForm.processing" />
                </template>

                <template v-else>
                    <div class="border border-surface-200 bg-surface-50 px-4 py-3 text-sm text-surface-700">
                        You do not have permission to open the shop day. Ask an authorized user to record today's counted opening balances.
                    </div>
                </template>
            </div>
        </Dialog>
    </div>
</template>
