<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import Button from 'primevue/button';
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

// Helper to get initials
const getInitials = (name) => {
    if (!name) return 'CU';
    const parts = name.trim().split(' ');
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
};

// Quick Shortcuts
const quickLinks = [
    { label: 'New Invoice', href: route('invoices.create'), icon: 'pi pi-plus-circle', desc: 'Create retail bill' },
    { label: 'Invoices', href: route('invoices.index'), icon: 'pi pi-file-check', desc: 'All sales & tax bills' },
    { label: 'Custom Orders', href: route('orders.index'), icon: 'pi pi-sparkles', desc: 'Workshop jobs' },
    { label: 'Customers', href: route('customers.index'), icon: 'pi pi-users', desc: 'Directory & khata' },
    { label: 'Inventory', href: route('products.index'), icon: 'pi pi-box', desc: 'Stock & barcodes' },
    { label: 'Karigars', href: route('karigars.index'), icon: 'pi pi-wrench', desc: 'Metal issue / return' },
];

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

// Chart Tabs & Interactive State
const activeChartTab = ref('sales'); // 'sales' | 'collections' | 'bullion'
const chartRange = ref('7D'); // '7D' | '14D' | '30D'
const hoveredIndex = ref(null);

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

const goldSellRate = computed(() => Number(props.rates?.gold_sell || 0));
const goldBuyRate = computed(() => Number(props.rates?.gold_buy || 0));
const silverSellRate = computed(() => Number(props.rates?.silver_sell || 0));
const gold22kRate = computed(() => Math.round(goldSellRate.value * (22 / 24)));

const goldValuation = computed(() => props.analytics?.valuations?.gold_value || (Number(props.vaults?.gold || 0) * goldSellRate.value));
const silverValuation = computed(() => props.analytics?.valuations?.silver_value || (Number(props.vaults?.silver || 0) * silverSellRate.value));
const liquidTotal = computed(() => Number(props.vaults?.cash || 0) + Number(props.vaults?.bank || 0));
const totalAssetsValuation = computed(() => liquidTotal.value + goldValuation.value + silverValuation.value);

const cashPercent = computed(() => (totalAssetsValuation.value > 0 ? (Number(props.vaults?.cash || 0) / totalAssetsValuation.value) * 100 : 25));
const bankPercent = computed(() => (totalAssetsValuation.value > 0 ? (Number(props.vaults?.bank || 0) / totalAssetsValuation.value) * 100 : 25));
const goldPercent = computed(() => (totalAssetsValuation.value > 0 ? (goldValuation.value / totalAssetsValuation.value) * 100 : 25));
const silverPercent = computed(() => (totalAssetsValuation.value > 0 ? (silverValuation.value / totalAssetsValuation.value) * 100 : 25));

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
// PURE VUE ULTRA-REACTIVE SVG CHART ENGINE
// ----------------------------------------------------

const chartViewWidth = 800;
const chartViewHeight = 220;
const padLeft = 60;
const padRight = 30;
const padTop = 20;
const padBottom = 30;
const plotWidth = chartViewWidth - padLeft - padRight;
const plotHeight = chartViewHeight - padTop - padBottom;

const filteredSalesData = computed(() => {
    const raw = props.analytics?.sales_trend || [];
    const count = chartRange.value === '7D' ? 7 : chartRange.value === '14D' ? 14 : 30;
    return raw.slice(-count);
});

const filteredBullionData = computed(() => {
    const raw = props.analytics?.bullion_trend || [];
    const count = chartRange.value === '7D' ? 7 : chartRange.value === '14D' ? 14 : 14;
    return raw.slice(-count);
});

const currentChartSummary = computed(() => {
    if (activeChartTab.value === 'collections') {
        const total = filteredSalesData.value.reduce((acc, i) => acc + Number(i.collections || 0), 0);
        return { label: `Total ${chartRange.value} Collections`, value: formatCurrency(total) };
    }
    if (activeChartTab.value === 'bullion') {
        return { label: 'Live 24K Gold Rate', value: `₹${Number(props.rates?.gold_sell || 0).toLocaleString('en-IN')}/g` };
    }
    const total = filteredSalesData.value.reduce((acc, i) => acc + Number(i.sales || 0), 0);
    return { label: `Total ${chartRange.value} Sales`, value: formatCurrency(total) };
});

const buildSmoothPath = (points) => {
    if (!points || points.length === 0) return '';
    if (points.length === 1) return `M ${points[0].x} ${points[0].y}`;

    let d = `M ${points[0].x} ${points[0].y}`;
    for (let i = 0; i < points.length - 1; i++) {
        const p0 = points[i === 0 ? 0 : i - 1];
        const p1 = points[i];
        const p2 = points[i + 1];
        const p3 = points[i + 2] || p2;

        const cp1x = p1.x + (p2.x - p0.x) / 6;
        const cp1y = p1.y + (p2.y - p0.y) / 6;
        const cp2x = p2.x - (p3.x - p1.x) / 6;
        const cp2y = p2.y - (p3.y - p1.y) / 6;

        d += ` C ${cp1x.toFixed(1)} ${cp1y.toFixed(1)}, ${cp2x.toFixed(1)} ${cp2y.toFixed(1)}, ${p2.x.toFixed(1)} ${p2.y.toFixed(1)}`;
    }
    return d;
};

// Financial Chart (Sales or Collections)
const financeChartCalculations = computed(() => {
    const items = filteredSalesData.value || [];
    if (!items.length) {
        const defaultYTicks = [
            { y: padTop, formatted: '₹10k' },
            { y: padTop + plotHeight * 0.5, formatted: '₹5k' },
            { y: padTop + plotHeight, formatted: '₹0' },
        ];
        return { points: [], linePath: '', areaPath: '', yTicks: defaultYTicks, xTicks: [] };
    }

    const values = items
        .map((i) => (activeChartTab.value === 'collections' ? Number(i.collections || 0) : Number(i.sales || 0)))
        .filter((v) => !isNaN(v));
    const rawMax = values.length ? Math.max(...values, 1000) : 1000;
    const maxVal = Math.max(rawMax * 1.15, 1000);
    const minVal = 0;

    const points = items.map((item, idx) => {
        const x = padLeft + (idx / Math.max(items.length - 1, 1)) * plotWidth;
        const val = activeChartTab.value === 'collections' ? Number(item.collections || 0) : Number(item.sales || 0);
        const safeVal = isNaN(val) ? 0 : val;
        const y = padTop + plotHeight * (1 - (safeVal - minVal) / (maxVal - minVal));
        return {
            x,
            y: isNaN(y) ? padTop + plotHeight : y,
            val: safeVal,
            label: item.label || '',
            short_label: item.short_label || '',
            formatted: formatCurrency(safeVal),
        };
    });

    const linePath = buildSmoothPath(points);
    const bottomY = padTop + plotHeight;
    const areaPath = points.length
        ? `${linePath} L ${points[points.length - 1].x} ${bottomY} L ${points[0].x} ${bottomY} Z`
        : '';

    const yTicks = [0, 0.33, 0.66, 1].map((pct) => {
        const val = minVal + (maxVal - minVal) * pct;
        const y = padTop + plotHeight * (1 - pct);
        const formatted = val >= 100000 ? `₹${(val / 100000).toFixed(1)}L` : val >= 1000 ? `₹${(val / 1000).toFixed(0)}k` : `₹${Math.round(val)}`;
        return { y, formatted };
    });

    const step = items.length > 14 ? 4 : items.length > 7 ? 2 : 1;
    const xTicks = points.filter((_, idx) => idx % step === 0 || idx === points.length - 1);

    return { points, linePath, areaPath, yTicks, xTicks };
});

// Bullion Chart Calculations (Dual Axis)
const bullionChartCalculations = computed(() => {
    const items = filteredBullionData.value || [];
    if (!items.length) {
        const defaultGoldYTicks = [
            { y: padTop, formatted: '₹7,500' },
            { y: padTop + plotHeight * 0.5, formatted: '₹7,250' },
            { y: padTop + plotHeight, formatted: '₹7,000' },
        ];
        const defaultSilverYTicks = [
            { y: padTop, formatted: '₹95' },
            { y: padTop + plotHeight * 0.5, formatted: '₹88' },
            { y: padTop + plotHeight, formatted: '₹80' },
        ];
        return {
            goldPoints: [],
            silverPoints: [],
            goldPath: '',
            silverPath: '',
            goldYTicks: defaultGoldYTicks,
            silverYTicks: defaultSilverYTicks,
            xTicks: [],
        };
    }

    const goldVals = items.map((i) => Number(i.gold_sell || 0)).filter((v) => v > 0 && !isNaN(v));
    const silverVals = items.map((i) => Number(i.silver_sell || 0)).filter((v) => v > 0 && !isNaN(v));

    const rawMinGold = goldVals.length ? Math.min(...goldVals) : 7000;
    const rawMaxGold = goldVals.length ? Math.max(...goldVals) : 7500;
    const minGold = Math.floor((rawMinGold * 0.98) / 50) * 50;
    const maxGold = Math.ceil((rawMaxGold * 1.02) / 50) * 50;

    const rawMinSilver = silverVals.length ? Math.min(...silverVals) : 80;
    const rawMaxSilver = silverVals.length ? Math.max(...silverVals) : 95;
    const minSilver = Math.floor((rawMinSilver * 0.95) / 5) * 5;
    const maxSilver = Math.ceil((rawMaxSilver * 1.05) / 5) * 5;

    const goldDenom = Math.max(maxGold - minGold, 1);
    const silverDenom = Math.max(maxSilver - minSilver, 1);

    const goldPoints = items.map((item, idx) => {
        const x = padLeft + (idx / Math.max(items.length - 1, 1)) * plotWidth;
        const val = Number(item.gold_sell || 0);
        const safeVal = isNaN(val) || val <= 0 ? (goldVals[0] || 7200) : val;
        const y = padTop + plotHeight * (1 - (safeVal - minGold) / goldDenom);
        return {
            x,
            y: isNaN(y) ? padTop + plotHeight * 0.5 : y,
            val: safeVal,
            label: item.label || '',
            formatted: `₹${safeVal.toLocaleString('en-IN')}/g`,
        };
    });

    const silverPoints = items.map((item, idx) => {
        const x = padLeft + (idx / Math.max(items.length - 1, 1)) * plotWidth;
        const val = Number(item.silver_sell || 0);
        const safeVal = isNaN(val) || val <= 0 ? (silverVals[0] || 88) : val;
        const y = padTop + plotHeight * (1 - (safeVal - minSilver) / silverDenom);
        return {
            x,
            y: isNaN(y) ? padTop + plotHeight * 0.5 : y,
            val: safeVal,
            label: item.label || '',
            formatted: `₹${safeVal.toLocaleString('en-IN')}/g`,
        };
    });

    const goldPath = buildSmoothPath(goldPoints);
    const silverPath = buildSmoothPath(silverPoints);

    const goldYTicks = [0, 0.5, 1].map((pct) => {
        const val = Math.round(minGold + (maxGold - minGold) * pct);
        const y = padTop + plotHeight * (1 - pct);
        return { y, formatted: `₹${val.toLocaleString('en-IN')}` };
    });

    const silverYTicks = [0, 0.5, 1].map((pct) => {
        const val = Math.round(minSilver + (maxSilver - minSilver) * pct);
        const y = padTop + plotHeight * (1 - pct);
        return { y, formatted: `₹${val.toLocaleString('en-IN')}` };
    });

    const step = items.length > 7 ? 2 : 1;
    const xTicks = goldPoints.filter((_, idx) => idx % step === 0 || idx === goldPoints.length - 1);

    return { goldPoints, silverPoints, goldPath, silverPath, goldYTicks, silverYTicks, xTicks };
});

const handleSvgMouseMove = (e) => {
    const rect = e.currentTarget.getBoundingClientRect();
    const clientX = e.clientX - rect.left;
    const ratio = clientX / rect.width;
    const svgX = ratio * chartViewWidth;

    const points = activeChartTab.value === 'bullion' ? bullionChartCalculations.value.goldPoints : financeChartCalculations.value.points;
    if (!points || !points.length) return;

    let closestIdx = 0;
    let minDiff = Infinity;
    points.forEach((p, idx) => {
        const diff = Math.abs(p.x - svgX);
        if (diff < minDiff) {
            minDiff = diff;
            closestIdx = idx;
        }
    });

    hoveredIndex.value = closestIdx;
};

const handleSvgMouseLeave = () => {
    hoveredIndex.value = null;
};
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl space-y-4 font-sans pb-10">
            <!-- ========================================== -->
            <!-- 1. HEADER                                  -->
            <!-- ========================================== -->
            <div class="border border-surface-200 bg-white p-5 shadow-2xs">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-3">
                            <h2 class="text-xl font-bold tracking-tight text-surface-900">Admin Dashboard</h2>
                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold"
                                :class="isDayOpen ? 'bg-emerald-50 text-emerald-700 border border-emerald-300' : 'bg-rose-50 text-rose-700 border border-rose-300'"
                            >
                                <span class="h-1.5 w-1.5 rounded-full" :class="isDayOpen ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'"></span>
                                {{ isDayOpen ? 'Store Open' : 'Register Closed' }}
                            </span>
                            <span v-if="activeAlerts" class="inline-flex items-center gap-1 bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800 border border-amber-300">
                                <i class="pi pi-bell text-[10px]"></i>
                                {{ activeAlerts }} Action Required
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-surface-500">Real-time overview of jewellery store sales, bullion positions, safe inventory, and workshop pipeline.</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Link :href="route('invoices.create')">
                            <Button label="New Bill" icon="pi pi-plus" size="small" class="!bg-surface-900 !text-white !border-surface-900 hover:!bg-surface-800" />
                        </Link>
                        <Button v-if="can.manage_expenses" label="Expense" icon="pi pi-minus-circle" size="small" outlined severity="secondary" @click="openExpenseDialog" />
                        <Button v-if="can.manage_daily_rates" label="Rates" icon="pi pi-pencil" size="small" outlined severity="secondary" @click="showRateDialog = true" />
                        <Button v-if="can.manage_vault && !isDayOpen" label="Open Day" icon="pi pi-lock-open" size="small" severity="success" @click="showDayDialog = true" />
                        <Button v-if="can.manage_vault && isDayOpen" label="Close Day" icon="pi pi-lock" size="small" severity="danger" outlined @click="openCloseDialog" />
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 2. QUICK ACCESS SHORTCUTS                  -->
            <!-- ========================================== -->
            <div class="border border-surface-200 bg-white p-5 shadow-2xs">
                <div class="flex items-center justify-between border-b border-surface-100 pb-3">
                    <div class="flex items-center gap-2">
                        <i class="pi pi-th-large text-xs text-surface-500"></i>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-surface-700">Quick Access Shortcuts</h3>
                    </div>
                    <span class="text-[11px] text-surface-400 font-medium">Fast Operations</span>
                </div>

                <div class="mt-3.5 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-6">
                    <Link
                        v-for="item in quickLinks"
                        :key="item.label"
                        :href="item.href"
                        class="group flex flex-col justify-between border border-surface-200 bg-surface-50 p-3 transition hover:border-surface-900 hover:bg-white hover:shadow-2xs"
                    >
                        <div class="flex h-8 w-8 items-center justify-center border border-surface-200 bg-white text-surface-800 group-hover:bg-surface-900 group-hover:text-white transition">
                            <i :class="[item.icon, 'text-xs']"></i>
                        </div>
                        <div class="mt-3">
                            <div class="text-xs font-bold text-surface-900 group-hover:text-surface-900">{{ item.label }}</div>
                            <div class="text-[10px] text-surface-400 truncate">{{ item.desc }}</div>
                        </div>
                    </Link>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 3. 2-COLUMN MAIN LAYOUT                    -->
            <!-- ========================================== -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <!-- ========================================== -->
                <!-- LEFT COLUMN (2/3)                          -->
                <!-- ========================================== -->
                <div class="space-y-4 lg:col-span-2">
                    <!-- Metric Cards (4 Grid) with Accent Top Borders -->
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <!-- Metric 1: Sales -->
                        <div class="border border-surface-200 border-t-2 border-t-surface-900 bg-white p-4 shadow-2xs">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-surface-500">Today's Sales</span>
                                <i class="pi pi-receipt text-xs text-surface-400"></i>
                            </div>
                            <div class="mt-2 text-xl font-bold tracking-tight text-surface-900">{{ formatCurrency(metrics?.today_sales) }}</div>
                            <div class="mt-1 flex items-center gap-1 text-[11px] text-surface-400">
                                <span class="font-semibold text-surface-700">{{ recent_invoices?.length || 0 }} bills</span> generated today
                            </div>
                        </div>

                        <!-- Metric 2: Collections -->
                        <div class="border border-surface-200 border-t-2 border-t-emerald-600 bg-white p-4 shadow-2xs">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-surface-500">Collections</span>
                                <i class="pi pi-check-circle text-xs text-emerald-600"></i>
                            </div>
                            <div class="mt-2 text-xl font-bold tracking-tight text-emerald-700">{{ formatCurrency(metrics?.today_collections) }}</div>
                            <div class="mt-1 text-[11px] font-semibold text-emerald-600">
                                100% Settled Inflows
                            </div>
                        </div>

                        <!-- Metric 3: Active Orders -->
                        <div class="border border-surface-200 border-t-2 border-t-amber-500 bg-white p-4 shadow-2xs">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-surface-500">Active Orders</span>
                                <i class="pi pi-sparkles text-xs text-amber-600"></i>
                            </div>
                            <div class="mt-2 text-xl font-bold tracking-tight text-surface-900">{{ (metrics?.new_orders || 0) + (metrics?.in_production || 0) }}</div>
                            <div class="mt-1 text-[11px] font-semibold text-amber-700">
                                {{ metrics?.ready_items || 0 }} items ready for pickup
                            </div>
                        </div>

                        <!-- Metric 4: Safe Gold Value -->
                        <div class="border border-surface-200 border-t-2 border-t-[#c4922a] bg-white p-4 shadow-2xs">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-surface-500">Safe Gold Stock</span>
                                <i class="pi pi-box text-xs text-[#c4922a]"></i>
                            </div>
                            <div class="mt-2 text-xl font-bold tracking-tight text-surface-900">{{ formatWeight(vaults?.gold) }}</div>
                            <div class="mt-1 text-[11px] font-medium text-surface-500 truncate">
                                Val: <strong class="text-amber-900">{{ formatCurrency(goldValuation) }}</strong>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- ULTRA-REACTIVE FINTECH SVG CHART           -->
                    <!-- ========================================== -->
                    <div class="border border-surface-200 bg-white p-5 shadow-2xs">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-surface-100 pb-3">
                            <!-- Tab Switcher -->
                            <div class="flex items-center border border-surface-200 bg-surface-50 p-0.5">
                                <button
                                    type="button"
                                    class="px-3.5 py-1.5 text-xs font-semibold transition cursor-pointer"
                                    :class="activeChartTab === 'sales' ? 'bg-surface-900 text-white shadow-xs' : 'text-surface-600 hover:bg-surface-200 hover:text-surface-900'"
                                    @click="activeChartTab = 'sales'"
                                >
                                    Total Sales
                                </button>
                                <button
                                    type="button"
                                    class="px-3.5 py-1.5 text-xs font-semibold transition cursor-pointer"
                                    :class="activeChartTab === 'collections' ? 'bg-surface-900 text-white shadow-xs' : 'text-surface-600 hover:bg-surface-200 hover:text-surface-900'"
                                    @click="activeChartTab = 'collections'"
                                >
                                    Collections
                                </button>
                                <button
                                    type="button"
                                    class="px-3.5 py-1.5 text-xs font-semibold transition cursor-pointer"
                                    :class="activeChartTab === 'bullion' ? 'bg-surface-900 text-white shadow-xs' : 'text-surface-600 hover:bg-surface-200 hover:text-surface-900'"
                                    @click="activeChartTab = 'bullion'"
                                >
                                    Rates Trend
                                </button>
                            </div>

                            <!-- Range Switcher & Summary -->
                            <div class="flex items-center gap-3">
                                <div class="hidden text-right sm:block">
                                    <div class="text-[10px] uppercase tracking-wider text-surface-400 font-semibold">{{ currentChartSummary.label }}</div>
                                    <div class="text-sm font-bold text-surface-900">{{ currentChartSummary.value }}</div>
                                </div>
                                <div class="flex items-center border border-surface-200 bg-surface-50 p-0.5">
                                    <button
                                        v-for="range in ['7D', '14D', '30D']"
                                        :key="range"
                                        type="button"
                                        class="px-3 py-1 text-xs font-medium transition cursor-pointer"
                                        :class="chartRange === range ? 'bg-surface-900 text-white font-bold shadow-xs' : 'text-surface-600 hover:bg-surface-200 hover:text-surface-900'"
                                        @click="chartRange = range"
                                    >
                                        {{ range }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Bullion Legend (When on Rates tab) -->
                        <div v-if="activeChartTab === 'bullion'" class="mt-2.5 flex items-center justify-end gap-5 text-xs font-medium">
                            <span class="flex items-center gap-1.5 text-amber-800">
                                <span class="h-2.5 w-4 bg-[#c4922a]"></span>
                                <strong>Gold 24K (Fine)</strong>
                            </span>
                            <span class="flex items-center gap-1.5 text-slate-600">
                                <span class="h-2.5 w-4 border-b-2 border-dashed border-slate-600"></span>
                                <strong>Silver 925</strong>
                            </span>
                        </div>

                        <!-- SVG Interactive Canvas Container -->
                        <div class="mt-3 relative h-64 w-full select-none" @mousemove="handleSvgMouseMove" @mouseleave="handleSvgMouseLeave">
                            <svg :viewBox="`0 0 ${chartViewWidth} ${chartViewHeight}`" class="h-full w-full overflow-visible">
                                <defs>
                                    <linearGradient id="salesGrad" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#0f172a" stop-opacity="0.12" />
                                        <stop offset="100%" stop-color="#0f172a" stop-opacity="0.0" />
                                    </linearGradient>

                                    <linearGradient id="collectionsGrad" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#059669" stop-opacity="0.16" />
                                        <stop offset="100%" stop-color="#059669" stop-opacity="0.0" />
                                    </linearGradient>
                                </defs>

                                <!-- Grid Lines & Y Ticks (Finance Mode) -->
                                <template v-if="activeChartTab !== 'bullion'">
                                    <g v-for="(tick, idx) in financeChartCalculations.yTicks" :key="`y-${idx}`">
                                        <line
                                            :x1="padLeft"
                                            :y1="tick.y"
                                            :x2="chartViewWidth - padRight"
                                            :y2="tick.y"
                                            stroke="#e2e8f0"
                                            stroke-dasharray="3 3"
                                            stroke-width="1"
                                        />
                                        <text
                                            :x="padLeft - 10"
                                            :y="tick.y + 4"
                                            text-anchor="end"
                                            fill="#64748b"
                                            font-size="11"
                                            font-family="Instrument Sans, sans-serif"
                                        >
                                            {{ tick.formatted }}
                                        </text>
                                    </g>
                                </template>

                                <!-- Grid Lines & Dual Y Ticks (Bullion Mode) -->
                                <template v-else>
                                    <g v-for="(tick, idx) in bullionChartCalculations.goldYTicks" :key="`gy-${idx}`">
                                        <line
                                            :x1="padLeft"
                                            :y1="tick.y"
                                            :x2="chartViewWidth - padRight"
                                            :y2="tick.y"
                                            stroke="#e2e8f0"
                                            stroke-dasharray="3 3"
                                            stroke-width="1"
                                        />
                                        <text
                                            :x="padLeft - 8"
                                            :y="tick.y + 4"
                                            text-anchor="end"
                                            fill="#c4922a"
                                            font-size="11"
                                            font-weight="600"
                                            font-family="Instrument Sans, sans-serif"
                                        >
                                            {{ tick.formatted }}
                                        </text>
                                    </g>

                                    <g v-for="(tick, idx) in bullionChartCalculations.silverYTicks" :key="`sy-${idx}`">
                                        <text
                                            :x="chartViewWidth - padRight + 8"
                                            :y="tick.y + 4"
                                            text-anchor="start"
                                            fill="#64748b"
                                            font-size="11"
                                            font-family="Instrument Sans, sans-serif"
                                        >
                                            {{ tick.formatted }}
                                        </text>
                                    </g>
                                </template>

                                <!-- X Axis Labels -->
                                <g v-if="activeChartTab !== 'bullion'">
                                    <text
                                        v-for="(p, idx) in financeChartCalculations.xTicks"
                                        :key="`x-${idx}`"
                                        :x="p.x"
                                        :y="chartViewHeight - 6"
                                        text-anchor="middle"
                                        fill="#64748b"
                                        font-size="11"
                                        font-family="Instrument Sans, sans-serif"
                                    >
                                        {{ chartRange === '30D' ? p.label.split(' ')[0] : `${p.short_label} ${p.label.split(' ')[0]}` }}
                                    </text>
                                </g>
                                <g v-else>
                                    <text
                                        v-for="(p, idx) in bullionChartCalculations.xTicks"
                                        :key="`bx-${idx}`"
                                        :x="p.x"
                                        :y="chartViewHeight - 6"
                                        text-anchor="middle"
                                        fill="#64748b"
                                        font-size="11"
                                        font-family="Instrument Sans, sans-serif"
                                    >
                                        {{ p.label }}
                                    </text>
                                </g>

                                <!-- Finance Chart Paths -->
                                <g v-if="activeChartTab === 'sales'">
                                    <path :d="financeChartCalculations.areaPath" fill="url(#salesGrad)" />
                                    <path
                                        :d="financeChartCalculations.linePath"
                                        fill="none"
                                        stroke="#0f172a"
                                        stroke-width="2.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                    <circle
                                        v-for="(p, idx) in financeChartCalculations.points"
                                        :key="`sp-${idx}`"
                                        :cx="p.x"
                                        :cy="p.y"
                                        r="3.5"
                                        fill="#0f172a"
                                        stroke="#ffffff"
                                        stroke-width="2"
                                    />
                                </g>

                                <g v-if="activeChartTab === 'collections'">
                                    <path :d="financeChartCalculations.areaPath" fill="url(#collectionsGrad)" />
                                    <path
                                        :d="financeChartCalculations.linePath"
                                        fill="none"
                                        stroke="#059669"
                                        stroke-width="2.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                    <circle
                                        v-for="(p, idx) in financeChartCalculations.points"
                                        :key="`cp-${idx}`"
                                        :cx="p.x"
                                        :cy="p.y"
                                        r="3.5"
                                        fill="#059669"
                                        stroke="#ffffff"
                                        stroke-width="2"
                                    />
                                </g>

                                <!-- Bullion Chart Paths -->
                                <g v-if="activeChartTab === 'bullion'">
                                    <path
                                        :d="bullionChartCalculations.goldPath"
                                        fill="none"
                                        stroke="#c4922a"
                                        stroke-width="2.5"
                                        stroke-linecap="round"
                                    />
                                    <circle
                                        v-for="(p, idx) in bullionChartCalculations.goldPoints"
                                        :key="`gp-${idx}`"
                                        :cx="p.x"
                                        :cy="p.y"
                                        r="3.5"
                                        fill="#c4922a"
                                        stroke="#ffffff"
                                        stroke-width="2"
                                    />

                                    <path
                                        :d="bullionChartCalculations.silverPath"
                                        fill="none"
                                        stroke="#64748b"
                                        stroke-width="2"
                                        stroke-dasharray="4 4"
                                    />
                                    <circle
                                        v-for="(p, idx) in bullionChartCalculations.silverPoints"
                                        :key="`sivp-${idx}`"
                                        :cx="p.x"
                                        :cy="p.y"
                                        r="3"
                                        fill="#64748b"
                                    />
                                </g>

                                <!-- Hover Cursor Guide Line & Highlights -->
                                <g v-if="hoveredIndex !== null">
                                    <template v-if="activeChartTab !== 'bullion' && financeChartCalculations.points[hoveredIndex]">
                                        <line
                                            :x1="financeChartCalculations.points[hoveredIndex].x"
                                            :y1="padTop"
                                            :x2="financeChartCalculations.points[hoveredIndex].x"
                                            :y2="chartViewHeight - padBottom"
                                            stroke="#0f172a"
                                            stroke-dasharray="3 3"
                                            stroke-width="1.5"
                                        />
                                        <circle
                                            :cx="financeChartCalculations.points[hoveredIndex].x"
                                            :cy="financeChartCalculations.points[hoveredIndex].y"
                                            r="6"
                                            :fill="activeChartTab === 'collections' ? '#059669' : '#0f172a'"
                                            stroke="#ffffff"
                                            stroke-width="2.5"
                                        />
                                    </template>

                                    <template v-if="activeChartTab === 'bullion' && bullionChartCalculations.goldPoints[hoveredIndex]">
                                        <line
                                            :x1="bullionChartCalculations.goldPoints[hoveredIndex].x"
                                            :y1="padTop"
                                            :x2="bullionChartCalculations.goldPoints[hoveredIndex].x"
                                            :y2="chartViewHeight - padBottom"
                                            stroke="#c4922a"
                                            stroke-dasharray="3 3"
                                            stroke-width="1.5"
                                        />
                                        <circle
                                            :cx="bullionChartCalculations.goldPoints[hoveredIndex].x"
                                            :cy="bullionChartCalculations.goldPoints[hoveredIndex].y"
                                            r="5.5"
                                            fill="#c4922a"
                                            stroke="#ffffff"
                                            stroke-width="2"
                                        />
                                        <circle
                                            :cx="bullionChartCalculations.silverPoints[hoveredIndex].x"
                                            :cy="bullionChartCalculations.silverPoints[hoveredIndex].y"
                                            r="5"
                                            fill="#64748b"
                                            stroke="#ffffff"
                                            stroke-width="2"
                                        />
                                    </template>
                                </g>
                            </svg>

                            <!-- Hover Tooltip Overlay Box -->
                            <div
                                v-if="hoveredIndex !== null"
                                class="pointer-events-none absolute z-20 border border-surface-800 bg-surface-900 px-3 py-2 text-xs text-white shadow-lg transition-all"
                                :style="{
                                    left: `${activeChartTab === 'bullion' ? (bullionChartCalculations.goldPoints[hoveredIndex]?.x / chartViewWidth) * 100 : (financeChartCalculations.points[hoveredIndex]?.x / chartViewWidth) * 100}%`,
                                    top: '15px',
                                    transform: 'translateX(-50%)',
                                }"
                            >
                                <template v-if="activeChartTab !== 'bullion' && financeChartCalculations.points[hoveredIndex]">
                                    <div class="text-[11px] text-surface-400 font-medium">{{ financeChartCalculations.points[hoveredIndex].label }}</div>
                                    <div class="mt-0.5 font-bold text-white">
                                        {{ activeChartTab === 'collections' ? 'Collections: ' : 'Sales: ' }}
                                        {{ financeChartCalculations.points[hoveredIndex].formatted }}
                                    </div>
                                </template>
                                <template v-if="activeChartTab === 'bullion' && bullionChartCalculations.goldPoints[hoveredIndex]">
                                    <div class="text-[11px] text-surface-400 font-medium">{{ bullionChartCalculations.goldPoints[hoveredIndex].label }}</div>
                                    <div class="mt-0.5 font-bold text-amber-400">Gold 24K: {{ bullionChartCalculations.goldPoints[hoveredIndex].formatted }}</div>
                                    <div class="text-[11px] text-slate-300">Silver: {{ bullionChartCalculations.silverPoints[hoveredIndex]?.formatted }}</div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Action Required Strip -->
                    <div v-if="metrics?.overdue_items > 0 || metrics?.new_orders > 0 || karigars?.length > 0" class="border border-surface-200 bg-white p-5 shadow-2xs">
                        <div class="flex items-center justify-between border-b border-surface-100 pb-2.5">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-exclamation-triangle text-amber-600 text-xs"></i>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-surface-700">Workshop & Store Alerts</h3>
                            </div>
                            <span class="text-[11px] text-surface-400 font-medium">Action Required</span>
                        </div>

                        <div class="mt-3 space-y-2 text-xs">
                            <div v-if="metrics?.overdue_items > 0" class="flex items-center justify-between border border-rose-200 bg-rose-50/80 p-3 text-rose-900">
                                <div class="flex items-center gap-2">
                                    <i class="pi pi-clock text-rose-600 text-xs"></i>
                                    <span><strong>{{ metrics.overdue_items }} Custom Order{{ metrics.overdue_items > 1 ? 's' : '' }}</strong> are past their promised delivery date.</span>
                                </div>
                                <Link :href="route('orders.index')" class="font-bold text-rose-900 hover:underline">
                                    View orders &rarr;
                                </Link>
                            </div>

                            <div v-if="metrics?.new_orders > 0" class="flex items-center justify-between border border-surface-200 bg-surface-50 p-3 text-surface-800">
                                <div class="flex items-center gap-2">
                                    <i class="pi pi-inbox text-surface-600 text-xs"></i>
                                    <span><strong>{{ metrics.new_orders }} New Order{{ metrics.new_orders > 1 ? 's' : '' }}</strong> awaiting workshop assignment.</span>
                                </div>
                                <Link :href="route('orders.index')" class="font-bold text-surface-900 hover:underline">
                                    Assign &rarr;
                                </Link>
                            </div>

                            <div v-if="karigars?.length > 0" class="flex items-center justify-between border border-amber-200 bg-amber-50/70 p-3 text-amber-900">
                                <div class="flex items-center gap-2">
                                    <i class="pi pi-users text-amber-700 text-xs"></i>
                                    <span><strong>{{ karigars.length }} Karigar{{ karigars.length > 1 ? 's' : '' }}</strong> holding store bullion.</span>
                                </div>
                                <Link :href="route('karigars.index')" class="font-bold text-amber-900 hover:underline">
                                    Karigar ledger &rarr;
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- ENTERPRISE RECENT INVOICES TABLE           -->
                    <!-- ========================================== -->
                    <div class="border border-surface-200 bg-white shadow-2xs">
                        <!-- Card Header -->
                        <div class="flex items-center justify-between border-b border-surface-200 bg-surface-50/50 px-5 py-4">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-receipt text-xs text-surface-500"></i>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-surface-700">Recent Invoices</h3>
                            </div>
                            <Link :href="route('invoices.index')" class="text-xs font-bold text-surface-700 hover:text-surface-900 flex items-center gap-1">
                                View all invoices <i class="pi pi-arrow-right text-[10px]"></i>
                            </Link>
                        </div>

                        <!-- Data Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="border-b border-surface-200 bg-surface-50 text-[11px] font-semibold text-surface-500 uppercase tracking-wider">
                                        <th class="px-5 py-3 font-semibold">Invoice #</th>
                                        <th class="px-5 py-3 font-semibold">Customer</th>
                                        <th class="px-5 py-3 font-semibold">Date</th>
                                        <th class="px-5 py-3 font-semibold">Status</th>
                                        <th class="px-5 py-3 font-semibold text-right">Total Amount</th>
                                        <th class="px-5 py-3 font-semibold text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-surface-100">
                                    <tr v-for="inv in recent_invoices" :key="inv.id" class="transition hover:bg-surface-50/70">
                                        <!-- Invoice # -->
                                        <td class="px-5 py-3.5">
                                            <span class="inline-flex items-center gap-1.5 border border-surface-200 bg-surface-50 px-2 py-0.5 font-mono text-xs font-semibold text-surface-900">
                                                {{ inv.invoice_number }}
                                            </span>
                                        </td>

                                        <!-- Customer with Avatar -->
                                        <td class="px-5 py-3.5">
                                            <div class="flex items-center gap-2.5">
                                                <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center bg-surface-900 text-[10px] font-bold text-white uppercase">
                                                    {{ getInitials(inv.customer_name) }}
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="font-bold text-surface-900 truncate">{{ inv.customer_name }}</div>
                                                    <div class="text-[10px] text-surface-400">Retail Client</div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Date -->
                                        <td class="px-5 py-3.5 text-surface-600 font-medium">
                                            {{ formatReminderDate(inv.date) }}
                                        </td>

                                        <!-- Status Badge -->
                                        <td class="px-5 py-3.5">
                                            <span class="inline-flex items-center gap-1 border border-emerald-300 bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                PAID
                                            </span>
                                        </td>

                                        <!-- Total Amount -->
                                        <td class="px-5 py-3.5 text-right font-bold text-surface-900 text-sm">
                                            {{ formatCurrency(inv.total_amount) }}
                                        </td>

                                        <!-- Print Button -->
                                        <td class="px-5 py-3.5 text-right">
                                            <a
                                                :href="route('invoices.print', inv.id)"
                                                target="_blank"
                                                class="inline-flex items-center gap-1.5 border border-surface-300 bg-white px-3 py-1 text-xs font-medium text-surface-800 shadow-2xs hover:border-surface-900 hover:bg-surface-900 hover:text-white transition"
                                            >
                                                <i class="pi pi-print text-[11px]"></i>
                                                <span>Print</span>
                                            </a>
                                        </td>
                                    </tr>

                                    <tr v-if="!recent_invoices?.length">
                                        <td colspan="6" class="px-5 py-8 text-center text-xs text-surface-400">
                                            No recent invoices created today.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- RIGHT COLUMN (1/3): STORE OPERATIONS       -->
                <!-- ========================================== -->
                <div class="space-y-4">
                    <!-- Today's Bullion Rates (Gold Accent Header) -->
                    <div class="border border-surface-200 border-t-2 border-t-[#c4922a] bg-white p-5 shadow-2xs">
                        <div class="flex items-center justify-between border-b border-surface-100 pb-3">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-chart-line text-[#c4922a] text-xs"></i>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-surface-700">Today's Bullion Rates</h3>
                            </div>
                            <Button v-if="can.manage_daily_rates" icon="pi pi-pencil" text size="small" class="!p-1 !h-6 !w-6" @click="showRateDialog = true" />
                        </div>

                        <div class="mt-3.5 space-y-2 text-xs">
                            <!-- Gold 24K -->
                            <div class="flex items-center justify-between border border-surface-200 bg-surface-50 p-3 transition hover:border-[#c4922a]/50">
                                <div>
                                    <div class="font-bold text-surface-900">Gold 24K (Fine)</div>
                                    <div class="text-[10px] text-surface-400">99.9% Pure Standard</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-bold text-amber-800">₹{{ Number(rates?.gold_sell || 0).toLocaleString('en-IN') }}</div>
                                    <div class="text-[10px] text-surface-400">per gram</div>
                                </div>
                            </div>

                            <!-- Gold 22K (916) -->
                            <div class="flex items-center justify-between border border-surface-200 bg-surface-50 p-3 transition hover:border-[#c4922a]/50">
                                <div>
                                    <div class="font-bold text-surface-900">Gold 22K (916)</div>
                                    <div class="text-[10px] text-surface-400">Hallmark Jewellery</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-bold text-amber-700">₹{{ Number(gold22kRate).toLocaleString('en-IN') }}</div>
                                    <div class="text-[10px] text-surface-400">per gram</div>
                                </div>
                            </div>

                            <!-- Silver 925 -->
                            <div class="flex items-center justify-between border border-surface-200 bg-surface-50 p-3 transition hover:border-slate-400">
                                <div>
                                    <div class="font-bold text-surface-900">Silver 925</div>
                                    <div class="text-[10px] text-surface-400">Sterling Fine</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-bold text-slate-700">₹{{ Number(rates?.silver_sell || 0).toLocaleString('en-IN') }}</div>
                                    <div class="text-[10px] text-surface-400">per gram</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Safe & Drawer Vaults (With Liquidity Split Bar) -->
                    <div class="border border-surface-200 bg-white p-5 shadow-2xs">
                        <div class="flex items-center justify-between border-b border-surface-100 pb-3">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-wallet text-surface-500 text-xs"></i>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-surface-700">Safe & Drawer Vaults</h3>
                            </div>
                            <Button v-if="can.manage_vault && isDayOpen" label="Transfer" text size="small" class="!p-0 !text-xs !font-bold" @click="openVaultTransferDialog" />
                        </div>

                        <!-- Multi-Asset Liquidity Progress Bar -->
                        <div class="mt-3.5">
                            <div class="flex h-2 w-full overflow-hidden bg-surface-100">
                                <div class="bg-surface-900" :style="{ width: `${cashPercent}%` }" title="Cash in hand"></div>
                                <div class="bg-sky-600" :style="{ width: `${bankPercent}%` }" title="Bank Account"></div>
                                <div class="bg-[#c4922a]" :style="{ width: `${goldPercent}%` }" title="Gold Safe"></div>
                                <div class="bg-slate-400" :style="{ width: `${silverPercent}%` }" title="Silver Drawer"></div>
                            </div>
                            <div class="mt-2 flex items-center justify-between text-[10px] text-surface-400">
                                <span>Total Valuation: <strong>{{ formatCurrency(totalAssetsValuation) }}</strong></span>
                            </div>
                        </div>

                        <div class="mt-3.5 space-y-2.5 text-xs">
                            <div class="flex items-center justify-between border-b border-surface-100 pb-2">
                                <div class="flex items-center gap-1.5">
                                    <span class="h-2 w-2 bg-surface-900"></span>
                                    <span class="text-surface-600 font-medium">Cash in Hand</span>
                                </div>
                                <span class="font-bold text-surface-900">{{ formatCurrency(vaults?.cash) }}</span>
                            </div>
                            <div class="flex items-center justify-between border-b border-surface-100 pb-2">
                                <div class="flex items-center gap-1.5">
                                    <span class="h-2 w-2 bg-sky-600"></span>
                                    <span class="text-surface-600 font-medium">Bank Account</span>
                                </div>
                                <span class="font-bold text-surface-900">{{ formatCurrency(vaults?.bank) }}</span>
                            </div>
                            <div class="flex items-center justify-between border-b border-surface-100 pb-2">
                                <div class="flex items-center gap-1.5">
                                    <span class="h-2 w-2 bg-[#c4922a]"></span>
                                    <span class="text-surface-600 font-medium">Gold Safe</span>
                                </div>
                                <span class="font-bold text-amber-800">{{ formatWeight(vaults?.gold) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1.5">
                                    <span class="h-2 w-2 bg-slate-400"></span>
                                    <span class="text-surface-600 font-medium">Silver Drawer</span>
                                </div>
                                <span class="font-bold text-slate-700">{{ formatWeight(vaults?.silver) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- CRM Celebrations (Modern Cards) -->
                    <div class="border border-surface-200 bg-white p-5 shadow-2xs">
                        <div class="flex items-center justify-between border-b border-surface-100 pb-3">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-heart text-rose-500 text-xs"></i>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-surface-700">CRM Celebrations</h3>
                            </div>
                            <span class="inline-flex items-center border border-surface-200 bg-surface-50 px-2 py-0.5 text-[10px] font-bold text-surface-700">
                                {{ customer_reminders?.length || 0 }} upcoming
                            </span>
                        </div>

                        <div class="mt-3 space-y-2">
                            <div
                                v-for="r in (customer_reminders || []).slice(0, 4)"
                                :key="`${r.customer_id}-${r.type}`"
                                class="flex items-center justify-between border p-3 text-xs transition hover:shadow-2xs"
                                :class="r.is_today ? 'bg-amber-50/70 border-amber-300' : 'bg-surface-50 border-surface-200'"
                            >
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="truncate font-bold text-surface-900">{{ r.customer_name }}</span>
                                        <Tag :value="r.is_today ? 'Today' : r.type" :severity="r.is_today ? 'warn' : 'secondary'" class="!text-[9px] !font-bold" />
                                    </div>
                                    <div class="mt-0.5 text-[10px] text-surface-500">{{ formatReminderDate(r.date) }} &bull; {{ r.mobile }}</div>
                                </div>

                                <Button
                                    icon="pi pi-whatsapp"
                                    size="small"
                                    severity="success"
                                    class="!h-7 !w-7 !p-0 !rounded-none hover:scale-105 transition"
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
                    <div class="border border-surface-200 bg-white p-5 shadow-2xs">
                        <div class="flex items-center justify-between border-b border-surface-100 pb-3">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-history text-surface-500 text-xs"></i>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-surface-700">Recent Movements</h3>
                            </div>
                            <span class="text-[10px] text-surface-400 font-semibold">Live Audit</span>
                        </div>

                        <div class="mt-3 space-y-2.5">
                            <div
                                v-for="m in (recent_vault_movements || []).slice(0, 4)"
                                :key="m.id"
                                class="flex items-center justify-between text-xs border-b border-surface-100 pb-2 last:border-0 last:pb-0"
                            >
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-bold text-surface-900">{{ vaultLabels[m.vault_type] || m.vault_type }}</span>
                                        <span
                                            class="px-1.5 py-0.2 text-[9px] font-bold"
                                            :class="m.direction === 'CREDIT' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'"
                                        >
                                            {{ m.direction === 'CREDIT' ? '+IN' : '-OUT' }}
                                        </span>
                                    </div>
                                    <div class="text-[10px] text-surface-400 truncate">{{ m.note || m.reference || m.time }}</div>
                                </div>
                                <span class="font-bold text-surface-900">{{ formatVaultMovementAmount(m) }}</span>
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
