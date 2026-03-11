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
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    suppliers: Array,
    recoveryDesk: Array,
    metrics: Object,
    filters: Object,
});

const page = usePage();
const toast = useToast();
const isDayOpen = ref(Boolean(page.props.dayStatus?.is_open));
const search = ref(props.filters?.search || '');
const supplierDialog = ref(false);
const deleteDialog = ref(false);
const deleteTarget = ref(null);
const isEditingSupplier = ref(false);

const supplierTypes = [
    { label: 'Gold', value: 'GOLD' },
    { label: 'Silver', value: 'SILVER' },
    { label: 'Diamond', value: 'DIAMOND' },
    { label: 'Packaging', value: 'PACKAGING' },
];

const supplierForm = useForm({
    id: null,
    company_name: '',
    contact_person: '',
    mobile: '',
    type: 'GOLD',
    gst_number: '',
    pan_no: '',
    bank_name: '',
    account_no: '',
    ifsc_code: '',
});

watch(
    search,
    throttle((value) => {
        router.get(route('suppliers.index'), { search: value }, { preserveState: true, preserveScroll: true, replace: true });
    }, 300),
);

const formatCurrency = (value) =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 0,
    }).format(value || 0);

const formatWeight = (value) => `${Number(value || 0).toFixed(3)} g`;

const getTypeSeverity = (type) => {
    if (type === 'GOLD') return 'warning';
    if (type === 'SILVER') return 'secondary';
    if (type === 'DIAMOND') return 'info';
    if (type === 'PACKAGING') return 'success';
    return 'contrast';
};

const openSupplierDialog = (supplier = null) => {
    if (!isDayOpen.value) {
        toast.add({ severity: 'warn', summary: 'Day Closed', detail: 'Open the shop day first from the dashboard.', life: 3000 });
        return;
    }
    supplierForm.reset();
    supplierForm.clearErrors();
    isEditingSupplier.value = Boolean(supplier);

    if (supplier) {
        Object.assign(supplierForm, supplier);
    } else {
        supplierForm.type = 'GOLD';
    }

    supplierDialog.value = true;
};

const saveSupplier = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            supplierDialog.value = false;
            toast.add({ severity: 'success', summary: 'Saved', detail: 'Supplier saved successfully', life: 3000 });
        },
    };

    if (isEditingSupplier.value) {
        supplierForm.put(route('suppliers.update', supplierForm.id), options);
        return;
    }

    supplierForm.post(route('suppliers.store'), options);
};

const confirmDelete = (record) => {
    deleteTarget.value = record;
    deleteDialog.value = true;
};

const deleteRecord = () => {
    if (!deleteTarget.value) return;

    router.delete(route('suppliers.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deleteDialog.value = false;
            deleteTarget.value = null;
            toast.add({ severity: 'success', summary: 'Deleted', detail: 'Supplier deleted successfully', life: 3000 });
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
                                <h1 class="text-2xl font-semibold tracking-tight text-surface-900">Supplier Desk</h1>
                                <Tag value="Vendor Relations" severity="secondary" />
                            </div>
                            <p class="mt-2 text-sm leading-6 text-surface-600">
                                Manage supplier relationships, review cash and gold exposure, and jump directly into supplier ledgers for settlement.
                            </p>
                        </div>

                        <div class="flex justify-start lg:justify-end">
                            <Button label="New Supplier" icon="pi pi-plus" @click="openSupplierDialog()" :disabled="!isDayOpen" />
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Suppliers</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ metrics?.supplier_count || 0 }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Cash Exposure</p>
                    <p class="mt-2 text-2xl font-semibold text-red-600">{{ formatCurrency(metrics?.supplier_cash_exposure) }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Gold With Suppliers</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-700">{{ formatWeight(metrics?.supplier_gold_out) }}</p>
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
                        <p class="mt-1 text-sm text-surface-500">Suppliers with the highest combined cash and metal exposure.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-3 p-4 md:grid-cols-2">
                        <div v-for="row in recoveryDesk" :key="row.id" class="border border-surface-200 px-4 py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-medium text-surface-900">{{ row.name }}</p>
                                    <p class="mt-1 text-xs text-surface-500">Supplier priority account</p>
                                </div>
                                <Link :href="route('ledger.show', { type: 'suppliers', id: row.id })">
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

                        <div v-if="!recoveryDesk.length" class="col-span-full py-12 text-center text-surface-500">No supplier exposure right now.</div>
                    </div>
                </div>

                <div class="overflow-hidden border border-surface-200 bg-white">
                    <div class="border-b border-surface-200 px-5 py-4">
                        <h2 class="text-lg font-semibold text-surface-900">Recovery Notes</h2>
                        <p class="mt-1 text-sm text-surface-500">Suggested supplier follow-up actions.</p>
                    </div>
                    <div class="space-y-3 p-4 text-sm text-surface-600">
                        <div class="border border-surface-200 bg-surface-50 px-4 py-3">Review supplier ledger before making settlement payments.</div>
                        <div class="border border-surface-200 bg-surface-50 px-4 py-3">Track cash paid against gold or stock received back from the supplier.</div>
                        <div class="border border-surface-200 bg-surface-50 px-4 py-3">Keep banking details updated for faster payout operations.</div>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden border border-surface-200 bg-white">
                <div class="border-b border-surface-200 px-5 py-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-surface-900">Supplier Register</h2>
                            <p class="mt-1 text-sm text-surface-500">All suppliers with balances, banking details, and ledger access.</p>
                        </div>

                        <div class="w-full lg:max-w-sm">
                            <InputText v-model="search" placeholder="Search suppliers..." class="w-full" />
                        </div>
                    </div>
                </div>

                <div class="p-4">
                    <DataTable :value="suppliers" stripedRows rowHover tableStyle="min-width: 72rem">
                        <template #empty>
                            <div class="py-12 text-center text-surface-500">No suppliers found.</div>
                        </template>

                        <Column field="company_name" header="Supplier">
                            <template #body="{ data }">
                                <div>
                                    <p class="font-medium text-surface-900">{{ data.company_name }}</p>
                                    <p class="mt-1 text-xs text-surface-500">{{ data.contact_person }} • {{ data.mobile }}</p>
                                </div>
                            </template>
                        </Column>

                        <Column field="type" header="Type">
                            <template #body="{ data }">
                                <Tag :value="data.type" :severity="getTypeSeverity(data.type)" />
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

                        <Column header="Bank">
                            <template #body="{ data }">
                                <div v-if="data.bank_name" class="text-sm">
                                    <p class="font-medium text-surface-900">{{ data.bank_name }}</p>
                                    <p class="mt-1 text-xs text-surface-500">{{ data.account_no || 'Account pending' }}</p>
                                </div>
                                <span v-else class="text-sm text-surface-400">Not added</span>
                            </template>
                        </Column>

                        <Column header="Actions" style="width: 220px">
                            <template #body="{ data }">
                                <div class="flex justify-end gap-2">
                                    <Link :href="route('ledger.show', { type: 'suppliers', id: data.id })">
                                        <Button label="Ledger" icon="pi pi-book" text size="small" />
                                    </Link>
                                    <Button icon="pi pi-pencil" outlined size="small" @click="openSupplierDialog(data)" />
                                    <Button icon="pi pi-trash" outlined severity="danger" size="small" @click="confirmDelete(data)" />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </section>
        </div>

        <Dialog v-model:visible="supplierDialog" :header="isEditingSupplier ? 'Edit Supplier' : 'New Supplier'" modal :style="{ width: '42rem' }">
            <form class="space-y-4 pt-2" @submit.prevent="saveSupplier">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-surface-700">Company Name</label>
                        <InputText v-model="supplierForm.company_name" class="w-full" placeholder="Enter supplier company name" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Contact Person</label>
                        <InputText v-model="supplierForm.contact_person" class="w-full" placeholder="Enter contact person" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Mobile</label>
                        <InputText v-model="supplierForm.mobile" class="w-full" placeholder="10-digit mobile number" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Type</label>
                        <Select v-model="supplierForm.type" :options="supplierTypes" optionLabel="label" optionValue="value" class="w-full" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">GST Number</label>
                        <InputText v-model="supplierForm.gst_number" class="w-full" placeholder="Optional GST number" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">PAN No</label>
                        <InputText v-model="supplierForm.pan_no" class="w-full" placeholder="Optional PAN number" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Bank Name</label>
                        <InputText v-model="supplierForm.bank_name" class="w-full" placeholder="Optional bank name" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Account No</label>
                        <InputText v-model="supplierForm.account_no" class="w-full" placeholder="Optional account number" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">IFSC Code</label>
                        <InputText v-model="supplierForm.ifsc_code" class="w-full" placeholder="Optional IFSC code" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-surface-200 pt-4">
                    <Button label="Cancel" severity="secondary" text type="button" @click="supplierDialog = false" />
                    <Button :label="isEditingSupplier ? 'Update Supplier' : 'Save Supplier'" type="submit" :loading="supplierForm.processing" />
                </div>
            </form>
        </Dialog>

        <Dialog v-model:visible="deleteDialog" header="Delete Supplier" modal :style="{ width: '28rem' }">
            <div class="space-y-4 pt-2">
                <p class="text-sm text-surface-600">
                    Delete
                    <span class="font-medium text-surface-900">{{ deleteTarget?.company_name }}</span>
                    from the supplier desk?
                </p>

                <div class="flex justify-end gap-2 border-t border-surface-200 pt-4">
                    <Button label="Cancel" severity="secondary" text @click="deleteDialog = false" />
                    <Button label="Delete" severity="danger" @click="deleteRecord" />
                </div>
            </div>
        </Dialog>
    </AppLayout>
</template>
