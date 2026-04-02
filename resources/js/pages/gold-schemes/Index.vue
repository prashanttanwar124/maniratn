<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import InputGroup from 'primevue/inputgroup';
import InputGroupAddon from 'primevue/inputgroupaddon';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { formatIndianDate, todayIndianDate, toIndianDateInput } from '@/utils/indiaTime';

const props = defineProps({
    customerSchemes: Array,
    customers: Array,
    summary: Object,
});

const toast = useToast();
const enrollDialog = ref(false);
const collectDialog = ref(false);
const installmentsDialog = ref(false);
const installmentsLoading = ref(false);
const voidDialog = ref(false);
const schemeDialog = ref(false);
const cancelDialog = ref(false);
const helpDialog = ref(false);
const selectedInstallment = ref(null);
const selectedScheme = ref(null);
const searchTerm = ref('');
const statusFilter = ref('ALL');

const schemeForm = useForm({
    id: null,
    customer_id: null,
    start_date: todayIndianDate(),
    monthly_amount: null,
    total_months: 11,
    bonus_amount: null,
    already_paid_months: 0,
    import_mode: 'HISTORY_ONLY',
    import_payment_method: 'CASH',
    notes: '',
});

const paymentMethodOptions = ['CASH', 'UPI', 'BANK', 'CARD'];
const statusOptions = [
    { label: 'All Schemes', value: 'ALL' },
    { label: 'Active', value: 'ACTIVE' },
    { label: 'Matured', value: 'MATURED' },
    { label: 'Cancelled', value: 'CANCELLED' },
];
const importModeOptions = [
    { label: 'History Only', value: 'HISTORY_ONLY' },
    { label: 'Post To Vault', value: 'POST_TO_VAULT' },
];
const installmentForm = useForm({
    amount_paid: null,
    paid_on: todayIndianDate(),
    payment_method: 'CASH',
    note: '',
});
const voidForm = useForm({
    reason: '',
});
const cancelForm = useForm({
    reason: '',
});

const formatCurrency = (value) =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 0,
    }).format(value || 0);

const formatDate = (value) =>
    value
        ? formatIndianDate(value, {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          })
        : '—';

const openEnrollDialog = () => {
    schemeForm.reset();
    schemeForm.clearErrors();
    schemeForm.id = null;
    schemeForm.start_date = todayIndianDate();
    schemeForm.total_months = 11;
    schemeForm.already_paid_months = 0;
    schemeForm.import_mode = 'HISTORY_ONLY';
    schemeForm.import_payment_method = 'CASH';
    enrollDialog.value = true;
};

const openEditSchemeDialog = (scheme) => {
    schemeForm.reset();
    schemeForm.clearErrors();
    schemeForm.id = scheme.id;
    schemeForm.customer_id = scheme.customer?.id ?? null;
    schemeForm.start_date = scheme.start_date;
    schemeForm.monthly_amount = Number(scheme.monthly_amount);
    schemeForm.total_months = Number(scheme.total_months);
    schemeForm.bonus_amount = Number(scheme.bonus_amount);
    schemeForm.already_paid_months = 0;
    schemeForm.import_mode = 'HISTORY_ONLY';
    schemeForm.import_payment_method = 'CASH';
    schemeForm.notes = scheme.notes || '';
    schemeDialog.value = true;
};

const openCancelSchemeDialog = (scheme) => {
    selectedScheme.value = scheme;
    cancelForm.reset();
    cancelForm.clearErrors();
    cancelDialog.value = true;
};

const openPrintScheme = (scheme) => {
    window.open(route('gold-schemes.print', scheme.id), '_blank', 'noopener,noreferrer');
};

watch(
    () => schemeForm.already_paid_months,
    (value) => {
        if (schemeForm.id) return;

        const today = new Date(`${todayIndianDate()}T00:00:00`);
        today.setMonth(today.getMonth() - Number(value || 0));
        schemeForm.start_date = toIndianDateInput(today);
    }
);

const saveEnrollment = () => {
    schemeForm.post(route('gold-schemes.enroll'), {
        preserveScroll: true,
        onSuccess: () => {
            enrollDialog.value = false;
            toast.add({ severity: 'success', summary: 'Enrolled', detail: 'Customer added to gold scheme', life: 2500 });
        },
    });
};

const updateScheme = () => {
    if (!schemeForm.id) return;

    schemeForm.transform((data) => ({ ...data, _method: 'patch' })).post(route('gold-schemes.update', schemeForm.id), {
        preserveScroll: true,
        onSuccess: () => {
            schemeDialog.value = false;
            router.reload({
                only: ['customerSchemes', 'summary'],
                preserveScroll: true,
                preserveState: true,
            });
            toast.add({ severity: 'success', summary: 'Updated', detail: 'Scheme updated successfully', life: 2500 });
        },
    });
};

const openCollectDialog = (installment) => {
    selectedInstallment.value = installment;
    installmentForm.reset();
    installmentForm.clearErrors();
    installmentForm.amount_paid = Number(installment.amount_due);
    installmentForm.paid_on = todayIndianDate();
    installmentForm.payment_method = 'CASH';
    installmentForm.note = '';
    collectDialog.value = true;
};

const openVoidDialog = (installment) => {
    selectedInstallment.value = {
        ...installment,
        scheme: {
            id: selectedScheme.value?.id,
            scheme_number: selectedScheme.value?.scheme_number,
            customer_name: selectedScheme.value?.customer?.name,
        },
    };
    voidForm.reset();
    voidForm.clearErrors();
    voidDialog.value = true;
};

const fetchSchemeDetails = async (schemeId) => {
    const response = await fetch(route('gold-schemes.show', schemeId), {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error('Failed to load scheme installments.');
    }

    return response.json();
};

const openInstallmentsDialog = async (scheme) => {
    installmentsLoading.value = true;
    selectedScheme.value = null;
    installmentsDialog.value = true;

    try {
        selectedScheme.value = await fetchSchemeDetails(scheme.id);
    } catch (error) {
        installmentsDialog.value = false;
        toast.add({
            severity: 'error',
            summary: 'Unable to load months',
            detail: 'We could not load the installment history for this scheme.',
            life: 3000,
        });
    } finally {
        installmentsLoading.value = false;
    }
};

const collectFromSchemeInstallment = (installment) => {
    if (!selectedScheme.value) return;

    openCollectDialog({
        ...installment,
        scheme: {
            id: selectedScheme.value.id,
            scheme_number: selectedScheme.value.scheme_number,
            customer_name: selectedScheme.value.customer?.name,
            scheme_label: selectedScheme.value.scheme_label,
        },
    });
};

const canCollectInstallment = (installment) =>
    installment?.status !== 'PAID' && selectedScheme.value?.next_pending_installment_id === installment?.id;

const submitInstallment = () => {
    if (!selectedInstallment.value) return;

    installmentForm.post(route('gold-schemes.installments.pay', selectedInstallment.value.id), {
        preserveScroll: true,
        onSuccess: async () => {
            collectDialog.value = false;
            const currentSchemeId = selectedScheme.value?.id ?? selectedInstallment.value?.scheme?.id;

            if (currentSchemeId) {
                try {
                    installmentsLoading.value = true;
                    selectedScheme.value = await fetchSchemeDetails(currentSchemeId);
                } catch (error) {
                    toast.add({
                        severity: 'warn',
                        summary: 'Collected',
                        detail: 'Payment saved, but we could not refresh the scheme months automatically.',
                        life: 3000,
                    });
                } finally {
                    installmentsLoading.value = false;
                }
            }

            router.reload({
                only: ['customerSchemes', 'summary'],
                preserveScroll: true,
                preserveState: true,
            });

            toast.add({ severity: 'success', summary: 'Collected', detail: 'Installment marked as paid', life: 2500 });
        },
    });
};

const submitVoidInstallment = () => {
    if (!selectedInstallment.value) return;

    voidForm.post(route('gold-schemes.installments.void', selectedInstallment.value.id), {
        preserveScroll: true,
        onSuccess: async () => {
            voidDialog.value = false;
            const currentSchemeId = selectedScheme.value?.id ?? selectedInstallment.value?.scheme?.id;

            if (currentSchemeId) {
                try {
                    installmentsLoading.value = true;
                    selectedScheme.value = await fetchSchemeDetails(currentSchemeId);
                } catch (error) {
                    toast.add({
                        severity: 'warn',
                        summary: 'Voided',
                        detail: 'Installment voided, but we could not refresh the scheme months automatically.',
                        life: 3000,
                    });
                } finally {
                    installmentsLoading.value = false;
                }
            }

            router.reload({
                only: ['customerSchemes', 'summary'],
                preserveScroll: true,
                preserveState: true,
            });

            toast.add({ severity: 'success', summary: 'Voided', detail: 'Installment collection has been reversed', life: 2500 });
        },
    });
};

const submitCancelScheme = () => {
    if (!selectedScheme.value) return;

    cancelForm.post(route('gold-schemes.cancel', selectedScheme.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            cancelDialog.value = false;
            router.reload({
                only: ['customerSchemes', 'summary'],
                preserveScroll: true,
                preserveState: true,
            });
            toast.add({ severity: 'success', summary: 'Cancelled', detail: 'Scheme cancelled successfully', life: 2500 });
        },
    });
};

const projectedMaturityAmount = computed(() => {
    const monthlyAmount = Number(schemeForm.monthly_amount || 0);
    const totalMonths = Number(schemeForm.total_months || 0);
    const bonusAmount = Number(schemeForm.bonus_amount || 0);

    return monthlyAmount * totalMonths + bonusAmount;
});

const projectedCustomerContribution = computed(() => Number(schemeForm.monthly_amount || 0) * Number(schemeForm.total_months || 0));
const importedPaidTotal = computed(() => Number(schemeForm.monthly_amount || 0) * Number(schemeForm.already_paid_months || 0));
const shouldShowImportOptions = computed(() => !schemeForm.id && Number(schemeForm.already_paid_months || 0) > 0);
const shouldLockStartDate = computed(() => !schemeForm.id && Number(schemeForm.already_paid_months || 0) > 0);
const filteredCustomerSchemes = computed(() => {
    const term = searchTerm.value.trim().toLowerCase();

    return (props.customerSchemes || []).filter((scheme) => {
        const matchesStatus = statusFilter.value === 'ALL' || scheme.status === statusFilter.value;

        if (!matchesStatus) return false;
        if (!term) return true;

        const haystack = [
            scheme.scheme_number,
            scheme.customer?.name,
            scheme.customer?.mobile,
            scheme.status,
            scheme.notes,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return haystack.includes(term);
    });
});

</script>

<template>
    <AppLayout>
        <Toast />

        <div class="space-y-6">
            <section class="border border-surface-200 bg-white px-5 py-6">
                <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-end">
                    <div class="max-w-3xl">
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-2xl font-semibold tracking-tight text-surface-900">Gold Schemes</h1>
                            <Tag value="Customer Savings Desk" severity="secondary" />
                        </div>
                        <p class="mt-2 text-sm leading-6 text-surface-600">
                            Run customer savings schemes with a cleaner month-by-month workflow. Add fresh schemes, import running ones safely, and keep maturity progress visible without mixing up the vault trail.
                        </p>

                    </div>

                    <div class="flex justify-start xl:justify-end">
                        <Button
                            type="button"
                            icon="pi pi-info-circle"
                            label="How It Works"
                            text
                            severity="secondary"
                            @click="helpDialog = true"
                        />
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Active Schemes</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ summary?.active_schemes || 0 }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Matured Schemes</p>
                    <p class="mt-2 text-2xl font-semibold text-emerald-700">{{ summary?.matured_schemes || 0 }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Monthly Commitment</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ formatCurrency(summary?.monthly_commitment) }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Collected So Far</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-700">{{ formatCurrency(summary?.scheme_collections) }}</p>
                </div>
            </section>

            <section class="overflow-hidden border border-surface-200 bg-white">
                <div class="border-b border-surface-200 px-5 py-4">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-surface-900">Customer Schemes</h2>
                            <p class="mt-1 text-sm text-surface-500">Search by scheme number or customer, filter by status, and open month details only when needed.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,20rem)_12rem_auto]">
                            <InputGroup>
                                <InputGroupAddon>
                                    <i class="pi pi-search text-surface-400" />
                                </InputGroupAddon>
                                <InputText v-model="searchTerm" placeholder="Search scheme, customer, mobile" />
                            </InputGroup>

                            <Select v-model="statusFilter" :options="statusOptions" optionLabel="label" optionValue="value" />

                            <Button label="Add Gold Scheme" icon="pi pi-user-plus" severity="contrast" @click="openEnrollDialog" />
                        </div>
                    </div>
                </div>

                <div class="p-4">
                        <DataTable :value="filteredCustomerSchemes" stripedRows rowHover tableStyle="min-width: 100%">
                            <template #empty>
                                <div class="py-12 text-center text-surface-500">No schemes match the current search or status filter.</div>
                            </template>

                            <Column field="scheme_number" header="Scheme">
                                <template #body="{ data }">
                                    <div>
                                        <p class="font-semibold text-surface-900">{{ data.scheme_number }}</p>
                                        <div class="mt-2 flex flex-wrap items-center gap-2">
                                            <Tag :value="data.status" :severity="data.status === 'MATURED' ? 'success' : data.status === 'ACTIVE' ? 'warn' : 'secondary'" />
                                            <span class="text-xs text-surface-500">{{ data.scheme_label }}</span>
                                        </div>
                                    </div>
                                </template>
                            </Column>

                            <Column header="Customer">
                                <template #body="{ data }">
                                    <div>
                                        <p class="font-medium text-surface-900">{{ data.customer?.name }}</p>
                                        <p class="mt-1 text-xs text-surface-500">{{ data.customer?.mobile }}</p>
                                        <p v-if="data.notes" class="mt-2 line-clamp-2 text-xs text-surface-500">{{ data.notes }}</p>
                                    </div>
                                </template>
                            </Column>

                            <Column header="Progress">
                                <template #body="{ data }">
                                    <div class="space-y-1 text-sm">
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="text-surface-500">Paid</span>
                                            <span class="font-semibold text-surface-900">{{ data.paid_installments_count }}/{{ data.total_months }}</span>
                                        </div>
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="text-surface-500">Collected</span>
                                            <span class="font-semibold text-surface-900">{{ formatCurrency(data.paid_total) }}</span>
                                        </div>
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="text-surface-500">Monthly</span>
                                            <span class="font-semibold text-surface-900">{{ formatCurrency(data.monthly_amount) }}</span>
                                        </div>
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="text-surface-500">Next</span>
                                            <span class="font-semibold text-surface-900">
                                                {{ data.next_pending_installment ? `Month ${data.next_pending_installment.installment_no}` : 'Completed' }}
                                            </span>
                                        </div>
                                    </div>
                                </template>
                            </Column>

                            <Column header="Value">
                                <template #body="{ data }">
                                    <div class="space-y-2">
                                        <div>
                                            <p class="text-xs uppercase tracking-[0.12em] text-surface-500">Redeemable</p>
                                            <p class="mt-1 font-semibold text-emerald-700">{{ formatCurrency(data.redeemable_total) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs uppercase tracking-[0.12em] text-surface-500">Bonus</p>
                                            <p class="mt-1 font-medium text-surface-900">{{ formatCurrency(data.bonus_amount) }}</p>
                                        </div>
                                    </div>
                                </template>
                            </Column>

                            <Column header="Timeline">
                                <template #body="{ data }">
                                    <div class="space-y-2 text-xs text-surface-500">
                                        <div>
                                            <p class="uppercase tracking-[0.12em]">Start</p>
                                            <p class="mt-1 font-medium text-surface-900">{{ formatDate(data.start_date) }}</p>
                                        </div>
                                        <div>
                                            <p class="uppercase tracking-[0.12em]">Maturity</p>
                                            <p class="mt-1 font-medium text-surface-900">{{ formatDate(data.maturity_date) }}</p>
                                        </div>
                                    </div>
                                </template>
                            </Column>

                            <Column header="Collection">
                                <template #body="{ data }">
                                    <div>
                                        <p v-if="data.next_pending_installment" class="text-xs uppercase tracking-[0.12em] text-surface-500">Next Due</p>
                                        <p v-if="data.next_pending_installment" class="mt-1 font-semibold text-surface-900">{{ formatCurrency(data.next_pending_installment.amount_due) }}</p>
                                        <p v-if="data.next_pending_installment" class="mt-1 text-xs text-surface-500">{{ formatDate(data.next_pending_installment.due_date) }}</p>
                                        <p v-else class="text-xs text-emerald-700">All months cleared</p>
                                    </div>
                                </template>
                            </Column>

                            <Column header="Actions">
                                <template #body="{ data }">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <Button label="View Months" size="small" @click="openInstallmentsDialog(data)" />
                                        <Button label="Print" size="small" text severity="secondary" @click="openPrintScheme(data)" />
                                        <Button v-if="data.can_edit" label="Edit" size="small" text @click="openEditSchemeDialog(data)" />
                                        <Button v-if="data.can_cancel" label="Cancel" size="small" text severity="danger" @click="openCancelSchemeDialog(data)" />
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
            </section>

        </div>

        <Dialog v-model:visible="enrollDialog" header="Add Gold Scheme" modal :style="{ width: '34rem' }">
            <form class="space-y-4 pt-2" @submit.prevent="saveEnrollment">
                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Customer</label>
                    <Select v-model="schemeForm.customer_id" :options="customers" optionLabel="name" optionValue="id" filter class="w-full" placeholder="Select customer" />
                    <small v-if="schemeForm.errors.customer_id" class="mt-1 block text-xs text-red-500">{{ schemeForm.errors.customer_id }}</small>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Monthly Amount</label>
                        <InputNumber v-model="schemeForm.monthly_amount" mode="currency" currency="INR" locale="en-IN" class="w-full" />
                        <small v-if="schemeForm.errors.monthly_amount" class="mt-1 block text-xs text-red-500">{{ schemeForm.errors.monthly_amount }}</small>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Months</label>
                        <InputNumber v-model="schemeForm.total_months" :min="1" :max="36" class="w-full" />
                        <small v-if="schemeForm.errors.total_months" class="mt-1 block text-xs text-red-500">{{ schemeForm.errors.total_months }}</small>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Store Bonus</label>
                        <InputNumber v-model="schemeForm.bonus_amount" mode="currency" currency="INR" locale="en-IN" class="w-full" />
                        <small v-if="schemeForm.errors.bonus_amount" class="mt-1 block text-xs text-red-500">{{ schemeForm.errors.bonus_amount }}</small>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Start Date</label>
                        <InputText v-model="schemeForm.start_date" type="date" class="w-full" :disabled="shouldLockStartDate" />
                        <small v-if="shouldLockStartDate" class="mt-1 block text-xs text-surface-500">Start date is auto-set from the already paid months.</small>
                        <small v-if="schemeForm.errors.start_date" class="mt-1 block text-xs text-red-500">{{ schemeForm.errors.start_date }}</small>
                    </div>
                </div>

                <div v-if="!schemeForm.id" class="border border-surface-200 bg-surface-50 px-4 py-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-surface-700">Already Paid Months</label>
                            <InputNumber v-model="schemeForm.already_paid_months" :min="0" :max="Number(schemeForm.total_months || 36)" class="w-full" />
                            <small class="mt-1 block text-xs text-surface-500">Use this when the customer already joined before you started tracking the scheme here.</small>
                            <small v-if="schemeForm.errors.already_paid_months" class="mt-1 block text-xs text-red-500">{{ schemeForm.errors.already_paid_months }}</small>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-surface-700">Imported Paid Value</label>
                            <div class="border border-surface-200 bg-white px-3 py-3 text-sm">
                                <p class="font-semibold text-surface-900">{{ formatCurrency(importedPaidTotal) }}</p>
                                <p class="mt-1 text-xs text-surface-500">This will be marked as already collected for the scheme.</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="shouldShowImportOptions" class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-surface-700">Import Mode</label>
                            <Select v-model="schemeForm.import_mode" :options="importModeOptions" optionLabel="label" optionValue="value" class="w-full" />
                            <small class="mt-1 block text-xs text-surface-500">
                                History Only keeps scheme progress without changing the vault. Post To Vault also credits the imported payments.
                            </small>
                            <small v-if="schemeForm.errors.import_mode" class="mt-1 block text-xs text-red-500">{{ schemeForm.errors.import_mode }}</small>
                        </div>

                        <div v-if="schemeForm.import_mode === 'POST_TO_VAULT'">
                            <label class="mb-2 block text-sm font-medium text-surface-700">Imported Payment Method</label>
                            <Select v-model="schemeForm.import_payment_method" :options="paymentMethodOptions" class="w-full" />
                            <small v-if="schemeForm.errors.import_payment_method" class="mt-1 block text-xs text-red-500">{{ schemeForm.errors.import_payment_method }}</small>
                        </div>
                    </div>
                </div>

                <div class="border border-surface-200 bg-surface-50 px-4 py-4">
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <p class="text-surface-500">Customer Contribution</p>
                            <p class="mt-1 font-semibold text-surface-900">{{ formatCurrency(projectedCustomerContribution) }}</p>
                        </div>
                        <div>
                            <p class="text-surface-500">Projected Maturity</p>
                            <p class="mt-1 font-semibold text-emerald-700">{{ formatCurrency(projectedMaturityAmount) }}</p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Notes</label>
                    <Textarea v-model="schemeForm.notes" rows="3" class="w-full" placeholder="Optional enrollment note" />
                </div>

                <div class="flex justify-end gap-2 border-t border-surface-200 pt-4">
                    <Button label="Cancel" text severity="secondary" type="button" @click="enrollDialog = false" />
                    <Button label="Create Scheme" type="submit" :loading="schemeForm.processing" />
                </div>
            </form>
        </Dialog>

        <Dialog v-model:visible="schemeDialog" header="Edit Gold Scheme" modal :style="{ width: '34rem' }">
            <form class="space-y-4 pt-2" @submit.prevent="updateScheme">
                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Customer</label>
                    <Select v-model="schemeForm.customer_id" :options="customers" optionLabel="name" optionValue="id" filter class="w-full" placeholder="Select customer" />
                    <small v-if="schemeForm.errors.customer_id" class="mt-1 block text-xs text-red-500">{{ schemeForm.errors.customer_id }}</small>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Monthly Amount</label>
                        <InputNumber v-model="schemeForm.monthly_amount" mode="currency" currency="INR" locale="en-IN" class="w-full" />
                        <small v-if="schemeForm.errors.monthly_amount" class="mt-1 block text-xs text-red-500">{{ schemeForm.errors.monthly_amount }}</small>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Months</label>
                        <InputNumber v-model="schemeForm.total_months" :min="1" :max="36" class="w-full" />
                        <small v-if="schemeForm.errors.total_months" class="mt-1 block text-xs text-red-500">{{ schemeForm.errors.total_months }}</small>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Store Bonus</label>
                        <InputNumber v-model="schemeForm.bonus_amount" mode="currency" currency="INR" locale="en-IN" class="w-full" />
                        <small v-if="schemeForm.errors.bonus_amount" class="mt-1 block text-xs text-red-500">{{ schemeForm.errors.bonus_amount }}</small>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Start Date</label>
                        <InputText v-model="schemeForm.start_date" type="date" class="w-full" />
                        <small v-if="schemeForm.errors.start_date" class="mt-1 block text-xs text-red-500">{{ schemeForm.errors.start_date }}</small>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Notes</label>
                    <Textarea v-model="schemeForm.notes" rows="3" class="w-full" placeholder="Optional scheme note" />
                </div>

                <div class="text-xs text-surface-500">
                    Editing is allowed only before any installment collection happens.
                </div>

                <div class="flex justify-end gap-2 border-t border-surface-200 pt-4">
                    <Button label="Cancel" text severity="secondary" type="button" @click="schemeDialog = false" />
                    <Button label="Update Scheme" type="submit" :loading="schemeForm.processing" />
                </div>
            </form>
        </Dialog>

        <Dialog v-model:visible="installmentsDialog" :header="selectedScheme ? `${selectedScheme.scheme_number} Installments` : 'Installments'" modal :style="{ width: '56rem' }">
            <div class="space-y-4 pt-2">
                <div v-if="installmentsLoading" class="border border-surface-200 bg-surface-50 px-4 py-10 text-center text-sm text-surface-500">
                    Loading installment history...
                </div>

                <div v-if="selectedScheme" class="border border-surface-200 bg-surface-50 px-4 py-4">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-4 text-sm">
                        <div>
                            <p class="text-surface-500">Customer</p>
                            <p class="mt-1 font-semibold text-surface-900">{{ selectedScheme.customer?.name }}</p>
                        </div>
                        <div>
                            <p class="text-surface-500">Monthly Amount</p>
                            <p class="mt-1 font-semibold text-surface-900">{{ formatCurrency(selectedScheme.monthly_amount) }}</p>
                        </div>
                        <div>
                            <p class="text-surface-500">Paid Progress</p>
                            <p class="mt-1 font-semibold text-surface-900">{{ selectedScheme.paid_installments_count }}/{{ selectedScheme.total_months }}</p>
                        </div>
                        <div>
                            <p class="text-surface-500">Redeemable</p>
                            <p class="mt-1 font-semibold text-emerald-700">{{ formatCurrency(selectedScheme.redeemable_total) }}</p>
                        </div>
                    </div>
                </div>

                <DataTable v-if="selectedScheme" :value="selectedScheme.installments || []" stripedRows rowHover tableStyle="min-width: 100%">
                    <template #empty>
                        <div class="py-12 text-center text-surface-500">No installments generated for this scheme yet.</div>
                    </template>

                    <Column header="Month">
                        <template #body="{ data }">
                            <span class="font-medium text-surface-900">Month {{ data.installment_no }}</span>
                        </template>
                    </Column>

                    <Column header="Due Date">
                        <template #body="{ data }">
                            <span class="text-surface-700">{{ formatDate(data.due_date) }}</span>
                        </template>
                    </Column>

                    <Column header="Amount">
                        <template #body="{ data }">
                            <span class="font-semibold text-surface-900">{{ formatCurrency(data.amount_due) }}</span>
                        </template>
                    </Column>

                    <Column header="Paid Info">
                        <template #body="{ data }">
                            <div v-if="data.status === 'PAID'" class="space-y-1 text-xs text-surface-500">
                                <p>Paid {{ formatDate(data.paid_on) }}</p>
                                <p>{{ data.payment_method || '—' }}</p>
                            </div>
                            <span v-else class="text-surface-500">Pending</span>
                        </template>
                    </Column>

                    <Column header="Status">
                        <template #body="{ data }">
                            <Tag :value="data.status" :severity="data.status === 'PAID' ? 'success' : 'warn'" />
                        </template>
                    </Column>

                    <Column header="">
                        <template #body="{ data }">
                            <div class="flex justify-end gap-2">
                                <Button v-if="data.status === 'PAID'" label="Void" size="small" severity="danger" text @click="openVoidDialog(data)" />
                                <Button v-if="data.status !== 'PAID'" label="Collect" size="small" :disabled="!canCollectInstallment(data)" @click="collectFromSchemeInstallment(data)" />
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <div v-if="selectedScheme && selectedScheme.next_pending_installment_id" class="text-xs text-surface-500">
                    Only the next pending month can be collected. Clear the earlier pending month first.
                </div>
            </div>
        </Dialog>

        <Dialog v-model:visible="collectDialog" header="Collect Installment" modal :style="{ width: '32rem' }">
            <form class="space-y-4 pt-2" @submit.prevent="submitInstallment">
                <div class="border border-surface-200 bg-surface-50 px-4 py-4">
                    <p class="text-sm font-semibold text-surface-900">{{ selectedInstallment?.scheme?.scheme_number }}</p>
                    <p class="mt-1 text-sm text-surface-500">{{ selectedInstallment?.scheme?.customer_name }}</p>
                    <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <p class="text-surface-500">Installment</p>
                            <p class="mt-1 font-semibold text-surface-900">Month {{ selectedInstallment?.installment_no }}</p>
                        </div>
                        <div>
                            <p class="text-surface-500">Due Amount</p>
                            <p class="mt-1 font-semibold text-surface-900">{{ formatCurrency(selectedInstallment?.amount_due) }}</p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Amount Received</label>
                    <InputNumber v-model="installmentForm.amount_paid" mode="currency" currency="INR" locale="en-IN" class="w-full" />
                    <small v-if="installmentForm.errors.amount_paid" class="mt-1 block text-xs text-red-500">{{ installmentForm.errors.amount_paid }}</small>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Paid On</label>
                        <InputText v-model="installmentForm.paid_on" type="date" class="w-full" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Payment Method</label>
                        <Select v-model="installmentForm.payment_method" :options="paymentMethodOptions" class="w-full" />
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Note</label>
                    <Textarea v-model="installmentForm.note" rows="3" class="w-full" placeholder="Optional collection note" />
                </div>

                <div class="flex justify-end gap-2 border-t border-surface-200 pt-4">
                    <Button label="Cancel" text severity="secondary" type="button" @click="collectDialog = false" />
                    <Button label="Collect Installment" type="submit" :loading="installmentForm.processing" />
                </div>
            </form>
        </Dialog>

        <Dialog v-model:visible="voidDialog" header="Void Collection" modal :style="{ width: '32rem' }">
            <form class="space-y-4 pt-2" @submit.prevent="submitVoidInstallment">
                <div class="border border-surface-200 bg-surface-50 px-4 py-4">
                    <p class="text-sm font-semibold text-surface-900">{{ selectedInstallment?.scheme?.scheme_number }}</p>
                    <p class="mt-1 text-sm text-surface-500">{{ selectedInstallment?.scheme?.customer_name }}</p>
                    <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <p class="text-surface-500">Installment</p>
                            <p class="mt-1 font-semibold text-surface-900">Month {{ selectedInstallment?.installment_no }}</p>
                        </div>
                        <div>
                            <p class="text-surface-500">Paid Amount</p>
                            <p class="mt-1 font-semibold text-surface-900">{{ formatCurrency(selectedInstallment?.amount_paid) }}</p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Void Reason</label>
                    <Textarea v-model="voidForm.reason" rows="3" class="w-full" placeholder="Why is this collection being voided?" />
                    <small v-if="voidForm.errors.reason" class="mt-1 block text-xs text-red-500">{{ voidForm.errors.reason }}</small>
                </div>

                <div class="text-xs text-surface-500">
                    Voiding will reverse the related vault movement and set this month back to pending.
                </div>

                <div class="flex justify-end gap-2 border-t border-surface-200 pt-4">
                    <Button label="Cancel" text severity="secondary" type="button" @click="voidDialog = false" />
                    <Button label="Void Collection" severity="danger" type="submit" :loading="voidForm.processing" />
                </div>
            </form>
        </Dialog>

        <Dialog v-model:visible="cancelDialog" header="Cancel Scheme" modal :style="{ width: '32rem' }">
            <form class="space-y-4 pt-2" @submit.prevent="submitCancelScheme">
                <div class="border border-surface-200 bg-surface-50 px-4 py-4">
                    <p class="text-sm font-semibold text-surface-900">{{ selectedScheme?.scheme_number }}</p>
                    <p class="mt-1 text-sm text-surface-500">{{ selectedScheme?.customer?.name }}</p>
                    <p class="mt-3 text-xs text-surface-500">
                        This is allowed only when no installments are currently marked as paid.
                    </p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Cancellation Reason</label>
                    <Textarea v-model="cancelForm.reason" rows="3" class="w-full" placeholder="Why is this scheme being cancelled?" />
                    <small v-if="cancelForm.errors.reason" class="mt-1 block text-xs text-red-500">{{ cancelForm.errors.reason }}</small>
                </div>

                <div class="flex justify-end gap-2 border-t border-surface-200 pt-4">
                    <Button label="Keep Scheme" text severity="secondary" type="button" @click="cancelDialog = false" />
                    <Button label="Cancel Scheme" severity="danger" type="submit" :loading="cancelForm.processing" />
                </div>
            </form>
        </Dialog>

        <Dialog v-model:visible="helpDialog" header="How Gold Schemes Work" modal :style="{ width: '32rem' }">
            <div class="space-y-4 pt-2 text-sm leading-6 text-surface-700">
                <div class="border border-surface-200 bg-surface-50 px-4 py-3">
                    <p class="font-medium text-surface-900">1. Add the scheme</p>
                    <p class="mt-1">Choose the customer, monthly amount, number of months, bonus amount, and start date.</p>
                </div>

                <div class="border border-surface-200 bg-surface-50 px-4 py-3">
                    <p class="font-medium text-surface-900">2. Import running schemes if needed</p>
                    <p class="mt-1">If the customer already paid some months before, fill `Already Paid Months`. Use `History Only` when those old payments should not change today’s vault balance.</p>
                </div>

                <div class="border border-surface-200 bg-surface-50 px-4 py-3">
                    <p class="font-medium text-surface-900">3. Collect month by month</p>
                    <p class="mt-1">Open `View Months` and collect only the next pending month. Later months stay locked until earlier dues are cleared.</p>
                </div>

                <div class="border border-surface-200 bg-surface-50 px-4 py-3">
                    <p class="font-medium text-surface-900">4. Correct mistakes safely</p>
                    <p class="mt-1">Schemes can be edited only before any collection. Paid months use `Void` instead of silent edits so the vault trail stays correct.</p>
                </div>
            </div>
        </Dialog>
    </AppLayout>
</template>
