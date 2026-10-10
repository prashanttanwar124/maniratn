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
import { ref } from 'vue';

const props = defineProps({
    businessSetting: Object,
});

const toast = useToast();
const showRegenerateModal = ref(false);

const voiceOptions = [
    { label: 'Aoede (Warm Female)', value: 'Aoede' },
    { label: 'Kore (Calm Female)', value: 'Kore' },
    { label: 'Puck (Natural Male)', value: 'Puck' },
    { label: 'Fenrir (Deep Male)', value: 'Fenrir' },
    { label: 'Charon (Authoritative Male)', value: 'Charon' },
];

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
        preset: props.businessSetting?.barcode_settings?.preset || 'dumbbell_100_15',
        paper_width: Number(props.businessSetting?.barcode_settings?.paper_width ?? 100),
        paper_height: Number(props.businessSetting?.barcode_settings?.paper_height ?? 15),
        barcode_width: Number(props.businessSetting?.barcode_settings?.barcode_width ?? 49),
        barcode_height: Number(props.businessSetting?.barcode_settings?.barcode_height ?? 8.6),
        store_suffix: props.businessSetting?.barcode_settings?.store_suffix !== undefined ? props.businessSetting.barcode_settings.store_suffix : 'MRTN',
        show_guides: props.businessSetting?.barcode_settings?.show_guides ?? true,
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
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Business profile" />

        <SettingsLayout>
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                <div class="erp-panel p-6">
                    <div class="border-b border-surface-200 pb-4 mb-6">
                        <h2 class="text-lg font-semibold text-surface-900">Business Profile</h2>
                        <p class="mt-1 text-sm text-surface-500">Store logo and shop details used in invoice print and future customer-facing documents.</p>
                    </div>

                    <div class="mt-6 grid gap-5 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="mb-2 block text-sm font-medium text-surface-700">Store Name</label>
                            <InputText v-model="form.store_name" class="w-full" placeholder="Enter store name" />
                            <small v-if="form.errors.store_name" class="mt-1 block text-xs text-red-500">{{ form.errors.store_name }}</small>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-surface-700">Phone Number</label>
                            <InputText v-model="form.phone" class="w-full" placeholder="Enter phone number" />
                            <small v-if="form.errors.phone" class="mt-1 block text-xs text-red-500">{{ form.errors.phone }}</small>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-surface-700">Email</label>
                            <InputText v-model="form.email" type="email" class="w-full" placeholder="Enter business email" />
                            <small v-if="form.errors.email" class="mt-1 block text-xs text-red-500">{{ form.errors.email }}</small>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-surface-700">Website</label>
                            <InputText v-model="form.website" class="w-full" placeholder="https://maniratnjewellers.com" />
                            <small v-if="form.errors.website" class="mt-1 block text-xs text-red-500">{{ form.errors.website }}</small>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-surface-700">GST Number</label>
                            <InputText v-model="form.gst_number" class="w-full" placeholder="Enter GST number" />
                            <small v-if="form.errors.gst_number" class="mt-1 block text-xs text-red-500">{{ form.errors.gst_number }}</small>
                        </div>

                        <div class="md:col-span-2 erp-subpanel p-4">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-sm font-semibold text-surface-900">
                                    ⭐ Google Maps Review URL & Counter Display
                                </label>
                                <div class="flex items-center gap-3">
                                    <a
                                        v-if="form.google_review_url"
                                        :href="form.google_review_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-xs font-medium text-amber-700 hover:text-amber-800 flex items-center gap-1"
                                    >
                                        <i class="pi pi-external-link text-[10px]"></i> Test Link
                                    </a>
                                    <Button
                                        v-if="form.google_review_url"
                                        label="Print Counter Standee"
                                        icon="pi pi-print"
                                        severity="secondary"
                                        size="small"
                                        type="button"
                                        @click="openStandee"
                                    />
                                </div>
                            </div>
                            <InputText
                                v-model="form.google_review_url"
                                class="w-full bg-white"
                                placeholder="e.g. https://g.page/r/your-shop/review or https://maps.app.goo.gl/..."
                            />
                            <p class="mt-2 text-xs text-surface-600">
                                When entered, a <strong>scannable Google Review QR Code</strong> appears on printed invoices, in the Customer Digital Vault, and in WhatsApp bills. You can also print a beautiful <strong>tabletop counter standee</strong> for your showroom counter!
                            </p>
                            <small v-if="form.errors.google_review_url" class="mt-1 block text-xs text-red-500">{{ form.errors.google_review_url }}</small>
                        </div>

                        <!-- VIP Customer QR Self-Registration & Standee -->
                        <div class="md:col-span-2 rounded-xl border border-amber-500/30 bg-amber-50/20 p-5 shadow-xs">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-2">
                                    <i class="pi pi-qrcode text-amber-600 font-bold text-base"></i>
                                    <div>
                                        <label class="block text-sm font-bold text-surface-900">
                                            VIP Customer QR Self-Registration (Walk-in Standee)
                                        </label>
                                        <p class="text-xs text-surface-500">Allow showroom walk-in customers to scan QR and register themselves securely via website.</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <label class="text-xs text-surface-600 font-medium">Enable QR Onboarding</label>
                                    <input type="checkbox" v-model="form.qr_onboarding_enabled" class="h-4 w-4 rounded text-amber-600 focus:ring-amber-500" />
                                </div>
                            </div>

                            <div v-if="form.qr_onboarding_enabled" class="grid gap-4 sm:grid-cols-2 pt-3 border-t border-amber-500/20">
                                <div>
                                    <div class="flex items-center justify-between mb-1.5">
                                        <label class="block text-xs font-semibold text-surface-700">Counter QR Secret Token</label>
                                        <button type="button" class="text-[11px] text-amber-700 hover:text-amber-800 font-medium underline" @click="regenerateToken">
                                            Regenerate
                                        </button>
                                    </div>
                                    <InputText v-model="form.qr_onboarding_token" readonly class="w-full bg-surface-100 font-mono text-xs text-surface-700" />
                                    <small class="text-[10px] text-surface-500">Embedded in standee QR code to authorize genuine submissions without exposing your ERP.</small>
                                </div>

                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold text-surface-700">Optional Store Counter PIN</label>
                                    <InputText v-model="form.qr_onboarding_pin" class="w-full bg-white font-mono text-xs" placeholder="e.g. 4123 (Leave empty for direct scan)" />
                                    <small class="text-[10px] text-surface-500">Optional 4-digit code. If entered, customers will be prompted for this counter code.</small>
                                </div>

                                <div class="sm:col-span-2 rounded-lg border border-amber-200 bg-white p-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-semibold text-surface-900 flex items-center gap-1.5">
                                            <i class="pi pi-link text-amber-600 text-xs"></i> Walk-in Customer Registration Link:
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

                        <!-- Barcode & Jewellery Tag Printing Configuration -->
                        <div class="md:col-span-2 rounded-xl border border-sky-500/30 bg-sky-50/20 p-5 shadow-xs">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-sky-100 text-sky-700">
                                        <i class="pi pi-qrcode text-base font-bold"></i>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-surface-900">
                                            Barcode & Jewellery Tag Printing
                                        </label>
                                        <p class="text-xs text-surface-500">Configure sticker tag roll dimensions and barcode layout for thermal printers (TSC, Zebra, Citizen, etc.).</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <Button label="Reset Defaults" icon="pi pi-refresh" severity="secondary" size="small" type="button" @click="resetBarcodeSettings" />
                                    <Button label="Test Print Sample" icon="pi pi-print" severity="info" size="small" type="button" @click="openBarcodeTestPrint" />
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 pt-3 border-t border-sky-500/20">
                                <div class="sm:col-span-2 lg:col-span-4">
                                    <label class="mb-1.5 block text-xs font-semibold text-surface-700">Quick Label Preset</label>
                                    <Select
                                        v-model="form.barcode_settings.preset"
                                        :options="barcodePresets"
                                        optionLabel="label"
                                        optionValue="value"
                                        placeholder="Select Preset"
                                        class="w-full text-xs"
                                        @update:modelValue="onPresetChange"
                                    />
                                    <small class="text-[10px] text-surface-500">Select standard jewellery tag size or customize individual dimensions below.</small>
                                </div>

                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold text-surface-700">Label Width (mm)</label>
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
                                    <small class="text-[10px] text-surface-500">Total width of sticker paper.</small>
                                </div>

                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold text-surface-700">Label Height (mm)</label>
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
                                    <small class="text-[10px] text-surface-500">Height of sticker tag.</small>
                                </div>

                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold text-surface-700">Barcode Width (mm)</label>
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
                                    <small class="text-[10px] text-surface-500">Width of 1D barcode lines.</small>
                                </div>

                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold text-surface-700">Barcode Height (mm)</label>
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
                                    <small class="text-[10px] text-surface-500">Vertical height of barcode.</small>
                                </div>

                                <div class="sm:col-span-2">
                                    <label class="mb-1.5 block text-xs font-semibold text-surface-700">Store Code Suffix</label>
                                    <InputText
                                        v-model="form.barcode_settings.store_suffix"
                                        placeholder="e.g. MRTN"
                                        class="w-full font-mono text-xs uppercase"
                                    />
                                    <small class="text-[10px] text-surface-500">Short showroom tag printed next to product barcode (e.g. MJ-00001 MRTN).</small>
                                </div>

                                <div class="sm:col-span-2 flex items-center gap-2 pt-5">
                                    <input
                                        type="checkbox"
                                        id="barcode_guides_toggle"
                                        v-model="form.barcode_settings.show_guides"
                                        class="h-4 w-4 rounded text-sky-600 focus:ring-sky-500"
                                    />
                                    <label for="barcode_guides_toggle" class="text-xs font-medium text-surface-700">
                                        Show Dumbbell Fold & Split Guide Lines (Helpful for jewellery tag folding)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-2 rounded-lg border border-emerald-500/30 bg-emerald-50/20 p-4 shadow-xs">

                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-2">
                                    <i class="pi pi-sparkles text-emerald-600 font-bold"></i>
                                    <label class="block text-sm font-bold text-surface-900">
                                        Karat AI Voice Assistant Configuration
                                    </label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <label class="text-xs text-surface-600 font-medium">Enable AI</label>
                                    <input type="checkbox" v-model="form.ai_enabled" class="h-4 w-4 rounded text-emerald-600 focus:ring-emerald-500" />
                                </div>
                            </div>

                            <div v-if="form.ai_enabled" class="grid gap-4 sm:grid-cols-2 pt-2 border-t border-emerald-500/20">
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold text-surface-700">Central AI Hub URL</label>
                                    <InputText v-model="form.ai_hub_url" class="w-full bg-white" placeholder="http://127.0.0.1:8001" />
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
                                    <label for="ai_voice_toggle" class="text-xs font-medium text-surface-700">Enable Spoken Audio Response</label>
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-2 block text-sm font-medium text-surface-700">Address</label>
                            <Textarea v-model="form.address" rows="4" class="w-full" placeholder="Enter full store address" />
                            <small v-if="form.errors.address" class="mt-1 block text-xs text-red-500">{{ form.errors.address }}</small>
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-2 block text-sm font-medium text-surface-700">Logo</label>
                            <input type="file" accept="image/*" class="block w-full text-sm text-surface-700 file:mr-4 file:rounded-lg file:border-0 file:bg-surface-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-surface-900" @change="onLogoChange" />
                            <small v-if="form.errors.logo" class="mt-1 block text-xs text-red-500">{{ form.errors.logo }}</small>

                            <div v-if="currentLogoUrl && !form.remove_logo" class="mt-4 rounded-lg border border-surface-200 bg-surface-50 p-4 shadow-xs">
                                <p class="mb-3 text-xs font-medium uppercase tracking-[0.16em] text-surface-500">Current logo</p>
                                <img :src="currentLogoUrl" alt="Business logo" class="max-h-24 max-w-full rounded-md object-contain" />
                                <Button label="Remove Logo" icon="pi pi-trash" severity="danger" text class="mt-3 !px-0" @click="removeLogo" />
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center gap-3 border-t border-surface-200 pt-4">
                        <Button label="Save Business Profile" :loading="form.processing" :disabled="form.processing" @click="saveBusinessProfile" />
                        <span v-if="form.recentlySuccessful" class="text-sm text-green-700">Business profile updated.</span>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="erp-panel p-5">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-surface-600">Store Identity</h3>
                        <p class="mt-2 text-xs leading-5 text-surface-500">
                            The business name and details entered here will automatically populate headers across customer printouts, receipts, and invoices.
                        </p>
                    </div>

                    <div class="erp-panel p-5">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-surface-600">Tax & Compliance</h3>
                        <p class="mt-2 text-xs leading-5 text-surface-500">
                            Valid GST format ensures accurate 3% GST calculation and legally compliant tax invoices for customers.
                        </p>
                    </div>

                    <div class="erp-panel p-5">
                        <h3 class="text-base font-semibold text-surface-900">Tips</h3>
                        <ul class="mt-4 space-y-2 text-sm text-surface-600">
                            <li>Use a clean square or horizontal logo for better invoice print clarity.</li>
                            <li>Keep the phone number and address exactly as you want them to appear on invoices.</li>
                            <li>Website can be added now even if it is not live yet.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Sleek PrimeVue Confirmation Modal for Token Regeneration -->
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

