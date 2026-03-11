<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import throttle from 'lodash/throttle';
import { ref, watch } from 'vue';
import { route } from 'ziggy-js';

import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    karigars: Array,
    recoveryDesk: Array,
    metrics: Object,
    filters: Object,
});

const page = usePage();
const toast = useToast();
const isDayOpen = ref(Boolean(page.props.dayStatus?.is_open));
const search = ref(props.filters?.search || '');
const karigarDialog = ref(false);
const deleteDialog = ref(false);
const deleteTarget = ref(null);
const isEditingKarigar = ref(false);

const karigarForm = useForm({
    id: null,
    name: '',
    mobile: '',
    work_type: '',
    city: '',
    notes: '',
});

watch(
    search,
    throttle((value) => {
        router.get(route('karigars.index'), { search: value }, { preserveState: true, preserveScroll: true, replace: true });
    }, 300),
);

const formatCurrency = (value) =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 0,
    }).format(value || 0);

const formatWeight = (value) => `${Number(value || 0).toFixed(3)} g`;

const openKarigarDialog = (karigar = null) => {
    if (!isDayOpen.value) {
        toast.add({ severity: 'warn', summary: 'Day Closed', detail: 'Open the shop day first from the dashboard.', life: 3000 });
        return;
    }
    karigarForm.reset();
    karigarForm.clearErrors();
    isEditingKarigar.value = Boolean(karigar);

    if (karigar) {
        karigarForm.id = karigar.id;
        karigarForm.name = karigar.name;
        karigarForm.mobile = karigar.mobile;
        karigarForm.work_type = karigar.work_type || '';
        karigarForm.city = karigar.city || '';
        karigarForm.notes = karigar.notes || '';
    }

    karigarDialog.value = true;
};

const saveKarigar = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            karigarDialog.value = false;
            toast.add({ severity: 'success', summary: 'Saved', detail: 'Karigar saved successfully', life: 3000 });
        },
    };

    if (isEditingKarigar.value) {
        karigarForm.put(route('karigars.update', karigarForm.id), options);
        return;
    }

    karigarForm.post(route('karigars.store'), options);
};

const confirmDelete = (record) => {
    deleteTarget.value = record;
    deleteDialog.value = true;
};

const deleteRecord = () => {
    if (!deleteTarget.value) return;

    router.delete(route('karigars.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deleteDialog.value = false;
            deleteTarget.value = null;
            toast.add({ severity: 'success', summary: 'Deleted', detail: 'Karigar deleted successfully', life: 3000 });
        },
    });
};
</script>

<template>
    <AppLayout>
        <Toast />
        <div class="space-y-6">
            <section class="border border-surface-200 bg-white px-5 py-6">
                <div class="flex flex-col gap-5">
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div class="max-w-3xl">
                            <div class="flex flex-wrap items-center gap-3">
                                <h1 class="text-2xl font-semibold tracking-tight text-surface-900">Karigar Desk</h1>
                                <Tag value="Workshop Relations" severity="secondary" />
                            </div>
                            <p class="mt-2 text-sm leading-6 text-surface-600">
                                Manage karigar relationships, monitor gold issued and labor exposure, and open karigar ledgers directly for follow-up and settlement.
                            </p>
                        </div>

                        <div class="flex justify-start lg:justify-end">
                            <Button label="New Karigar" icon="pi pi-user-plus" @click="openKarigarDialog()" :disabled="!isDayOpen" />
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Karigars</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ metrics?.karigar_count || 0 }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Cash Exposure</p>
                    <p class="mt-2 text-2xl font-semibold text-red-600">{{ formatCurrency(metrics?.karigar_cash_exposure) }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Gold With Karigars</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-700">{{ formatWeight(metrics?.karigar_gold_out) }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Urgent Accounts</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ metrics?.urgent_accounts || 0 }}</p>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <div class="overflow-hidden border border-surface-200 bg-white xl:col-span-2">
                    <div class="border-b border-surface-200 px-5 py-4">
                        <h2 class="text-lg font-semibold text-surface-900">Priority Recovery</h2>
                        <p class="mt-1 text-sm text-surface-500">Karigars with the highest combined cash and metal exposure.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-3 p-4 md:grid-cols-2">
                        <div v-for="row in recoveryDesk" :key="row.id" class="border border-surface-200 px-4 py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-medium text-surface-900">{{ row.name }}</p>
                                    <p class="mt-1 text-xs text-surface-500">Karigar priority account</p>
                                </div>
                                <Link :href="route('ledger.show', { type: 'karigars', id: row.id })">
                                    <Button icon="pi pi-arrow-right" text rounded />
                                </Link>
                            </div>

                            <div class="mt-4 grid grid-cols-2 gap-3">
                                <div class="border border-surface-200 bg-surface-50 px-3 py-3">
                                    <p class="text-xs uppercase tracking-wide text-surface-500">Cash</p>
                                    <p class="mt-1 text-sm font-semibold text-surface-900">{{ formatCurrency(row.cash_balance) }}</p>
                                </div>
                                <div class="border border-surface-200 bg-surface-50 px-3 py-3">
                                    <p class="text-xs uppercase tracking-wide text-surface-500">Gold</p>
                                    <p class="mt-1 text-sm font-semibold text-surface-900">{{ formatWeight(row.metal_balance) }}</p>
                                </div>
                            </div>
                        </div>

                        <div v-if="!recoveryDesk.length" class="col-span-full py-12 text-center text-surface-500">No karigar exposure right now.</div>
                    </div>
                </div>

                <div class="overflow-hidden border border-surface-200 bg-white">
                    <div class="border-b border-surface-200 px-5 py-4">
                        <h2 class="text-lg font-semibold text-surface-900">Recovery Notes</h2>
                        <p class="mt-1 text-sm text-surface-500">Suggested workshop follow-up actions.</p>
                    </div>
                    <div class="space-y-3 p-4 text-sm text-surface-600">
                        <div class="border border-surface-200 bg-surface-50 px-4 py-3">Review karigar ledger before issuing more gold.</div>
                        <div class="border border-surface-200 bg-surface-50 px-4 py-3">Track labor cash separately from metal return status.</div>
                        <div class="border border-surface-200 bg-surface-50 px-4 py-3">Use ledger entries for gold return and labor settlement to keep history clean.</div>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden border border-surface-200 bg-white">
                <div class="border-b border-surface-200 px-5 py-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-surface-900">Karigar Register</h2>
                            <p class="mt-1 text-sm text-surface-500">All karigars with running balances and direct ledger access.</p>
                        </div>

                        <div class="w-full lg:max-w-sm">
                            <InputText v-model="search" placeholder="Search karigars..." class="w-full" />
                        </div>
                    </div>
                </div>

                <div class="p-4">
                    <DataTable :value="karigars" stripedRows rowHover tableStyle="min-width: 60rem">
                        <template #empty>
                            <div class="py-12 text-center text-surface-500">No karigars found.</div>
                        </template>

                        <Column field="name" header="Karigar">
                            <template #body="{ data }">
                                <div>
                                    <p class="font-medium text-surface-900">{{ data.name }}</p>
                                    <p class="mt-1 text-xs text-surface-500">{{ data.mobile }}</p>
                                    <p v-if="data.work_type || data.city" class="mt-1 text-xs text-surface-400">
                                        {{ data.work_type || 'General Work' }}<span v-if="data.work_type && data.city"> • </span>{{ data.city || '' }}
                                    </p>
                                </div>
                            </template>
                        </Column>

                        <Column header="Profile">
                            <template #body="{ data }">
                                <div class="text-sm">
                                    <p class="font-medium text-surface-900">{{ data.work_type || 'General Work' }}</p>
                                    <p class="mt-1 text-xs text-surface-500">{{ data.city || 'City not added' }}</p>
                                </div>
                            </template>
                        </Column>

                        <Column header="Cash Balance">
                            <template #body="{ data }">
                                <span class="font-semibold text-surface-900">{{ formatCurrency(data.cash_balance) }}</span>
                            </template>
                        </Column>

                        <Column header="Gold Balance">
                            <template #body="{ data }">
                                <span class="font-semibold text-surface-900">{{ formatWeight(data.metal_balance) }}</span>
                            </template>
                        </Column>

                        <Column header="Notes">
                            <template #body="{ data }">
                                <p class="max-w-52 truncate text-sm text-surface-500">
                                    {{ data.notes || 'No notes added' }}
                                </p>
                            </template>
                        </Column>

                        <Column header="Actions" style="width: 220px">
                            <template #body="{ data }">
                                <div class="flex justify-end gap-2">
                                    <Link :href="route('ledger.show', { type: 'karigars', id: data.id })">
                                        <Button label="Ledger" icon="pi pi-book" text size="small" />
                                    </Link>
                                    <Button icon="pi pi-pencil" outlined size="small" @click="openKarigarDialog(data)" />
                                    <Button icon="pi pi-trash" outlined severity="danger" size="small" @click="confirmDelete(data)" />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </section>
        </div>

        <Dialog v-model:visible="karigarDialog" :header="isEditingKarigar ? 'Edit Karigar' : 'New Karigar'" modal :style="{ width: '28rem' }">
            <form class="space-y-4 pt-2" @submit.prevent="saveKarigar">
                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Karigar Name</label>
                    <InputText v-model="karigarForm.name" class="w-full" placeholder="Enter karigar name" />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Mobile</label>
                    <InputText v-model="karigarForm.mobile" class="w-full" placeholder="10-digit mobile number" />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Work Type</label>
                    <InputText v-model="karigarForm.work_type" class="w-full" placeholder="Polish, casting, setting, chain work..." />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">City</label>
                    <InputText v-model="karigarForm.city" class="w-full" placeholder="Enter city" />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Notes</label>
                    <InputText v-model="karigarForm.notes" class="w-full" placeholder="Specialisation or follow-up notes" />
                </div>

                <div class="flex justify-end gap-2 border-t border-surface-200 pt-4">
                    <Button label="Cancel" severity="secondary" text type="button" @click="karigarDialog = false" />
                    <Button :label="isEditingKarigar ? 'Update Karigar' : 'Save Karigar'" type="submit" :loading="karigarForm.processing" />
                </div>
            </form>
        </Dialog>

        <Dialog v-model:visible="deleteDialog" header="Delete Karigar" modal :style="{ width: '28rem' }">
            <div class="space-y-4 pt-2">
                <p class="text-sm text-surface-600">
                    Delete
                    <span class="font-medium text-surface-900">{{ deleteTarget?.name }}</span>
                    from the karigar desk?
                </p>

                <div class="flex justify-end gap-2 border-t border-surface-200 pt-4">
                    <Button label="Cancel" severity="secondary" text @click="deleteDialog = false" />
                    <Button label="Delete" severity="danger" @click="deleteRecord" />
                </div>
            </div>
        </Dialog>
    </AppLayout>
</template>
