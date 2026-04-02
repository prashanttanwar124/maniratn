<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';

const props = defineProps({
    businessSetting: Object,
});

const form = useForm({
    store_name: props.businessSetting?.store_name || '',
    address: props.businessSetting?.address || '',
    phone: props.businessSetting?.phone || '',
    email: props.businessSetting?.email || '',
    website: props.businessSetting?.website || '',
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
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Business profile" />

        <SettingsLayout>
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                <div class="border border-surface-200 bg-white p-5">
                    <div>
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

                        <div class="md:col-span-2">
                            <label class="mb-2 block text-sm font-medium text-surface-700">Website</label>
                            <InputText v-model="form.website" class="w-full" placeholder="Enter website URL" />
                            <small v-if="form.errors.website" class="mt-1 block text-xs text-red-500">{{ form.errors.website }}</small>
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
                    <div class="border border-surface-200 bg-white p-5">
                        <h3 class="text-base font-semibold text-surface-900">Where this will be used</h3>
                        <div class="mt-4 space-y-3 text-sm text-surface-600">
                            <div class="border border-surface-200 bg-surface-50 px-4 py-3">Invoice print header</div>
                            <div class="border border-surface-200 bg-surface-50 px-4 py-3">Future WhatsApp invoice share</div>
                            <div class="border border-surface-200 bg-surface-50 px-4 py-3">Customer-facing documents and reports</div>
                        </div>
                    </div>

                    <div class="border border-surface-200 bg-white p-5">
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
