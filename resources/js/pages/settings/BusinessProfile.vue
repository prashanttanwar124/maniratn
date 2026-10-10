<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tab from 'primevue/tab';
import TabList from 'primevue/tablist';
import TabPanel from 'primevue/tabpanel';
import TabPanels from 'primevue/tabpanels';
import Tabs from 'primevue/tabs';
import Textarea from 'primevue/textarea';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, ref } from 'vue';

const props = defineProps({
    businessSetting: Object,
});

const toast = useToast();
const showRegenerateModal = ref(false);

// Active Tab with URL query support (e.g. /settings/business-profile?tab=barcode)
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
            return 'General Store Profile & Branding';
    }
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Business Settings" />

        <SettingsLayout>
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                <!-- Main Settings Container with Tabs -->
                <div class="erp-panel overflow-hidden">
                    <!-- Top Action Header with Sticky Save Button -->
                    <div class="p-6 border-b border-surface-200 bg-white sticky top-0 z-20 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-lg font-bold text-surface-900">{{ currentTabTitle }}</h2>
                                <span v-if="form.isDirty" class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">
                                    Unsaved Changes
                                </span>
                            </div>
                            <p class="mt-0.5 text-xs text-surface-500">
                                Manage showroom profile, barcode sizing, QR standees, and AI assistant configurations.
                            </p>
                        </div>
                        <div class="flex items-center gap-2.5 shrink-0">
                            <span v-if="form.recentlySuccessful" class="text-xs font-semibold text-emerald-600 flex items-center gap-1">
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

                    <!-- Modern Tabs Navigation -->
                    <Tabs v-model:value="activeTab">
                        <TabList class="border-b border-surface-200 bg-surface-50/70 px-4 pt-1 overflow-x-auto flex-nowrap">
                            <Tab value="general" class="!px-4 !py-3">
                                <div class="flex items-center gap-2 text-xs font-semibold whitespace-nowrap">
                                    <i class="pi pi-building text-sm"></i>
                                    <span>Store Profile</span>
                                </div>
                            </Tab>

                            <Tab value="barcode" class="!px-4 !py-3">
                                <div class="flex items-center gap-2 text-xs font-semibold whitespace-nowrap">
                                    <i class="pi pi-qrcode text-sm"></i>
                                    <span>Barcode & Tags</span>
                                </div>
                            </Tab>

                            <Tab value="reviews" class="!px-4 !py-3">
                                <div class="flex items-center gap-2 text-xs font-semibold whitespace-nowrap">
                                    <i class="pi pi-star text-sm"></i>
                                    <span>Google Reviews</span>
                                </div>
                            </Tab>

                            <Tab value="vip_qr" class="!px-4 !py-3">
                                <div class="flex items-center gap-2 text-xs font-semibold whitespace-nowrap">
                                    <i class="pi pi-id-card text-sm"></i>
                                    <span>VIP Walk-in QR</span>
                                </div>
                            </Tab>

                            <Tab value="ai" class="!px-4 !py-3">
                                <div class="flex items-center gap-2 text-xs font-semibold whitespace-nowrap">
                                    <i class="pi pi-sparkles text-sm text-emerald-600"></i>
                                    <span>AI Assistant</span>
                                </div>
                            </Tab>
                        </TabList>

                        <TabPanels class="p-6">
                            <!-- TAB 1: STORE PROFILE & BRANDING -->
                            <TabPanel value="general">
                                <div class="space-y-6">
                                    <div class="grid gap-5 md:grid-cols-2">
                                        <div class="md:col-span-2">
                                            <label class="mb-1.5 block text-xs font-semibold text-surface-700">Store Name</label>
                                            <InputText v-model="form.store_name" class="w-full text-sm" placeholder="Enter store name" />
                                            <small v-if="form.errors.store_name" class="mt-1 block text-xs text-red-500">{{ form.errors.store_name }}</small>
                                        </div>

                                        <div>
                                            <label class="mb-1.5 block text-xs font-semibold text-surface-700">Phone Number</label>
                                            <InputText v-model="form.phone" class="w-full text-sm" placeholder="Enter phone number" />
                                            <small v-if="form.errors.phone" class="mt-1 block text-xs text-red-500">{{ form.errors.phone }}</small>
                                        </div>

                                        <div>
                                            <label class="mb-1.5 block text-xs font-semibold text-surface-700">Email Address</label>
                                            <InputText v-model="form.email" type="email" class="w-full text-sm" placeholder="Enter business email" />
                                            <small v-if="form.errors.email" class="mt-1 block text-xs text-red-500">{{ form.errors.email }}</small>
                                        </div>

                                        <div>
                                            <label class="mb-1.5 block text-xs font-semibold text-surface-700">Website URL</label>
                                            <InputText v-model="form.website" class="w-full text-sm" placeholder="https://maniratnjewellers.com" />
                                            <small v-if="form.errors.website" class="mt-1 block text-xs text-red-500">{{ form.errors.website }}</small>
                                        </div>

                                        <div>
                                            <label class="mb-1.5 block text-xs font-semibold text-surface-700">GST Identification Number (GSTIN)</label>
                                            <InputText v-model="form.gst_number" class="w-full font-mono text-sm uppercase" placeholder="e.g. 24ABCDE1234F1Z5" />
                                            <small v-if="form.errors.gst_number" class="mt-1 block text-xs text-red-500">{{ form.errors.gst_number }}</small>
                                        </div>

                                        <div class="md:col-span-2">
                                            <label class="mb-1.5 block text-xs font-semibold text-surface-700">Showroom Address</label>
                                            <Textarea v-model="form.address" rows="3" class="w-full text-sm" placeholder="Enter full store address printed on bills" />
                                            <small v-if="form.errors.address" class="mt-1 block text-xs text-red-500">{{ form.errors.address }}</small>
                                        </div>

                                        <div class="md:col-span-2 border-t border-surface-200 pt-5">
                                            <label class="mb-1.5 block text-xs font-semibold text-surface-700">Store Logo</label>
                                            <p class="mb-3 text-xs text-surface-500">Logo appears in top-left of printed tax invoices and estimate slips.</p>
                                            <input
                                                type="file"
                                                accept="image/*"
                                                class="block w-full text-xs text-surface-700 file:mr-4 file:rounded-lg file:border-0 file:bg-surface-100 file:px-4 file:py-2 file:text-xs file:font-semibold file:text-surface-900 hover:file:bg-surface-200 cursor-pointer"
                                                @change="onLogoChange"
                                            />
                                            <small v-if="form.errors.logo" class="mt-1 block text-xs text-red-500">{{ form.errors.logo }}</small>

                                            <div v-if="currentLogoUrl && !form.remove_logo" class="mt-4 flex items-center gap-4 rounded-xl border border-surface-200 bg-surface-50 p-4 shadow-2xs">
                                                <img :src="currentLogoUrl" alt="Business logo" class="h-16 max-w-44 rounded-md object-contain border border-surface-200 bg-white p-1" />
                                                <div>
                                                    <p class="text-xs font-bold text-surface-800">Active Logo</p>
                                                    <p class="text-[11px] text-surface-500">Used across all invoice headers.</p>
                                                    <Button label="Remove Logo" icon="pi pi-trash" severity="danger" text size="small" class="mt-1 !p-0" @click="removeLogo" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-end gap-3 border-t border-surface-200 pt-5">
                                        <Button label="Save Changes" icon="pi pi-check" :loading="form.processing" :disabled="form.processing" @click="saveBusinessProfile" />
                                    </div>
                                </div>
                            </TabPanel>

                            <!-- TAB 2: BARCODE & TAG PRINTING -->
                            <TabPanel value="barcode">
                                <div class="space-y-6">
                                    <!-- Barcode Controls Header -->
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-xl border border-sky-200 bg-sky-50/40">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-sky-100 text-sky-700">
                                                <i class="pi pi-qrcode text-lg font-bold"></i>
                                            </div>
                                            <div>
                                                <h3 class="text-sm font-bold text-surface-900">Thermal Jewellery Tag Sizing</h3>
                                                <p class="text-xs text-surface-500">Adjust roll dimensions for TSC, Zebra, Citizen, or Argox thermal printers.</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <Button label="Reset Defaults" icon="pi pi-refresh" severity="secondary" size="small" type="button" @click="resetBarcodeSettings" />
                                            <Button label="Test Print Sample" icon="pi pi-print" severity="info" size="small" type="button" @click="openBarcodeTestPrint" />
                                        </div>
                                    </div>

                                    <!-- Preset Selector -->
                                    <div>
                                        <label class="mb-1.5 block text-xs font-semibold text-surface-700">Sticker Tag Preset</label>
                                        <Select
                                            v-model="form.barcode_settings.preset"
                                            :options="barcodePresets"
                                            optionLabel="label"
                                            optionValue="value"
                                            placeholder="Select Preset"
                                            class="w-full text-xs"
                                            @update:modelValue="onPresetChange"
                                        />
                                        <small class="text-[11px] text-surface-500">Select standard jewellery tag size or customize individual dimensions below.</small>
                                    </div>

                                    <!-- Dimensions Inputs Grid -->
                                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                        <div class="rounded-lg border border-surface-200 bg-surface-50/50 p-3.5">
                                            <label class="mb-1 block text-xs font-semibold text-surface-700">Label Width (mm)</label>
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
                                            <small class="text-[10px] text-surface-500">Full sticker width (Default: 100 mm)</small>
                                        </div>

                                        <div class="rounded-lg border border-surface-200 bg-surface-50/50 p-3.5">
                                            <label class="mb-1 block text-xs font-semibold text-surface-700">Label Height (mm)</label>
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
                                            <small class="text-[10px] text-surface-500">Tag height (Default: 15 mm)</small>
                                        </div>

                                        <div class="rounded-lg border border-surface-200 bg-surface-50/50 p-3.5">
                                            <label class="mb-1 block text-xs font-semibold text-surface-700">Barcode Width (mm)</label>
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
                                            <small class="text-[10px] text-surface-500">1D Barcode width (Default: 49 mm)</small>
                                        </div>

                                        <div class="rounded-lg border border-surface-200 bg-surface-50/50 p-3.5">
                                            <label class="mb-1 block text-xs font-semibold text-surface-700">Barcode Height (mm)</label>
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
                                            <small class="text-[10px] text-surface-500">Vertical height (Default: 8.6 mm)</small>
                                        </div>
                                    </div>

                                    <!-- Tag Extra Settings -->
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label class="mb-1.5 block text-xs font-semibold text-surface-700">Store Code Suffix</label>
                                            <InputText
                                                v-model="form.barcode_settings.store_suffix"
                                                placeholder="e.g. MRTN"
                                                class="w-full font-mono text-xs uppercase"
                                            />
                                            <small class="text-[10px] text-surface-500">Showroom identifier printed under the barcode (e.g. MJ-00001 MRTN).</small>
                                        </div>

                                        <div class="flex items-center gap-2 pt-6">
                                            <input
                                                type="checkbox"
                                                id="barcode_guides_toggle"
                                                v-model="form.barcode_settings.show_guides"
                                                class="h-4 w-4 rounded text-sky-600 focus:ring-sky-500"
                                            />
                                            <label for="barcode_guides_toggle" class="text-xs font-medium text-surface-700 cursor-pointer">
                                                Show Fold & Split Guide Lines (Helpful for dumbbell tag alignment)
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Live Visual Tag Preview -->
                                    <div class="rounded-xl border border-surface-200 bg-surface-50/70 p-4">
                                        <div class="flex items-center justify-between mb-3">
                                            <span class="text-xs font-bold uppercase tracking-wider text-surface-600">
                                                Interactive Tag Preview
                                            </span>
                                            <span class="text-[11px] font-mono text-surface-500 bg-white px-2 py-0.5 rounded border border-surface-200">
                                                {{ form.barcode_settings.paper_width }}mm × {{ form.barcode_settings.paper_height }}mm
                                            </span>
                                        </div>

                                        <div class="relative w-full overflow-x-auto py-2">
                                            <div
                                                class="relative mx-auto rounded border-2 border-dashed border-surface-300 bg-white p-2 shadow-sm flex items-center justify-between select-none"
                                                :style="{
                                                    width: 'min(100%, 540px)',
                                                    minHeight: '85px',
                                                }"
                                            >
                                                <!-- Left Zone: Item Info -->
                                                <div class="w-2/5 border-r border-surface-200 pr-3 flex flex-col justify-center text-center">
                                                    <p class="text-xs font-bold text-surface-900 tracking-tight">GOLD 22K</p>
                                                    <p class="text-[11px] font-semibold text-surface-800 font-mono mt-0.5">GW 12.450G</p>
                                                    <p class="text-[11px] font-semibold text-surface-800 font-mono">NW 11.200G</p>
                                                </div>

                                                <!-- Right Zone: Simulated Vector Barcode -->
                                                <div class="w-3/5 pl-3 flex flex-col items-center justify-center">
                                                    <div class="flex items-center gap-[2px] h-7 w-3/4 justify-center bg-surface-100/50 py-1 px-2 rounded">
                                                        <div class="w-[2px] h-full bg-surface-900"></div>
                                                        <div class="w-[1px] h-full bg-surface-900"></div>
                                                        <div class="w-[3px] h-full bg-surface-900"></div>
                                                        <div class="w-[1px] h-full bg-surface-900"></div>
                                                        <div class="w-[2px] h-full bg-surface-900"></div>
                                                        <div class="w-[1px] h-full bg-surface-900"></div>
                                                        <div class="w-[3px] h-full bg-surface-900"></div>
                                                        <div class="w-[2px] h-full bg-surface-900"></div>
                                                        <div class="w-[1px] h-full bg-surface-900"></div>
                                                        <div class="w-[3px] h-full bg-surface-900"></div>
                                                        <div class="w-[2px] h-full bg-surface-900"></div>
                                                        <div class="w-[1px] h-full bg-surface-900"></div>
                                                    </div>
                                                    <p class="mt-1 font-mono text-[10px] font-bold text-surface-700">
                                                        MJ-00001 {{ form.barcode_settings.store_suffix || 'MRTN' }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-end gap-3 border-t border-surface-200 pt-5">
                                        <Button label="Save Changes" icon="pi pi-check" :loading="form.processing" :disabled="form.processing" @click="saveBusinessProfile" />
                                    </div>
                                </div>
                            </TabPanel>

                            <!-- TAB 3: GOOGLE REVIEWS & STANDEE -->
                            <TabPanel value="reviews">
                                <div class="space-y-6">
                                    <div class="rounded-xl border border-blue-200 bg-blue-50/30 p-5">
                                        <div class="flex items-center gap-3 mb-4">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 text-blue-700">
                                                <i class="pi pi-star-fill text-lg"></i>
                                            </div>
                                            <div>
                                                <h3 class="text-sm font-bold text-surface-900">Google Review Integration</h3>
                                                <p class="text-xs text-surface-500">Collect 5-star customer feedback straight from counter standees and invoice QR codes.</p>
                                            </div>
                                        </div>

                                        <div>
                                            <label class="mb-1.5 block text-xs font-semibold text-surface-700">Google Business Review Short Link</label>
                                            <InputText
                                                v-model="form.google_review_url"
                                                class="w-full text-xs font-mono"
                                                placeholder="https://g.page/r/YOUR_SHORT_CODE/review"
                                            />
                                            <small class="text-[11px] text-surface-500 block mt-1">
                                                Obtained from your Google Business Profile &gt; "Ask for reviews".
                                            </small>
                                        </div>

                                        <div class="mt-5 rounded-lg border border-blue-200 bg-white p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs">
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

                                    <div class="flex items-center justify-end gap-3 border-t border-surface-200 pt-5">
                                        <Button label="Save Changes" icon="pi pi-check" :loading="form.processing" :disabled="form.processing" @click="saveBusinessProfile" />
                                    </div>
                                </div>
                            </TabPanel>

                            <!-- TAB 4: VIP WALK-IN QR SELF-REGISTRATION -->
                            <TabPanel value="vip_qr">
                                <div class="space-y-6">
                                    <div class="rounded-xl border border-amber-200 bg-amber-50/30 p-5">
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
                                                <label class="text-xs text-surface-600 font-medium">Enable QR Onboarding</label>
                                                <input type="checkbox" v-model="form.qr_onboarding_enabled" class="h-4 w-4 rounded text-amber-600 focus:ring-amber-500" />
                                            </div>
                                        </div>

                                        <div v-if="form.qr_onboarding_enabled" class="space-y-4 pt-3 border-t border-amber-200">
                                            <div class="grid gap-4 sm:grid-cols-2">
                                                <div>
                                                    <div class="flex items-center justify-between mb-1.5">
                                                        <label class="block text-xs font-semibold text-surface-700">Secret Token</label>
                                                        <button type="button" class="text-[11px] text-amber-700 hover:text-amber-800 font-medium underline" @click="regenerateToken">
                                                            Regenerate
                                                        </button>
                                                    </div>
                                                    <InputText v-model="form.qr_onboarding_token" readonly class="w-full bg-surface-100 font-mono text-xs text-surface-700" />
                                                    <small class="text-[10px] text-surface-500">Authorizes genuine submissions without exposing your ERP internals.</small>
                                                </div>

                                                <div>
                                                    <label class="mb-1.5 block text-xs font-semibold text-surface-700">Optional Store Counter PIN</label>
                                                    <InputText v-model="form.qr_onboarding_pin" class="w-full bg-white font-mono text-xs" placeholder="e.g. 9892 (Leave empty for direct scan)" />
                                                    <small class="text-[10px] text-surface-500">Optional 4-digit code prompted during scanning.</small>
                                                </div>
                                            </div>

                                            <div class="rounded-lg border border-amber-200 bg-white p-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs">
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

                                    <div class="flex items-center justify-end gap-3 border-t border-surface-200 pt-5">
                                        <Button label="Save Changes" icon="pi pi-check" :loading="form.processing" :disabled="form.processing" @click="saveBusinessProfile" />
                                    </div>
                                </div>
                            </TabPanel>

                            <!-- TAB 5: AI VOICE ASSISTANT -->
                            <TabPanel value="ai">
                                <div class="space-y-6">
                                    <div class="rounded-xl border border-emerald-200 bg-emerald-50/30 p-5">
                                        <div class="flex items-center justify-between mb-4">
                                            <div class="flex items-center gap-3">
                                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                                                    <i class="pi pi-sparkles text-lg"></i>
                                                </div>
                                                <div>
                                                    <h3 class="text-sm font-bold text-surface-900">Karat AI Voice Assistant</h3>
                                                    <p class="text-xs text-surface-500">Configure central AI LLM endpoints and spoken voice persona.</p>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <label class="text-xs text-surface-600 font-medium">Enable AI</label>
                                                <input type="checkbox" v-model="form.ai_enabled" class="h-4 w-4 rounded text-emerald-600 focus:ring-emerald-500" />
                                            </div>
                                        </div>

                                        <div v-if="form.ai_enabled" class="grid gap-4 sm:grid-cols-2 pt-3 border-t border-emerald-200">
                                            <div>
                                                <label class="mb-1.5 block text-xs font-semibold text-surface-700">Central AI Hub URL</label>
                                                <InputText v-model="form.ai_hub_url" class="w-full bg-white text-xs font-mono" placeholder="http://127.0.0.1:8001" />
                                                <small class="text-[10px] text-surface-500">URL of your central maniratn-ai server.</small>
                                            </div>

                                            <div>
                                                <label class="mb-1.5 block text-xs font-semibold text-surface-700">Shop AI Secret Key</label>
                                                <InputText v-model="form.ai_api_key" class="w-full bg-white font-mono text-xs" placeholder="mn_live_..." />
                                                <small class="text-[10px] text-surface-500">Unique secret token from your AI subscription.</small>
                                            </div>

                                            <div>
                                                <label class="mb-1.5 block text-xs font-semibold text-surface-700">HD Voice Persona</label>
                                                <Select
                                                    v-model="form.ai_voice_name"
                                                    :options="voiceOptions"
                                                    optionLabel="label"
                                                    optionValue="value"
                                                    placeholder="Select Voice Persona"
                                                    class="w-full text-xs"
                                                />
                                            </div>

                                            <div class="flex items-center gap-2 pt-6">
                                                <input type="checkbox" id="ai_voice_toggle" v-model="form.ai_voice_enabled" class="h-4 w-4 rounded text-emerald-600 focus:ring-emerald-500" />
                                                <label for="ai_voice_toggle" class="text-xs font-medium text-surface-700 cursor-pointer">
                                                    Enable Spoken Audio Response
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-end gap-3 border-t border-surface-200 pt-5">
                                        <Button label="Save Changes" icon="pi pi-check" :loading="form.processing" :disabled="form.processing" @click="saveBusinessProfile" />
                                    </div>
                                </div>
                            </TabPanel>
                        </TabPanels>
                    </Tabs>
                </div>

                <!-- Right Contextual Sidebar -->
                <div class="space-y-5">
                    <!-- Dynamic Tip Card based on active tab -->
                    <div v-if="activeTab === 'barcode'" class="erp-panel p-5 bg-sky-50/30 border-sky-200">
                        <div class="flex items-center gap-2 text-sky-800 font-bold text-sm mb-2">
                            <i class="pi pi-info-circle"></i>
                            <span>Barcode Printing Guide</span>
                        </div>
                        <ul class="space-y-2 text-xs text-surface-600 leading-relaxed">
                            <li><strong>Standard 100×15mm</strong> is standard for Indian dumbbell/rat-tail jewellery stickers.</li>
                            <li>Always click <strong>Test Print Sample</strong> before printing in bulk to check thermal alignment.</li>
                            <li>Fold guide lines help karigars and staff wrap the tail around the ornament without covering the barcode.</li>
                        </ul>
                    </div>

                    <div v-else-if="activeTab === 'vip_qr'" class="erp-panel p-5 bg-amber-50/30 border-amber-200">
                        <div class="flex items-center gap-2 text-amber-800 font-bold text-sm mb-2">
                            <i class="pi pi-shield"></i>
                            <span>VIP QR Security</span>
                        </div>
                        <p class="text-xs text-surface-600 leading-relaxed">
                            Regenerating the token invalidates previous counter standees. Use an optional counter PIN if you only want walk-ins assisted by showroom staff to submit.
                        </p>
                    </div>

                    <div v-else-if="activeTab === 'ai'" class="erp-panel p-5 bg-emerald-50/30 border-emerald-200">
                        <div class="flex items-center gap-2 text-emerald-800 font-bold text-sm mb-2">
                            <i class="pi pi-sparkles"></i>
                            <span>AI Copilot & Voice</span>
                        </div>
                        <p class="text-xs text-surface-600 leading-relaxed">
                            Karat AI provides instant stock lookups, customer ledger summaries, and billing voice queries. Ensure your Central AI Hub is reachable.
                        </p>
                    </div>

                    <div v-else class="erp-panel p-5">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-surface-600">Store Identity</h3>
                        <p class="mt-2 text-xs leading-5 text-surface-500">
                            The business name and details entered here will automatically populate headers across customer printouts, receipts, and invoices.
                        </p>
                    </div>

                    <!-- General Persistent Tips -->
                    <div class="erp-panel p-5">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-surface-600 mb-3">Quick Navigation</h3>
                        <div class="space-y-1.5">
                            <button
                                type="button"
                                class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium transition-colors flex items-center justify-between"
                                :class="activeTab === 'general' ? 'bg-surface-100 text-surface-900 font-bold' : 'text-surface-600 hover:bg-surface-50'"
                                @click="activeTab = 'general'"
                            >
                                <span class="flex items-center gap-2"><i class="pi pi-building text-xs"></i> Store Profile</span>
                                <i class="pi pi-chevron-right text-[10px] text-surface-400"></i>
                            </button>
                            <button
                                type="button"
                                class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium transition-colors flex items-center justify-between"
                                :class="activeTab === 'barcode' ? 'bg-surface-100 text-surface-900 font-bold' : 'text-surface-600 hover:bg-surface-50'"
                                @click="activeTab = 'barcode'"
                            >
                                <span class="flex items-center gap-2"><i class="pi pi-qrcode text-xs"></i> Barcode & Tags</span>
                                <i class="pi pi-chevron-right text-[10px] text-surface-400"></i>
                            </button>
                            <button
                                type="button"
                                class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium transition-colors flex items-center justify-between"
                                :class="activeTab === 'reviews' ? 'bg-surface-100 text-surface-900 font-bold' : 'text-surface-600 hover:bg-surface-50'"
                                @click="activeTab = 'reviews'"
                            >
                                <span class="flex items-center gap-2"><i class="pi pi-star text-xs"></i> Google Reviews</span>
                                <i class="pi pi-chevron-right text-[10px] text-surface-400"></i>
                            </button>
                            <button
                                type="button"
                                class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium transition-colors flex items-center justify-between"
                                :class="activeTab === 'vip_qr' ? 'bg-surface-100 text-surface-900 font-bold' : 'text-surface-600 hover:bg-surface-50'"
                                @click="activeTab = 'vip_qr'"
                            >
                                <span class="flex items-center gap-2"><i class="pi pi-id-card text-xs"></i> VIP Walk-in QR</span>
                                <i class="pi pi-chevron-right text-[10px] text-surface-400"></i>
                            </button>
                            <button
                                type="button"
                                class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium transition-colors flex items-center justify-between"
                                :class="activeTab === 'ai' ? 'bg-surface-100 text-surface-900 font-bold' : 'text-surface-600 hover:bg-surface-50'"
                                @click="activeTab = 'ai'"
                            >
                                <span class="flex items-center gap-2"><i class="pi pi-sparkles text-xs text-emerald-600"></i> AI Assistant</span>
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
