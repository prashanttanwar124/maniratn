<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';

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

// Form state
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

// Dialog Visibility
const showRateDialog = ref(false);
const showDayDialog = ref(false);
const showCloseDialog = ref(false);
const showExpenseDialog = ref(false);
const showVaultTransferDialog = ref(false);

// Chart Time Range Filter (7D, 14D, 30D)
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
    { label: 'New Bill', href: route('invoices.create'), icon: 'pi pi-file-plus', color: 'text-amber-600 bg-amber-50 hover:bg-amber-100 border-amber-200' },
    { label: 'Custom Orders', href: route('orders.index'), icon: 'pi pi-briefcase', color: 'text-sky-600 bg-sky-50 hover:bg-sky-100 border-sky-200' },
    { label: 'Customer Vault', href: route('customers.index'), icon: 'pi pi-id-card', color: 'text-purple-600 bg-purple-50 hover:bg-purple-100 border-purple-200' },
    { label: 'Inventory Stock', href: route('products.index'), icon: 'pi pi-box', color: 'text-emerald-600 bg-emerald-50 hover:bg-emerald-100 border-emerald-200' },
    { label: 'Ledger Khata', href: route('ledger.index'), icon: 'pi pi-book', color: 'text-indigo-600 bg-indigo-50 hover:bg-indigo-100 border-indigo-200' },
    { label: 'Daily Expenses', href: route('expenses.index'), icon: 'pi pi-wallet', color: 'text-rose-600 bg-rose-50 hover:bg-rose-100 border-rose-200' },
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

// Math computations for live rate deltas
const goldSellRate = computed(() => Number(props.rates?.gold_sell || 0));
const goldBuyRate = computed(() => Number(props.rates?.gold_buy || 0));
const silverSellRate = computed(() => Number(props.rates?.silver_sell || 0));
const gold22kRate = computed(() => Math.round(goldSellRate.value * (22 / 24)));

// Physical valuations
const goldValuation = computed(() => props.analytics?.valuations?.gold_value || (Number(props.vaults?.gold || 0) * goldSellRate.value));
const silverValuation = computed(() => props.analytics?.valuations?.silver_value || (Number(props.vaults?.silver || 0) * silverSellRate.value));
const liquidCashTotal = computed(() => Number(props.vaults?.cash || 0) + Number(props.vaults?.bank || 0));

// Form calculations
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

// Dialog submissions
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

// Direct WhatsApp Wish Trigger
const sendWhatsAppWish = (reminder) => {
    const cleanMobile = String(reminder.mobile || '').replace(/\D/g, '');
    const phone = cleanMobile.startsWith('91') ? cleanMobile : `91${cleanMobile}`;
    const greeting = reminder.type === 'Birthday' ? 'Happy Birthday' : 'Happy Anniversary';
    const text = encodeURIComponent(
        `Dear ${reminder.customer_name},\n\nWarmest wishes on your ${greeting} from all of us at Maniratn Jewellers! ✨ May your day be blessed with joy, prosperity, and sparkling moments.\n\nVisit us to explore our latest fine jewellery collection!\n\nWarm regards,\nManiratn Jewellers`
    );
    window.open(`https://wa.me/${phone}?text=${text}`, '_blank');
};

// ----------------------------------------------------
// CHART CONFIGURATIONS (Chart.js via PrimeVue)
// ----------------------------------------------------

// Filtered Sales Trend Data based on chartRange ('7D', '14D', '30D')
const filteredSalesData = computed(() => {
    const raw = props.analytics?.sales_trend || [];
    const count = chartRange.value === '7D' ? 7 : chartRange.value === '14D' ? 14 : 30;
    return raw.slice(-count);
});

// 1. Sales & Cash Flow Area Chart
const salesChartData = computed(() => {
    const items = filteredSalesData.value;
    const labels = items.map((i) => (chartRange.value === '30D' ? i.label : `${i.short_label} ${i.label.split(' ')[0]}`));
    const sales = items.map((i) => i.sales);
    const collections = items.map((i) => i.collections);

    return {
        labels,
        datasets: [
            {
                label: 'Gross Sales (₹)',
                data: sales,
                borderColor: '#c4922a',
                backgroundColor: 'rgba(196, 146, 42, 0.12)',
                borderWidth: 2.5,
                fill: true,
                tension: 0.38,
                pointBackgroundColor: '#c4922a',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
            },
            {
                label: 'Collections (₹)',
                data: collections,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.08)',
                borderWidth: 2.5,
                fill: true,
                tension: 0.38,
                pointBackgroundColor: '#10b981',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
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
                boxWidth: 12,
                boxHeight: 12,
                usePointStyle: true,
                pointStyle: 'circle',
                font: { family: 'Outfit, Inter, sans-serif', size: 12, weight: '500' },
                color: '#475569',
            },
        },
        tooltip: {
            backgroundColor: '#1e1b18',
            titleFont: { family: 'Outfit, Inter, sans-serif', size: 12, weight: '600' },
            bodyFont: { family: 'Outfit, Inter, sans-serif', size: 12 },
            padding: 10,
            cornerRadius: 8,
            callbacks: {
                label: (ctx) => ` ${ctx.dataset.label}: ${formatCurrency(ctx.parsed.y)}`,
            },
        },
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: {
                font: { family: 'Outfit, Inter, sans-serif', size: 11 },
                color: '#64748b',
            },
        },
        y: {
            grid: { color: 'rgba(226, 232, 240, 0.6)', borderDash: [4, 4] },
            ticks: {
                font: { family: 'Outfit, Inter, sans-serif', size: 11 },
                color: '#64748b',
                callback: (val) => (val >= 100000 ? `₹${(val / 100000).toFixed(1)}L` : val >= 1000 ? `₹${(val / 1000).toFixed(0)}k` : `₹${val}`),
            },
        },
    },
};

// 2. Metal & Category Doughnut Chart
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
                backgroundColor: ['#d4af37', '#94a3b8'],
                hoverBackgroundColor: ['#b89428', '#64748b'],
                borderWidth: 2,
                borderColor: '#ffffff',
            },
        ],
    };
});

const metalDoughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '72%',
    plugins: {
        legend: {
            position: 'bottom',
            labels: {
                usePointStyle: true,
                pointStyle: 'circle',
                boxWidth: 10,
                font: { family: 'Outfit, Inter, sans-serif', size: 11, weight: '500' },
                color: '#475569',
            },
        },
        tooltip: {
            backgroundColor: '#1e1b18',
            padding: 10,
            cornerRadius: 8,
            callbacks: {
                label: (ctx) => ` ${ctx.label}: ${formatWeight(ctx.parsed)}`,
            },
        },
    },
};

// 3. 14-Day Bullion Rate Line Chart
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
                borderColor: '#b45309',
                backgroundColor: 'rgba(180, 83, 9, 0.05)',
                borderWidth: 2,
                tension: 0.35,
                yAxisID: 'yGold',
                pointRadius: 3,
            },
            {
                label: 'Silver (₹/g)',
                data: silverRates,
                borderColor: '#64748b',
                backgroundColor: 'rgba(100, 116, 139, 0.05)',
                borderWidth: 2,
                borderDash: [3, 3],
                tension: 0.35,
                yAxisID: 'ySilver',
                pointRadius: 3,
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
                boxWidth: 10,
                font: { family: 'Outfit, Inter, sans-serif', size: 11 },
                color: '#475569',
            },
        },
        tooltip: {
            backgroundColor: '#1e1b18',
            padding: 10,
            cornerRadius: 8,
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
        yGold: {
            type: 'linear',
            position: 'left',
            grid: { color: 'rgba(226, 232, 240, 0.6)' },
            ticks: {
                font: { size: 10 },
                color: '#b45309',
                callback: (val) => `₹${val}`,
            },
        },
        ySilver: {
            type: 'linear',
            position: 'right',
            grid: { drawOnChartArea: false },
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
        <div class="space-y-6 pb-12">
            <!-- ========================================== -->
            <!-- 1. LUXURY COMMAND HEADER & LIVE BULLION BAR -->
            <!-- ========================================== -->
            <div class="relative overflow-hidden rounded-2xl border border-amber-200/60 bg-gradient-to-r from-stone-900 via-[#3a0a0e] to-[#5b0d13] p-6 text-white shadow-xl">
                <!-- Background ambient glow -->
                <div class="pointer-events-none absolute -right-16 -top-16 h-64 w-64 rounded-full bg-amber-500/15 blur-3xl"></div>
                <div class="pointer-events-none absolute -left-16 -bottom-16 h-64 w-64 rounded-full bg-rose-500/10 blur-3xl"></div>

                <div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                    <!-- Title & Live Status -->
                    <div>
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-2xl font-bold tracking-tight text-white lg:text-3xl">Executive Dashboard</h1>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold tracking-wide"
                                :class="isDayOpen ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30'"
                            >
                                <span class="h-2 w-2 rounded-full" :class="isDayOpen ? 'bg-emerald-400 animate-pulse' : 'bg-rose-400'"></span>
                                {{ isDayOpen ? 'Store Register Open' : 'Register Closed' }}
                            </span>
                            <span v-if="activeAlerts" class="inline-flex items-center gap-1 rounded-full border border-amber-400/30 bg-amber-500/20 px-2.5 py-0.5 text-xs font-medium text-amber-200">
                                <i class="pi pi-bell text-[10px]"></i>
                                {{ activeAlerts }} Active Alert{{ activeAlerts > 1 ? 's' : '' }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-stone-300/80">
                            Live operational command center for jewellery retail, safe vaults, production pipeline, and ledger.
                        </p>
                    </div>

                    <!-- Live Bullion Rate Ribbon -->
                    <div class="flex flex-wrap items-center gap-2.5 rounded-xl border border-white/10 bg-white/5 p-2 backdrop-blur-md">
                        <!-- Gold 24K -->
                        <div class="flex items-center gap-2 rounded-lg bg-black/30 px-3 py-2">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500/20 text-amber-400 font-bold text-xs">
                                24K
                            </div>
                            <div>
                                <div class="text-[10px] uppercase tracking-wider text-amber-200/70">Gold (Fine)</div>
                                <div class="text-sm font-bold text-amber-300">₹{{ Number(rates?.gold_sell || 0).toLocaleString('en-IN') }} <span class="text-[10px] font-normal text-stone-300">/g</span></div>
                            </div>
                        </div>

                        <!-- Gold 22K (916) -->
                        <div class="flex items-center gap-2 rounded-lg bg-black/30 px-3 py-2">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-600/20 text-amber-300 font-bold text-xs">
                                916
                            </div>
                            <div>
                                <div class="text-[10px] uppercase tracking-wider text-amber-200/70">Gold (22K)</div>
                                <div class="text-sm font-bold text-amber-200">₹{{ Number(gold22kRate).toLocaleString('en-IN') }} <span class="text-[10px] font-normal text-stone-300">/g</span></div>
                            </div>
                        </div>

                        <!-- Silver -->
                        <div class="flex items-center gap-2 rounded-lg bg-black/30 px-3 py-2">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-500/20 text-slate-300 font-bold text-xs">
                                925
                            </div>
                            <div>
                                <div class="text-[10px] uppercase tracking-wider text-slate-300/70">Silver</div>
                                <div class="text-sm font-bold text-slate-200">₹{{ Number(rates?.silver_sell || 0).toLocaleString('en-IN') }} <span class="text-[10px] font-normal text-stone-300">/g</span></div>
                            </div>
                        </div>

                        <!-- Quick Action in Ribbon -->
                        <Button
                            v-if="can.manage_daily_rates"
                            icon="pi pi-pencil"
                            label="Update"
                            size="small"
                            class="!border-amber-400/40 !bg-amber-500/20 !text-amber-200 hover:!bg-amber-500/30"
                            @click="showRateDialog = true"
                        />
                    </div>
                </div>

                <!-- Quick Action Buttons Row -->
                <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <Link :href="route('invoices.create')">
                            <Button label="New Tax Bill" icon="pi pi-plus" class="!bg-gradient-to-r !from-amber-500 !to-amber-600 !text-stone-950 !font-semibold !border-none !shadow-lg shadow-amber-500/20" size="small" />
                        </Link>
                        <Link :href="route('orders.index')">
                            <Button label="Orders Pipeline" icon="pi pi-briefcase" class="!bg-white/10 !text-white !border-white/20 hover:!bg-white/20" size="small" />
                        </Link>
                        <Button v-if="can.manage_expenses" label="Record Expense" icon="pi pi-minus-circle" class="!bg-rose-500/20 !text-rose-200 !border-rose-400/30 hover:!bg-rose-500/30" size="small" @click="openExpenseDialog" />
                        <Button v-if="can.manage_vault && isDayOpen" label="Transfer Funds" icon="pi pi-arrows-h" class="!bg-sky-500/20 !text-sky-200 !border-sky-400/30 hover:!bg-sky-500/30" size="small" @click="openVaultTransferDialog" />
                    </div>

                    <div class="flex items-center gap-2">
                        <Button v-if="can.manage_vault && !isDayOpen" label="Open Daily Register" icon="pi pi-lock-open" class="!bg-emerald-500 !text-stone-950 !font-semibold !border-none" size="small" @click="showDayDialog = true" />
                        <Button v-if="can.manage_vault && isDayOpen" label="Close Day Register" icon="pi pi-lock" severity="danger" size="small" class="!border-rose-500/50" @click="openCloseDialog" />
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 2. CORE FINANCIAL & LIQUIDITY KPI CARDS -->
            <!-- ========================================== -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- KPI 1: Today Gross Sales -->
                <div class="group relative overflow-hidden rounded-2xl border border-surface-200 bg-white p-5 shadow-sm transition hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-surface-500">Today Gross Sales</span>
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                            <i class="pi pi-receipt text-lg"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-2xl font-bold tracking-tight text-surface-900">
                            {{ formatCurrency(metrics?.today_sales) }}
                        </div>
                        <div class="mt-1 flex items-center justify-between text-xs text-surface-500">
                            <span>{{ recent_invoices?.length || 0 }} bills generated today</span>
                            <span class="font-medium text-amber-600">Active POS</span>
                        </div>
                    </div>
                    <div class="mt-3 h-1 w-full overflow-hidden rounded-full bg-surface-100">
                        <div class="h-full bg-gradient-to-r from-amber-400 to-amber-600" style="width: 75%"></div>
                    </div>
                </div>

                <!-- KPI 2: Collections & Inflows -->
                <div class="group relative overflow-hidden rounded-2xl border border-surface-200 bg-white p-5 shadow-sm transition hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-surface-500">Today Collections</span>
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                            <i class="pi pi-wallet text-lg"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-2xl font-bold tracking-tight text-emerald-700">
                            {{ formatCurrency(metrics?.today_collections) }}
                        </div>
                        <div class="mt-1 flex items-center justify-between text-xs text-surface-500">
                            <span>Cash, UPI & Bank</span>
                            <span class="font-medium text-emerald-600">Settled</span>
                        </div>
                    </div>
                    <div class="mt-3 h-1 w-full overflow-hidden rounded-full bg-surface-100">
                        <div class="h-full bg-gradient-to-r from-emerald-400 to-emerald-600" style="width: 85%"></div>
                    </div>
                </div>

                <!-- KPI 3: Gold Vault Stock & Valuation -->
                <div class="group relative overflow-hidden rounded-2xl border border-surface-200 bg-white p-5 shadow-sm transition hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-surface-500">Main Gold Safe</span>
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500/10 text-amber-700">
                            <i class="pi pi-shield text-lg"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-2xl font-bold tracking-tight text-surface-900">
                            {{ formatWeight(vaults?.gold) }}
                        </div>
                        <div class="mt-1 flex items-center justify-between text-xs text-surface-500">
                            <span>Market Value:</span>
                            <span class="font-bold text-amber-700">{{ formatCurrency(goldValuation) }}</span>
                        </div>
                    </div>
                    <div class="mt-3 h-1 w-full overflow-hidden rounded-full bg-surface-100">
                        <div class="h-full bg-amber-500" style="width: 60%"></div>
                    </div>
                </div>

                <!-- KPI 4: Liquid Cash & Bank Position -->
                <div class="group relative overflow-hidden rounded-2xl border border-surface-200 bg-white p-5 shadow-sm transition hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-surface-500">Liquid Funds</span>
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-50 text-sky-600">
                            <i class="pi pi-building-columns text-lg"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-2xl font-bold tracking-tight text-surface-900">
                            {{ formatCurrency(liquidCashTotal) }}
                        </div>
                        <div class="mt-1 flex items-center justify-between text-xs text-surface-500">
                            <span>Cash: {{ formatCurrency(vaults?.cash) }}</span>
                            <span>Bank: {{ formatCurrency(vaults?.bank) }}</span>
                        </div>
                    </div>
                    <div class="mt-3 h-1 w-full overflow-hidden rounded-full bg-surface-100">
                        <div class="h-full bg-sky-500" style="width: 70%"></div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 3. INTERACTIVE ANALYTICS & CHARTS SECTION -->
            <!-- ========================================== -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Main Area/Line Chart: Sales & Cashflow Trend (2 Cols) -->
                <div class="rounded-2xl border border-surface-200 bg-white p-6 shadow-sm lg:col-span-2">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-base font-bold text-surface-900">Revenue & Collections Dynamics</h2>
                            <p class="text-xs text-surface-500">Track daily billing vs actual customer payment collections.</p>
                        </div>

                        <!-- Range Pills (7D / 14D / 30D) -->
                        <div class="flex items-center gap-1 rounded-xl bg-surface-100 p-1">
                            <button
                                v-for="range in ['7D', '14D', '30D']"
                                :key="range"
                                class="rounded-lg px-3 py-1 text-xs font-semibold transition"
                                :class="chartRange === range ? 'bg-white text-surface-900 shadow-sm' : 'text-surface-600 hover:text-surface-900'"
                                @click="chartRange = range"
                            >
                                {{ range }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-6 h-72 w-full">
                        <Chart type="line" :data="salesChartData" :options="salesChartOptions" class="h-full w-full" />
                    </div>
                </div>

                <!-- Secondary Doughnut Chart: Metal Mix & Category Share (1 Col) -->
                <div class="flex flex-col justify-between rounded-2xl border border-surface-200 bg-white p-6 shadow-sm">
                    <div>
                        <div class="flex items-center justify-between">
                            <h2 class="text-base font-bold text-surface-900">Sales Metal Mix</h2>
                            <Tag value="30 Days" severity="secondary" />
                        </div>
                        <p class="mt-0.5 text-xs text-surface-500">Volume proportion of Gold vs Silver sold.</p>

                        <div class="relative mt-4 flex h-48 items-center justify-center">
                            <Chart type="doughnut" :data="metalDoughnutData" :options="metalDoughnutOptions" class="h-full w-full" />
                            <div class="pointer-events-none absolute text-center">
                                <div class="text-xs font-medium uppercase text-surface-400">Total Wt</div>
                                <div class="text-base font-bold text-surface-900">
                                    {{ formatWeight(Number(analytics?.metal_mix?.gold_weight || 0) + Number(analytics?.metal_mix?.silver_weight || 0)) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Category Breakdown Chips -->
                    <div class="mt-4 border-t border-surface-100 pt-3">
                        <div class="text-xs font-semibold text-surface-700">Top Performing Categories</div>
                        <div class="mt-2 space-y-1.5">
                            <div v-for="cat in (analytics?.metal_mix?.top_categories || []).slice(0, 3)" :key="cat.label" class="flex items-center justify-between text-xs">
                                <span class="text-surface-600">{{ cat.label }}</span>
                                <span class="font-semibold text-surface-900">{{ formatCurrency(cat.amount) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 4. BULLION MOVEMENT & PRODUCTION PIPELINE -->
            <!-- ========================================== -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Bullion 14-Day Price Movement (1 Col) -->
                <div class="rounded-2xl border border-surface-200 bg-white p-6 shadow-sm lg:col-span-1">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-bold text-surface-900">Bullion Price Trend</h2>
                            <p class="text-xs text-surface-500">14-day Gold & Silver daily rates.</p>
                        </div>
                        <i class="pi pi-chart-line text-amber-600"></i>
                    </div>

                    <div class="mt-4 h-56 w-full">
                        <Chart type="line" :data="bullionChartData" :options="bullionChartOptions" class="h-full w-full" />
                    </div>
                </div>

                <!-- Production & Custom Orders Pipeline (2 Cols) -->
                <div class="rounded-2xl border border-surface-200 bg-white p-6 shadow-sm lg:col-span-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-bold text-surface-900">Workshop & Production Pipeline</h2>
                            <p class="text-xs text-surface-500">Current order stages and customer delivery readiness.</p>
                        </div>
                        <Link :href="route('orders.index')">
                            <Button label="View All Orders" icon="pi pi-arrow-right" iconPos="right" text size="small" />
                        </Link>
                    </div>

                    <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <!-- Stage 1: New -->
                        <div class="rounded-xl border border-surface-200 bg-surface-50 p-4 text-center">
                            <div class="text-xs font-semibold uppercase tracking-wider text-surface-500">New Orders</div>
                            <div class="mt-2 text-2xl font-bold text-surface-800">{{ metrics?.new_orders || 0 }}</div>
                            <div class="mt-1 text-[11px] text-surface-500">Pending Assignment</div>
                        </div>

                        <!-- Stage 2: In Production -->
                        <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-4 text-center">
                            <div class="text-xs font-semibold uppercase tracking-wider text-amber-800">With Karigars</div>
                            <div class="mt-2 text-2xl font-bold text-amber-900">{{ metrics?.in_production || 0 }}</div>
                            <div class="mt-1 text-[11px] text-amber-700">In Benchwork</div>
                        </div>

                        <!-- Stage 3: Ready for Delivery -->
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 text-center">
                            <div class="text-xs font-semibold uppercase tracking-wider text-emerald-800">Ready for Pickup</div>
                            <div class="mt-2 text-2xl font-bold text-emerald-900">{{ metrics?.ready_items || 0 }}</div>
                            <div class="mt-1 text-[11px] text-emerald-700">Hallmarked & Polished</div>
                        </div>

                        <!-- Stage 4: Overdue -->
                        <div class="rounded-xl border border-rose-200 bg-rose-50/60 p-4 text-center">
                            <div class="text-xs font-semibold uppercase tracking-wider text-rose-800">Overdue</div>
                            <div class="mt-2 text-2xl font-bold text-rose-900">{{ metrics?.overdue_items || 0 }}</div>
                            <div class="mt-1 text-[11px] text-rose-700">Past Due Date</div>
                        </div>
                    </div>

                    <!-- Karigars Holding Gold Strip -->
                    <div class="mt-5 rounded-xl border border-surface-200 bg-stone-50 p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-users text-amber-600"></i>
                                <span class="text-xs font-bold text-surface-900">Karigars Holding Store Metal ({{ karigars?.length || 0 }})</span>
                            </div>
                            <Link :href="route('karigars.index')" class="text-xs font-semibold text-amber-700 hover:underline">
                                Karigar Ledger &rarr;
                            </Link>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <div v-for="k in (karigars || []).slice(0, 6)" :key="k.id" class="flex items-center gap-2 rounded-lg border border-surface-200 bg-white px-3 py-1.5 text-xs shadow-2xs">
                                <span class="font-medium text-surface-800">{{ k.name }}</span>
                                <span class="rounded bg-amber-100 px-1.5 py-0.5 font-bold text-amber-800">{{ formatWeight(k.gold_due) }}</span>
                            </div>
                            <div v-if="!karigars?.length" class="text-xs text-surface-500">
                                All karigar metal accounts balanced. Zero outstanding gold held.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 5. OPERATIONAL TABLES & CRM CELEBRATIONS -->
            <!-- ========================================== -->
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <!-- Recent Invoices & Live Billing (2 Cols) -->
                <div class="rounded-2xl border border-surface-200 bg-white p-6 shadow-sm xl:col-span-2">
                    <div class="flex items-center justify-between border-b border-surface-100 pb-4">
                        <div>
                            <h2 class="text-base font-bold text-surface-900">Recent Customer Bills</h2>
                            <p class="text-xs text-surface-500">Latest retail invoices generated from the counter.</p>
                        </div>
                        <Link :href="route('invoices.index')">
                            <Button label="View All Invoices" icon="pi pi-arrow-right" iconPos="right" text size="small" />
                        </Link>
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <DataTable :value="recent_invoices" class="p-datatable-sm" responsiveLayout="scroll">
                            <Column field="invoice_number" header="Invoice #">
                                <template #body="{ data }">
                                    <span class="font-mono text-xs font-semibold text-surface-900">{{ data.invoice_number }}</span>
                                </template>
                            </Column>
                            <Column field="customer_name" header="Customer">
                                <template #body="{ data }">
                                    <span class="font-medium text-surface-800">{{ data.customer_name }}</span>
                                </template>
                            </Column>
                            <Column field="date" header="Date">
                                <template #body="{ data }">
                                    <span class="text-xs text-surface-600">{{ formatReminderDate(data.date) }}</span>
                                </template>
                            </Column>
                            <Column field="total_amount" header="Total Amount" class="text-right">
                                <template #body="{ data }">
                                    <span class="font-bold text-surface-900">{{ formatCurrency(data.total_amount) }}</span>
                                </template>
                            </Column>
                            <Column header="Action" class="text-right">
                                <template #body="{ data }">
                                    <a :href="route('invoices.print', data.id)" target="_blank" class="inline-flex items-center gap-1 rounded-lg border border-surface-200 bg-surface-50 px-2.5 py-1 text-xs font-medium text-surface-700 hover:bg-surface-100">
                                        <i class="pi pi-print text-[11px]"></i>
                                        Print
                                    </a>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>

                <!-- Customer CRM Celebrations (1 Col) -->
                <div class="flex flex-col justify-between rounded-2xl border border-surface-200 bg-white p-6 shadow-sm">
                    <div>
                        <div class="flex items-center justify-between border-b border-surface-100 pb-3">
                            <div class="flex items-center gap-2">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                                    <i class="pi pi-heart text-sm"></i>
                                </div>
                                <div>
                                    <h2 class="text-base font-bold text-surface-900">CRM Celebrations</h2>
                                    <p class="text-[11px] text-surface-500">Birthdays & Anniversaries (Next 7 Days)</p>
                                </div>
                            </div>
                            <Tag :value="`${customer_reminders?.length || 0}`" severity="info" />
                        </div>

                        <div class="mt-3 space-y-3">
                            <div
                                v-for="r in (customer_reminders || []).slice(0, 5)"
                                :key="`${r.customer_id}-${r.type}`"
                                class="flex items-center justify-between rounded-xl border p-3 transition"
                                :class="r.is_today ? 'bg-amber-50/80 border-amber-300' : 'bg-surface-50 border-surface-200'"
                            >
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="truncate text-xs font-bold text-surface-900">{{ r.customer_name }}</span>
                                        <Tag :value="r.is_today ? 'Today!' : r.type" :severity="r.is_today ? 'warn' : 'secondary'" class="!text-[10px]" />
                                    </div>
                                    <div class="mt-0.5 text-[11px] text-surface-500">
                                        {{ r.mobile }} &bull; {{ formatReminderDate(r.date) }}
                                    </div>
                                </div>

                                <Button
                                    icon="pi pi-whatsapp"
                                    size="small"
                                    class="!h-8 !w-8 !rounded-full !bg-emerald-600 !p-0 !text-white hover:!bg-emerald-700"
                                    title="Send WhatsApp Greeting"
                                    @click="sendWhatsAppWish(r)"
                                />
                            </div>

                            <div v-if="!customer_reminders?.length" class="py-8 text-center text-xs text-surface-500">
                                <i class="pi pi-calendar-plus text-2xl text-surface-300"></i>
                                <p class="mt-2">No customer birthdays or anniversaries in the upcoming week.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Link -->
                    <div class="mt-4 border-t border-surface-100 pt-3 text-center">
                        <Link :href="route('customers.index')" class="text-xs font-semibold text-amber-700 hover:underline">
                            Manage Customer Vault Passes &rarr;
                        </Link>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 6. RECENT VAULT MOVEMENTS FEED -->
            <!-- ========================================== -->
            <div class="rounded-2xl border border-surface-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between border-b border-surface-100 pb-4">
                    <div>
                        <h2 class="text-base font-bold text-surface-900">Recent Safe & Drawer Vault Movements</h2>
                        <p class="text-xs text-surface-500">Audit trail of cash, bank, gold, and silver transfers.</p>
                    </div>
                    <Link :href="route('vaults.index')">
                        <Button label="View Vault Ledger" icon="pi pi-arrow-right" iconPos="right" text size="small" />
                    </Link>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <DataTable :value="recent_vault_movements" class="p-datatable-sm" responsiveLayout="scroll">
                        <Column field="vault_type" header="Vault Safe">
                            <template #body="{ data }">
                                <Tag :value="vaultLabels[data.vault_type] || data.vault_type" :severity="['GOLD', 'SILVER'].includes(data.vault_type) ? 'warn' : 'info'" />
                            </template>
                        </Column>
                        <Column field="direction" header="Flow">
                            <template #body="{ data }">
                                <span
                                    class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-bold"
                                    :class="data.direction === 'IN' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
                                >
                                    <i :class="data.direction === 'IN' ? 'pi pi-arrow-down-left' : 'pi pi-arrow-up-right'" class="text-[10px]"></i>
                                    {{ data.direction }}
                                </span>
                            </template>
                        </Column>
                        <Column header="Amount" class="text-right">
                            <template #body="{ data }">
                                <span class="font-semibold text-surface-900">{{ formatVaultMovementAmount(data) }}</span>
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
                <div class="rounded-xl border border-amber-200 bg-amber-50/80 p-3 text-xs text-amber-900">
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
                    <Button label="Transfer Funds" icon="pi pi-arrows-h" size="small" :disabled="!transferCanSubmit" :loading="vaultTransferForm.processing" @click="saveVaultTransfer" />
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>
