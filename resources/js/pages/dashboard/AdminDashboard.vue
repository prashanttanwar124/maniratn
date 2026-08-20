<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import Button from 'primevue/button';
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

// Forms
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

// Dialogs
const showRateDialog = ref(false);
const showDayDialog = ref(false);
const showCloseDialog = ref(false);
const showExpenseDialog = ref(false);
const showVaultTransferDialog = ref(false);

// Chart Tabs
const activeChartTab = ref('sales');
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

// Chart Data
const filteredSalesData = computed(() => {
    const raw = props.analytics?.sales_trend || [];
    const count = chartRange.value === '7D' ? 7 : chartRange.value === '14D' ? 14 : 30;
    return raw.slice(-count);
});

const shopifyChartData = computed(() => {
    const items = filteredSalesData.value;
    const labels = items.map((i) => (chartRange.value === '30D' ? i.label : `${i.short_label} ${i.label.split(' ')[0]}`));

    if (activeChartTab.value === 'collections') {
        return {
            labels,
            datasets: [
                {
                    label: 'Collections',
                    data: items.map((i) => i.collections),
                    borderColor: '#059669',
                    backgroundColor: 'rgba(5, 150, 105, 0.05)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                },
            ],
        };
    }

    if (activeChartTab.value === 'bullion') {
        const bullionItems = (props.analytics?.bullion_trend || []).slice(-(chartRange.value === '7D' ? 7 : chartRange.value === '14D' ? 14 : 14));
        return {
            labels: bullionItems.map((i) => i.label),
            datasets: [
                {
                    label: 'Gold 24K (₹/g)',
                    data: bullionItems.map((i) => i.gold_sell),
                    borderColor: '#c4922a',
                    borderWidth: 2,
                    tension: 0.3,
                    pointRadius: 3,
                },
                {
                    label: 'Silver (₹/g)',
                    data: bullionItems.map((i) => i.silver_sell),
                    borderColor: '#64748b',
                    borderWidth: 2,
                    borderDash: [3, 3],
                    tension: 0.3,
                    pointRadius: 3,
                },
            ],
        };
    }

    return {
        labels,
        datasets: [
            {
                label: 'Gross Sales',
                data: items.map((i) => i.sales),
                borderColor: '#1e293b',
                backgroundColor: 'rgba(30, 41, 59, 0.04)',
                borderWidth: 2,
                fill: true,
                tension: 0.35,
                pointRadius: 3,
            },
        ],
    };
});

const shopifyChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: '#1e293b',
            titleFont: { size: 12, weight: '600' },
            bodyFont: { size: 12 },
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
            grid: { color: 'rgba(226, 232, 240, 0.8)', borderDash: [2, 2] },
            ticks: {
                font: { size: 11 },
                color: '#64748b',
                callback: (val) => (val >= 100000 ? `₹${(val / 100000).toFixed(1)}L` : val >= 1000 ? `₹${(val / 1000).toFixed(0)}k` : `₹${val}`),
            },
        },
    },
};
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl space-y-4">
            <!-- ========================================== -->
            <!-- 1. HEADER                                  -->
            <!-- ========================================== -->
            <div class="border border-surface-200 bg-white p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-3">
                            <h2 class="text-xl font-semibold text-surface-900">Overview</h2>
                            <Tag :value="isDayOpen ? 'Store Open' : 'Register Closed'" :severity="isDayOpen ? 'success' : 'danger'" />
                            <Tag v-if="activeAlerts" :value="`${activeAlerts} alert${activeAlerts > 1 ? 's' : ''}`" severity="warn" />
                        </div>
                        <p class="mt-1 text-xs text-surface-500">Live store performance and bullion position for today.</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Link :href="route('invoices.create')">
                            <Button label="New Bill" icon="pi pi-plus" size="small" />
                        </Link>
                        <Button v-if="can.manage_expenses" label="Expense" icon="pi pi-minus-circle" size="small" outlined severity="secondary" @click="openExpenseDialog" />
                        <Button v-if="can.manage_daily_rates" label="Rates" icon="pi pi-pencil" size="small" outlined severity="secondary" @click="showRateDialog = true" />
                        <Button v-if="can.manage_vault && !isDayOpen" label="Open Day" icon="pi pi-lock-open" size="small" severity="success" @click="showDayDialog = true" />
                        <Button v-if="can.manage_vault && isDayOpen" label="Close Day" icon="pi pi-lock" size="small" severity="danger" outlined @click="openCloseDialog" />
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 2. 2-COLUMN MAIN LAYOUT                    -->
            <!-- ========================================== -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <!-- ========================================== -->
                <!-- LEFT COLUMN (2/3)                          -->
                <!-- ========================================== -->
                <div class="space-y-4 lg:col-span-2">
                    <!-- Metric Cards -->
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div class="border border-surface-200 bg-white p-4">
                            <div class="text-xs text-surface-500 font-medium">Total Sales</div>
                            <div class="mt-1 text-xl font-bold text-surface-900">{{ formatCurrency(metrics?.today_sales) }}</div>
                            <div class="mt-1 text-xs text-surface-400">{{ recent_invoices?.length || 0 }} orders today</div>
                        </div>

                        <div class="border border-surface-200 bg-white p-4">
                            <div class="text-xs text-surface-500 font-medium">Collections</div>
                            <div class="mt-1 text-xl font-bold text-emerald-700">{{ formatCurrency(metrics?.today_collections) }}</div>
                            <div class="mt-1 text-xs text-emerald-600 font-medium">Settled in full</div>
                        </div>

                        <div class="border border-surface-200 bg-white p-4">
                            <div class="text-xs text-surface-500 font-medium">Active Orders</div>
                            <div class="mt-1 text-xl font-bold text-surface-900">{{ (metrics?.new_orders || 0) + (metrics?.in_production || 0) }}</div>
                            <div class="mt-1 text-xs text-amber-700 font-medium">{{ metrics?.ready_items || 0 }} ready for pickup</div>
                        </div>

                        <div class="border border-surface-200 bg-white p-4">
                            <div class="text-xs text-surface-500 font-medium">Safe Gold Stock</div>
                            <div class="mt-1 text-xl font-bold text-surface-900">{{ formatWeight(vaults?.gold) }}</div>
                            <div class="mt-1 text-xs text-surface-400 truncate">Val: {{ formatCurrency(goldValuation) }}</div>
                        </div>
                    </div>

                    <!-- Analytics Chart Card -->
                    <div class="border border-surface-200 bg-white p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-surface-100 pb-3">
                            <div class="flex items-center gap-1 border border-surface-200 p-0.5 bg-surface-50">
                                <button
                                    class="px-3 py-1 text-xs font-semibold transition"
                                    :class="activeChartTab === 'sales' ? 'bg-surface-900 text-white' : 'text-surface-600 hover:bg-surface-200'"
                                    @click="activeChartTab === 'sales'"
                                >
                                    Total Sales
                                </button>
                                <button
                                    class="px-3 py-1 text-xs font-semibold transition"
                                    :class="activeChartTab === 'collections' ? 'bg-surface-900 text-white' : 'text-surface-600 hover:bg-surface-200'"
                                    @click="activeChartTab === 'collections'"
                                >
                                    Collections
                                </button>
                                <button
                                    class="px-3 py-1 text-xs font-semibold transition"
                                    :class="activeChartTab === 'bullion' ? 'bg-surface-900 text-white' : 'text-surface-600 hover:bg-surface-200'"
                                    @click="activeChartTab === 'bullion'"
                                >
                                    Rates Trend
                                </button>
                            </div>

                            <div class="flex items-center gap-1 border border-surface-200 p-0.5 bg-surface-50">
                                <button
                                    v-for="range in ['7D', '14D', '30D']"
                                    :key="range"
                                    class="px-2.5 py-0.5 text-xs font-medium transition"
                                    :class="chartRange === range ? 'bg-surface-900 text-white' : 'text-surface-600 hover:bg-surface-200'"
                                    @click="chartRange = range"
                                >
                                    {{ range }}
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 h-64 w-full">
                            <Chart type="line" :data="shopifyChartData" :options="shopifyChartOptions" class="h-full w-full" />
                        </div>
                    </div>

                    <!-- Action Required Strip -->
                    <div v-if="metrics?.overdue_items > 0 || metrics?.new_orders > 0 || karigars?.length > 0" class="border border-surface-200 bg-white p-5">
                        <div class="flex items-center justify-between border-b border-surface-100 pb-2">
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-surface-600">Needs Attention</h3>
                            <span class="text-xs text-surface-400">Action items</span>
                        </div>

                        <div class="mt-3 space-y-2 text-xs">
                            <div v-if="metrics?.overdue_items > 0" class="flex items-center justify-between border border-rose-200 bg-rose-50 p-2.5 text-rose-800">
                                <span><strong>{{ metrics.overdue_items }} Custom Order{{ metrics.overdue_items > 1 ? 's' : '' }}</strong> are past their promised delivery date.</span>
                                <Link :href="route('orders.index')" class="font-semibold text-rose-900 hover:underline">
                                    View orders &rarr;
                                </Link>
                            </div>

                            <div v-if="metrics?.new_orders > 0" class="flex items-center justify-between border border-surface-200 bg-surface-50 p-2.5 text-surface-800">
                                <span><strong>{{ metrics.new_orders }} New Order{{ metrics.new_orders > 1 ? 's' : '' }}</strong> awaiting workshop karigar assignment.</span>
                                <Link :href="route('orders.index')" class="font-semibold text-surface-900 hover:underline">
                                    Assign &rarr;
                                </Link>
                            </div>

                            <div v-if="karigars?.length > 0" class="flex items-center justify-between border border-amber-200 bg-amber-50 p-2.5 text-amber-900">
                                <span><strong>{{ karigars.length }} Karigar{{ karigars.length > 1 ? 's' : '' }}</strong> currently holding store metal.</span>
                                <Link :href="route('karigars.index')" class="font-semibold text-amber-900 hover:underline">
                                    Karigar ledger &rarr;
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Invoices Table -->
                    <div class="border border-surface-200 bg-white p-5">
                        <div class="flex items-center justify-between border-b border-surface-100 pb-3">
                            <h3 class="text-sm font-semibold text-surface-900">Recent Invoices</h3>
                            <Link :href="route('invoices.index')" class="text-xs font-semibold text-surface-600 hover:text-surface-900">
                                View all &rarr;
                            </Link>
                        </div>

                        <div class="mt-2 overflow-x-auto">
                            <DataTable :value="recent_invoices" class="p-datatable-sm text-xs" responsiveLayout="scroll">
                                <Column field="invoice_number" header="Invoice #">
                                    <template #body="{ data }">
                                        <span class="font-mono font-medium text-surface-900">{{ data.invoice_number }}</span>
                                    </template>
                                </Column>
                                <Column field="customer_name" header="Customer">
                                    <template #body="{ data }">
                                        <span class="text-surface-800">{{ data.customer_name }}</span>
                                    </template>
                                </Column>
                                <Column field="date" header="Date">
                                    <template #body="{ data }">
                                        <span class="text-surface-500">{{ formatReminderDate(data.date) }}</span>
                                    </template>
                                </Column>
                                <Column field="total_amount" header="Total" class="text-right">
                                    <template #body="{ data }">
                                        <span class="font-semibold text-surface-900">{{ formatCurrency(data.total_amount) }}</span>
                                    </template>
                                </Column>
                                <Column header="Action" class="text-right">
                                    <template #body="{ data }">
                                        <a :href="route('invoices.print', data.id)" target="_blank" class="inline-flex items-center gap-1 border border-surface-200 bg-surface-50 px-2 py-0.5 text-xs text-surface-700 hover:bg-surface-100">
                                            <i class="pi pi-print text-[10px]"></i>
                                            Print
                                        </a>
                                    </template>
                                </Column>
                            </DataTable>
                        </div>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- RIGHT COLUMN (1/3)                         -->
                <!-- ========================================== -->
                <div class="space-y-4">
                    <!-- Today's Bullion Rates -->
                    <div class="border border-surface-200 bg-white p-5">
                        <div class="flex items-center justify-between border-b border-surface-100 pb-3">
                            <h3 class="text-sm font-semibold text-surface-900">Today's Bullion Rates</h3>
                            <Button v-if="can.manage_daily_rates" icon="pi pi-pencil" text size="small" class="!p-0" @click="showRateDialog = true" />
                        </div>

                        <div class="mt-3 space-y-2 text-xs">
                            <div class="flex items-center justify-between border border-surface-100 bg-surface-50 p-2.5">
                                <span class="text-surface-600 font-medium">Gold 24K (Fine)</span>
                                <span class="font-semibold text-amber-800">₹{{ Number(rates?.gold_sell || 0).toLocaleString('en-IN') }}/g</span>
                            </div>
                            <div class="flex items-center justify-between border border-surface-100 bg-surface-50 p-2.5">
                                <span class="text-surface-600 font-medium">Gold 22K (916)</span>
                                <span class="font-semibold text-amber-700">₹{{ Number(gold22kRate).toLocaleString('en-IN') }}/g</span>
                            </div>
                            <div class="flex items-center justify-between border border-surface-100 bg-surface-50 p-2.5">
                                <span class="text-surface-600 font-medium">Silver 925</span>
                                <span class="font-semibold text-slate-700">₹{{ Number(rates?.silver_sell || 0).toLocaleString('en-IN') }}/g</span>
                            </div>
                        </div>
                    </div>

                    <!-- Safe & Drawer Vaults -->
                    <div class="border border-surface-200 bg-white p-5">
                        <div class="flex items-center justify-between border-b border-surface-100 pb-3">
                            <h3 class="text-sm font-semibold text-surface-900">Safe & Drawer Vaults</h3>
                            <Button v-if="can.manage_vault && isDayOpen" label="Transfer" text size="small" class="!p-0 !text-xs" @click="openVaultTransferDialog" />
                        </div>

                        <div class="mt-3 space-y-2.5 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-surface-600">Cash in Hand</span>
                                <span class="font-semibold text-surface-900">{{ formatCurrency(vaults?.cash) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-surface-600">Bank Account</span>
                                <span class="font-semibold text-surface-900">{{ formatCurrency(vaults?.bank) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-surface-600">Gold Safe (g)</span>
                                <span class="font-semibold text-amber-800">{{ formatWeight(vaults?.gold) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-surface-600">Silver Drawer (g)</span>
                                <span class="font-semibold text-slate-700">{{ formatWeight(vaults?.silver) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- CRM Celebrations -->
                    <div class="border border-surface-200 bg-white p-5">
                        <div class="flex items-center justify-between border-b border-surface-100 pb-3">
                            <h3 class="text-sm font-semibold text-surface-900">CRM Celebrations</h3>
                            <Tag :value="`${customer_reminders?.length || 0}`" severity="secondary" />
                        </div>

                        <div class="mt-3 space-y-2">
                            <div
                                v-for="r in (customer_reminders || []).slice(0, 4)"
                                :key="`${r.customer_id}-${r.type}`"
                                class="flex items-center justify-between border p-2.5 text-xs"
                                :class="r.is_today ? 'bg-amber-50/70 border-amber-300' : 'bg-surface-50 border-surface-200'"
                            >
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="truncate font-semibold text-surface-900">{{ r.customer_name }}</span>
                                        <Tag :value="r.is_today ? 'Today' : r.type" :severity="r.is_today ? 'warn' : 'secondary'" class="!text-[10px]" />
                                    </div>
                                    <div class="mt-0.5 text-surface-500">{{ formatReminderDate(r.date) }}</div>
                                </div>

                                <Button
                                    icon="pi pi-whatsapp"
                                    size="small"
                                    severity="success"
                                    class="!h-7 !w-7 !p-0"
                                    title="Send WhatsApp Greeting"
                                    @click="sendWhatsAppWish(r)"
                                />
                            </div>

                            <div v-if="!customer_reminders?.length" class="py-4 text-center text-xs text-surface-400">
                                No upcoming birthdays this week.
                            </div>
                        </div>
                    </div>

                    <!-- Recent Safe & Drawer Movements -->
                    <div class="border border-surface-200 bg-white p-5">
                        <div class="flex items-center justify-between border-b border-surface-100 pb-3">
                            <h3 class="text-sm font-semibold text-surface-900">Recent Movements</h3>
                            <span class="text-xs text-surface-400">Live feed</span>
                        </div>

                        <div class="mt-3 space-y-2">
                            <div
                                v-for="m in (recent_vault_movements || []).slice(0, 4)"
                                :key="m.id"
                                class="flex items-center justify-between text-xs border-b border-surface-100 pb-2 last:border-0 last:pb-0"
                            >
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-medium text-surface-800">{{ vaultLabels[m.vault_type] || m.vault_type }}</span>
                                        <span :class="m.direction === 'IN' ? 'text-emerald-700' : 'text-rose-700'" class="font-bold text-[10px]">
                                            {{ m.direction === 'IN' ? '+IN' : '-OUT' }}
                                        </span>
                                    </div>
                                    <div class="text-surface-400 truncate">{{ m.note || m.reference || m.time }}</div>
                                </div>
                                <span class="font-semibold text-surface-900">{{ formatVaultMovementAmount(m) }}</span>
                            </div>

                            <div v-if="!recent_vault_movements?.length" class="py-3 text-center text-xs text-surface-400">
                                No recent movements.
                            </div>
                        </div>
                    </div>
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
                <div class="border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
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
