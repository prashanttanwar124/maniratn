<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, Landmark, MapPin, Phone, ShieldCheck, WalletCards } from 'lucide-vue-next';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Paginator from 'primevue/paginator';
import Tag from 'primevue/tag';
import { computed } from 'vue';
import { route } from 'ziggy-js';
import { formatIndianDate } from '@/utils/indiaTime';

const props = defineProps({
    customer: Object,
    transactions: Object,
    stats: Object,
});

const breadcrumbs = [
    {
        title: 'Customers',
        href: '/customers',
    },
    {
        title: props.customer.name,
        href: route('customers.show', props.customer.id),
    },
];

const tableData = computed(() => props.transactions.data);

const onPageChange = (event) => {
    router.get(
        route('customers.show', props.customer.id),
        { page: event.page + 1 },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const formatMoney = (value) =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
    }).format(Number(value || 0));

const formatDate = (dateString) =>
    formatIndianDate(dateString, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });

const balanceTone = computed(() => (Number(props.stats.current_balance || 0) > 0 ? 'text-red-600' : 'text-emerald-600'));
const balanceLabel = computed(() => (Number(props.stats.current_balance || 0) > 0 ? 'Receivable from customer' : 'Account settled / advance'));

const getSeverity = (type) => {
    if (type === 'SALE') return 'danger';
    if (type === 'PAYMENT') return 'success';
    if (type === 'VOID') return 'secondary';
    return 'info';
};
</script>

<template>
    <Head :title="customer.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4">
            <section class="relative overflow-hidden border border-surface-200 bg-white">
                <div class="absolute inset-y-0 right-0 hidden w-96 bg-[radial-gradient(circle_at_top_right,_rgba(245,158,11,0.16),_transparent_62%)] lg:block" />
                <div class="relative flex flex-col gap-6 px-5 py-6 lg:flex-row lg:items-start lg:justify-between">
                    <div class="max-w-3xl">
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-2xl font-semibold tracking-tight text-surface-900">{{ customer.name }}</h1>
                            <Tag value="Customer Profile" severity="secondary" />
                            <Tag :value="Number(stats.current_balance || 0) > 0 ? 'Due Pending' : 'Healthy Account'" :severity="Number(stats.current_balance || 0) > 0 ? 'danger' : 'success'" />
                        </div>

                        <p class="mt-3 max-w-2xl text-sm leading-6 text-surface-600">
                            Review purchase history, payment movement, and move into the full ledger when you need account-level cash entries or settlement context.
                        </p>

                        <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div class="flex items-center gap-3 border border-surface-200 bg-surface-0 px-4 py-3">
                                <MapPin class="h-4 w-4 text-surface-500" />
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-surface-500">City</p>
                                    <p class="mt-1 text-sm font-medium text-surface-900">{{ customer.city || 'Unknown city' }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 border border-surface-200 bg-surface-0 px-4 py-3">
                                <Phone class="h-4 w-4 text-surface-500" />
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-surface-500">Mobile</p>
                                    <p class="mt-1 text-sm font-medium text-surface-900">{{ customer.mobile || 'No mobile' }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 border border-surface-200 bg-surface-0 px-4 py-3">
                                <ShieldCheck class="h-4 w-4 text-surface-500" />
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-surface-500">PAN</p>
                                    <p class="mt-1 text-sm font-medium text-surface-900">{{ customer.pan_no || '--' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Link :href="route('ledger.show', { type: 'customers', id: customer.id })">
                            <Button label="Open Ledger" icon="pi pi-book" outlined />
                        </Link>
                        <Button label="New Bill" icon="pi pi-plus" @click="$inertia.visit(route('invoices.create', { customer_id: customer.id }))" />
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 xl:grid-cols-4">
                <div class="border border-surface-200 bg-white p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm text-surface-500">Total purchased</p>
                            <p class="mt-2 text-2xl font-semibold text-surface-900">{{ formatMoney(stats.total_sales) }}</p>
                        </div>
                        <span class="rounded-full bg-surface-100 p-2 text-surface-600">
                            <Landmark class="h-4 w-4" />
                        </span>
                    </div>
                </div>

                <div class="border border-surface-200 bg-white p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm text-surface-500">Total paid</p>
                            <p class="mt-2 text-2xl font-semibold text-emerald-600">{{ formatMoney(stats.total_paid) }}</p>
                        </div>
                        <span class="rounded-full bg-emerald-100 p-2 text-emerald-700">
                            <WalletCards class="h-4 w-4" />
                        </span>
                    </div>
                </div>

                <div class="border border-surface-200 bg-white p-5 xl:col-span-2">
                    <p class="text-sm text-surface-500">Current balance</p>
                    <div class="mt-2 flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                        <p :class="['text-3xl font-semibold', balanceTone]">{{ formatMoney(stats.current_balance) }}</p>
                        <p class="text-sm text-surface-500">{{ balanceLabel }}</p>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1.6fr_1fr]">
                <div class="overflow-hidden border border-surface-200 bg-white">
                    <div class="border-b border-surface-200 px-5 py-4">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h3 class="text-base font-semibold text-surface-900">Recent transaction history</h3>
                                <p class="mt-1 text-sm text-surface-500">Quick view of invoice and payment records on the customer account.</p>
                            </div>

                            <Link :href="route('ledger.show', { type: 'customers', id: customer.id })" class="inline-flex">
                                <Button label="View Full Ledger" icon="pi pi-arrow-right" size="small" text />
                            </Link>
                        </div>
                    </div>

                    <div class="p-4">
                        <DataTable :value="tableData" stripedRows rowHover tableStyle="min-width: 54rem">
                            <template #empty>
                                <div class="py-12 text-center text-surface-500">No transactions found for this customer.</div>
                            </template>

                            <Column field="date" header="Date" style="width: 130px">
                                <template #body="{ data }">
                                    <span class="font-mono text-sm text-surface-600">{{ formatDate(data.date) }}</span>
                                </template>
                            </Column>

                            <Column field="description" header="Description">
                                <template #body="{ data }">
                                    <div>
                                        <p class="font-medium text-surface-900">{{ data.description }}</p>
                                        <p v-if="data.invoice_id" class="mt-1 text-xs text-surface-500">Bill reference #{{ data.invoice_id }}</p>
                                    </div>
                                </template>
                            </Column>

                            <Column header="Handled By" style="width: 170px">
                                <template #body="{ data }">
                                    <span class="text-sm font-medium text-surface-700">{{ data.user ? data.user.name : 'System' }}</span>
                                </template>
                            </Column>

                            <Column field="type" header="Type" style="width: 120px">
                                <template #body="{ data }">
                                    <Tag :value="data.type" :severity="getSeverity(data.type)" />
                                </template>
                            </Column>

                            <Column field="amount" header="Amount" sortable style="width: 150px">
                                <template #body="{ data }">
                                    <span v-if="data.type === 'VOID'" class="font-semibold text-surface-400 line-through">
                                        {{ formatMoney(data.amount) }}
                                    </span>
                                    <span v-else-if="data.type === 'SALE'" class="font-semibold text-red-600">
                                        + {{ formatMoney(data.amount) }}
                                    </span>
                                    <span v-else class="font-semibold text-emerald-600">
                                        - {{ formatMoney(data.amount) }}
                                    </span>
                                </template>
                            </Column>
                        </DataTable>
                    </div>

                    <Paginator
                        :rows="transactions.per_page"
                        :totalRecords="transactions.total"
                        :first="(transactions.current_page - 1) * transactions.per_page"
                        @page="onPageChange"
                        class="border-t border-surface-200"
                    />
                </div>

                <div class="space-y-6">
                    <div class="overflow-hidden border border-surface-200 bg-white">
                        <div class="border-b border-surface-200 px-5 py-4">
                            <h3 class="text-base font-semibold text-surface-900">Account actions</h3>
                            <p class="mt-1 text-sm text-surface-500">Most common next steps for this customer account.</p>
                        </div>

                        <div class="space-y-3 p-5">
                            <Link :href="route('ledger.show', { type: 'customers', id: customer.id })" class="flex items-center justify-between border border-surface-200 px-4 py-3 transition-colors hover:border-amber-300 hover:bg-amber-50">
                                <div>
                                    <p class="font-medium text-surface-900">Open customer ledger</p>
                                    <p class="mt-1 text-xs text-surface-500">Post manual cash entries or review full balance flow</p>
                                </div>
                                <ArrowRight class="h-4 w-4 text-surface-500" />
                            </Link>

                            <Link :href="route('invoices.create', { customer_id: customer.id })" class="flex items-center justify-between border border-surface-200 px-4 py-3 transition-colors hover:border-surface-300 hover:bg-surface-50">
                                <div>
                                    <p class="font-medium text-surface-900">Prepare new bill</p>
                                    <p class="mt-1 text-xs text-surface-500">Start a new invoice for this customer</p>
                                </div>
                                <ArrowRight class="h-4 w-4 text-surface-500" />
                            </Link>
                        </div>
                    </div>

                    <div class="overflow-hidden border border-surface-200 bg-white">
                        <div class="border-b border-surface-200 px-5 py-4">
                            <h3 class="text-base font-semibold text-surface-900">Recovery note</h3>
                            <p class="mt-1 text-sm text-surface-500">Use this at-a-glance summary during collection follow-up.</p>
                        </div>

                        <div class="p-5">
                            <div class="rounded border border-surface-200 bg-surface-50 px-4 py-4">
                                <p class="text-sm text-surface-500">Outstanding amount</p>
                                <p :class="['mt-2 text-2xl font-semibold', balanceTone]">{{ formatMoney(stats.current_balance) }}</p>
                                <p class="mt-2 text-sm text-surface-500">{{ balanceLabel }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
