<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Chart from 'primevue/chart';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { formatIndianDate } from '@/utils/indiaTime';

const props = defineProps({
    rates: Object,
    vaults: Object,
    isDayOpen: Boolean,
    metrics: Object,
    analytics: Object,
    karigars: Array,
    activities: Array,
    recent_vault_movements: Array,
    recent_invoices: Array,
    recent_expenses: Array,
    opening_expectation: Object,
    customer_reminders: Array,
});

const page = usePage();
const can = computed(() => page.props.auth?.can || {});
const isInitialSetup = computed(() => Boolean(page.props.dayStatus?.is_initial_setup));
const openingExpectation = computed(() => props.opening_expectation || { cash: 0, gold: 0, silver: 0, date: null });

const rateForm = useForm({
    gold_sell: parseFloat(props.rates?.gold_sell || 0),
    gold_buy: parseFloat(props.rates?.gold_buy || 0),
    silver_sell: parseFloat(props.rates?.silver_sell || 0),
});

const dayForm = useForm({
    opening_cash: parseFloat(openingExpectation.value?.cash || 0),
    opening_gold: parseFloat(openingExpectation.value?.gold || 0),
    opening_silver: parseFloat(openingExpectation.value?.silver || 0),
    mismatch_reason: '',
    reopen_reason: '',
});

const dayOpeningMismatch = computed(() => {
    return !isInitialSetup.value && (
        Math.abs(Number(dayForm.opening_cash || 0) - Number(openingExpectation.value?.cash || 0)) > 0.0001 ||
        Math.abs(Number(dayForm.opening_gold || 0) - Number(openingExpectation.value?.gold || 0)) > 0.0001 ||
        Math.abs(Number(dayForm.opening_silver || 0) - Number(openingExpectation.value?.silver || 0)) > 0.0001
    );
});

const closeForm = useForm({
    closing_cash: null,
    closing_gold: null,
    closing_silver: null,
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

const chartRange = ref('7D');

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
    SILVER: 'Silver Vault',
    GOLD: 'Gold Vault',
};

const quickLinks = [
    { label: 'New Bill', href: route('invoices.create'), icon: 'pi pi-file-edit' },
    { label: 'Orders', href: route('orders.index'), icon: 'pi pi-briefcase' },
    { label: 'Customers', href: route('customers.index'), icon: 'pi pi-users' },
    { label: 'Products', href: route('products.index'), icon: 'pi pi-box' },
    { label: 'Suppliers', href: route('suppliers.index'), icon: 'pi pi-truck' },
    { label: 'Expenses', href: route('expenses.index'), icon: 'pi pi-wallet' },
];

const formatCurrency = (val) =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 0,
    }).format(val || 0);

const formatWeight = (val) => `${Number(val || 0).toFixed(3)} g`;

const formatReminderDate = (val) =>
    formatIndianDate(val, {
        day: 'numeric',
        month: 'short',
    });

const formatVaultMovementAmount = (movement) => {
    return ['GOLD', 'SILVER'].includes(movement.vault_type) ? formatWeight(movement.amount) : formatCurrency(movement.amount);
};

const formatVaultMovementBalance = (movement) => {
    return ['GOLD', 'SILVER'].includes(movement.vault_type) ? formatWeight(movement.balance_after) : formatCurrency(movement.balance_after);
};

const goldSellRate = computed(() => Number(props.rates?.gold_sell || 0));
const goldBuyRate = computed(() => Number(props.rates?.gold_buy || 0));
const silverSellRate = computed(() => Number(props.rates?.silver_sell || 0));
const gold22kRate = computed(() => Math.round(goldSellRate.value * (22 / 24)));

const goldValuation = computed(() => props.analytics?.valuations?.gold_value || (Number(props.vaults?.gold || 0) * goldSellRate.value));
const silverValuation = computed(() => props.analytics?.valuations?.silver_value || (Number(props.vaults?.silver || 0) * silverSellRate.value));
const liquidCashTotal = computed(() => Number(props.vaults?.cash || 0) + Number(props.vaults?.bank || 0));

const closingCashDifference = computed(() => {
    if (closeForm.closing_cash === null || closeForm.closing_cash === undefined) return null;
    return Number(closeForm.closing_cash || 0) - Number(props.vaults?.cash || 0);
});

const closingGoldDifference = computed(() => {
    if (closeForm.closing_gold === null || closeForm.closing_gold === undefined) return null;
    return Number(closeForm.closing_gold || 0) - Number(props.vaults?.gold || 0);
});

const closingSilverDifference = computed(() => {
    if (closeForm.closing_silver === null || closeForm.closing_silver === undefined) return null;
    return Number(closeForm.closing_silver || 0) - Number(props.vaults?.silver || 0);
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

const openCloseDialog = () => {
    closeForm.clearErrors();
    showCloseDialog.value = true;
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

const sendWhatsAppWish = (reminder) => {
    const cleanMobile = String(reminder.mobile || '').replace(/\D/g, '');
    const phone = cleanMobile.startsWith('91') ? cleanMobile : `91${cleanMobile}`;
    const greeting = reminder.type === 'Birthday' ? 'Happy Birthday' : 'Happy Anniversary';
    const text = encodeURIComponent(
        `Dear ${reminder.customer_name},\n\nWarmest wishes on your ${greeting} from all of us at Maniratn Jewellers! ✨\n\nVisit us to explore our latest fine jewellery collection!\n\nWarm regards,\nManiratn Jewellers`
    );
    window.open(`https://wa.me/${phone}?text=${text}`, '_blank');
};

// ----------------------------------------------------
// CHART CONFIGURATIONS (Chart.js via PrimeVue)
// ----------------------------------------------------

const filteredSalesData = computed(() => {
    const raw = props.analytics?.sales_trend || [];
    const count = chartRange.value === '7D' ? 7 : chartRange.value === '14D' ? 14 : 30;
    return raw.slice(-count);
});

// 1. Sales & Collections Line Chart
const salesChartData = computed(() => {
    const items = filteredSalesData.value;
    const labels = items.map((i) => (chartRange.value === '30D' ? i.label : `${i.short_label} ${i.label.split(' ')[0]}`));
    const sales = items.map((i) => i.sales);
    const collections = items.map((i) => i.collections);

    return {
        labels,
        datasets: [
            {
                label: 'Gross Sales',
                data: sales,
                borderColor: '#c4922a',
                backgroundColor: 'rgba(196, 146, 42, 0.08)',
                borderWidth: 2,
                fill: true,
                tension: 0.35,
                pointBackgroundColor: '#c4922a',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 3,
            },
            {
                label: 'Collections',
                data: collections,
                borderColor: '#059669',
                backgroundColor: 'rgba(5, 150, 105, 0.04)',
                borderWidth: 2,
                fill: true,
                tension: 0.35,
                pointBackgroundColor: '#059669',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 3,
            },
        ],
    };
});

const salesChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top',
            align: 'end',
            labels: {
                boxWidth: 10,
                boxHeight: 10,
                usePointStyle: true,
                pointStyle: 'circle',
                font: { size: 12 },
                color: '#475569',
            },
        },
        tooltip: {
            padding: 8,
            callbacks: {
                label: (ctx) => ` ${ctx.dataset.label}: ${formatCurrency(ctx.parsed.y)}`,
            },
        },
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: { font: { size: 11 }, color: '#64748b' },
        },
        y: {
            grid: { color: 'rgba(226, 232, 240, 0.7)', borderDash: [3, 3] },
            ticks: {
                font: { size: 11 },
                color: '#64748b',
                callback: (val) => (val >= 100000 ? `₹${(val / 100000).toFixed(1)}L` : val >= 1000 ? `₹${(val / 1000).toFixed(0)}k` : `₹${val}`),
            },
        },
    },
};

// 2. Metal Mix Doughnut Chart
const metalDoughnutData = computed(() => {
    const goldWt = Number(props.analytics?.metal_mix?.gold_weight || 0);
    const silverWt = Number(props.analytics?.metal_mix?.silver_weight || 0);
    const total = goldWt + silverWt;

    const dataValues = total > 0 ? [goldWt, silverWt] : [75, 25];

    return {
        labels: ['Gold Jewellery', 'Silver Ornaments'],
        datasets: [
            {
                data: dataValues,
                backgroundColor: ['#d97706', '#94a3b8'],
                borderWidth: 2,
                borderColor: '#ffffff',
            },
        ],
    };
});

const metalDoughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '70%',
    plugins: {
        legend: {
            position: 'bottom',
            labels: {
                usePointStyle: true,
                pointStyle: 'circle',
                boxWidth: 8,
                font: { size: 11 },
                color: '#475569',
            },
        },
        tooltip: {
            padding: 8,
            callbacks: {
                label: (ctx) => ` ${ctx.label}: ${formatWeight(ctx.parsed)}`,
            },
        },
    },
};

// 3. Bullion Price History
const bullionChartData = computed(() => {
    const items = props.analytics?.bullion_trend || [];
    const labels = items.map((i) => i.label);
    const goldRates = items.map((i) => i.gold_sell);
    const silverRates = items.map((i) => i.silver_sell);

    return {
        labels,
        datasets: [
            {
                label: 'Gold 24K (₹/g)',
                data: goldRates,
                borderColor: '#d97706',
                borderWidth: 2,
                tension: 0.3,
                pointRadius: 2,
            },
            {
                label: 'Silver (₹/g)',
                data: silverRates,
                borderColor: '#64748b',
                borderWidth: 2,
                borderDash: [3, 3],
                tension: 0.3,
                pointRadius: 2,
            },
        ],
    };
});

const bullionChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top',
            align: 'end',
            labels: {
                usePointStyle: true,
                pointStyle: 'circle',
                boxWidth: 8,
                font: { size: 11 },
                color: '#475569',
            },
        },
        tooltip: {
            padding: 8,
            callbacks: {
                label: (ctx) => ` ${ctx.dataset.label}: ₹${Number(ctx.parsed.y).toLocaleString('en-IN')}`,
            },
        },
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: { font: { size: 10 }, color: '#64748b' },
        },
        y: {
            grid: { color: 'rgba(226, 232, 240, 0.7)', borderDash: [3, 3] },
            ticks: {
                font: { size: 10 },
                color: '#64748b',
                callback: (val) => `₹${val}`,
            },
        },
    },
};
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <!-- ========================================== -->
            <!-- 1. CLEAN STANDARD ERP HEADER              -->
            <!-- ========================================== -->
            <div class="border border-surface-200 bg-white px-5 py-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-3">
                            <h2 class="text-2xl font-semibold tracking-tight text-surface-900">Admin Dashboard</h2>
                            <Tag :value="isDayOpen ? 'Day Open' : 'Day Closed'" :severity="isDayOpen ? 'success' : 'danger'" />
                            <Tag v-if="activeAlerts" :value="`${activeAlerts} alert${activeAlerts > 1 ? 's' : ''}`" severity="warn" />
                        </div>
                        <p class="mt-1 text-sm text-surface-500">Live view of sales, bullion rates, production load, and shop balances.</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Button v-if="can.manage_expenses" label="Add Expense" icon="pi pi-minus-circle" severity="danger" outlined size="small" @click="openExpenseDialog" />
                        <Button v-if="can.manage_vault && isDayOpen" label="Transfer Funds" icon="pi pi-arrow-right-arrow-left" outlined size="small" @click="openVaultTransferDialog" />
                        <Button v-if="can.manage_daily_rates" label="Update Rates" icon="pi pi-pencil" outlined size="small" @click="showRateDialog = true" />
                        <Button v-if="can.manage_vault && !isDayOpen" label="Open Day" icon="pi pi-lock-open" size="small" @click="showDayDialog = true" />
                        <Button v-if="can.manage_vault && isDayOpen" label="Close Day" icon="pi pi-lock" severity="danger" size="small" @click="openCloseDialog" />
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 2. QUICK ACCESS & TODAY SNAPSHOT           -->
            <!-- ========================================== -->
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-4">
                <div class="border border-surface-200 bg-white p-5 xl:col-span-3">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-base font-semibold text-surface-900">Quick Access</h3>
                            <p class="mt-1 text-sm text-surface-500">Jump into billing, orders, customers, stock, and suppliers.</p>
                        </div>
                        <span class="text-xs uppercase tracking-wide text-surface-400">Shortcuts</span>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-6">
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
                            <span class="text-sm text-surface-500">Active Karigars</span>
                            <span class="font-semibold text-surface-900">{{ totalKarigars }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-surface-500">Gold (24K)</span>
                            <span class="font-semibold text-amber-700">₹{{ Number(rates?.gold_sell || 0).toLocaleString('en-IN') }}/g</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-surface-500">Silver</span>
                            <span class="font-semibold text-slate-700">₹{{ Number(rates?.silver_sell || 0).toLocaleString('en-IN') }}/g</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 3. KPI FINANCIAL SUMMARY CARDS             -->
            <!-- ========================================== -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="border border-surface-200 bg-white p-5">
                    <div class="flex items-center justify-between text-surface-500">
                        <span class="text-xs uppercase tracking-wide font-medium">Today Sales</span>
                        <i class="pi pi-receipt text-surface-400"></i>
                    </div>
                    <div class="mt-2 text-2xl font-bold text-surface-900">{{ formatCurrency(metrics?.today_sales) }}</div>
                    <div class="mt-1 text-xs text-surface-500">{{ recent_invoices?.length || 0 }} bills generated</div>
                </div>

                <div class="border border-surface-200 bg-white p-5">
                    <div class="flex items-center justify-between text-surface-500">
                        <span class="text-xs uppercase tracking-wide font-medium">Today Collections</span>
                        <i class="pi pi-wallet text-surface-400"></i>
                    </div>
                    <div class="mt-2 text-2xl font-bold text-emerald-700">{{ formatCurrency(metrics?.today_collections) }}</div>
                    <div class="mt-1 text-xs text-surface-500">Cash, UPI & Bank</div>
                </div>

                <div class="border border-surface-200 bg-white p-5">
                    <div class="flex items-center justify-between text-surface-500">
                        <span class="text-xs uppercase tracking-wide font-medium">Gold in Safe</span>
                        <i class="pi pi-shield text-surface-400"></i>
                    </div>
                    <div class="mt-2 text-2xl font-bold text-amber-800">{{ formatWeight(vaults?.gold) }}</div>
                    <div class="mt-1 text-xs text-surface-500">Value: {{ formatCurrency(goldValuation) }}</div>
                </div>

                <div class="border border-surface-200 bg-white p-5">
                    <div class="flex items-center justify-between text-surface-500">
                        <span class="text-xs uppercase tracking-wide font-medium">Liquid Funds</span>
                        <i class="pi pi-building-columns text-surface-400"></i>
                    </div>
                    <div class="mt-2 text-2xl font-bold text-surface-900">{{ formatCurrency(liquidCashTotal) }}</div>
                    <div class="mt-1 text-xs text-surface-500">Cash: {{ formatCurrency(vaults?.cash) }} | Bank: {{ formatCurrency(vaults?.bank) }}</div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 4. MODERN INTERACTIVE CHARTS               -->
            <!-- ========================================== -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <!-- Sales & Collections Dynamics (2 Cols) -->
                <div class="border border-surface-200 bg-white p-5 lg:col-span-2">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-surface-900">Revenue & Collections Dynamics</h3>
                            <p class="mt-0.5 text-xs text-surface-500">Daily sales vs collections inflow.</p>
                        </div>
                        <div class="flex items-center gap-1">
                            <button
                                v-for="range in ['7D', '14D', '30D']"
                                :key="range"
                                class="rounded px-2.5 py-1 text-xs font-medium border transition"
                                :class="chartRange === range ? 'bg-surface-900 text-white border-surface-900' : 'bg-surface-50 text-surface-600 border-surface-200 hover:bg-surface-100'"
                                @click="chartRange = range"
                            >
                                {{ range }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 h-64 w-full">
                        <Chart type="line" :data="salesChartData" :options="salesChartOptions" class="h-full w-full" />
                    </div>
                </div>

                <!-- Sales Metal Mix (1 Col) -->
                <div class="border border-surface-200 bg-white p-5">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold text-surface-900">Sales Metal Mix</h3>
                        <span class="text-xs text-surface-400">Last 30 Days</span>
                    </div>
                    <p class="mt-0.5 text-xs text-surface-500">Volume proportion of Gold vs Silver sold.</p>

                    <div class="relative mt-4 flex h-52 items-center justify-center">
                        <Chart type="doughnut" :data="metalDoughnutData" :options="metalDoughnutOptions" class="h-full w-full" />
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 5. BULLION RATE TREND & WORKSHOP PIPELINE  -->
            <!-- ========================================== -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <!-- 14-Day Bullion Trend -->
                <div class="border border-surface-200 bg-white p-5">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold text-surface-900">Bullion Price Trend</h3>
                        <span class="text-xs text-surface-400">14 Days</span>
                    </div>
                    <p class="mt-0.5 text-xs text-surface-500">Daily gold & silver market rate changes.</p>

                    <div class="mt-4 h-52 w-full">
                        <Chart type="line" :data="bullionChartData" :options="bullionChartOptions" class="h-full w-full" />
                    </div>
                </div>

                <!-- Workshop & Orders Pipeline -->
                <div class="border border-surface-200 bg-white p-5 lg:col-span-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-surface-900">Workshop & Production Load</h3>
                            <p class="mt-0.5 text-sm text-surface-500">Order pipeline stages and delivery status.</p>
                        </div>
                        <Link :href="route('orders.index')">
                            <Button label="All Orders" icon="pi pi-arrow-right" iconPos="right" text size="small" />
                        </Link>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="border border-surface-200 bg-surface-50 p-3 text-center">
                            <div class="text-xs uppercase text-surface-500 font-medium">New</div>
                            <div class="mt-1 text-xl font-bold text-surface-900">{{ metrics?.new_orders || 0 }}</div>
                        </div>
                        <div class="border border-amber-200 bg-amber-50/50 p-3 text-center">
                            <div class="text-xs uppercase text-amber-700 font-medium">With Karigars</div>
                            <div class="mt-1 text-xl font-bold text-amber-800">{{ metrics?.in_production || 0 }}</div>
                        </div>
                        <div class="border border-emerald-200 bg-emerald-50/50 p-3 text-center">
                            <div class="text-xs uppercase text-emerald-700 font-medium">Ready</div>
                            <div class="mt-1 text-xl font-bold text-emerald-800">{{ metrics?.ready_items || 0 }}</div>
                        </div>
                        <div class="border border-rose-200 bg-rose-50/50 p-3 text-center">
                            <div class="text-xs uppercase text-rose-700 font-medium">Overdue</div>
                            <div class="mt-1 text-xl font-bold text-rose-800">{{ metrics?.overdue_items || 0 }}</div>
                        </div>
                    </div>

                    <!-- Karigars Holding Gold -->
                    <div class="mt-4 border-t border-surface-100 pt-3">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-semibold text-surface-700">Karigars Holding Store Gold ({{ karigars?.length || 0 }})</span>
                            <Link :href="route('karigars.index')" class="text-amber-700 hover:underline">
                                Karigar Ledger &rarr;
                            </Link>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <div v-for="k in (karigars || []).slice(0, 6)" :key="k.id" class="flex items-center gap-2 border border-surface-200 bg-surface-50 px-2.5 py-1 text-xs">
                                <span class="text-surface-700">{{ k.name }}</span>
                                <span class="font-semibold text-amber-800">{{ formatWeight(k.gold_due) }}</span>
                            </div>
                            <div v-if="!karigars?.length" class="text-xs text-surface-400">
                                Zero gold balance held with karigars.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 6. RECENT INVOICES & CRM CELEBRATIONS      -->
            <!-- ========================================== -->
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <!-- Recent Invoices (2 Cols) -->
                <div class="border border-surface-200 bg-white p-5 xl:col-span-2">
                    <div class="flex items-center justify-between border-b border-surface-100 pb-3">
                        <div>
                            <h3 class="text-base font-semibold text-surface-900">Recent Invoices</h3>
                            <p class="mt-0.5 text-xs text-surface-500">Latest retail bills generated at the store.</p>
                        </div>
                        <Link :href="route('invoices.index')">
                            <Button label="View All" icon="pi pi-arrow-right" iconPos="right" text size="small" />
                        </Link>
                    </div>

                    <div class="mt-3 overflow-x-auto">
                        <DataTable :value="recent_invoices" class="p-datatable-sm" responsiveLayout="scroll">
                            <Column field="invoice_number" header="Invoice #">
                                <template #body="{ data }">
                                    <span class="font-mono text-xs font-medium text-surface-900">{{ data.invoice_number }}</span>
                                </template>
                            </Column>
                            <Column field="customer_name" header="Customer">
                                <template #body="{ data }">
                                    <span class="text-sm text-surface-800">{{ data.customer_name }}</span>
                                </template>
                            </Column>
                            <Column field="date" header="Date">
                                <template #body="{ data }">
                                    <span class="text-xs text-surface-500">{{ formatReminderDate(data.date) }}</span>
                                </template>
                            </Column>
                            <Column field="total_amount" header="Total" class="text-right">
                                <template #body="{ data }">
                                    <span class="font-semibold text-surface-900">{{ formatCurrency(data.total_amount) }}</span>
                                </template>
                            </Column>
                            <Column header="Action" class="text-right">
                                <template #body="{ data }">
                                    <a :href="route('invoices.print', data.id)" target="_blank" class="inline-flex items-center gap-1 rounded border border-surface-200 bg-surface-50 px-2 py-0.5 text-xs font-medium text-surface-700 hover:bg-surface-100">
                                        <i class="pi pi-print text-[10px]"></i>
                                        Print
                                    </a>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>

                <!-- Customer CRM Reminders (1 Col) -->
                <div class="border border-surface-200 bg-white p-5">
                    <div class="flex items-center justify-between border-b border-surface-100 pb-3">
                        <div>
                            <h3 class="text-base font-semibold text-surface-900">CRM Celebrations</h3>
                            <p class="mt-0.5 text-xs text-surface-500">Birthdays & Anniversaries (Next 7 Days)</p>
                        </div>
                        <Tag :value="`${customer_reminders?.length || 0}`" severity="info" />
                    </div>

                    <div class="mt-3 space-y-2.5">
                        <div
                            v-for="r in (customer_reminders || []).slice(0, 5)"
                            :key="`${r.customer_id}-${r.type}`"
                            class="flex items-center justify-between border p-2.5 transition"
                            :class="r.is_today ? 'bg-amber-50/70 border-amber-300' : 'bg-surface-50 border-surface-200'"
                        >
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <span class="truncate text-xs font-semibold text-surface-900">{{ r.customer_name }}</span>
                                    <Tag :value="r.is_today ? 'Today!' : r.type" :severity="r.is_today ? 'warn' : 'secondary'" class="!text-[10px]" />
                                </div>
                                <div class="mt-0.5 text-[11px] text-surface-500">
                                    {{ r.mobile }} &bull; {{ formatReminderDate(r.date) }}
                                </div>
                            </div>

                            <Button
                                icon="pi pi-whatsapp"
                                size="small"
                                class="!h-7 !w-7 !rounded-full !bg-emerald-600 !p-0 !text-white hover:!bg-emerald-700"
                                title="Send WhatsApp Greeting"
                                @click="sendWhatsAppWish(r)"
                            />
                        </div>

                        <div v-if="!customer_reminders?.length" class="py-6 text-center text-xs text-surface-400">
                            No upcoming birthdays or anniversaries this week.
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 7. RECENT VAULT MOVEMENTS                  -->
            <!-- ========================================== -->
            <div class="border border-surface-200 bg-white p-5">
                <div class="flex items-center justify-between border-b border-surface-100 pb-3">
                    <div>
                        <h3 class="text-base font-semibold text-surface-900">Recent Safe & Drawer Vault Movements</h3>
                        <p class="mt-0.5 text-xs text-surface-500">Audit trail of cash, bank, gold, and silver transfers.</p>
                    </div>
                    <Button
                        v-if="can.manage_vault && isDayOpen"
                        label="Transfer Funds"
                        icon="pi pi-arrow-right-arrow-left"
                        outlined
                        size="small"
                        @click="openVaultTransferDialog"
                    />
                </div>

                <div class="mt-3 overflow-x-auto">
                    <DataTable :value="recent_vault_movements" class="p-datatable-sm" responsiveLayout="scroll">
                        <Column field="vault_type" header="Vault Safe">
                            <template #body="{ data }">
                                <Tag :value="vaultLabels[data.vault_type] || data.vault_type" :severity="['GOLD', 'SILVER'].includes(data.vault_type) ? 'warn' : 'info'" />
                            </template>
                        </Column>
                        <Column field="direction" header="Flow">
                            <template #body="{ data }">
                                <span
                                    class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-xs font-semibold"
                                    :class="data.direction === 'IN' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
                                >
                                    <i :class="data.direction === 'IN' ? 'pi pi-arrow-down-left' : 'pi pi-arrow-up-right'" class="text-[10px]"></i>
                                    {{ data.direction }}
                                </span>
                            </template>
                        </Column>
                        <Column header="Amount" class="text-right">
                            <template #body="{ data }">
                                <span class="font-medium text-surface-900">{{ formatVaultMovementAmount(data) }}</span>
                            </template>
                        </Column>
                        <Column header="Balance After" class="text-right">
                            <template #body="{ data }">
                                <span class="text-xs text-surface-600">{{ formatVaultMovementBalance(data) }}</span>
                            </template>
                        </Column>
                        <Column field="note" header="Remarks">
                            <template #body="{ data }">
                                <span class="text-xs text-surface-600">{{ data.note || data.reference || '—' }}</span>
                            </template>
                        </Column>
                        <Column field="time" header="Time" class="text-right">
                            <template #body="{ data }">
                                <span class="text-xs text-surface-400">{{ data.time }}</span>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- DIALOGS & MODALS                           -->
        <!-- ========================================== -->

        <!-- 1. Update Daily Rates Dialog -->
        <Dialog v-model:visible="showRateDialog" modal header="Update Daily Market Rates" :style="{ width: '420px' }">
            <div class="space-y-4 pt-2">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-surface-700">Gold Sell Rate (24K fine per gram)</label>
                    <InputNumber v-model="rateForm.gold_sell" mode="currency" currency="INR" locale="en-IN" class="w-full" :min="0" />
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-surface-700">Gold Buy Rate (Old Gold per gram)</label>
                    <InputNumber v-model="rateForm.gold_buy" mode="currency" currency="INR" locale="en-IN" class="w-full" :min="0" />
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-surface-700">Silver Sell Rate (Per gram)</label>
                    <InputNumber v-model="rateForm.silver_sell" mode="currency" currency="INR" locale="en-IN" class="w-full" :min="0" />
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2 pt-3">
                    <Button label="Cancel" text severity="secondary" size="small" @click="showRateDialog = false" />
                    <Button label="Save Rates" icon="pi pi-check" size="small" :loading="rateForm.processing" @click="saveRates" />
                </div>
            </template>
        </Dialog>

        <!-- 2. Open Day Dialog -->
        <Dialog v-model:visible="showDayDialog" modal header="Open Store Day Register" :style="{ width: '460px' }">
            <div class="space-y-4 pt-2">
                <div class="rounded border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                    Verify cash and metal in safe drawers before opening the store for billing.
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-surface-700">Opening Cash in Hand</label>
                    <InputNumber v-model="dayForm.opening_cash" mode="currency" currency="INR" locale="en-IN" class="w-full" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-surface-700">Opening Gold in Safe (g)</label>
                    <InputNumber v-model="dayForm.opening_gold" :minFractionDigits="3" class="w-full" suffix=" g" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-surface-700">Opening Silver in Drawer (g)</label>
                    <InputNumber v-model="dayForm.opening_silver" :minFractionDigits="3" class="w-full" suffix=" g" />
                </div>

                <div v-if="dayOpeningMismatch">
                    <label class="mb-1 block text-xs font-semibold text-rose-700">Reason for Register Discrepancy</label>
                    <Textarea v-model="dayForm.mismatch_reason" rows="2" class="w-full" placeholder="Explain why physical count differs from expected closing..." />
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2 pt-3">
                    <Button label="Cancel" text severity="secondary" size="small" @click="showDayDialog = false" />
                    <Button label="Confirm & Open Day" icon="pi pi-lock-open" severity="success" size="small" :loading="dayForm.processing" @click="openShop" />
                </div>
            </template>
        </Dialog>

        <!-- 3. Close Day Dialog -->
        <Dialog v-model:visible="showCloseDialog" modal header="Close Daily Register" :style="{ width: '460px' }">
            <div class="space-y-4 pt-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-surface-700">Physical Closing Cash</label>
                    <InputNumber v-model="closeForm.closing_cash" mode="currency" currency="INR" locale="en-IN" class="w-full" />
                    <small v-if="closingCashDifference !== null" :class="closingCashDifference === 0 ? 'text-emerald-600' : 'text-rose-600'" class="block mt-1 font-semibold text-xs">
                        Variance: {{ formatCurrency(closingCashDifference) }}
                    </small>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-surface-700">Physical Closing Gold (g)</label>
                    <InputNumber v-model="closeForm.closing_gold" :minFractionDigits="3" class="w-full" suffix=" g" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-surface-700">Physical Closing Silver (g)</label>
                    <InputNumber v-model="closeForm.closing_silver" :minFractionDigits="3" class="w-full" suffix=" g" />
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2 pt-3">
                    <Button label="Cancel" text severity="secondary" size="small" @click="showCloseDialog = false" />
                    <Button label="Finalize & Close Day" icon="pi pi-lock" severity="danger" size="small" :loading="closeForm.processing" @click="closeShop" />
                </div>
            </template>
        </Dialog>

        <!-- 4. Add Expense Dialog -->
        <Dialog v-model:visible="showExpenseDialog" modal header="Record Store Expense" :style="{ width: '440px' }">
            <div class="space-y-4 pt-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-surface-700">Expense Title / Description</label>
                    <InputText v-model="expenseForm.title" class="w-full" placeholder="e.g. Workshop Snacks, Cleaning, Staff Lunch" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-surface-700">Category</label>
                        <Select v-model="expenseForm.category" :options="expenseCategories" class="w-full" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-surface-700">Payment Mode</label>
                        <Select v-model="expenseForm.payment_method" :options="expenseMethods" class="w-full" />
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-surface-700">Amount</label>
                    <InputNumber v-model="expenseForm.amount" mode="currency" currency="INR" locale="en-IN" class="w-full" :min="1" />
                    <small v-if="expenseAmountExceedsBalance" class="block mt-1 font-semibold text-xs text-rose-600">
                        Amount exceeds available {{ expenseSourceVaultLabel }} ({{ formatCurrency(expenseSourceBalance) }})
                    </small>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2 pt-3">
                    <Button label="Cancel" text severity="secondary" size="small" @click="showExpenseDialog = false" />
                    <Button label="Save Expense" icon="pi pi-check" severity="danger" size="small" :disabled="!expenseCanSubmit" :loading="expenseForm.processing" @click="saveExpense" />
                </div>
            </template>
        </Dialog>

        <!-- 5. Vault Transfer Dialog -->
        <Dialog v-model:visible="showVaultTransferDialog" modal header="Vault Funds Transfer" :style="{ width: '440px' }">
            <div class="space-y-4 pt-2">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-surface-700">From Account</label>
                        <Select v-model="vaultTransferForm.from_vault" :options="vaultTransferOptions" optionLabel="label" optionValue="value" class="w-full" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-surface-700">To Account</label>
                        <Select v-model="vaultTransferForm.to_vault" :options="vaultTransferOptions" optionLabel="label" optionValue="value" class="w-full" />
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-surface-700">Transfer Amount</label>
                    <InputNumber v-model="vaultTransferForm.amount" mode="currency" currency="INR" locale="en-IN" class="w-full" :min="1" />
                    <small v-if="transferAmountExceedsBalance" class="block mt-1 font-semibold text-xs text-rose-600">
                        Amount exceeds available source balance ({{ formatCurrency(transferSourceBalance) }})
                    </small>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-surface-700">Transfer Note / Reason</label>
                    <InputText v-model="vaultTransferForm.note" class="w-full" placeholder="e.g. Bank cash deposit, UPI settlement" />
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2 pt-3">
                    <Button label="Cancel" text severity="secondary" size="small" @click="showVaultTransferDialog = false" />
                    <Button label="Transfer Funds" icon="pi pi-arrow-right-arrow-left" size="small" :disabled="!transferCanSubmit" :loading="vaultTransferForm.processing" @click="saveVaultTransfer" />
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>
