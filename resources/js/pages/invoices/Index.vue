<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Search, Coins } from 'lucide-vue-next';
import { route } from 'ziggy-js';

import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { formatIndianDate, formatIndianDateTime, todayIndianDate } from '@/utils/indiaTime';

const props = defineProps({
    invoices: Array,
    drafts: {
        type: Array,
        default: () => [],
    },
    business: {
        type: Object,
        default: () => ({}),
    },
});

const toast = useToast();
const selectedInvoice = ref(null);
const showVoidDialog = ref(false);
const showPaymentDialog = ref(false);
const showViewDialog = ref(false);
const viewInvoice = ref(null);
const paymentInvoice = ref(null);
const search = ref('');

const voidForm = useForm({
    mode: 'keep_advance',
    old_gold_mode: 'keep_advance',
    reason: '',
});

const paymentForm = useForm({
    amount: null,
    payment_method: 'CASH',
    date: todayIndianDate(),
    note: '',
});

const paymentMethodOptions = [
    { label: 'Cash', value: 'CASH' },
    { label: 'Bank', value: 'BANK' },
    { label: 'UPI', value: 'UPI' },
    { label: 'Card', value: 'CARD' },
];

const totalSales = computed(() => props.invoices?.filter((invoice) => invoice.status !== 'CANCELLED').reduce((sum, invoice) => sum + Number(invoice.total_amount || 0), 0) || 0);
const totalCollected = computed(() => props.invoices?.filter((invoice) => invoice.status !== 'CANCELLED').reduce((sum, invoice) => sum + Number(invoice.paid_amount || 0), 0) || 0);
const totalPending = computed(() => props.invoices?.filter((invoice) => invoice.status !== 'CANCELLED').reduce((sum, invoice) => sum + Number(invoice.pending_amount || 0), 0) || 0);
const cancelledCount = computed(() => props.invoices?.filter((invoice) => invoice.status === 'CANCELLED').length || 0);
const filteredInvoices = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (!term) return props.invoices || [];

    return (props.invoices || []).filter((invoice) =>
        [
            invoice.invoice_number,
            invoice.customer?.name,
            invoice.status,
            invoice.cancellation_mode,
            invoice.cancelled_by,
        ]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(term)),
    );
});

const voidModeOptions = [
    { label: 'Keep Cash As Advance', value: 'keep_advance', hint: 'Paid cash/card stays in the customer ledger as advance credit for the next bill.' },
    { label: 'Refund Cash To Customer', value: 'refund', hint: 'Paid cash is reversed from the Cash Vault and refunded to the customer.' },
];

const oldGoldVoidModeOptions = [
    { label: 'Keep Old Metal Value As Advance', value: 'keep_advance', hint: 'Old metal stays in store vault, and its full exchange value is credited to customer ledger as advance balance.' },
    { label: 'Return Physical Old Metal To Customer', value: 'return_metal', hint: 'Metal is debited from vault and returned to customer. Old metal credit is cancelled.' },
];

const formatCurrency = (value) =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value || 0);

const formatDate = (dateString) =>
    formatIndianDate(dateString, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });

const openPaymentDialog = (invoice) => {
    paymentInvoice.value = invoice;
    paymentForm.reset();
    paymentForm.clearErrors();
    paymentForm.amount = Number(invoice.pending_amount || 0);
    paymentForm.payment_method = 'CASH';
    paymentForm.date = todayIndianDate();
    paymentForm.note = '';
    showPaymentDialog.value = true;
};

const closePaymentDialog = () => {
    showPaymentDialog.value = false;
    paymentInvoice.value = null;
    paymentForm.reset();
    paymentForm.clearErrors();
};

const submitPayment = () => {
    if (!paymentInvoice.value) return;

    paymentForm.post(route('invoices.payment', paymentInvoice.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Payment Recorded',
                detail: `Payment of ₹${Number(paymentForm.amount).toLocaleString('en-IN')} recorded successfully.`,
                life: 3000,
            });
            closePaymentDialog();
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Payment Failed',
                detail: 'Please check the payment fields and try again.',
                life: 3000,
            });
        },
    });
};

const openVoidDialog = (invoice) => {
    selectedInvoice.value = invoice;
    voidForm.reset();
    voidForm.clearErrors();
    const cashPaid = Math.max(0, Number(invoice.paid_amount || 0) - Number(invoice.old_gold_amount || 0));
    const hasOldGold = Number(invoice.old_gold_amount || 0) > 0;

    voidForm.mode = cashPaid > 0 ? 'keep_advance' : 'none';
    voidForm.old_gold_mode = hasOldGold ? 'keep_advance' : 'none';
    voidForm.reason = '';
    showVoidDialog.value = true;
};

const closeVoidDialog = () => {
    showVoidDialog.value = false;
    selectedInvoice.value = null;
    voidForm.reset();
    voidForm.clearErrors();
    voidForm.mode = 'keep_advance';
    voidForm.old_gold_mode = 'keep_advance';
};

const voidPreviewAdvance = computed(() => {
    if (!selectedInvoice.value) return 0;
    let total = 0;
    if (voidForm.old_gold_mode === 'keep_advance') {
        total += Number(selectedInvoice.value.old_gold_amount || 0);
    }
    if (voidForm.mode === 'keep_advance') {
        total += Math.max(0, Number(selectedInvoice.value.paid_amount || 0) - Number(selectedInvoice.value.old_gold_amount || 0));
    }
    return total;
});

const openViewDialog = (invoice) => {
    viewInvoice.value = invoice;
    showViewDialog.value = true;
};

const closeViewDialog = () => {
    showViewDialog.value = false;
    viewInvoice.value = null;
};

const shareInvoiceOnWhatsapp = (invoice) => {
    if (!invoice) return;
    const phone = invoice.customer?.mobile ? invoice.customer.mobile.replace(/[^0-9]/g, '') : '';
    const storeName = props.business?.store_name || 'Maniratn Jewellers';
    const customerName = invoice.customer?.name || 'Customer';
    const total = formatCurrency(invoice.total_amount);
    const paid = formatCurrency(invoice.paid_amount);
    const pending = Number(invoice.pending_amount || 0) > 0 ? `\n• *Pending Balance:* ${formatCurrency(invoice.pending_amount)}` : '';

    let text = `Namaste ${customerName},\n\nThank you for shopping at *${storeName}*! Here are your invoice details:\n\n• *Invoice No:* ${invoice.invoice_number}\n• *Date:* ${formatDate(invoice.date)}\n• *Items:* ${invoice.items?.length || invoice.item_count || 1} item(s)\n• *Total Amount:* ${total}\n• *Paid Amount:* ${paid}${pending}`;

    if (invoice.vault_url) {
        text += `\n\n*Access Your Digital Vault & Certificates:*\n${invoice.vault_url}`;
    }

    if (props.business?.google_review_url) {
        text += `\n\n*Rate Your Experience on Google:*\n${props.business.google_review_url}`;
    }

    text += `\n\nWarm regards,\n*${storeName}*`;

    const url = phone ? `https://wa.me/${phone}?text=${encodeURIComponent(text)}` : `https://wa.me/?text=${encodeURIComponent(text)}`;
    window.open(url, '_blank', 'noopener');
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

const resumeDraft = (draftId) => {
    router.visit(route('invoices.create', { draft: draftId }));
};

const deleteDraft = (draftId) => {
    router.delete(route('invoices.drafts.destroy', draftId), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            toast.add({ severity: 'info', summary: 'Draft Deleted', life: 1500 });
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Delete Failed', detail: 'Unable to delete invoice draft.', life: 2000 });
        },
    });
};

const formatDraftTime = (iso) => formatIndianDateTime(iso);

const draftFormatCurrency = (val) =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 0,
    }).format(val || 0);
</script>

<template>
    <AppLayout>
        <Toast />
        <div class="space-y-6">
            <section class="erp-page-header border border-surface-200 bg-white px-5 py-6">
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
                <div class="erp-stat-card p-5">
                    <span class="erp-stat-card__label">Active Sales</span>
                    <span class="erp-stat-card__value mt-2 block">{{ formatCurrency(totalSales) }}</span>
                    <span class="erp-stat-card__meta">Valid billed revenue</span>
                </div>

                <div class="erp-stat-card p-5">
                    <span class="erp-stat-card__label">Collected</span>
                    <span class="erp-stat-card__value mt-2 block !text-emerald-700">{{ formatCurrency(totalCollected) }}</span>
                    <span class="erp-stat-card__meta">Cash, card & bank receipts</span>
                </div>

                <div class="erp-stat-card p-5">
                    <span class="erp-stat-card__label">Pending</span>
                    <span class="erp-stat-card__value mt-2 block !text-amber-700">{{ formatCurrency(totalPending) }}</span>
                    <span class="erp-stat-card__meta">Receivable ledger balance</span>
                </div>

                <div class="erp-stat-card p-5">
                    <span class="erp-stat-card__label">Voided Bills</span>
                    <span class="erp-stat-card__value mt-2 block !text-red-600">{{ cancelledCount }}</span>
                    <span class="erp-stat-card__meta">Cancelled invoices</span>
                </div>
            </section>

            <section v-if="props.drafts.length > 0" class="rounded-lg border border-amber-200 bg-amber-50 shadow-xs">
                <div class="flex items-center justify-between border-b border-amber-200 px-5 py-3">
                    <div class="flex items-center gap-2">
                        <i class="pi pi-file-edit text-amber-700"></i>
                        <h2 class="text-sm font-semibold text-amber-800">Saved Drafts ({{ props.drafts.length }})</h2>
                    </div>
                    <span class="text-xs text-amber-600">Stored on the server for your login</span>
                </div>
                <div class="flex flex-col gap-2 p-4">
                    <div
                        v-for="draft in props.drafts"
                        :key="draft.id"
                        class="flex items-center justify-between gap-4 rounded-md border border-amber-200 bg-white px-4 py-3 shadow-xs"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-surface-900">{{ draft.customerName }}</p>
                            <p class="mt-0.5 text-xs text-surface-500">
                                {{ draft.itemCount }} item{{ draft.itemCount === 1 ? '' : 's' }}
                                <span class="mx-1 text-surface-300">&middot;</span>
                                {{ draftFormatCurrency(draft.grandTotal) }}
                                <span class="mx-1 text-surface-300">&middot;</span>
                                {{ formatDraftTime(draft.savedAt) }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <Button label="Resume" icon="pi pi-play" size="small" severity="warn" outlined @click="resumeDraft(draft.id)" />
                            <Button icon="pi pi-trash" size="small" severity="danger" text @click="deleteDraft(draft.id)" />
                        </div>
                    </div>
                </div>
            </section>

            <section class="erp-panel overflow-hidden border border-surface-200 bg-white">
                <div class="border-b border-surface-200 px-5 py-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-surface-900">Invoice Register</h2>
                            <p class="mt-1 text-sm text-surface-500">Paid amount, pending amount, and void outcome are shown per bill.</p>
                        </div>

                        <div class="relative w-full lg:w-80">
                            <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-surface-400" />
                            <InputText v-model="search" placeholder="Search bill, customer, or status..." class="w-full !pl-10" />
                        </div>
                    </div>
                </div>

                <div class="p-4">
                    <DataTable class="erp-data-table" :value="filteredInvoices" paginator :rows="10" stripedRows rowHover tableStyle="min-width: 76rem">
                        <template #empty>
                            <div class="py-12 text-center text-surface-500">No invoices recorded yet.</div>
                        </template>

                        <Column field="invoice_number" header="Bill" sortable style="width: 200px">
                            <template #body="{ data }">
                                <div>
                                    <p
                                        class="font-semibold text-surface-900 hover:text-indigo-600 cursor-pointer inline-flex items-center gap-1.5 transition-colors"
                                        @click="openViewDialog(data)"
                                        title="Click to view full bill details"
                                    >
                                        <span>{{ data.invoice_number }}</span>
                                        <i class="pi pi-eye text-[11px] text-surface-400"></i>
                                    </p>
                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-surface-500">
                                        <span>{{ formatDate(data.date) }}</span>
                                        <span class="text-surface-300">•</span>
                                        <span>{{ data.item_count }} item{{ data.item_count === 1 ? '' : 's' }}</span>
                                    </div>
                                </div>
                            </template>
                        </Column>

                        <Column field="customer.name" header="Customer" sortable style="width: 220px">
                            <template #body="{ data }">
                                <div>
                                    <p class="font-medium text-surface-900">{{ data.customer?.name || 'Walk-in' }}</p>
                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-surface-500">
                                        <span>Total {{ formatCurrency(data.total_amount) }}</span>
                                        <template v-if="Number(data.discount_amount || 0) > 0">
                                            <span class="text-surface-300">•</span>
                                            <span>Discount {{ formatCurrency(data.discount_amount) }}</span>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </Column>

                        <Column header="Financials" style="width: 220px">
                            <template #body="{ data }">
                                <div class="space-y-1 text-sm">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-surface-500">Paid</span>
                                        <span class="font-semibold text-emerald-700">{{ formatCurrency(data.paid_amount) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-surface-500">Pending</span>
                                        <span class="font-semibold" :class="data.pending_amount > 0 ? 'text-amber-700' : 'text-surface-900'">
                                            {{ formatCurrency(data.pending_amount) }}
                                        </span>
                                    </div>
                                    <div v-if="Number(data.old_gold_amount || 0) > 0" class="pt-0.5">
                                        <Tag :value="`Exch: ${formatCurrency(data.old_gold_amount)}`" severity="warn" class="!text-[10px] !py-0 !px-1.5 !bg-amber-50 !text-amber-900 !border-amber-200" />
                                    </div>
                                </div>
                            </template>
                        </Column>

                        <Column field="status" header="Status" sortable style="width: 170px">
                            <template #body="{ data }">
                                <div class="space-y-2">
                                    <Tag :severity="data.status === 'CANCELLED' ? 'danger' : 'success'" :value="data.status === 'CANCELLED' ? 'Voided' : 'Valid'" />
                                    <p v-if="data.status !== 'CANCELLED'" class="text-xs text-surface-500">
                                        {{ data.pending_amount > 0 ? 'Payment pending' : 'Fully settled' }}
                                    </p>
                                    <p v-else class="text-xs text-surface-500">
                                        {{ Number(data.void_amount || 0) > 0 ? (data.cancellation_mode === 'refund' ? 'Refunded to customer' : 'Kept as advance') : 'No payments collected' }}
                                    </p>
                                </div>
                            </template>
                        </Column>

                        <Column header="Void / Notes" style="min-width: 250px">
                            <template #body="{ data }">
                                <div v-if="data.status === 'CANCELLED'" class="space-y-1">
                                    <p v-if="Number(data.void_amount || 0) > 0" class="text-xs font-medium" :class="data.cancellation_mode === 'refund' ? 'text-red-600' : 'text-amber-700'">
                                        {{ formatCurrency(data.void_amount) }} {{ data.cancellation_mode === 'refund' ? 'refunded' : 'kept as advance' }}
                                    </p>
                                    <p v-else class="text-xs font-medium text-surface-600">
                                        Unpaid bill voided (Stock restored)
                                    </p>
                                    <p v-if="data.cancelled_by" class="text-xs text-surface-500">
                                        {{ data.cancelled_by }} on {{ formatDate(data.cancelled_at || data.date) }}
                                    </p>
                                    <p v-if="data.cancellation_reason" class="text-sm text-surface-600">{{ data.cancellation_reason }}</p>
                                    <span v-else class="text-sm text-surface-400">No remarks</span>
                                </div>
                                <span v-else class="text-sm text-surface-400">No remarks</span>
                            </template>
                        </Column>

                        <Column header="Actions" style="width: 250px">
                            <template #body="{ data }">
                                <div class="flex justify-end gap-1">
                                    <Button
                                        label="View"
                                        icon="pi pi-eye"
                                        severity="info"
                                        text
                                        size="small"
                                        @click="openViewDialog(data)"
                                        title="View bill breakdown and receipts"
                                    />
                                    <Button
                                        v-if="data.status !== 'CANCELLED' && data.pending_amount > 0"
                                        label="Collect"
                                        icon="pi pi-wallet"
                                        severity="success"
                                        text
                                        size="small"
                                        @click="openPaymentDialog(data)"
                                        title="Collect pending balance"
                                    />
                                    <Button label="Print" icon="pi pi-print" severity="secondary" text size="small" @click="printInvoice(data)" />
                                    <Button
                                        icon="pi pi-whatsapp"
                                        severity="success"
                                        text
                                        size="small"
                                        title="Share Bill & Review on WhatsApp"
                                        @click="shareInvoiceOnWhatsapp(data)"
                                    />
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

            <section class="erp-panel overflow-hidden border border-surface-200 bg-white">
                <div class="border-b border-surface-200 px-5 py-4">
                    <h2 class="text-lg font-semibold text-surface-900">Collection Snapshot</h2>
                    <p class="mt-1 text-sm text-surface-500">Quick view of billed amount, recovery, and pending book.</p>
                </div>

                <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-3">
                    <div class="rounded-lg border border-surface-200 bg-surface-50 px-4 py-4 shadow-xs">
                        <p class="text-xs uppercase tracking-wide text-surface-500">Total Billed</p>
                        <p class="mt-2 text-xl font-semibold text-surface-900">{{ formatCurrency(totalSales) }}</p>
                    </div>

                    <div class="rounded-lg border border-surface-200 bg-surface-50 px-4 py-4 shadow-xs">
                        <p class="text-xs uppercase tracking-wide text-surface-500">Recovered</p>
                        <p class="mt-2 text-xl font-semibold text-emerald-700">{{ formatCurrency(totalCollected) }}</p>
                    </div>

                    <div class="rounded-lg border border-surface-200 bg-surface-50 px-4 py-4 shadow-xs">
                        <p class="text-xs uppercase tracking-wide text-surface-500">Outstanding</p>
                        <p class="mt-2 text-xl font-semibold text-amber-700">{{ formatCurrency(totalPending) }}</p>
                    </div>
                </div>
            </section>
        </div>

        <!-- Collect Payment Dialog -->
        <Dialog v-model:visible="showPaymentDialog" header="Collect Invoice Payment" modal :style="{ width: '32rem' }" @hide="closePaymentDialog">
            <form @submit.prevent="submitPayment" class="space-y-4 pt-2">
                <div class="erp-dialog-banner rounded-lg border border-surface-200 bg-surface-50 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-surface-500">Invoice Number</p>
                            <p class="font-semibold text-surface-900">{{ paymentInvoice?.invoice_number }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-surface-500">Customer</p>
                            <p class="font-medium text-surface-900">{{ paymentInvoice?.customer?.name || 'Walk-in' }}</p>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center justify-between border-t border-surface-200 pt-2 text-xs">
                        <span class="text-surface-500">Total: <strong>{{ formatCurrency(paymentInvoice?.total_amount) }}</strong></span>
                        <span class="text-emerald-700">Paid: <strong>{{ formatCurrency(paymentInvoice?.paid_amount) }}</strong></span>
                        <span class="font-semibold text-amber-700">Pending: <strong>{{ formatCurrency(paymentInvoice?.pending_amount) }}</strong></span>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Amount to Collect (₹) *</label>
                    <InputNumber
                        v-model="paymentForm.amount"
                        mode="currency"
                        currency="INR"
                        locale="en-IN"
                        :max="Number(paymentInvoice?.pending_amount || 0)"
                        :min="0.01"
                        required
                        class="w-full"
                        :class="{ 'p-invalid': paymentForm.errors.amount }"
                    />
                    <small v-if="paymentForm.errors.amount" class="mt-1 block text-xs text-red-500">{{ paymentForm.errors.amount }}</small>
                    <small v-else class="mt-1 block text-xs text-surface-400">Max collectable: {{ formatCurrency(paymentInvoice?.pending_amount) }}</small>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Payment Mode *</label>
                        <Select
                            v-model="paymentForm.payment_method"
                            :options="paymentMethodOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                        />
                        <small v-if="paymentForm.errors.payment_method" class="mt-1 block text-xs text-red-500">{{ paymentForm.errors.payment_method }}</small>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Payment Date *</label>
                        <InputText
                            v-model="paymentForm.date"
                            type="date"
                            required
                            class="w-full"
                            :class="{ 'p-invalid': paymentForm.errors.date }"
                        />
                        <small v-if="paymentForm.errors.date" class="mt-1 block text-xs text-red-500">{{ paymentForm.errors.date }}</small>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Note / Reference (Optional)</label>
                    <InputText
                        v-model="paymentForm.note"
                        placeholder="e.g., UPI ref, check no, or receipt note"
                        class="w-full"
                    />
                    <small v-if="paymentForm.errors.note" class="mt-1 block text-xs text-red-500">{{ paymentForm.errors.note }}</small>
                </div>

                <div class="flex justify-end gap-2 border-t border-surface-200 pt-4">
                    <Button label="Cancel" text severity="secondary" type="button" @click="closePaymentDialog" />
                    <Button label="Record Payment" icon="pi pi-check" type="submit" :loading="paymentForm.processing" severity="success" />
                </div>
            </form>
        </Dialog>

        <Dialog v-model:visible="showVoidDialog" header="Void Invoice" modal :style="{ width: '36rem' }" @hide="closeVoidDialog">
            <div class="space-y-4 pt-2">
                <!-- Summary Card -->
                <div class="rounded-xl border border-surface-200 bg-surface-50 p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-bold text-surface-900">{{ selectedInvoice?.invoice_number }}</p>
                        <Tag :value="selectedInvoice?.status" severity="secondary" class="!text-[10px] font-bold" />
                    </div>
                    <p class="mt-0.5 text-xs text-surface-500">{{ selectedInvoice?.customer?.name || 'Walk-in Customer' }}</p>

                    <div class="mt-3 grid grid-cols-3 gap-2.5 rounded-lg border border-surface-200/80 bg-white p-3 text-xs">
                        <div>
                            <p class="text-surface-500">Gross Total</p>
                            <p class="mt-0.5 font-mono font-bold text-surface-900">{{ formatCurrency(selectedInvoice?.total_amount) }}</p>
                        </div>
                        <div>
                            <p class="text-surface-500">Old Metal Credit</p>
                            <p class="mt-0.5 font-mono font-bold text-amber-800">{{ formatCurrency(selectedInvoice?.old_gold_amount || 0) }}</p>
                        </div>
                        <div>
                            <p class="text-surface-500">Cash / Paid</p>
                            <p class="mt-0.5 font-mono font-bold text-emerald-700">
                                {{ formatCurrency(Math.max(0, Number(selectedInvoice?.paid_amount || 0) - Number(selectedInvoice?.old_gold_amount || 0))) }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- 1. OLD METAL HANDLING (If bill has Old Metal Exchange) -->
                <div v-if="Number(selectedInvoice?.old_gold_amount || 0) > 0" class="rounded-xl border border-amber-200/80 bg-amber-50/50 p-3.5 space-y-2">
                    <label class="text-xs font-bold uppercase tracking-wider text-amber-900 flex items-center justify-between">
                        <span>Old Metal Handling ({{ formatCurrency(selectedInvoice?.old_gold_amount) }} • {{ Number(selectedInvoice?.old_gold_weight || 0).toFixed(3) }}g)</span>
                    </label>
                    <Select
                        v-model="voidForm.old_gold_mode"
                        :options="oldGoldVoidModeOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full !text-xs"
                    />
                    <p class="text-[11px] text-amber-800 leading-relaxed">
                        {{ oldGoldVoidModeOptions.find((o) => o.value === voidForm.old_gold_mode)?.hint }}
                    </p>
                </div>

                <!-- 2. CASH / CARD PAYMENT HANDLING (If cash/card was collected) -->
                <div v-if="Number(selectedInvoice?.paid_amount || 0) > Number(selectedInvoice?.old_gold_amount || 0)" class="rounded-xl border border-surface-200 bg-surface-50/80 p-3.5 space-y-2">
                    <label class="text-xs font-bold uppercase tracking-wider text-surface-700">
                        Cash / Card Collected ({{ formatCurrency(Number(selectedInvoice?.paid_amount || 0) - Number(selectedInvoice?.old_gold_amount || 0)) }})
                    </label>
                    <Select
                        v-model="voidForm.mode"
                        :options="voidModeOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full !text-xs"
                    />
                    <p class="text-[11px] text-surface-500 leading-relaxed">
                        {{ voidModeOptions.find((o) => o.value === voidForm.mode)?.hint }}
                    </p>
                </div>

                <!-- If NO payments and NO old gold was collected -->
                <div v-if="Number(selectedInvoice?.paid_amount || 0) <= 0" class="rounded-xl border border-surface-200 bg-surface-50 p-3 text-xs text-surface-600">
                    <p class="font-semibold text-surface-900">No Payments or Old Metal Collected</p>
                    <p class="mt-0.5 text-surface-500">Voiding this invoice will cancel the bill and restore jewellery stock back to active inventory.</p>
                </div>

                <!-- Reason -->
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-surface-700">Void Reason <span class="text-red-500">*</span></label>
                    <Textarea v-model="voidForm.reason" rows="2" class="w-full !text-xs" placeholder="Why is this invoice being voided/cancelled?" />
                    <small v-if="voidForm.errors.reason" class="mt-0.5 block text-[11px] text-red-500">{{ voidForm.errors.reason }}</small>
                </div>

                <!-- Stock Note -->
                <div class="rounded-lg border border-surface-200 bg-surface-50 px-3 py-2 text-[11.5px] text-surface-600">
                    <i class="pi pi-info-circle mr-1 text-primary"></i>
                    Sold jewellery pieces will automatically return to active store inventory.
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between border-t border-surface-200 pt-3.5">
                    <div class="text-xs">
                        <span class="text-surface-500">Customer Advance: </span>
                        <span class="font-mono font-bold text-emerald-700">
                            {{ formatCurrency(voidPreviewAdvance) }}
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button label="Close" severity="secondary" text size="small" @click="closeVoidDialog" />
                        <Button
                            :label="voidPreviewAdvance > 0 ? 'Void & Keep Advance (' + formatCurrency(voidPreviewAdvance) + ')' : 'Confirm Void Invoice'"
                            icon="pi pi-check"
                            severity="danger"
                            size="small"
                            @click="submitVoid"
                            :loading="voidForm.processing"
                        />
                    </div>
                </div>
            </div>
        </Dialog>

        <!-- Invoice Details Modal -->
        <Dialog v-model:visible="showViewDialog" header="Invoice Details" modal :style="{ width: '52rem', maxWidth: '95vw' }" @hide="closeViewDialog">
            <div v-if="viewInvoice" class="space-y-4 pt-1 text-sm">
                <!-- Top Banner / Header -->
                <div class="erp-dialog-banner overflow-hidden rounded-xl border border-surface-200 bg-white">
                    <div class="bg-surface-50 px-4 py-4">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-lg font-bold text-surface-900">{{ viewInvoice.invoice_number }}</span>
                                    <Tag :severity="viewInvoice.status === 'CANCELLED' ? 'danger' : 'success'" :value="viewInvoice.status === 'CANCELLED' ? 'Voided' : 'Valid'" />
                                </div>
                                <p class="mt-1 text-xs text-surface-500">
                                    Billed on {{ formatDate(viewInvoice.date) }}
                                    <span v-if="viewInvoice.created_at" class="text-surface-400"> ({{ viewInvoice.created_at }})</span>
                                    <span v-if="viewInvoice.created_by" class="ml-2 font-medium text-surface-700">• Billed by: {{ viewInvoice.created_by }}</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <Button label="Print Bill" icon="pi pi-print" size="small" outlined @click="printInvoice(viewInvoice)" />
                                <Button
                                    v-if="viewInvoice.status !== 'CANCELLED' && viewInvoice.pending_amount > 0"
                                    label="Collect Payment"
                                    icon="pi pi-wallet"
                                    severity="success"
                                    size="small"
                                    @click="openPaymentDialog(viewInvoice); showViewDialog = false;"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Customer Snapshot -->
                    <div class="grid grid-cols-1 divide-y divide-surface-100 text-sm sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                        <div class="px-4 py-3">
                            <p class="text-[11px] font-semibold tracking-wider text-surface-500 uppercase">Customer</p>
                            <p class="mt-1 font-semibold text-surface-900">{{ viewInvoice.customer?.name || 'Walk-in Customer' }}</p>
                        </div>
                        <div class="px-4 py-3">
                            <p class="text-[11px] font-semibold tracking-wider text-surface-500 uppercase">Mobile</p>
                            <p class="mt-1 font-medium text-surface-800">{{ viewInvoice.customer?.mobile || 'Not provided' }}</p>
                        </div>
                        <div class="px-4 py-3">
                            <p class="text-[11px] font-semibold tracking-wider text-surface-500 uppercase">City</p>
                            <p class="mt-1 font-medium text-surface-800">{{ viewInvoice.customer?.city || 'Not provided' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Void Alert if Cancelled -->
                <div v-if="viewInvoice.status === 'CANCELLED'" class="rounded-lg border border-red-200 bg-red-50 p-3.5 text-xs text-red-800">
                    <div class="flex items-start gap-2.5">
                        <i class="pi pi-times-circle mt-0.5 text-base text-red-600"></i>
                        <div>
                            <p class="font-bold text-red-900">This invoice has been voided</p>
                            <p class="mt-0.5">
                                Mode: <strong>{{ Number(viewInvoice.paid_amount || 0) > 0 ? (viewInvoice.cancellation_mode === 'refund' ? 'Refunded to customer (' + formatCurrency(viewInvoice.paid_amount) + ')' : 'Kept as customer advance (' + formatCurrency(viewInvoice.paid_amount) + ')') : 'Unpaid bill voided (No payments were collected)' }}</strong>
                                <span v-if="viewInvoice.cancelled_by"> • By: {{ viewInvoice.cancelled_by }}</span>
                                <span v-if="viewInvoice.cancelled_at"> • On: {{ viewInvoice.cancelled_at }}</span>
                            </p>
                            <p v-if="viewInvoice.cancellation_reason" class="mt-1 italic text-red-700">
                                "{{ viewInvoice.cancellation_reason }}"
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Billed Items Table -->
                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-xs font-bold tracking-wider text-surface-600 uppercase">Billed Items ({{ viewInvoice.items?.length || 0 }})</p>
                    </div>
                    <div class="erp-native-table overflow-hidden rounded-lg border border-surface-200">
                        <table class="w-full text-left text-xs">
                            <thead class="border-b border-surface-200 bg-surface-50 font-semibold tracking-wider text-surface-600 uppercase">
                                <tr>
                                    <th class="px-3 py-2">#</th>
                                    <th class="px-3 py-2">Item</th>
                                    <th class="px-3 py-2 text-right">Weight</th>
                                    <th class="px-3 py-2">Purity</th>
                                    <th class="px-3 py-2 text-right">Rate</th>
                                    <th class="px-3 py-2 text-right">Making</th>
                                    <th class="px-3 py-2 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-100 bg-white">
                                <tr v-for="(item, idx) in viewInvoice.items" :key="item.id || idx" class="hover:bg-surface-50/50">
                                    <td class="px-3 py-2 text-surface-400">{{ idx + 1 }}</td>
                                    <td class="px-3 py-2 font-medium text-surface-900">
                                        <div>{{ item.item_name }}</div>
                                        <div v-if="item.product_barcode || item.silver_product_barcode" class="font-mono text-[10px] text-surface-500">
                                            {{ item.product_barcode || item.silver_product_barcode }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono text-surface-700">
                                        {{ item.weight > 0 ? Number(item.weight).toFixed(3) + ' g' : (item.quantity ? item.quantity + ' pcs' : '—') }}
                                    </td>
                                    <td class="px-3 py-2 text-surface-600">{{ item.purity || '—' }}</td>
                                    <td class="px-3 py-2 text-right font-mono text-surface-700">{{ formatCurrency(item.rate) }}</td>
                                    <td class="px-3 py-2 text-right font-mono text-surface-700">
                                        <span v-if="item.making_charge_type === 'flat' || item.making_charge_type === 'lump_sum'">
                                            {{ formatCurrency(item.making_charges) }}
                                        </span>
                                        <span v-else-if="item.making_charge_type === 'per_gram'">
                                            {{ formatCurrency(item.making_charges) }}/g
                                        </span>
                                        <span v-else>
                                            {{ Number(item.making_charges || 0).toFixed(2) }}%
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono font-bold text-surface-900">{{ formatCurrency(item.final_price || item.total_price) }}</td>
                                </tr>
                                <tr v-if="!viewInvoice.items?.length">
                                    <td colspan="7" class="px-4 py-6 text-center text-xs text-surface-400">No item details recorded for this invoice.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Old Metal Exchange Table -->
                <div v-if="viewInvoice.old_golds?.length" class="mt-3 overflow-hidden rounded-xl border border-amber-200 bg-white">
                    <div class="flex items-center justify-between border-b border-amber-200 bg-amber-50/70 px-3 py-2.5">
                        <p class="text-xs font-bold tracking-wider text-surface-700 uppercase flex items-center gap-1.5">
                            <Coins class="w-3.5 h-3.5 text-amber-600" />
                            <span>Old Metal Exchange ({{ viewInvoice.old_golds.length }})</span>
                        </p>
                        <span class="text-xs font-semibold text-amber-800">
                            Total: {{ Number(viewInvoice.old_gold_weight || 0).toFixed(3) }} g
                        </span>
                    </div>
                    <div class="erp-native-table overflow-x-auto bg-white">
                        <table class="w-full min-w-[760px] text-left text-xs">
                            <thead class="border-b border-surface-200 bg-surface-50/80 font-semibold tracking-wider text-surface-700 uppercase">
                                <tr>
                                    <th class="px-3 py-2.5">#</th>
                                    <th class="px-3 py-2.5">Metal</th>
                                    <th class="px-3 py-2.5">Description</th>
                                    <th class="px-3 py-2.5 text-right">Gross Wt</th>
                                    <th class="px-3 py-2.5 text-right">Deduction</th>
                                    <th class="px-3 py-2.5 text-right">Net Wt</th>
                                    <th class="px-3 py-2.5">Purity</th>
                                    <th class="px-3 py-2.5 text-right">Buy Rate</th>
                                    <th class="px-3 py-2.5 text-right">Credit Value</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-100">
                                <tr v-for="(og, oidx) in viewInvoice.old_golds" :key="og.id || oidx" class="hover:bg-amber-50/20">
                                    <td class="px-3 py-2 text-surface-400">{{ oidx + 1 }}</td>
                                    <td class="px-3 py-2">
                                        <Tag :value="og.metal_type" :severity="og.metal_type === 'SILVER' ? 'secondary' : 'warn'" class="!text-[10px]" />
                                    </td>
                                    <td class="px-3 py-2 font-medium text-surface-900">{{ og.description || `Old ${og.metal_type}` }}</td>
                                    <td class="px-3 py-2 text-right font-mono text-surface-700">{{ Number(og.gross_weight).toFixed(3) }} g</td>
                                    <td class="px-3 py-2 text-right font-mono text-surface-500">{{ Number(og.wastage_weight).toFixed(3) }} g</td>
                                    <td class="px-3 py-2 text-right font-mono font-semibold text-surface-800">{{ Number(og.net_weight).toFixed(3) }} g</td>
                                    <td class="px-3 py-2 font-semibold">{{ og.purity }}</td>
                                    <td class="px-3 py-2 text-right font-mono text-surface-700">{{ formatCurrency(og.rate) }}</td>
                                    <td class="px-3 py-2 text-right font-mono font-bold text-amber-900">{{ formatCurrency(og.final_price) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Financial Summary & Payment Breakdown -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <!-- Payment Timeline -->
                    <div class="erp-subpanel border border-surface-200 bg-white p-3.5">
                        <div class="mb-2.5 flex items-center justify-between border-b border-surface-100 pb-2">
                            <p class="text-xs font-bold tracking-wider text-surface-600 uppercase">Payment Receipts</p>
                            <span class="text-[11px] font-medium text-surface-500">{{ viewInvoice.payments?.length || 0 }} received</span>
                        </div>

                        <div v-if="viewInvoice.payments?.length" class="max-h-44 space-y-2 overflow-y-auto py-1 pr-1">
                            <div
                                v-for="pmt in viewInvoice.payments"
                                :key="pmt.id"
                                class="erp-list-item flex items-center justify-between rounded-md border border-surface-200 bg-surface-50/90 px-3 py-2 text-xs"
                            >
                                <div>
                                    <div class="flex items-center gap-2">
                                        <Tag :value="pmt.payment_method || 'CASH'" severity="secondary" class="!px-2 !py-0.5 !text-[10px] font-bold" />
                                        <span class="text-surface-700 font-medium">{{ formatDate(pmt.date) }}</span>
                                    </div>
                                    <p v-if="pmt.description" class="mt-1 max-w-[15rem] truncate text-[11px] text-surface-500">{{ pmt.description }}</p>
                                </div>
                                <span class="font-mono font-bold text-emerald-700 text-xs">{{ formatCurrency(pmt.amount) }}</span>
                            </div>
                        </div>
                        <div v-else class="py-5 text-center text-xs text-surface-400">
                            No separate payment transactions found.
                        </div>
                    </div>

                    <!-- Calculations Card -->
                    <div class="erp-subpanel space-y-1.5 border border-surface-200 bg-surface-50 p-3.5 text-xs">
                        <div class="flex justify-between text-surface-600">
                            <span>Subtotal</span>
                            <span class="font-mono font-medium text-surface-900">
                                {{ formatCurrency(Number(viewInvoice.total_amount || 0) - Number(viewInvoice.tax_amount || 0) + Number(viewInvoice.discount_amount || 0)) }}
                            </span>
                        </div>
                        <div v-if="Number(viewInvoice.discount_amount || 0) > 0" class="flex justify-between text-emerald-700">
                            <span>Discount</span>
                            <span class="font-mono font-medium">- {{ formatCurrency(viewInvoice.discount_amount) }}</span>
                        </div>
                        <div v-if="Number(viewInvoice.tax_amount || 0) > 0" class="flex justify-between text-surface-600">
                            <span>GST (3%)</span>
                            <span class="font-mono font-medium text-surface-900">{{ formatCurrency(viewInvoice.tax_amount) }}</span>
                        </div>
                        <div class="flex justify-between border-t border-surface-200 pt-1.5 font-bold text-surface-900">
                            <span>Gross Total</span>
                            <span class="font-mono">{{ formatCurrency(viewInvoice.total_amount) }}</span>
                        </div>
                        <div v-if="Number(viewInvoice.old_gold_amount || 0) > 0" class="flex justify-between text-amber-800 font-medium">
                            <span>Less Old Metal Exchange</span>
                            <span class="font-mono font-bold">- {{ formatCurrency(viewInvoice.old_gold_amount) }}</span>
                        </div>
                        <div v-if="Number(viewInvoice.old_gold_amount || 0) > 0" class="flex justify-between border-t border-surface-200 pt-1 font-bold text-amber-900">
                            <span>Net Payable</span>
                            <span class="font-mono">{{ formatCurrency(Math.max(0, Number(viewInvoice.total_amount || 0) - Number(viewInvoice.old_gold_amount || 0))) }}</span>
                        </div>
                        <div class="flex justify-between font-medium text-emerald-700">
                            <span>Total Settled (Cash/Card/Exch)</span>
                            <span class="font-mono font-bold">{{ formatCurrency(viewInvoice.paid_amount) }}</span>
                        </div>
                        <div class="flex justify-between font-bold" :class="viewInvoice.pending_amount > 0 ? 'text-amber-700' : 'text-surface-500'">
                            <span>Pending Balance</span>
                            <span class="font-mono">{{ formatCurrency(viewInvoice.pending_amount) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between border-t border-surface-200 pt-3">
                    <div>
                        <Button
                            v-if="viewInvoice.status !== 'CANCELLED'"
                            label="Void Invoice"
                            icon="pi pi-times"
                            severity="danger"
                            text
                            size="small"
                            @click="openVoidDialog(viewInvoice); showViewDialog = false;"
                        />
                    </div>
                    <div class="flex gap-2">
                        <Button label="Close" text severity="secondary" size="small" @click="closeViewDialog" />
                        <Button
                            label="WhatsApp Bill"
                            icon="pi pi-whatsapp"
                            severity="success"
                            size="small"
                            @click="shareInvoiceOnWhatsapp(viewInvoice)"
                        />
                        <Button label="Print Bill" icon="pi pi-print" size="small" @click="printInvoice(viewInvoice)" />
                    </div>
                </div>
            </div>
        </Dialog>
    </AppLayout>
</template>
