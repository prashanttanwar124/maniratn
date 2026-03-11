<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    invoices: Array,
});

const toast = useToast();
const selectedInvoice = ref(null);
const showVoidDialog = ref(false);

const voidForm = useForm({
    mode: 'keep_advance',
    reason: '',
});

const totalSales = computed(() => props.invoices?.filter((invoice) => invoice.status !== 'CANCELLED').reduce((sum, invoice) => sum + Number(invoice.total_amount || 0), 0) || 0);
const totalCollected = computed(() => props.invoices?.filter((invoice) => invoice.status !== 'CANCELLED').reduce((sum, invoice) => sum + Number(invoice.paid_amount || 0), 0) || 0);
const totalPending = computed(() => props.invoices?.filter((invoice) => invoice.status !== 'CANCELLED').reduce((sum, invoice) => sum + Number(invoice.pending_amount || 0), 0) || 0);
const cancelledCount = computed(() => props.invoices?.filter((invoice) => invoice.status === 'CANCELLED').length || 0);

const voidModeOptions = [
    { label: 'Keep As Advance', value: 'keep_advance', hint: 'Paid amount stays in the customer ledger as advance for the next bill.' },
    { label: 'Refund Customer', value: 'refund', hint: 'Paid amount is reversed from the vault and returned to the customer.' },
];

const formatCurrency = (value) =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 0,
    }).format(value || 0);

const formatDate = (dateString) =>
    new Date(dateString).toLocaleDateString('en-IN', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });

const openVoidDialog = (invoice) => {
    selectedInvoice.value = invoice;
    voidForm.reset();
    voidForm.clearErrors();
    voidForm.mode = 'keep_advance';
    voidForm.reason = '';
    showVoidDialog.value = true;
};

const closeVoidDialog = () => {
    showVoidDialog.value = false;
    selectedInvoice.value = null;
    voidForm.reset();
    voidForm.clearErrors();
    voidForm.mode = 'keep_advance';
};

const submitVoid = () => {
    if (!selectedInvoice.value) return;

    voidForm.post(route('invoices.cancel', selectedInvoice.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Invoice Voided',
                detail: 'Invoice has been voided successfully',
                life: 3000,
            });
            closeVoidDialog();
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Unable To Void',
                detail: 'Check the void reason and try again',
                life: 3000,
            });
        },
    });
};

const printInvoice = (invoice) => {
    window.open(route('invoices.print', invoice.id), '_blank');
};
</script>

<template>
    <AppLayout>
        <Toast />
        <div class="space-y-6">
            <section class="border border-surface-200 bg-white px-5 py-6">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl">
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-2xl font-semibold tracking-tight text-surface-900">Invoice History</h1>
                            <Tag value="Billing Register" severity="secondary" />
                        </div>
                        <p class="mt-2 text-sm leading-6 text-surface-600">
                            Review posted bills, track collected and pending amounts, and safely void invoices with either customer advance retention or refund.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button label="New Bill" icon="pi pi-plus" @click="router.visit(route('invoices.create'))" />
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Active Sales</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ formatCurrency(totalSales) }}</p>
                </div>

                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Collected</p>
                    <p class="mt-2 text-2xl font-semibold text-emerald-700">{{ formatCurrency(totalCollected) }}</p>
                </div>

                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Pending</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-700">{{ formatCurrency(totalPending) }}</p>
                </div>

                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Voided Bills</p>
                    <p class="mt-2 text-2xl font-semibold text-red-600">{{ cancelledCount }}</p>
                </div>
            </section>

            <section class="overflow-hidden border border-surface-200 bg-white">
                <div class="border-b border-surface-200 px-5 py-4">
                    <h2 class="text-lg font-semibold text-surface-900">Invoice Register</h2>
                    <p class="mt-1 text-sm text-surface-500">Paid amount, pending amount, and void outcome are shown per bill.</p>
                </div>

                <div class="p-4">
                    <DataTable :value="invoices" paginator :rows="10" stripedRows rowHover tableStyle="min-width: 72rem">
                        <template #empty>
                            <div class="py-12 text-center text-surface-500">No invoices recorded yet.</div>
                        </template>

                        <Column field="invoice_number" header="Bill No" sortable style="width: 160px">
                            <template #body="{ data }">
                                <div>
                                    <p class="font-semibold text-surface-900">{{ data.invoice_number }}</p>
                                    <p class="mt-1 text-xs text-surface-500">{{ formatDate(data.date) }}</p>
                                </div>
                            </template>
                        </Column>

                        <Column field="customer.name" header="Customer" sortable style="width: 220px">
                            <template #body="{ data }">
                                <div>
                                    <p class="font-medium text-surface-900">{{ data.customer?.name || 'Walk-in' }}</p>
                                    <p class="mt-1 text-xs text-surface-500">{{ data.item_count }} item{{ data.item_count === 1 ? '' : 's' }}</p>
                                </div>
                            </template>
                        </Column>

                        <Column field="total_amount" header="Total" sortable style="width: 140px">
                            <template #body="{ data }">
                                <span class="font-semibold text-surface-900">{{ formatCurrency(data.total_amount) }}</span>
                            </template>
                        </Column>

                        <Column field="paid_amount" header="Paid" sortable style="width: 140px">
                            <template #body="{ data }">
                                <span class="font-semibold text-emerald-700">{{ formatCurrency(data.paid_amount) }}</span>
                            </template>
                        </Column>

                        <Column field="pending_amount" header="Pending" sortable style="width: 140px">
                            <template #body="{ data }">
                                <span class="font-semibold" :class="data.pending_amount > 0 ? 'text-amber-700' : 'text-surface-900'">
                                    {{ formatCurrency(data.pending_amount) }}
                                </span>
                            </template>
                        </Column>

                        <Column field="status" header="Status" sortable style="width: 150px">
                            <template #body="{ data }">
                                <Tag :severity="data.status === 'CANCELLED' ? 'danger' : 'success'" :value="data.status === 'CANCELLED' ? 'Voided' : 'Valid'" />
                            </template>
                        </Column>

                        <Column header="Void Outcome" style="width: 220px">
                            <template #body="{ data }">
                                <div v-if="data.status === 'CANCELLED'" class="space-y-1">
                                    <Tag
                                        :severity="data.cancellation_mode === 'refund' ? 'danger' : 'warn'"
                                        :value="data.cancellation_mode === 'refund' ? 'Refunded' : 'Kept As Advance'"
                                    />
                                    <p class="text-xs font-medium" :class="data.cancellation_mode === 'refund' ? 'text-red-600' : 'text-amber-700'">
                                        {{ formatCurrency(data.void_amount) }}
                                    </p>
                                    <p v-if="data.cancelled_by" class="text-xs text-surface-500">
                                        {{ data.cancelled_by }} on {{ formatDate(data.cancelled_at || data.date) }}
                                    </p>
                                </div>
                                <span v-else class="text-sm text-surface-400">Active invoice</span>
                            </template>
                        </Column>

                        <Column header="Notes" style="min-width: 240px">
                            <template #body="{ data }">
                                <p v-if="data.cancellation_reason" class="text-sm text-surface-600">{{ data.cancellation_reason }}</p>
                                <span v-else class="text-sm text-surface-400">No remarks</span>
                            </template>
                        </Column>

                        <Column header="Actions" style="width: 140px">
                            <template #body="{ data }">
                                <div class="flex justify-end gap-2">
                                    <Button label="Print" icon="pi pi-print" severity="secondary" text size="small" @click="printInvoice(data)" />
                                    <Button
                                        v-if="data.status !== 'CANCELLED'"
                                        label="Void"
                                        icon="pi pi-times"
                                        severity="danger"
                                        text
                                        size="small"
                                        @click="openVoidDialog(data)"
                                    />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </section>
        </div>

        <Dialog v-model:visible="showVoidDialog" header="Void Invoice" modal :style="{ width: '34rem' }" @hide="closeVoidDialog">
            <div class="space-y-5 pt-2">
                <div class="border border-surface-200 bg-surface-50 px-4 py-4">
                    <p class="text-sm font-medium text-surface-900">{{ selectedInvoice?.invoice_number }}</p>
                    <p class="mt-1 text-sm text-surface-500">{{ selectedInvoice?.customer?.name || 'Walk-in' }}</p>
                    <div class="mt-3 grid grid-cols-3 gap-3 text-sm">
                        <div>
                            <p class="text-surface-500">Total</p>
                            <p class="mt-1 font-semibold text-surface-900">{{ formatCurrency(selectedInvoice?.total_amount) }}</p>
                        </div>
                        <div>
                            <p class="text-surface-500">Paid</p>
                            <p class="mt-1 font-semibold text-emerald-700">{{ formatCurrency(selectedInvoice?.paid_amount) }}</p>
                        </div>
                        <div>
                            <p class="text-surface-500">Pending</p>
                            <p class="mt-1 font-semibold text-amber-700">{{ formatCurrency(selectedInvoice?.pending_amount) }}</p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Void Mode</label>
                    <Select v-model="voidForm.mode" :options="voidModeOptions" optionLabel="label" optionValue="value" class="w-full" />
                    <p class="mt-2 text-xs text-surface-500">
                        {{ voidModeOptions.find((option) => option.value === voidForm.mode)?.hint }}
                    </p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Reason</label>
                    <Textarea v-model="voidForm.reason" rows="4" class="w-full" placeholder="Why is this bill being voided?" />
                    <small v-if="voidForm.errors.reason" class="mt-1 block text-xs text-red-500">{{ voidForm.errors.reason }}</small>
                </div>

                <div class="border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Stock and custom order items will be restored. If you choose refund, received money will also be reversed from the vault.
                </div>

                <div class="flex justify-end gap-2 border-t border-surface-200 pt-4">
                    <Button label="Close" severity="secondary" text @click="closeVoidDialog" />
                    <Button
                        :label="voidForm.mode === 'refund' ? 'Void And Refund' : 'Void To Advance'"
                        icon="pi pi-check"
                        severity="danger"
                        @click="submitVoid"
                        :loading="voidForm.processing"
                    />
                </div>
            </div>
        </Dialog>
    </AppLayout>
</template>
