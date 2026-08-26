<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';

const props = defineProps({
    businessSetting: Object,
});

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

const saveBusinessProfile = () => {
    form.transform((data) => ({
        ...data,
        _method: 'patch',
    })).post(route('business-settings.update'), {
        forceFormData: true,
    });
};

const openStandee = () => {
    window.open(route('business-settings.standee.print'), '_blank', 'noopener');
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

                        <div class="md:col-span-2 border border-emerald-500/30 bg-emerald-50/20 p-4">
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
                            <input type="file" accept="image/*" class="block w-full text-sm text-surface-700 file:mr-4 file:border-0 file:bg-surface-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-surface-900" @change="onLogoChange" />
                            <small v-if="form.errors.logo" class="mt-1 block text-xs text-red-500">{{ form.errors.logo }}</small>

                            <div v-if="currentLogoUrl && !form.remove_logo" class="mt-4 border border-surface-200 bg-surface-50 p-4">
                                <p class="mb-3 text-xs font-medium uppercase tracking-[0.16em] text-surface-500">Current logo</p>
                                <img :src="currentLogoUrl" alt="Business logo" class="max-h-24 max-w-full object-contain" />
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
        </SettingsLayout>
    </AppLayout>
</template>
