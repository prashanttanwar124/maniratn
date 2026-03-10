<script setup lang="ts">
import InputError from '@/components/InputError.vue';

import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { confirm } from '@/routes/two-factor';
import { Form } from '@inertiajs/vue3';
import { useClipboard } from '@vueuse/core';
import { Check, Copy, ScanLine } from 'lucide-vue-next';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputOtp from 'primevue/inputotp';
import ProgressSpinner from 'primevue/progressspinner';
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue';

interface Props {
    requiresConfirmation: boolean;
    twoFactorEnabled: boolean;
}

const props = defineProps<Props>();
const isOpen = defineModel<boolean>('isOpen');

const { copy, copied } = useClipboard();
const { qrCodeSvg, manualSetupKey, clearSetupData, fetchSetupData, errors } = useTwoFactorAuth();

const showVerificationStep = ref(false);
const code = ref<string>('');

const pinInputContainerRef = useTemplateRef('pinInputContainerRef');

const modalConfig = computed<{
    title: string;
    description: string;
    buttonText: string;
}>(() => {
    if (props.twoFactorEnabled) {
        return {
            title: 'Two-Factor Authentication Enabled',
            description: 'Two-factor authentication is now enabled. Scan the QR code or enter the setup key in your authenticator app.',
            buttonText: 'Close',
        };
    }

    if (showVerificationStep.value) {
        return {
            title: 'Verify Authentication Code',
            description: 'Enter the 6-digit code from your authenticator app',
            buttonText: 'Continue',
        };
    }

    return {
        title: 'Enable Two-Factor Authentication',
        description: 'To finish enabling two-factor authentication, scan the QR code or enter the setup key in your authenticator app',
        buttonText: 'Continue',
    };
});

const handleModalNextStep = () => {
    if (props.requiresConfirmation) {
        showVerificationStep.value = true;

        nextTick(() => {
            pinInputContainerRef.value?.querySelector('input')?.focus();
        });

        return;
    }

    clearSetupData();
    isOpen.value = false;
};

const resetModalState = () => {
    if (props.twoFactorEnabled) {
        clearSetupData();
    }

    showVerificationStep.value = false;
    code.value = '';
};

watch(
    () => isOpen.value,
    async (open) => {
        if (!open) {
            resetModalState();
            return;
        }

        if (!qrCodeSvg.value) {
            await fetchSetupData();
        }
    },
);
</script>

<template>
    <Dialog :visible="isOpen" @update:visible="isOpen = $event" modal :style="{ width: '28rem' }">
        <template #header>
            <div class="flex w-full flex-col items-center gap-2">
                <div class="rounded-full border border-surface-200 bg-surface-0 p-0.5 shadow-sm dark:border-surface-700 dark:bg-surface-800">
                    <div class="relative overflow-hidden rounded-full border border-surface-200 bg-surface-100 p-2.5 dark:border-surface-700 dark:bg-surface-700">
                        <ScanLine class="relative z-20 size-6 text-surface-700 dark:text-surface-200" />
                    </div>
                </div>
                <h3 class="text-lg font-semibold">{{ modalConfig.title }}</h3>
                <p class="text-center text-sm text-surface-500">{{ modalConfig.description }}</p>
            </div>
        </template>

        <div class="relative flex w-auto flex-col items-center justify-center space-y-5">
            <template v-if="!showVerificationStep">
                <div v-if="errors?.length" class="w-full rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                    <p v-for="(error, i) in errors" :key="i" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>
                </div>
                <template v-else>
                    <div class="relative mx-auto flex max-w-md items-center overflow-hidden">
                        <div class="relative mx-auto aspect-square w-64 overflow-hidden rounded-lg border border-surface-200 dark:border-surface-700">
                            <div v-if="!qrCodeSvg" class="absolute inset-0 z-10 flex aspect-square h-auto w-full animate-pulse items-center justify-center bg-surface-0 dark:bg-surface-800">
                                <ProgressSpinner style="width: 24px; height: 24px" strokeWidth="4" />
                            </div>
                            <div v-else class="relative z-10 overflow-hidden border p-5">
                                <div v-html="qrCodeSvg" class="aspect-square w-full justify-center rounded-lg bg-white p-2 [&_svg]:size-full" />
                            </div>
                        </div>
                    </div>

                    <div class="flex w-full items-center space-x-5">
                        <Button class="w-full" :label="modalConfig.buttonText" @click="handleModalNextStep" />
                    </div>

                    <div class="relative flex w-full items-center justify-center">
                        <div class="absolute inset-0 top-1/2 h-px w-full bg-surface-200 dark:bg-surface-700" />
                        <span class="relative bg-surface-0 px-2 py-1 text-sm dark:bg-surface-800">or, enter the code manually</span>
                    </div>

                    <div class="flex w-full items-center justify-center space-x-2">
                        <div class="flex w-full items-stretch overflow-hidden rounded-xl border border-surface-200 dark:border-surface-700">
                            <div v-if="!manualSetupKey" class="flex h-full w-full items-center justify-center bg-surface-100 p-3 dark:bg-surface-800">
                                <ProgressSpinner style="width: 20px; height: 20px" strokeWidth="4" />
                            </div>
                            <template v-else>
                                <input type="text" readonly :value="manualSetupKey" class="h-full w-full bg-surface-0 p-3 text-sm text-surface-900 dark:bg-surface-800 dark:text-surface-0" />
                                <button
                                    @click="copy(manualSetupKey || '')"
                                    class="relative block h-auto border-l border-surface-200 px-3 hover:bg-surface-100 dark:border-surface-700 dark:hover:bg-surface-700"
                                >
                                    <Check v-if="copied" class="w-4 text-green-500" />
                                    <Copy v-else class="w-4" />
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
            </template>

            <template v-else>
                <Form v-bind="confirm.form()" reset-on-error @finish="code = ''" @success="isOpen = false" v-slot="{ errors, processing }">
                    <input type="hidden" name="code" :value="code" />
                    <div ref="pinInputContainerRef" class="relative w-full space-y-3">
                        <div class="flex w-full flex-col items-center justify-center space-y-3 py-2">
                            <InputOtp v-model="code" :length="6" integerOnly :disabled="processing" />
                            <InputError :message="errors?.confirmTwoFactorAuthentication?.code" />
                        </div>

                        <div class="flex w-full items-center space-x-5">
                            <Button severity="secondary" label="Back" class="flex-1" @click="showVerificationStep = false" :disabled="processing" />
                            <Button type="submit" label="Confirm" class="flex-1" :disabled="processing || code.length < 6" :loading="processing" />
                        </div>
                    </div>
                </Form>
            </template>
        </div>
    </Dialog>
</template>
