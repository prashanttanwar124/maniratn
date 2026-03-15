<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';

const props = defineProps({
    rates: Object,
    vaults: Object,
    isDayOpen: Boolean,
    metrics: Object,
    karigars: Array,
    activities: Array,
    recent_vault_movements: Array,
    recent_invoices: Array,
    recent_expenses: Array,
    opening_expectation: Object,
});

const page = usePage();
const can = computed(() => page.props.auth?.can || {});
const isInitialSetup = computed(() => Boolean(page.props.dayStatus?.is_initial_setup));
const openingExpectation = computed(() => props.opening_expectation || { cash: 0, gold: 0, date: null });
const rateForm = useForm({
    gold_sell: parseFloat(props.rates?.gold_sell || 0),
    gold_buy: parseFloat(props.rates?.gold_buy || 0),
    silver_sell: parseFloat(props.rates?.silver_sell || 0),
});

const dayForm = useForm({
    opening_cash: parseFloat(openingExpectation.value?.cash || 0),
    opening_gold: parseFloat(openingExpectation.value?.gold || 0),
    mismatch_reason: '',
    reopen_reason: '',
});

const dayOpeningMismatch = computed(() => {
    return !isInitialSetup.value && (
        Math.abs(Number(dayForm.opening_cash || 0) - Number(openingExpectation.value?.cash || 0)) > 0.0001 ||
        Math.abs(Number(dayForm.opening_gold || 0) - Number(openingExpectation.value?.gold || 0)) > 0.0001
    );
});

const closeForm = useForm({
    closing_cash: null,
    closing_gold: null,
});

const expenseForm = useForm({
    title: '',
    category: 'Food',
    amount: null,
    payment_method: 'CASH',
    date: new Date(),
});

const vaultTransferForm = useForm({
    from_vault: 'CASH',
    to_vault: 'BANK',
    amount: null,
    note: '',
    date: new Date(),
});

const showRateDialog = ref(false);
const showDayDialog = ref(false);
const showCloseDialog = ref(false);
const showExpenseDialog = ref(false);
const showVaultTransferDialog = ref(false);

const totalKarigars = computed(() => props.karigars?.length || 0);
const activeAlerts = computed(() => Number(props.metrics?.overdue_items || 0) + (props.isDayOpen ? 0 : 1));
const expenseCategories = ['Food', 'Travel', 'Utility', 'Salary', 'Repair', 'Other'];
const expenseMethods = ['CASH', 'UPI', 'BANK'];
const vaultTransferOptions = [
    { label: 'Cash in Hand', value: 'CASH' },
    { label: 'Bank', value: 'BANK' },
];
const vaultLabels = {
    CASH: 'Cash in Hand',
    BANK: 'Bank',
};
const quickLinks = [
    { label: 'New Bill', href: route('invoices.create'), icon: 'pi pi-file-edit' },
    { label: 'Orders', href: route('orders.index'), icon: 'pi pi-briefcase' },
    { label: 'Customers', href: route('customers.index'), icon: 'pi pi-users' },
    { label: 'Products', href: route('products.index'), icon: 'pi pi-box' },
    { label: 'Expenses', href: route('expenses.index'), icon: 'pi pi-wallet' },
];

const formatCurrency = (val) =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 0,
    }).format(val || 0);

const formatWeight = (val) => `${Number(val || 0).toFixed(3)} g`;

const formatVaultMovementAmount = (movement) => {
    return ['GOLD', 'SILVER'].includes(movement.vault_type) ? formatWeight(movement.amount) : formatCurrency(movement.amount);
};

const formatVaultMovementBalance = (movement) => {
    return ['GOLD', 'SILVER'].includes(movement.vault_type) ? formatWeight(movement.balance_after) : formatCurrency(movement.balance_after);
};

const closingCashDifference = computed(() => {
    if (closeForm.closing_cash === null || closeForm.closing_cash === undefined) return null;

    return Number(closeForm.closing_cash || 0) - Number(props.vaults?.cash || 0);
});

const closingGoldDifference = computed(() => {
    if (closeForm.closing_gold === null || closeForm.closing_gold === undefined) return null;

    return Number(closeForm.closing_gold || 0) - Number(props.vaults?.gold || 0);
});

const transferSourceBalance = computed(() => {
    const source = vaultTransferForm.from_vault?.toLowerCase();

    return Number(props.vaults?.[source] || 0);
});

const transferAmountExceedsBalance = computed(() => {
    if (vaultTransferForm.amount === null || vaultTransferForm.amount === undefined) return false;

    return Number(vaultTransferForm.amount || 0) > transferSourceBalance.value;
});

const transferCanSubmit = computed(() => {
    return (
        vaultTransferForm.from_vault &&
        vaultTransferForm.to_vault &&
        vaultTransferForm.from_vault !== vaultTransferForm.to_vault &&
        Number(vaultTransferForm.amount || 0) > 0 &&
        !transferAmountExceedsBalance.value &&
        String(vaultTransferForm.note || '').trim().length > 0
    );
});

const expenseSourceVaultLabel = computed(() => (expenseForm.payment_method === 'CASH' ? 'Cash in Hand' : 'Bank'));
const expenseSourceBalance = computed(() => {
    return expenseForm.payment_method === 'CASH' ? Number(props.vaults?.cash || 0) : Number(props.vaults?.bank || 0);
});
const expenseAmountExceedsBalance = computed(() => {
    if (expenseForm.amount === null || expenseForm.amount === undefined) return false;

    return Number(expenseForm.amount || 0) > expenseSourceBalance.value;
});
const expenseCanSubmit = computed(() => {
    return (
        String(expenseForm.title || '').trim().length > 0 &&
        String(expenseForm.category || '').trim().length > 0 &&
        expenseForm.payment_method &&
        Number(expenseForm.amount || 0) > 0 &&
        !expenseAmountExceedsBalance.value &&
        Boolean(expenseForm.date)
    );
});

const saveRates = () => {
    rateForm.post(route('dashboard.update-rates'), {
        onSuccess: () => (showRateDialog.value = false),
    });
};

const openShop = () => {
    dayForm.post(route('dashboard.open-day'), {
        onSuccess: () => (showDayDialog.value = false),
    });
};

const closeShop = () => {
    closeForm.post(route('dashboard.close-day'), {
        onSuccess: () => (showCloseDialog.value = false),
    });
};

const saveExpense = () => {
    expenseForm.post(route('expenses.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showExpenseDialog.value = false;
            expenseForm.reset();
            expenseForm.category = 'Food';
            expenseForm.payment_method = 'CASH';
            expenseForm.date = new Date();
        },
    });
};

const openExpenseDialog = () => {
    expenseForm.clearErrors();
    showExpenseDialog.value = true;
};

const saveVaultTransfer = () => {
    vaultTransferForm.post(route('dashboard.add-funds'), {
        preserveScroll: true,
        onSuccess: () => {
            showVaultTransferDialog.value = false;
            vaultTransferForm.reset();
            vaultTransferForm.from_vault = 'CASH';
            vaultTransferForm.to_vault = 'BANK';
            vaultTransferForm.date = new Date();
        },
    });
};

const openVaultTransferDialog = () => {
    vaultTransferForm.clearErrors();
    showVaultTransferDialog.value = true;
};
</script>

<template>
    <AppLayout>
        <div class="">
            <div class="mx-auto max-w-7xl space-y-6">
                <!-- Header -->
                <div class="border border-surface-200 bg-white px-5 py-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-3">
                                <h2 class="text-2xl font-semibold tracking-tight text-surface-900">Admin Dashboard</h2>
                                <Tag :value="isDayOpen ? 'Day Open' : 'Day Closed'" :severity="isDayOpen ? 'success' : 'danger'" />
                                <Tag v-if="activeAlerts" :value="`${activeAlerts} alert${activeAlerts > 1 ? 's' : ''}`" severity="warn" />
                            </div>

                            <p class="mt-1 text-sm text-surface-500">Live view of sales, vault balances, production load, and shop spending.</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <Button v-if="can.manage_expenses" label="Add Expense" icon="pi pi-minus-circle" severity="danger" outlined size="small" @click="openExpenseDialog" />
                            <Button v-if="can.manage_vault && isDayOpen" label="Transfer Funds" icon="pi pi-arrow-right-arrow-left" outlined size="small" @click="openVaultTransferDialog" />
                            <Button v-if="can.manage_daily_rates" label="Update Rates" icon="pi pi-pencil" outlined size="small" @click="showRateDialog = true" />
                            <Button v-if="can.manage_vault && !isDayOpen" label="Open Day" icon="pi pi-lock-open" size="small" @click="showDayDialog = true" />
                            <Button v-if="can.manage_vault && isDayOpen" label="Close Day" icon="pi pi-lock" severity="danger" size="small" @click="showCloseDialog = true" />
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 xl:grid-cols-4">
                    <div class="border border-surface-200 bg-white p-5 xl:col-span-3">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h3 class="text-base font-semibold text-surface-900">Quick Access</h3>
                                <p class="mt-1 text-sm text-surface-500">Jump into billing, orders, customers, stock, and expenses.</p>
                            </div>
                            <span class="text-xs uppercase tracking-wide text-surface-400">Shortcuts</span>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-5">
                            <Link
                                v-for="item in quickLinks"
                                :key="item.label"
                                :href="item.href"
                                class="flex min-h-24 flex-col justify-between border border-surface-200 bg-surface-50 px-4 py-3 transition hover:border-amber-300 hover:bg-amber-50"
                            >
                                <i :class="[item.icon, 'text-lg text-surface-700']"></i>
                                <span class="text-sm font-medium text-surface-900">{{ item.label }}</span>
                            </Link>
                        </div>
                    </div>

                    <div class="border border-surface-200 bg-white p-5">
                        <h3 class="text-base font-semibold text-surface-900">Today Snapshot</h3>
                        <div class="mt-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-surface-500">Invoices</span>
                                <span class="font-semibold text-surface-900">{{ recent_invoices?.length || 0 }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-surface-500">Expenses</span>
                                <span class="font-semibold text-surface-900">{{ recent_expenses?.length || 0 }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-surface-500">Active Karigars</span>
                                <span class="font-semibold text-surface-900">{{ totalKarigars }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-surface-500">Gold Buy Rate</span>
                                <span class="font-semibold text-surface-900">{{ formatCurrency(rates?.gold_buy) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="border border-surface-200 bg-white p-4">
                        <p class="text-sm text-surface-500">Today's Sales</p>
                        <p class="mt-2 text-xl font-semibold text-surface-900">{{ formatCurrency(metrics?.today_sales) }}</p>
                    </div>

                    <div class="border border-surface-200 bg-white p-4">
                        <p class="text-sm text-surface-500">Today's Collections</p>
                        <p class="mt-2 text-xl font-semibold text-green-700">{{ formatCurrency(metrics?.today_collections) }}</p>
                    </div>

                    <div class="border border-surface-200 bg-white p-4">
                        <p class="text-sm text-surface-500">Today's Expenses</p>
                        <p class="mt-2 text-xl font-semibold text-red-600">{{ formatCurrency(metrics?.today_expenses) }}</p>
                    </div>

                    <div class="border border-surface-200 bg-white p-4">
                        <p class="text-sm text-surface-500">Overdue Items</p>
                        <p class="mt-2 text-xl font-semibold" :class="metrics?.overdue_items ? 'text-orange-600' : 'text-surface-900'">
                            {{ metrics?.overdue_items || 0 }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-6">
                    <div class="border border-surface-200 bg-white p-4">
                        <p class="text-sm text-surface-500">Cash in Hand</p>
                        <p class="mt-2 text-xl font-semibold text-surface-900">{{ formatCurrency(vaults?.cash) }}</p>
                    </div>

                    <div class="border border-surface-200 bg-white p-4">
                        <p class="text-sm text-surface-500">Bank Balance</p>
                        <p class="mt-2 text-xl font-semibold text-surface-900">{{ formatCurrency(vaults?.bank) }}</p>
                    </div>

                    <div class="border border-surface-200 bg-white p-4">
                        <p class="text-sm text-surface-500">Gold in Vault</p>
                        <p class="mt-2 text-xl font-semibold text-surface-900">{{ formatWeight(vaults?.gold) }}</p>
                    </div>

                    <div class="border border-surface-200 bg-white p-4">
                        <p class="text-sm text-surface-500">New Items</p>
                        <p class="mt-2 text-xl font-semibold text-surface-900">{{ metrics?.new_orders || 0 }}</p>
                    </div>

                    <div class="border border-surface-200 bg-white p-4">
                        <p class="text-sm text-surface-500">In Production</p>
                        <p class="mt-2 text-xl font-semibold text-surface-900">{{ metrics?.in_production || 0 }}</p>
                    </div>

                    <div class="border border-surface-200 bg-white p-4">
                        <p class="text-sm text-surface-500">Ready for Billing</p>
                        <p class="mt-2 text-xl font-semibold text-surface-900">{{ metrics?.ready_items || 0 }}</p>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
                    <!-- Karigar Table -->
                    <div class="overflow-hidden border border-surface-200 bg-white xl:col-span-6">
                        <div class="flex items-center justify-between border-b border-surface-200 px-5 py-4">
                            <div>
                                <h3 class="text-base font-semibold text-surface-900">Karigar Holdings</h3>
                                <p class="mt-1 text-sm text-surface-500">Gold currently issued to workers</p>
                            </div>
                            <span class="text-sm text-surface-500">{{ totalKarigars }} records</span>
                        </div>

                        <div class="p-4">
                            <DataTable :value="karigars" size="small" stripedRows rowHover tableStyle="min-width: 100%">
                                <template #empty>
                                    <div class="py-12 text-center text-surface-500">No gold currently issued to workers.</div>
                                </template>

                                <Column field="name" header="Karigar">
                                    <template #body="{ data }">
                                        <div class="flex flex-col">
                                            <span class="font-medium text-surface-900">{{ data.name }}</span>
                                            <span class="text-xs text-surface-500">{{ data.phone }}</span>
                                        </div>
                                    </template>
                                </Column>

                                <Column field="gold_due" header="Gold With Them">
                                    <template #body="{ data }">
                                        <span class="font-semibold text-surface-900"> {{ Number(data.gold_due || 0).toFixed(3) }} g </span>
                                    </template>
                                </Column>

                                <Column header="Status">
                                    <template #body>
                                        <Tag value="Active" severity="warning" />
                                    </template>
                                </Column>

                                <Column header="">
                                    <template #body="{ data }">
                                        <Link :href="route('ledger.show', { type: 'karigars', id: data.id })">
                                            <Button icon="pi pi-arrow-right" text rounded />
                                        </Link>
                                    </template>
                                </Column>
                            </DataTable>
                        </div>
                    </div>

                    <div class="overflow-hidden border border-surface-200 bg-white xl:col-span-3">
                        <div class="border-b border-surface-200 px-5 py-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-base font-semibold text-surface-900">Recent Invoices</h3>
                                    <p class="mt-1 text-sm text-surface-500">Latest bills created in the shop.</p>
                                </div>
                                <Link :href="route('invoices.index')" class="text-sm font-medium text-amber-700 hover:text-amber-800">View all</Link>
                            </div>
                        </div>

                        <div class="space-y-3 p-4">
                            <template v-if="recent_invoices?.length">
                                <div v-for="invoice in recent_invoices" :key="invoice.id" class="border border-surface-200 px-4 py-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-surface-900">{{ invoice.invoice_number }}</p>
                                            <p class="mt-1 truncate text-xs text-surface-500">{{ invoice.customer_name }}</p>
                                        </div>

                                        <div class="text-right">
                                            <p class="text-sm font-semibold text-surface-900">{{ formatCurrency(invoice.total_amount) }}</p>
                                            <p class="mt-1 text-xs text-surface-400">{{ invoice.date }}</p>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <div v-else class="py-12 text-center text-sm text-surface-500">No invoices recorded yet.</div>
                        </div>
                    </div>

                    <!-- Activity -->
                    <div class="overflow-hidden border border-surface-200 bg-white xl:col-span-3">
                        <div class="border-b border-surface-200 px-5 py-4">
                            <h3 class="text-base font-semibold text-surface-900">Recent Activity</h3>
                            <p class="mt-1 text-sm text-surface-500">Latest money movement and ledger activity</p>
                        </div>

                        <div class="max-h-[520px] space-y-3 overflow-y-auto p-4">
                            <template v-if="activities?.length">
                                <div v-for="act in activities" :key="act.id" class="border border-surface-200 p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-surface-900">
                                                {{ act.desc }}
                                            </p>
                                            <p class="mt-1 text-xs text-surface-500">By {{ act.user }}</p>
                                        </div>

                                        <div class="text-right">
                                            <p class="text-sm font-semibold" :class="act.type === 'RECEIPT' ? 'text-green-600' : 'text-red-600'">
                                                {{ act.type === 'RECEIPT' ? '+' : '-' }}
                                                {{ formatCurrency(act.amount) }}
                                            </p>
                                            <p class="mt-1 text-xs text-surface-400">{{ act.time }}</p>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <div v-else class="py-12 text-center text-sm text-surface-500">No transactions yet today.</div>
                        </div>

                        <div class="border-t border-surface-200 p-4">
                            <Link :href="route('customers.index')" class="block">
                                <Button label="Open Customers" text class="w-full justify-center" />
                            </Link>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                    <div class="overflow-hidden border border-surface-200 bg-white">
                        <div class="border-b border-surface-200 px-5 py-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-base font-semibold text-surface-900">Recent Expenses</h3>
                                    <p class="mt-1 text-sm text-surface-500">Latest outgoing shop expenses.</p>
                                </div>
                                <Button label="Add Expense" text size="small" @click="openExpenseDialog" />
                            </div>
                        </div>

                        <div class="space-y-3 p-4">
                            <template v-if="recent_expenses?.length">
                                <div v-for="expense in recent_expenses" :key="expense.id" class="border border-surface-200 px-4 py-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-surface-900">{{ expense.title }}</p>
                                            <p class="mt-1 text-xs text-surface-500">{{ expense.category }} • {{ expense.user }}</p>
                                        </div>

                                        <div class="text-right">
                                            <p class="text-sm font-semibold text-red-600">-{{ formatCurrency(expense.amount) }}</p>
                                            <p class="mt-1 text-xs text-surface-400">{{ expense.time }}</p>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <div v-else class="py-12 text-center text-sm text-surface-500">No expenses recorded yet.</div>
                        </div>
                    </div>

                    <div class="overflow-hidden border border-surface-200 bg-white">
                        <div class="border-b border-surface-200 px-5 py-4">
                            <h3 class="text-base font-semibold text-surface-900">Vault Movement History</h3>
                            <p class="mt-1 text-sm text-surface-500">Latest balance changes across cash, bank, gold, and silver.</p>
                        </div>

                        <div class="p-4">
                            <DataTable :value="recent_vault_movements" size="small" stripedRows rowHover tableStyle="min-width: 100%">
                                <template #empty>
                                    <div class="py-12 text-center text-sm text-surface-500">No vault movements recorded yet.</div>
                                </template>

                                <Column field="vault_type" header="Vault" style="width: 90px">
                                    <template #body="{ data }">
                                        <Tag :value="data.vault_type" :severity="data.vault_type === 'GOLD' ? 'warn' : data.vault_type === 'BANK' ? 'info' : 'secondary'" />
                                    </template>
                                </Column>

                                <Column header="Movement">
                                    <template #body="{ data }">
                                        <div>
                                            <p class="text-sm font-medium" :class="data.direction === 'CREDIT' ? 'text-green-700' : 'text-red-600'">
                                                {{ data.direction === 'CREDIT' ? '+' : '-' }} {{ formatVaultMovementAmount(data) }}
                                            </p>
                                            <p class="mt-1 text-xs text-surface-500">{{ data.note }}</p>
                                        </div>
                                    </template>
                                </Column>

                                <Column header="Balance After" style="width: 130px">
                                    <template #body="{ data }">
                                        <span class="text-sm font-semibold text-surface-900">{{ formatVaultMovementBalance(data) }}</span>
                                    </template>
                                </Column>

                                <Column header="When" style="width: 120px">
                                    <template #body="{ data }">
                                        <div class="text-right">
                                            <p class="text-xs text-surface-500">{{ data.time || 'Just now' }}</p>
                                            <p v-if="data.reference" class="mt-1 text-xs text-surface-400">{{ data.reference }}</p>
                                        </div>
                                    </template>
                                </Column>
                            </DataTable>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 xl:grid-cols-1">
                    <div class="overflow-hidden border border-surface-200 bg-white">
                        <div class="border-b border-surface-200 px-5 py-4">
                            <h3 class="text-base font-semibold text-surface-900">Market Rates</h3>
                            <p class="mt-1 text-sm text-surface-500">Today’s active rates used across billing and valuation.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-3">
                            <div class="border border-surface-200 bg-surface-50 px-4 py-4">
                                <p class="text-xs uppercase tracking-wide text-surface-500">Gold Sell</p>
                                <p class="mt-2 text-lg font-semibold text-surface-900">{{ formatCurrency(rates?.gold_sell) }}</p>
                            </div>
                            <div class="border border-surface-200 bg-surface-50 px-4 py-4">
                                <p class="text-xs uppercase tracking-wide text-surface-500">Gold Buy</p>
                                <p class="mt-2 text-lg font-semibold text-surface-900">{{ formatCurrency(rates?.gold_buy) }}</p>
                            </div>
                            <div class="border border-surface-200 bg-surface-50 px-4 py-4">
                                <p class="text-xs uppercase tracking-wide text-surface-500">Silver Sell</p>
                                <p class="mt-2 text-lg font-semibold text-surface-900">{{ formatCurrency(rates?.silver_sell) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Open Day -->
        <Dialog v-model:visible="showDayDialog" header="Start Day" modal :closable="false" class="w-full max-w-md">
            <div class="space-y-4 pt-2">
                <p class="text-sm text-surface-500">Record the counted opening balances before opening the business day.</p>

                <div class="border border-surface-200 bg-surface-50 px-4 py-3 text-sm text-surface-700">
                    This is a day-opening snapshot only. It does not add cash or gold into the live vault.
                </div>

                <div v-if="openingExpectation.date || openingExpectation.cash || openingExpectation.gold" class="border border-surface-200 bg-surface-50 px-4 py-3 text-sm text-surface-700">
                    <p class="font-medium text-surface-800">Expected from last closed day</p>
                    <div class="mt-1 flex items-center justify-between gap-4 text-xs text-surface-500">
                        <span>Cash: {{ formatCurrency(openingExpectation.cash) }}</span>
                        <span>Gold: {{ formatWeight(openingExpectation.gold) }}</span>
                    </div>
                    <p v-if="openingExpectation.date" class="mt-1 text-xs text-surface-400">Based on close of {{ openingExpectation.date }}</p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Counted Opening Cash</label>
                    <InputNumber v-model="dayForm.opening_cash" mode="currency" currency="INR" locale="en-IN" class="w-full" />
                    <small class="mt-1 block text-xs text-surface-500">Enter the physically counted opening cash. Zero is not allowed.</small>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Counted Opening Gold</label>
                    <InputNumber v-model="dayForm.opening_gold" :minFractionDigits="3" suffix=" g" class="w-full" />
                    <small class="mt-1 block text-xs text-surface-500">Enter the physically counted opening gold. Zero is not allowed.</small>
                </div>

                <div v-if="dayOpeningMismatch">
                    <label class="mb-2 block text-sm font-medium text-surface-700">Mismatch Reason</label>
                    <Textarea v-model="dayForm.mismatch_reason" rows="3" class="w-full" placeholder="Why does today's counted opening differ from the last closed day?" />
                    <small class="mt-1 block text-xs text-amber-700">Required because the counted opening differs from the previous closing balance.</small>
                </div>

                <div v-if="page.props.dayStatus?.has_register">
                    <label class="mb-2 block text-sm font-medium text-surface-700">Reopen Reason</label>
                    <Textarea v-model="dayForm.reopen_reason" rows="3" class="w-full" placeholder="Why are you reopening the shop day for the same date?" />
                    <small class="mt-1 block text-xs text-amber-700">Required because a register already exists for today.</small>
                </div>

                <Button label="Open Day" icon="pi pi-check" class="w-full" @click="openShop" :loading="dayForm.processing" />
            </div>
        </Dialog>

        <!-- Rates -->
        <Dialog v-model:visible="showRateDialog" header="Update Market Rates" modal class="w-full max-w-md">
            <div class="space-y-4 pt-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Gold Sell Rate</label>
                    <InputNumber v-model="rateForm.gold_sell" mode="currency" currency="INR" locale="en-IN" class="w-full" />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Gold Buy Rate</label>
                    <InputNumber v-model="rateForm.gold_buy" mode="currency" currency="INR" locale="en-IN" class="w-full" />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Silver Rate</label>
                    <InputNumber v-model="rateForm.silver_sell" mode="currency" currency="INR" locale="en-IN" class="w-full" />
                </div>

                <Button label="Save Rates" icon="pi pi-save" class="w-full" @click="saveRates" :loading="rateForm.processing" />
            </div>
        </Dialog>

        <!-- Close Day -->
        <Dialog v-model:visible="showCloseDialog" header="Close Day" modal class="w-full max-w-xl">
            <div class="space-y-5 pt-2">
                <!-- Intro -->
                <div class="border border-surface-200 bg-surface-50 px-4 py-3">
                    <p class="text-sm font-medium text-surface-800">End-of-day reconciliation</p>
                    <p class="mt-1 text-sm text-surface-500">Enter the physically counted cash and gold weight before closing the day.</p>
                </div>

                <!-- Expected Summary -->
                <div class="border border-surface-200 bg-white">
                    <div class="border-b border-surface-200 px-4 py-3">
                        <h4 class="text-sm font-semibold text-surface-900">System Summary</h4>
                    </div>

                    <div class="space-y-3 px-4 py-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-surface-500">Expected Cash</span>
                            <span class="text-sm font-semibold text-surface-900">
                                {{ formatCurrency(vaults.cash) }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-sm text-surface-500">Expected Gold</span>
                            <span class="text-sm font-semibold text-surface-900"> {{ Number(vaults.gold || 0).toFixed(3) }} g </span>
                        </div>
                    </div>
                </div>

                <!-- Inputs -->
                <div class="space-y-4">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700"> Closing Cash </label>
                        <InputNumber v-model="closeForm.closing_cash" mode="currency" currency="INR" locale="en-IN" class="w-full" placeholder="Enter counted cash" />
                        <small class="mt-1 block text-xs text-surface-400"> Count physical cash available in drawer and safe. </small>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700"> Closing Gold </label>
                        <InputNumber v-model="closeForm.closing_gold" :minFractionDigits="3" suffix=" g" class="w-full" placeholder="Enter weighed gold" />
                        <small class="mt-1 block text-xs text-surface-400"> Enter total physical gold available at day close. </small>
                    </div>
                </div>

                <div class="border border-surface-200 bg-surface-50">
                    <div class="border-b border-surface-200 px-4 py-3">
                        <h4 class="text-sm font-semibold text-surface-900">Live Reconciliation</h4>
                    </div>

                    <div class="space-y-3 px-4 py-4">
                        <div class="grid grid-cols-3 gap-3 text-sm">
                            <span class="text-surface-500">Cash</span>
                            <span class="font-medium text-surface-900">{{ closeForm.closing_cash == null ? '—' : formatCurrency(closeForm.closing_cash) }}</span>
                            <span
                                class="text-right font-semibold"
                                :class="
                                    closingCashDifference === null
                                        ? 'text-surface-400'
                                        : closingCashDifference === 0
                                          ? 'text-green-600'
                                          : closingCashDifference > 0
                                            ? 'text-orange-600'
                                            : 'text-red-600'
                                "
                            >
                                {{
                                    closingCashDifference === null
                                        ? 'Enter count'
                                        : `${closingCashDifference >= 0 ? '+' : ''}${formatCurrency(closingCashDifference)}`
                                }}
                            </span>
                        </div>

                        <div class="grid grid-cols-3 gap-3 text-sm">
                            <span class="text-surface-500">Gold</span>
                            <span class="font-medium text-surface-900">{{ closeForm.closing_gold == null ? '—' : formatWeight(closeForm.closing_gold) }}</span>
                            <span
                                class="text-right font-semibold"
                                :class="
                                    closingGoldDifference === null
                                        ? 'text-surface-400'
                                        : closingGoldDifference === 0
                                          ? 'text-green-600'
                                          : closingGoldDifference > 0
                                            ? 'text-orange-600'
                                            : 'text-red-600'
                                "
                            >
                                {{
                                    closingGoldDifference === null
                                        ? 'Enter count'
                                        : `${closingGoldDifference >= 0 ? '+' : ''}${formatWeight(closingGoldDifference)}`
                                }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Warning -->
                <div class="border border-red-200 bg-red-50 px-4 py-3">
                    <p class="text-sm font-medium text-red-700">Important</p>
                    <p class="mt-1 text-sm text-red-600">Once the day is closed, new transactions should not be added without reopening or admin correction.</p>
                </div>

                <!-- Action -->
                <div class="pt-1">
                    <Button label="Close Day & Save" icon="pi pi-lock" severity="danger" class="w-full" @click="closeShop" :loading="closeForm.processing" />
                </div>
            </div>
        </Dialog>

        <Dialog v-model:visible="showExpenseDialog" header="Add Expense" modal class="w-full max-w-md">
            <div class="space-y-4 pt-2">
                <div class="border border-surface-200 bg-white px-4 py-3 text-sm text-surface-700">
                    Available in {{ expenseSourceVaultLabel }}:
                    <span class="font-semibold text-surface-900">{{ formatCurrency(expenseSourceBalance) }}</span>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Expense Title</label>
                    <InputText v-model="expenseForm.title" placeholder="Tea, courier, repair, salary..." class="w-full" />
                    <small v-if="expenseForm.errors.title" class="mt-1 block text-red-600">{{ expenseForm.errors.title }}</small>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Category</label>
                        <Select v-model="expenseForm.category" :options="expenseCategories" class="w-full" />
                        <small v-if="expenseForm.errors.category" class="mt-1 block text-red-600">{{ expenseForm.errors.category }}</small>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Payment Method</label>
                        <Select v-model="expenseForm.payment_method" :options="expenseMethods" class="w-full" />
                        <small v-if="expenseForm.errors.payment_method" class="mt-1 block text-red-600">{{ expenseForm.errors.payment_method }}</small>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Amount</label>
                    <InputNumber v-model="expenseForm.amount" mode="currency" currency="INR" locale="en-IN" class="w-full" />
                    <small v-if="expenseForm.errors.amount" class="mt-1 block text-red-600">{{ expenseForm.errors.amount }}</small>
                    <small v-else-if="expenseAmountExceedsBalance" class="mt-1 block text-red-600">
                        Amount cannot be more than {{ formatCurrency(expenseSourceBalance) }}.
                    </small>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Date</label>
                    <Calendar v-model="expenseForm.date" showIcon dateFormat="yy-mm-dd" class="w-full" inputClass="w-full" />
                    <small v-if="expenseForm.errors.date" class="mt-1 block text-red-600">{{ expenseForm.errors.date }}</small>
                </div>

                <Button label="Save Expense" icon="pi pi-check" severity="danger" class="w-full" @click="saveExpense" :loading="expenseForm.processing" :disabled="!expenseCanSubmit" />
            </div>
        </Dialog>

        <Dialog v-model:visible="showVaultTransferDialog" header="Transfer Funds" modal class="w-full max-w-md">
            <div class="space-y-4 pt-2">
                <div class="border border-surface-200 bg-surface-50 px-4 py-3 text-sm text-surface-700">
                    Move money between cash in hand and bank without affecting customer, supplier, or karigar ledgers.
                </div>

                <div class="border border-surface-200 bg-white px-4 py-3 text-sm text-surface-700">
                    Available in {{ vaultLabels[vaultTransferForm.from_vault] || 'source vault' }}:
                    <span class="font-semibold text-surface-900">{{ formatCurrency(transferSourceBalance) }}</span>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">From</label>
                        <Select v-model="vaultTransferForm.from_vault" :options="vaultTransferOptions" optionLabel="label" optionValue="value" class="w-full" />
                        <small v-if="vaultTransferForm.errors.from_vault" class="mt-1 block text-red-600">{{ vaultTransferForm.errors.from_vault }}</small>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">To</label>
                        <Select v-model="vaultTransferForm.to_vault" :options="vaultTransferOptions" optionLabel="label" optionValue="value" class="w-full" />
                        <small v-if="vaultTransferForm.errors.to_vault" class="mt-1 block text-red-600">{{ vaultTransferForm.errors.to_vault }}</small>
                        <small v-else-if="vaultTransferForm.from_vault === vaultTransferForm.to_vault" class="mt-1 block text-red-600">Choose a different destination vault.</small>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Amount</label>
                    <InputNumber v-model="vaultTransferForm.amount" mode="currency" currency="INR" locale="en-IN" class="w-full" />
                    <small v-if="vaultTransferForm.errors.amount" class="mt-1 block text-red-600">{{ vaultTransferForm.errors.amount }}</small>
                    <small v-else-if="transferAmountExceedsBalance" class="mt-1 block text-red-600">
                        Amount cannot be more than {{ formatCurrency(transferSourceBalance) }}.
                    </small>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Date</label>
                    <Calendar v-model="vaultTransferForm.date" showIcon dateFormat="yy-mm-dd" class="w-full" inputClass="w-full" />
                    <small v-if="vaultTransferForm.errors.date" class="mt-1 block text-red-600">{{ vaultTransferForm.errors.date }}</small>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Note</label>
                    <InputText v-model="vaultTransferForm.note" class="w-full" placeholder="Cash deposited in bank, cash withdrawn for payout..." />
                    <small v-if="vaultTransferForm.errors.note" class="mt-1 block text-red-600">{{ vaultTransferForm.errors.note }}</small>
                </div>

                <Button label="Save Transfer" icon="pi pi-check" class="w-full" @click="saveVaultTransfer" :loading="vaultTransferForm.processing" :disabled="!transferCanSubmit" />
            </div>
        </Dialog>
    </AppLayout>
</template>
