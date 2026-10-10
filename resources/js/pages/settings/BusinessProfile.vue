<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, ref } from 'vue';

const props = defineProps({
    businessSetting: Object,
});

const toast = useToast();
const showRegenerateModal = ref(false);

const tabs = [
    { id: 'general', label: 'Store Profile', icon: 'pi pi-building' },
    { id: 'barcode', label: 'Barcode & Tags', icon: 'pi pi-qrcode' },
    { id: 'reviews', label: 'Google Reviews', icon: 'pi pi-star' },
    { id: 'vip_qr', label: 'VIP Walk-in QR', icon: 'pi pi-id-card' },
    { id: 'ai', label: 'AI Voice Copilot', icon: 'pi pi-sparkles' },
];

const getInitialTab = () => {
    if (typeof window !== 'undefined') {
        const tab = new URLSearchParams(window.location.search).get('tab');
        if (['general', 'barcode', 'reviews', 'vip_qr', 'ai'].includes(tab)) {
            return tab;
        }
    }
    return 'general';
};
const activeTab = ref(getInitialTab());

const voiceOptions = [
    { label: 'Aoede (Warm Female)', value: 'Aoede' },
    { label: 'Kore (Calm Female)', value: 'Kore' },
    { label: 'Puck (Natural Male)', value: 'Puck' },
    { label: 'Fenrir (Deep Male)', value: 'Fenrir' },
    { label: 'Charon (Authoritative Male)', value: 'Charon' },
];

const savedBarcode = props.businessSetting?.barcode_settings || {};

const form = useForm({
    store_name: props.businessSetting?.store_name || '',
    address: props.businessSetting?.address || '',
    phone: props.businessSetting?.phone || '',
    email: props.businessSetting?.email || '',
    website: props.businessSetting?.website || '',
    google_review_url: props.businessSetting?.google_review_url || '',
    gst_number: props.businessSetting?.gst_number || '',
    qr_onboarding_enabled: props.businessSetting?.qr_onboarding_enabled ?? true,
    qr_onboarding_token: props.businessSetting?.qr_onboarding_token || '',
    qr_onboarding_pin: props.businessSetting?.qr_onboarding_pin || '',
    regenerate_qr_token: false,
    barcode_settings: {
        preset: savedBarcode.preset || 'dumbbell_100_15',
        paper_width: Number(savedBarcode.paper_width ?? 100),
        paper_height: Number(savedBarcode.paper_height ?? 15),
        barcode_width: Number(savedBarcode.barcode_width ?? 49),
        barcode_height: Number(savedBarcode.barcode_height ?? 8.6),
        store_suffix: savedBarcode.store_suffix !== undefined ? savedBarcode.store_suffix : 'MRTN',
        show_guides: savedBarcode.show_guides ?? true,
    },
    ai_enabled: props.businessSetting?.ai_enabled ?? true,
    ai_hub_url: props.businessSetting?.ai_hub_url || 'http://127.0.0.1:8001',
    ai_api_key: props.businessSetting?.ai_api_key || '',
    ai_voice_enabled: props.businessSetting?.ai_voice_enabled ?? true,
    ai_voice_name: props.businessSetting?.ai_voice_name || 'Aoede',
    logo: null,
    remove_logo: false,
});

const breadcrumbs = [
    {
        title: 'Business profile',
        href: '/settings/business-profile',
    },
];

const currentLogoUrl = props.businessSetting?.logo_url || null;

const onLogoChange = (event) => {
    form.logo = event.target.files?.[0] || null;
    form.remove_logo = false;
};

const removeLogo = () => {
    form.logo = null;
    form.remove_logo = true;
};

const regenerateToken = () => {
    showRegenerateModal.value = true;
};

const confirmRegenerate = () => {
    showRegenerateModal.value = false;
    form.regenerate_qr_token = true;
    saveBusinessProfile();
    toast.add({
        severity: 'info',
        summary: 'Regenerating Token',
        detail: 'Creating a new QR onboarding token...',
        life: 2500,
    });
};

const copyJoinUrl = () => {
    if (props.businessSetting?.qr_onboarding_url) {
        navigator.clipboard.writeText(props.businessSetting.qr_onboarding_url);
        toast.add({
            severity: 'success',
            summary: 'Link Copied',
            detail: 'VIP Onboarding Link copied to clipboard.',
            life: 2500,
        });
    }
};

const saveBusinessProfile = () => {
    form.transform((data) => ({
        ...data,
        _method: 'patch',
    })).post(route('business-settings.update'), {
        forceFormData: true,
        onSuccess: () => {
            form.regenerate_qr_token = false;
            toast.add({
                severity: 'success',
                summary: 'Settings Saved',
                detail: 'Business profile and settings updated successfully.',
                life: 3000,
            });
        },
    });
};

const openStandee = () => {
    window.open(route('business-settings.standee.print'), '_blank', 'noopener');
};

const openOnboardingStandee = () => {
    window.open(route('business-settings.onboarding-standee.print'), '_blank', 'noopener');
};

const barcodePresets = [
    {
        label: 'Standard Dumbbell Tag (100mm × 15mm) - Default',
        value: 'dumbbell_100_15',
        paper_width: 100,
        paper_height: 15,
        barcode_width: 49,
        barcode_height: 8.6,
    },
    {
        label: 'Butterfly Tag (84mm × 12mm)',
        value: 'butterfly_84_12',
        paper_width: 84,
        paper_height: 12,
        barcode_width: 41,
        barcode_height: 6.8,
    },
    {
        label: 'Mini Tag (75mm × 12mm)',
        value: 'mini_75_12',
        paper_width: 75,
        paper_height: 12,
        barcode_width: 36,
        barcode_height: 6.8,
    },
    {
        label: 'Compact Tag (50mm × 15mm)',
        value: 'compact_50_15',
        paper_width: 50,
        paper_height: 15,
        barcode_width: 25,
        barcode_height: 8.6,
    },
    {
        label: 'Custom Dimensions',
        value: 'custom',
    },
];

const onPresetChange = (presetValue) => {
    form.barcode_settings.preset = presetValue;
    const found = barcodePresets.find((p) => p.value === presetValue);
    if (found && found.value !== 'custom') {
        form.barcode_settings.paper_width = found.paper_width;
        form.barcode_settings.paper_height = found.paper_height;
        form.barcode_settings.barcode_width = found.barcode_width;
        form.barcode_settings.barcode_height = found.barcode_height;
    }
};

const resetBarcodeSettings = () => {
    onPresetChange('dumbbell_100_15');
    form.barcode_settings.store_suffix = 'MRTN';
    form.barcode_settings.show_guides = true;
    toast.add({
        severity: 'info',
        summary: 'Defaults Restored',
        detail: 'Standard 100mm × 15mm values loaded.',
        life: 2500,
    });
};

const openBarcodeTestPrint = () => {
    window.open(route('business-settings.barcode.test'), '_blank', 'noopener');
};

const currentTabTitle = computed(() => {
    switch (activeTab.value) {
        case 'barcode':
            return 'Barcode & Jewellery Tag Printing';
        case 'reviews':
            return 'Google Review & Counter Standee';
        case 'vip_qr':
            return 'VIP Customer QR Self-Registration';
        case 'ai':
            return 'Karat AI Voice Assistant';
        default:
            return 'Showroom Profile & Store Details';
    }
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Business Settings" />

        <SettingsLayout>
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
                <!-- Main Settings Panel -->
                <div class="erp-panel overflow-hidden border border-surface-200 bg-white shadow-sm rounded-xl">
                    <!-- Top Action Header with Sticky Save Button -->
                    <div class="px-6 py-5 sm:px-8 border-b border-surface-200 bg-white sticky top-0 z-20 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2.5">
                                <h2 class="text-lg font-bold text-surface-900 tracking-tight">{{ currentTabTitle }}</h2>
                                <span v-if="form.isDirty" class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20">
                                    Unsaved
                                </span>
                            </div>
                            <p class="mt-0.5 text-xs text-surface-500">
                                Configure showroom identity, thermal barcode sizing, and customer QR standees.
                            </p>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <span v-if="form.recentlySuccessful" class="text-xs font-semibold text-emerald-600 flex items-center gap-1.5 bg-emerald-50 px-2.5 py-1.5 rounded-md border border-emerald-200">
                                <i class="pi pi-check-circle"></i> Saved
                            </span>
                            <Button
                                label="Save Changes"
                                icon="pi pi-check"
                                severity="primary"
                                size="small"
                                :loading="form.processing"
                                :disabled="form.processing"
                                @click="saveBusinessProfile"
                            />
                        </div>
                    </div>

                    <!-- Clean Segmented Tab Navigation with Guaranteed Spacing -->
                    <div class="border-b border-surface-200 bg-surface-50/70 px-6 py-3.5 sm:px-8">
                        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar py-0.5">
                            <button
                                v-for="tab in tabs"
                                :key="tab.id"
                                type="button"
                                @click="activeTab = tab.id"
                                class="flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-semibold whitespace-nowrap transition-all cursor-pointer border"
                                :class="activeTab === tab.id
                                    ? 'bg-surface-900 text-white border-surface-900 shadow-xs'
                                    : 'bg-white text-surface-600 border-surface-200 hover:bg-surface-100 hover:text-surface-900'"
                            >
                                <i :class="[tab.icon, 'text-xs', { 'text-emerald-400': tab.id === 'ai' && activeTab === tab.id }]"></i>
                                <span>{{ tab.label }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- TAB 1: STORE PROFILE & BRANDING -->
                    <div v-if="activeTab === 'general'" class="p-6 sm:p-8 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-surface-700 mb-1.5">Store Name</label>
                                <InputText v-model="form.store_name" class="w-full text-sm" placeholder="Enter showroom name" />
                                <small v-if="form.errors.store_name" class="mt-1 block text-xs text-red-500">{{ form.errors.store_name }}</small>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1.5">Phone Number</label>
                                <InputText v-model="form.phone" class="w-full text-sm" placeholder="e.g. 9892820518" />
                                <small v-if="form.errors.phone" class="mt-1 block text-xs text-red-500">{{ form.errors.phone }}</small>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1.5">Email Address</label>
                                <InputText v-model="form.email" type="email" class="w-full text-sm" placeholder="e.g. contact@maniratnjewellers.com" />
                                <small v-if="form.errors.email" class="mt-1 block text-xs text-red-500">{{ form.errors.email }}</small>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1.5">Website URL</label>
                                <InputText v-model="form.website" class="w-full text-sm" placeholder="https://maniratnjewellers.com" />
                                <small v-if="form.errors.website" class="mt-1 block text-xs text-red-500">{{ form.errors.website }}</small>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1.5">GST Identification Number (GSTIN)</label>
                                <InputText v-model="form.gst_number" class="w-full font-mono text-sm uppercase" placeholder="e.g. 27MANIRATN1234Z" />
                                <small v-if="form.errors.gst_number" class="mt-1 block text-xs text-red-500">{{ form.errors.gst_number }}</small>
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-surface-700 mb-1.5">Showroom Address</label>
                                <Textarea v-model="form.address" rows="3" class="w-full text-sm" placeholder="Enter full store address printed on bills" />
                                <small v-if="form.errors.address" class="mt-1 block text-xs text-red-500">{{ form.errors.address }}</small>
                            </div>
                        </div>

                        <!-- Store Logo Row -->
                        <div class="border-t border-surface-200 pt-6">
                            <label class="block text-sm font-medium text-surface-700 mb-1">Store Logo</label>
                            <p class="text-xs text-surface-500 mb-4">Logo appears in top-left of printed tax invoices and estimate slips.</p>

                            <div class="flex flex-col sm:flex-row items-start gap-5">
                                <div class="flex-1 w-full">
                                    <input
                                        type="file"
                                        accept="image/*"
                                        class="block w-full text-xs text-surface-700 file:mr-4 file:rounded-lg file:border-0 file:bg-surface-100 file:px-4 file:py-2.5 file:text-xs file:font-semibold file:text-surface-900 hover:file:bg-surface-200 cursor-pointer border border-surface-200 rounded-lg p-1.5 bg-surface-50/50"
                                        @change="onLogoChange"
                                    />
                                    <small v-if="form.errors.logo" class="mt-1 block text-xs text-red-500">{{ form.errors.logo }}</small>
                                </div>

                                <div v-if="currentLogoUrl && !form.remove_logo" class="flex items-center gap-4 rounded-xl border border-surface-200 bg-surface-50 px-4 py-3 shrink-0">
                                    <img :src="currentLogoUrl" alt="Business logo" class="h-12 max-w-36 rounded-md object-contain border border-surface-200 bg-white p-1" />
                                    <div>
                                        <p class="text-xs font-bold text-surface-800">Active Logo</p>
                                        <Button label="Remove" icon="pi pi-trash" severity="danger" text size="small" class="mt-0.5 !p-0" @click="removeLogo" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Save Action -->
                        <div class="flex items-center justify-end gap-3 border-t border-surface-200 pt-6">
                            <Button label="Save Changes" icon="pi pi-check" :loading="form.processing" :disabled="form.processing" @click="saveBusinessProfile" />
                        </div>
                    </div>

                    <!-- TAB 2: BARCODE & TAG PRINTING -->
                    <div v-if="activeTab === 'barcode'" class="p-6 sm:p-8 space-y-6">
                        <!-- Top Banner with Action Buttons -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-xl border border-sky-200 bg-sky-50/50">
                            <div>
                                <h3 class="text-sm font-bold text-surface-900 flex items-center gap-2">
                                    <i class="pi pi-qrcode text-sky-600"></i>
                                    Thermal Jewellery Tag Sizing
                                </h3>
                                <p class="text-xs text-surface-500 mt-1">Configure roll dimensions and barcode layout for TSC, Zebra, Citizen, or Argox thermal printers.</p>
                            </div>
                            <div class="flex items-center gap-2.5 shrink-0">
                                <Button label="Reset Defaults" icon="pi pi-refresh" severity="secondary" size="small" type="button" @click="resetBarcodeSettings" />
                                <Button label="Test Print Sample" icon="pi pi-print" severity="info" size="small" type="button" @click="openBarcodeTestPrint" />
                            </div>
                        </div>

                        <!-- Preset Dropdown -->
                        <div>
                            <label class="block text-sm font-medium text-surface-700 mb-1.5">Sticker Tag Preset</label>
                            <Select
                                v-model="form.barcode_settings.preset"
                                :options="barcodePresets"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Select Preset"
                                class="w-full text-xs"
                                @update:modelValue="onPresetChange"
                            />
                            <span class="text-[11px] text-surface-400 mt-1 block">Choose a standard Indian jewellery tag preset or fine-tune below.</span>
                        </div>

                        <!-- 4 Dimension Inputs in Clean Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-surface-600 mb-2">Label Width (mm)</label>
                                <InputNumber
                                    v-model="form.barcode_settings.paper_width"
                                    :min="20"
                                    :max="200"
                                    :minFractionDigits="1"
                                    :maxFractionDigits="2"
                                    class="w-full text-xs"
                                    suffix=" mm"
                                    @update:modelValue="form.barcode_settings.preset = 'custom'"
                                />
                                <span class="block text-[11px] text-surface-400 mt-1">Full sticker width (Default: 100 mm)</span>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-surface-600 mb-2">Label Height (mm)</label>
                                <InputNumber
                                    v-model="form.barcode_settings.paper_height"
                                    :min="8"
                                    :max="100"
                                    :minFractionDigits="1"
                                    :maxFractionDigits="2"
                                    class="w-full text-xs"
                                    suffix=" mm"
                                    @update:modelValue="form.barcode_settings.preset = 'custom'"
                                />
                                <span class="block text-[11px] text-surface-400 mt-1">Tag height (Default: 15 mm)</span>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-surface-600 mb-2">Barcode Width (mm)</label>
                                <InputNumber
                                    v-model="form.barcode_settings.barcode_width"
                                    :min="10"
                                    :max="150"
                                    :minFractionDigits="1"
                                    :maxFractionDigits="2"
                                    class="w-full text-xs"
                                    suffix=" mm"
                                    @update:modelValue="form.barcode_settings.preset = 'custom'"
                                />
                                <span class="block text-[11px] text-surface-400 mt-1">1D Barcode width (Default: 49 mm)</span>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-surface-600 mb-2">Barcode Height (mm)</label>
                                <InputNumber
                                    v-model="form.barcode_settings.barcode_height"
                                    :min="4"
                                    :max="50"
                                    :minFractionDigits="1"
                                    :maxFractionDigits="2"
                                    class="w-full text-xs"
                                    suffix=" mm"
                                    @update:modelValue="form.barcode_settings.preset = 'custom'"
                                />
                                <span class="block text-[11px] text-surface-400 mt-1">Barcode height (Default: 8.6 mm)</span>
                            </div>
                        </div>

                        <!-- Store Suffix and Fold Guides Row -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center pt-2">
                            <div>
                                <label class="block text-sm font-medium text-surface-700 mb-1.5">Store Code Suffix</label>
                                <InputText
                                    v-model="form.barcode_settings.store_suffix"
                                    placeholder="e.g. MRTN"
                                    class="w-full font-mono uppercase text-xs"
                                />
                                <span class="block text-[11px] text-surface-400 mt-1">Printed under barcode (e.g. MJ-00001 MRTN)</span>
                            </div>

                            <div class="flex items-center gap-3 p-3.5 rounded-lg border border-surface-200 bg-surface-50/70 mt-1">
                                <input
                                    type="checkbox"
                                    id="barcode_guides_toggle"
                                    v-model="form.barcode_settings.show_guides"
                                    class="h-4 w-4 rounded text-sky-600 focus:ring-sky-500 cursor-pointer"
                                />
                                <label for="barcode_guides_toggle" class="text-xs font-medium text-surface-700 cursor-pointer">
                                    Show Dumbbell Fold & Split Guide Lines (Helpful for jewellery tag folding)
                                </label>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3 border-t border-surface-200 pt-6">
                            <Button label="Save Changes" icon="pi pi-check" :loading="form.processing" :disabled="form.processing" @click="saveBusinessProfile" />
                        </div>
                    </div>

                    <!-- TAB 3: GOOGLE REVIEWS & STANDEE -->
                    <div v-if="activeTab === 'reviews'" class="p-6 sm:p-8 space-y-6">
                        <div class="rounded-xl border border-blue-200 bg-blue-50/40 p-5">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 text-blue-700">
                                    <i class="pi pi-star-fill text-lg"></i>
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-surface-900">Google Review Counter Integration</h3>
                                    <p class="text-xs text-surface-500">Collect 5-star customer feedback straight from counter standees and invoice QR codes.</p>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-surface-700 mb-1.5">Google Business Review Short Link</label>
                                    <InputText
                                        v-model="form.google_review_url"
                                        class="w-full text-xs font-mono"
                                        placeholder="https://g.page/r/YOUR_SHORT_CODE/review"
                                    />
                                    <small class="text-[11px] text-surface-500 block mt-1">
                                        Obtained from Google Business Profile &gt; "Ask for reviews".
                                    </small>
                                </div>

                                <div class="rounded-lg border border-blue-200 bg-white p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs">
                                    <div>
                                        <p class="text-xs font-bold text-surface-800">Countertop Review Standee</p>
                                        <p class="text-[11px] text-surface-500">Print an elegant A5 tabletop standee for your billing desk.</p>
                                    </div>
                                    <Button
                                        label="Print Counter Standee"
                                        icon="pi pi-print"
                                        severity="secondary"
                                        size="small"
                                        type="button"
                                        @click="openStandee"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Save Action -->
                        <div class="flex items-center justify-end gap-3 border-t border-surface-200 pt-6">
                            <Button label="Save Changes" icon="pi pi-check" :loading="form.processing" :disabled="form.processing" @click="saveBusinessProfile" />
                        </div>
                    </div>

                    <!-- TAB 4: VIP WALK-IN QR SELF-REGISTRATION -->
                    <div v-if="activeTab === 'vip_qr'" class="p-6 sm:p-8 space-y-6">
                        <div class="rounded-xl border border-amber-200 bg-amber-50/40 p-5">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                                        <i class="pi pi-qrcode text-lg"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold text-surface-900">Walk-in VIP Self-Registration</h3>
                                        <p class="text-xs text-surface-500">Allow customers to scan counter QR and register their KYC / membership.</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <label class="text-xs text-surface-600 font-medium cursor-pointer">Enable QR Onboarding</label>
                                    <input type="checkbox" v-model="form.qr_onboarding_enabled" class="h-4 w-4 rounded text-amber-600 focus:ring-amber-500 cursor-pointer" />
                                </div>
                            </div>

                            <div v-if="form.qr_onboarding_enabled" class="space-y-5 pt-3 border-t border-amber-200">
                                <div class="grid gap-5 sm:grid-cols-2">
                                    <div>
                                        <div class="flex items-center justify-between mb-1.5">
                                            <label class="block text-sm font-medium text-surface-700">Secret Token</label>
                                            <button type="button" class="text-[11px] text-amber-700 hover:text-amber-800 font-semibold underline cursor-pointer" @click="regenerateToken">
                                                Regenerate
                                            </button>
                                        </div>
                                        <InputText v-model="form.qr_onboarding_token" readonly class="w-full bg-surface-100 font-mono text-xs text-surface-700" />
                                        <small class="text-[10px] text-surface-500 mt-1 block">Authorizes genuine submissions without exposing your ERP internals.</small>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-surface-700 mb-1.5">Optional Store Counter PIN</label>
                                        <InputText v-model="form.qr_onboarding_pin" class="w-full bg-white font-mono text-xs" placeholder="e.g. 9892 (Leave empty for direct scan)" />
                                        <small class="text-[10px] text-surface-500 mt-1 block">Optional 4-digit code prompted during scanning.</small>
                                    </div>
                                </div>

                                <div class="rounded-lg border border-amber-200 bg-white p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-semibold text-surface-900 flex items-center gap-1.5">
                                            <i class="pi pi-link text-amber-600 text-xs"></i> Walk-in Registration URL:
                                        </p>
                                        <p class="mt-1 text-xs font-mono text-surface-600 truncate">
                                            {{ props.businessSetting?.qr_onboarding_url || 'https://maniratnjewellers.com/join?code=...' }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <Button label="Copy Link" icon="pi pi-copy" severity="secondary" size="small" type="button" @click="copyJoinUrl" />
                                        <Button label="Print VIP Standee" icon="pi pi-print" severity="warning" size="small" type="button" @click="openOnboardingStandee" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Save Action -->
                        <div class="flex items-center justify-end gap-3 border-t border-surface-200 pt-6">
                            <Button label="Save Changes" icon="pi pi-check" :loading="form.processing" :disabled="form.processing" @click="saveBusinessProfile" />
                        </div>
                    </div>

                    <!-- TAB 5: AI VOICE ASSISTANT -->
                    <div v-if="activeTab === 'ai'" class="p-6 sm:p-8 space-y-6">
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50/40 p-5">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                                        <i class="pi pi-sparkles text-lg"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold text-surface-900">Karat AI Voice Copilot</h3>
                                        <p class="text-xs text-surface-500">Configure central AI LLM endpoints and spoken voice persona.</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <label class="text-xs text-surface-600 font-medium cursor-pointer">Enable AI</label>
                                    <input type="checkbox" v-model="form.ai_enabled" class="h-4 w-4 rounded text-emerald-600 focus:ring-emerald-500 cursor-pointer" />
                                </div>
                            </div>

                            <div v-if="form.ai_enabled" class="grid gap-5 sm:grid-cols-2 pt-3 border-t border-emerald-200">
                                <div>
                                    <label class="block text-sm font-medium text-surface-700 mb-1.5">Central AI Hub URL</label>
                                    <InputText v-model="form.ai_hub_url" class="w-full bg-white text-xs font-mono" placeholder="http://127.0.0.1:8001" />
                                    <small class="text-[10px] text-surface-500 mt-1 block">URL of your central maniratn-ai server.</small>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-surface-700 mb-1.5">Shop AI Secret Key</label>
                                    <InputText v-model="form.ai_api_key" class="w-full bg-white font-mono text-xs" placeholder="mn_live_..." />
                                    <small class="text-[10px] text-surface-500 mt-1 block">Unique secret token from your AI subscription.</small>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-surface-700 mb-1.5">HD Voice Persona</label>
                                    <Select
                                        v-model="form.ai_voice_name"
                                        :options="voiceOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        placeholder="Select Voice Persona"
                                        class="w-full text-xs"
                                    />
                                </div>

                                <div class="flex items-center gap-2.5 pt-6">
                                    <input type="checkbox" id="ai_voice_toggle" v-model="form.ai_voice_enabled" class="h-4 w-4 rounded text-emerald-600 focus:ring-emerald-500 cursor-pointer" />
                                    <label for="ai_voice_toggle" class="text-xs font-medium text-surface-700 cursor-pointer">
                                        Enable Spoken Audio Response
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Save Action -->
                        <div class="flex items-center justify-end gap-3 border-t border-surface-200 pt-6">
                            <Button label="Save Changes" icon="pi pi-check" :loading="form.processing" :disabled="form.processing" @click="saveBusinessProfile" />
                        </div>
                    </div>
                </div>

                <!-- Right Contextual Sidebar -->
                <div class="space-y-5">
                    <!-- Dynamic Tip Card based on active tab -->
                    <div v-if="activeTab === 'barcode'" class="erp-panel p-5 bg-sky-50/40 border-sky-200 rounded-xl">
                        <div class="flex items-center gap-2 text-sky-800 font-bold text-sm mb-2.5">
                            <i class="pi pi-info-circle"></i>
                            <span>Barcode Printing Guide</span>
                        </div>
                        <ul class="space-y-2 text-xs text-surface-600 leading-relaxed">
                            <li><strong>Standard 100×15mm</strong> is the standard for Indian dumbbell/rat-tail jewellery stickers.</li>
                            <li>Always click <strong>Test Print Sample</strong> before printing in bulk to check thermal alignment.</li>
                            <li>Fold guide lines help karigars and staff wrap the tail around the ornament without covering the barcode.</li>
                        </ul>
                    </div>

                    <div v-else-if="activeTab === 'vip_qr'" class="erp-panel p-5 bg-amber-50/40 border-amber-200 rounded-xl">
                        <div class="flex items-center gap-2 text-amber-800 font-bold text-sm mb-2.5">
                            <i class="pi pi-shield"></i>
                            <span>VIP QR Security</span>
                        </div>
                        <p class="text-xs text-surface-600 leading-relaxed">
                            Regenerating the token invalidates previous counter standees. Use an optional counter PIN if you only want walk-ins assisted by showroom staff to submit.
                        </p>
                    </div>

                    <div v-else-if="activeTab === 'ai'" class="erp-panel p-5 bg-emerald-50/40 border-emerald-200 rounded-xl">
                        <div class="flex items-center gap-2 text-emerald-800 font-bold text-sm mb-2.5">
                            <i class="pi pi-sparkles"></i>
                            <span>AI Copilot & Voice</span>
                        </div>
                        <p class="text-xs text-surface-600 leading-relaxed">
                            Karat AI provides instant stock lookups, customer ledger summaries, and billing voice queries. Ensure your Central AI Hub is reachable.
                        </p>
                    </div>

                    <div v-else class="erp-panel p-5 rounded-xl border border-surface-200">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-surface-600">Store Identity</h3>
                        <p class="mt-2 text-xs leading-5 text-surface-500">
                            The business name and details entered here will automatically populate headers across customer printouts, receipts, and invoices.
                        </p>
                    </div>

                    <!-- Quick Navigation Links -->
                    <div class="erp-panel p-5 rounded-xl border border-surface-200">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-surface-600 mb-3">Sections</h3>
                        <div class="space-y-1">
                            <button
                                v-for="tab in tabs"
                                :key="tab.id"
                                type="button"
                                class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium transition-colors flex items-center justify-between cursor-pointer"
                                :class="activeTab === tab.id ? 'bg-surface-100 text-surface-900 font-bold' : 'text-surface-600 hover:bg-surface-50'"
                                @click="activeTab = tab.id"
                            >
                                <span class="flex items-center gap-2">
                                    <i :class="tab.icon" class="text-xs"></i>
                                    {{ tab.label }}
                                </span>
                                <i class="pi pi-chevron-right text-[10px] text-surface-400"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Confirmation Modal for Token Regeneration -->
            <Dialog
                v-model:visible="showRegenerateModal"
                modal
                header="Regenerate QR Secret Token?"
                :style="{ width: '440px' }"
                :closable="true"
            >
                <div class="space-y-3 pt-1">
                    <div class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-3.5 text-amber-900">
                        <i class="pi pi-exclamation-triangle text-amber-600 text-lg shrink-0 mt-0.5"></i>
                        <div class="text-xs leading-relaxed">
                            <p class="font-bold text-amber-900 mb-1">Important Notice</p>
                            <p>Regenerating this token will immediately invalidate all previously printed tabletop standees and QR codes. Customers scanning old QR codes will not be able to register until you reprint the standees.</p>
                        </div>
                    </div>
                    <p class="text-xs text-surface-600">Are you sure you want to proceed and generate a new token?</p>
                </div>

                <template #footer>
                    <div class="flex items-center justify-end gap-2 pt-2">
                        <Button label="Cancel" severity="secondary" text size="small" @click="showRegenerateModal = false" />
                        <Button label="Yes, Regenerate Token" severity="warn" size="small" icon="pi pi-refresh" @click="confirmRegenerate" />
                    </div>
                </template>
            </Dialog>

            <Toast position="top-right" />
        </SettingsLayout>
    </AppLayout>
</template>
