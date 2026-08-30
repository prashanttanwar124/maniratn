<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref, reactive } from 'vue';
import axios from 'axios';

import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import { formatIndianDate } from '@/utils/indiaTime';

const props = defineProps({
    user: Object,
    rates: Object,
    metrics: Object,
    isDayOpen: Boolean,
    recent_invoices: Array,
    ready_for_delivery: Array,
    attention_items: Array,
    customer_reminders: Array,
    my_tasks: Array,
    my_attendance: Object,
    opening_expectation: Object,
});

const page = usePage();
const can = computed(() => page.props.auth?.can || {});

// Currency and Weight Formatters
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

// Helper for Initials
const getInitials = (name) => {
    if (!name) return 'CU';
    const parts = name.trim().split(' ');
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
};

// Time of day greeting
const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good Morning';
    if (hour < 17) return 'Good Afternoon';
    return 'Good Evening';
});

// Bullion Rates calculations
const goldSellRate = computed(() => Number(props.rates?.gold_sell || 0));
const goldBuyRate = computed(() => Number(props.rates?.gold_buy || 0));
const silverSellRate = computed(() => Number(props.rates?.silver_sell || 0));

const gold22kRate = computed(() => (goldSellRate.value > 0 ? Math.round(goldSellRate.value * (22 / 24)) : 0));
const gold18kRate = computed(() => (goldSellRate.value > 0 ? Math.round(goldSellRate.value * (18 / 24)) : 0));

// Quick Launchpad Items for Counter Staff
const quickLinks = computed(() => [
    {
        label: 'New Retail Bill',
        href: route('invoices.create'),
        icon: 'pi pi-plus',
        badge: 'POS',
        badgeClass: 'bg-surface-900 text-white',
        iconBoxClass: 'bg-surface-900 text-white',
        desc: 'Fast billing & tax invoice',
        permission: can.value.manage_invoices,
    },
    {
        label: 'Gold Inventory',
        href: route('products.index'),
        icon: 'pi pi-box',
        badge: 'Stock',
        badgeClass: 'bg-amber-50 text-amber-800 border border-amber-200',
        iconBoxClass: 'bg-amber-50 text-amber-700 border border-amber-200',
        desc: 'Browse jewellery catalog',
        permission: can.value.manage_products,
    },
    {
        label: 'Silver Products',
        href: route('silver-products.index'),
        icon: 'pi pi-sparkles',
        badge: 'Silver',
        badgeClass: 'bg-slate-100 text-slate-700 border border-slate-300',
        iconBoxClass: 'bg-slate-100 text-slate-700 border border-slate-300',
        desc: 'Articles & silver ornaments',
        permission: can.value.manage_products,
    },
    {
        label: 'Custom Orders',
        href: route('orders.index'),
        icon: 'pi pi-wrench',
        badge: `${props.metrics?.ready_items || 0} Ready`,
        badgeClass: props.metrics?.ready_items > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-300' : 'bg-surface-100 text-surface-700 border border-surface-200',
        iconBoxClass: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        desc: 'Customer orders & workshop',
        permission: can.value.create_order || can.value.manage_orders,
    },
    {
        label: 'Customer Khata',
        href: route('customers.index'),
        icon: 'pi pi-users',
        badge: 'CRM',
        badgeClass: 'bg-sky-50 text-sky-700 border border-sky-200',
        iconBoxClass: 'bg-sky-50 text-sky-700 border border-sky-200',
        desc: 'Balances & customer phonebook',
        permission: can.value.manage_customers,
    },
    {
        label: 'Gold Bachat Scheme',
        href: route('gold-schemes.index'),
        icon: 'pi pi-wallet',
        badge: 'Bachat',
        badgeClass: 'bg-purple-50 text-purple-700 border border-purple-200',
        iconBoxClass: 'bg-purple-50 text-purple-700 border border-purple-200',
        desc: 'Monthly installments & savings',
        permission: can.value.manage_gold_schemes,
    },
    {
        label: 'Showroom Tasks',
        href: route('tasks.index'),
        icon: 'pi pi-check-square',
        badge: `${props.my_tasks?.length || 0} Open`,
        badgeClass: props.my_tasks?.length > 0 ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-surface-100 text-surface-700 border border-surface-200',
        iconBoxClass: 'bg-rose-50 text-rose-700 border border-rose-200',
        desc: 'Daily duties & checklist',
        permission: true,
    },
    {
        label: 'Attendance Terminal',
        href: route('attendance-terminal.show'),
        icon: 'pi pi-clock',
        badge: props.my_attendance?.status === 'PRESENT' ? 'Punch OK' : 'Shift',
        badgeClass: props.my_attendance?.status === 'PRESENT' ? 'bg-emerald-50 text-emerald-700 border border-emerald-300' : 'bg-amber-50 text-amber-700 border border-amber-300',
        iconBoxClass: 'bg-teal-50 text-teal-700 border border-teal-200',
        desc: 'Shift check-in / check-out',
        permission: true,
    },
]);

// ----------------------------------------------------
// 🧮 LIVE COUNTER PRICE & QUOTE ESTIMATOR
// ----------------------------------------------------
const estimatorPurities = [
    { label: 'Gold 22K (916 Hallmark)', value: '22K', rate: gold22kRate },
    { label: 'Gold 24K (999 Fine)', value: '24K', rate: goldSellRate },
    { label: 'Gold 18K (750 Hallmark)', value: '18K', rate: gold18kRate },
    { label: 'Silver 925 (Sterling)', value: 'SILVER', rate: silverSellRate },
];

const estimator = reactive({
    selectedPurity: '22K',
    grossWeight: 10.0,
    makingChargeType: 'PERCENT', // 'PERCENT' | 'PER_GRAM'
    makingChargeValue: 12, // 12% or ₹450/g
    includeGst: true,
});

const currentEstimatorRate = computed(() => {
    if (estimator.selectedPurity === '24K') return goldSellRate.value;
    if (estimator.selectedPurity === '22K') return gold22kRate.value;
    if (estimator.selectedPurity === '18K') return gold18kRate.value;
    if (estimator.selectedPurity === 'SILVER') return silverSellRate.value;
    return gold22kRate.value;
});

const estimatedMetalAmount = computed(() => {
    const wt = Number(estimator.grossWeight || 0);
    return Math.round(wt * currentEstimatorRate.value);
});

const estimatedMakingAmount = computed(() => {
    const wt = Number(estimator.grossWeight || 0);
    const metalAmt = estimatedMetalAmount.value;
    const val = Number(estimator.makingChargeValue || 0);

    if (estimator.makingChargeType === 'PERCENT') {
        return Math.round(metalAmt * (val / 100));
    } else {
        return Math.round(wt * val);
    }
});

const estimatedSubtotal = computed(() => estimatedMetalAmount.value + estimatedMakingAmount.value);

const estimatedGstAmount = computed(() => {
    if (!estimator.includeGst) return 0;
    return Math.round(estimatedSubtotal.value * 0.03); // 3% GST
});

const estimatedTotalQuote = computed(() => estimatedSubtotal.value + estimatedGstAmount.value);

const quoteCopied = ref(false);
const copyQuoteToClipboard = () => {
    const purityLabel = estimatorPurities.find((p) => p.value === estimator.selectedPurity)?.label || estimator.selectedPurity;
    const text = `*MANIRATN JEWELLERS - INSTANT QUOTE*\n✨ Metal: ${purityLabel}\n⚖️ Weight: ${Number(estimator.grossWeight || 0).toFixed(3)} g\n💰 Metal Rate: ₹${currentEstimatorRate.value.toLocaleString('en-IN')}/g\n💵 Metal Cost: ₹${estimatedMetalAmount.value.toLocaleString('en-IN')}\n🔨 Making Charges: ₹${estimatedMakingAmount.value.toLocaleString('en-IN')}\n🧾 GST (3%): ₹${estimatedGstAmount.value.toLocaleString('en-IN')}\n\n*⭐ Total Estimate: ₹${estimatedTotalQuote.value.toLocaleString('en-IN')}* \n\n_Note: Rate valid for today only._`;

    navigator.clipboard.writeText(text).then(() => {
        quoteCopied.value = true;
        setTimeout(() => {
            quoteCopied.value = false;
        }, 2500);
    });
};

// ----------------------------------------------------
// 🔍 INSTANT BARCODE & STOCK SCANNER
// ----------------------------------------------------
const scanQuery = ref('');
const isSearchingBarcode = ref(false);
const scannedItem = ref(null);
const scanError = ref('');
const showScanModal = ref(false);

const performBarcodeSearch = async (barcodeOverride = null) => {
    const code = (barcodeOverride || scanQuery.value || '').trim();
    if (!code) return;

    isSearchingBarcode.value = true;
    scanError.value = '';
    scannedItem.value = null;

    try {
        const response = await axios.get(`/api/inventory/${encodeURIComponent(code)}`);
        scannedItem.value = response.data;
    } catch (err) {
        scanError.value = err.response?.data?.message || `No product found matching barcode '${code}'.`;
    } finally {
        isSearchingBarcode.value = false;
    }
};

const openBarcodeModal = () => {
    scanQuery.value = '';
    scannedItem.value = null;
    scanError.value = '';
    showScanModal.value = true;
};

// ----------------------------------------------------
// 📱 WHATSAPP MESSAGING HELPERS
// ----------------------------------------------------
const sendWhatsAppWish = (reminder) => {
    const cleanMobile = String(reminder.mobile || '').replace(/\D/g, '');
    if (!cleanMobile) {
        alert('Customer does not have a valid mobile number.');
        return;
    }
    const phone = cleanMobile.startsWith('91') ? cleanMobile : `91${cleanMobile}`;
    const greeting = reminder.type === 'Birthday' ? 'Happy Birthday' : 'Happy Anniversary';
    const text = encodeURIComponent(
        `Dear ${reminder.customer_name},\n\nWarmest wishes on your ${greeting} from all of us at Maniratn Jewellers! ✨🎂\n\nMay this special day bring you abundant happiness and prosperity.\n\nVisit us to explore our latest fine jewellery collection!\n\nWarm regards,\nManiratn Jewellers`
    );
    window.open(`https://wa.me/${phone}?text=${text}`, '_blank');
};

const sendOrderReadyWhatsApp = (item) => {
    const cleanMobile = String(item.customer_phone || '').replace(/\D/g, '');
    if (!cleanMobile) {
        alert('Customer mobile number is missing.');
        return;
    }
    const phone = cleanMobile.startsWith('91') ? cleanMobile : `91${cleanMobile}`;
    const text = encodeURIComponent(
        `Namaste ${item.customer_name},\n\nGood news! ✨ Your custom order for *"${item.design_name}"* (Order #${item.order_id}) is finished and ready for pickup at Maniratn Jewellers.\n\nPlease visit our showroom at your convenience to collect your jewellery.\n\nThank you for choosing Maniratn Jewellers!`
    );
    window.open(`https://wa.me/${phone}?text=${text}`, '_blank');
};

// ----------------------------------------------------
// 📋 TASK CHECKLIST TOGGLING
// ----------------------------------------------------
const toggleTaskSubtask = (task, subtaskId) => {
    router.patch(
        route('tasks.toggle-checklist', { task: task.id, itemId: subtaskId }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
        }
    );
};

const markTaskComplete = (task) => {
    router.patch(
        route('tasks.update-status', task.id),
        { status: 'COMPLETED' },
        {
            preserveScroll: true,
            preserveState: true,
        }
    );
};

const statusSeverity = (status) => {
    if (status === 'READY') return 'success';
    if (status === 'ASSIGNED') return 'warn';
    if (status === 'NEW') return 'info';
    return 'contrast';
};

const taskPriorityClass = (priority) => {
    if (priority === 'URGENT') return 'bg-rose-50 text-rose-700 border border-rose-300 font-bold';
    if (priority === 'HIGH') return 'bg-amber-50 text-amber-800 border border-amber-300 font-semibold';
    if (priority === 'MEDIUM') return 'bg-sky-50 text-sky-700 border border-sky-300';
    return 'bg-surface-100 text-surface-600 border border-surface-200';
};
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl space-y-4 font-sans pb-12">
            <!-- ========================================== -->
            <!-- 1. COUNTER & STAFF HEADER                  -->
            <!-- ========================================== -->
            <div class="erp-page-header border border-surface-200 bg-white p-5 shadow-2xs">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-center gap-3.5">
                        <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-surface-900 text-sm font-bold text-white shadow-xs">
                            {{ getInitials(user.name) }}
                        </div>
                        <div>
                            <div class="flex flex-wrap items-center gap-2.5">
                                <h2 class="text-xl font-bold tracking-tight text-surface-900">
                                    {{ greeting }}, {{ user.name }}
                                </h2>
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-0.5 text-xs font-semibold"
                                    :class="isDayOpen ? 'bg-emerald-50 text-emerald-700 border border-emerald-300' : 'bg-rose-50 text-rose-700 border border-rose-300'"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full" :class="isDayOpen ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'"></span>
                                    {{ isDayOpen ? 'Store Open' : 'Register Closed' }}
                                </span>

                                <span
                                    v-if="my_attendance?.status === 'PRESENT'"
                                    class="inline-flex items-center gap-1 rounded-md bg-teal-50 px-2 py-0.5 text-xs font-semibold text-teal-800 border border-teal-300"
                                >
                                    <i class="pi pi-check text-[10px]"></i>
                                    In at {{ my_attendance.check_in_at }}
                                </span>
                                <Link
                                    v-else
                                    :href="route('attendance-terminal.show')"
                                    class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800 border border-amber-300 hover:bg-amber-100 transition"
                                >
                                    <i class="pi pi-clock text-[10px]"></i>
                                    Punch In
                                </Link>
                            </div>
                            <p class="mt-1 text-xs text-surface-500">Showroom Sales & Counter Terminal &bull; Billing, order deliveries, and customer follow-up.</p>
                        </div>
                    </div>

                    <!-- Header Action Buttons -->
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            label="Scan Barcode"
                            icon="pi pi-qrcode"
                            size="small"
                            outlined
                            severity="secondary"
                            class="!border-surface-300 hover:!border-surface-900"
                            @click="openBarcodeModal"
                        />

                        <Link v-if="can.manage_invoices" :href="route('invoices.create')">
                            <Button label="New Bill" icon="pi pi-plus" size="small" class="!bg-surface-900 !text-white !border-surface-900 hover:!bg-surface-800 shadow-xs" />
                        </Link>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 2. LIVE BULLION RATES RIBBON               -->
            <!-- ========================================== -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <!-- Gold 24K Fine -->
                <div class="rounded-lg border border-surface-200 border-t-2 border-t-[#c4922a] bg-white p-4 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-surface-500">Gold 24K (999 Fine)</span>
                        <span class="rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-800 border border-amber-200">Standard</span>
                    </div>
                    <div class="mt-2 text-xl font-bold tracking-tight text-amber-800">
                        ₹{{ goldSellRate.toLocaleString('en-IN') }}<span class="text-xs font-normal text-surface-400">/g</span>
                    </div>
                    <div class="mt-1 text-[11px] text-surface-400">
                        Buy (Old Gold): <strong class="text-surface-700">₹{{ goldBuyRate.toLocaleString('en-IN') }}/g</strong>
                    </div>
                </div>

                <!-- Gold 22K (916 Hallmark) -->
                <div class="rounded-lg border border-surface-200 border-t-2 border-t-[#c4922a] bg-white p-4 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-surface-500">Gold 22K (916)</span>
                        <span class="rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-800 border border-amber-200">Hallmark</span>
                    </div>
                    <div class="mt-2 text-xl font-bold tracking-tight text-amber-700">
                        ₹{{ gold22kRate.toLocaleString('en-IN') }}<span class="text-xs font-normal text-surface-400">/g</span>
                    </div>
                    <div class="mt-1 text-[11px] text-surface-400">
                        Daily retail standard rate
                    </div>
                </div>

                <!-- Gold 18K (750 Hallmark) -->
                <div class="rounded-lg border border-surface-200 border-t-2 border-t-amber-600 bg-white p-4 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-surface-500">Gold 18K (750)</span>
                        <span class="rounded bg-surface-100 px-1.5 py-0.5 text-[10px] font-bold text-surface-700">Diamonds</span>
                    </div>
                    <div class="mt-2 text-xl font-bold tracking-tight text-surface-900">
                        ₹{{ gold18kRate.toLocaleString('en-IN') }}<span class="text-xs font-normal text-surface-400">/g</span>
                    </div>
                    <div class="mt-1 text-[11px] text-surface-400">
                        Fine studded ornaments
                    </div>
                </div>

                <!-- Silver 925 -->
                <div class="rounded-lg border border-surface-200 border-t-2 border-t-slate-500 bg-white p-4 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-surface-500">Silver 925 (Fine)</span>
                        <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-700">Sterling</span>
                    </div>
                    <div class="mt-2 text-xl font-bold tracking-tight text-slate-800">
                        ₹{{ silverSellRate.toLocaleString('en-IN') }}<span class="text-xs font-normal text-surface-400">/g</span>
                    </div>
                    <div class="mt-1 text-[11px] text-surface-400">
                        ₹{{ (silverSellRate * 1000).toLocaleString('en-IN') }}/kg
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 3. STORE QUICK LAUNCHPAD                   -->
            <!-- ========================================== -->
            <div class="erp-panel border border-surface-200 bg-white p-5 shadow-2xs">
                <div class="flex items-center justify-between border-b border-surface-100 pb-3">
                    <div class="flex items-center gap-2">
                        <i class="pi pi-bolt text-surface-600 text-xs flex-shrink-0"></i>
                        <span class="text-xs font-bold uppercase tracking-wider text-surface-700 leading-none">Counter Quick Launchpad</span>
                    </div>
                    <span class="text-[11px] text-surface-400 font-medium">Daily Counter Operations</span>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-4 xl:grid-cols-8">
                    <template v-for="item in quickLinks" :key="item.label">
                        <Link
                            v-if="item.permission"
                            :href="item.href"
                            class="erp-quick-link group flex flex-col justify-between rounded-lg border border-surface-200 bg-surface-50/60 p-3.5 transition shadow-xs hover:shadow-sm"
                        >
                            <!-- Top Row: Icon Container + Badge -->
                            <div class="flex items-center justify-between">
                                <div class="erp-quick-link__icon flex h-9 w-9 items-center justify-center rounded-lg text-xs" :class="item.iconBoxClass">
                                    <i :class="item.icon"></i>
                                </div>
                                <span class="erp-quick-link__badge rounded-md px-1.5 py-0.5 text-[9.5px] font-bold" :class="item.badgeClass">
                                    {{ item.badge }}
                                </span>
                            </div>

                            <!-- Bottom Row: Title + Description -->
                            <div class="mt-3">
                                <div class="flex items-center justify-between">
                                    <div class="text-xs font-bold text-surface-900 truncate">{{ item.label }}</div>
                                    <i class="pi pi-arrow-right text-[8px] text-surface-400 opacity-0 group-hover:opacity-100 group-hover:translate-x-0.5 transition-all"></i>
                                </div>
                                <div class="mt-0.5 truncate text-[10.5px] text-surface-500">{{ item.desc }}</div>
                            </div>
                        </Link>
                    </template>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 4. PERFORMANCE & KPI CARDS                 -->
            <!-- ========================================== -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <!-- My Billing Today -->
                <div class="rounded-lg border border-surface-200 border-t-2 border-t-surface-900 bg-white p-4 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-surface-500">My Sales Today</span>
                        <i class="pi pi-receipt text-xs text-surface-400"></i>
                    </div>
                    <div class="mt-2 text-xl font-bold tracking-tight text-surface-900">{{ formatCurrency(metrics?.my_sales) }}</div>
                    <div class="mt-1 flex items-center gap-1 text-[11px] text-surface-400">
                        <span class="font-semibold text-surface-700">{{ metrics?.my_invoices || 0 }} bills</span> generated today
                    </div>
                </div>

                <!-- My Collections -->
                <div class="rounded-lg border border-surface-200 border-t-2 border-t-emerald-600 bg-white p-4 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-surface-500">My Collections</span>
                        <i class="pi pi-check-circle text-xs text-emerald-600"></i>
                    </div>
                    <div class="mt-2 text-xl font-bold tracking-tight text-emerald-700">{{ formatCurrency(metrics?.my_collections) }}</div>
                    <div class="mt-1 text-[11px] font-semibold text-emerald-600">
                        Cash & digital receipts received
                    </div>
                </div>

                <!-- Month-to-date Personal Performance -->
                <div class="rounded-lg border border-surface-200 border-t-2 border-t-sky-600 bg-white p-4 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-surface-500">Month-to-Date Sales</span>
                        <i class="pi pi-calendar text-xs text-sky-600"></i>
                    </div>
                    <div class="mt-2 text-xl font-bold tracking-tight text-surface-900">{{ formatCurrency(metrics?.my_month_sales) }}</div>
                    <div class="mt-1 text-[11px] text-surface-400">
                        <span class="font-semibold text-surface-700">{{ metrics?.my_month_invoices || 0 }} bills</span> this month
                    </div>
                </div>

                <!-- Ready for Delivery / Workshop -->
                <div class="rounded-lg border border-surface-200 border-t-2 border-t-amber-500 bg-white p-4 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-surface-500">Ready for Delivery</span>
                        <i class="pi pi-sparkles text-xs text-amber-600"></i>
                    </div>
                    <div class="mt-2 text-xl font-bold tracking-tight text-amber-800">{{ metrics?.ready_items || 0 }} items</div>
                    <div class="mt-1 text-[11px] font-semibold text-amber-700">
                        {{ metrics?.in_production || 0 }} currently in workshop
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 5. INTERACTIVE LIVE COUNTER CALCULATOR     -->
            <!-- ========================================== -->
            <div class="erp-panel border border-surface-200 bg-white p-5 shadow-2xs">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-surface-100 pb-3">
                    <div class="flex items-center gap-2">
                        <i class="pi pi-calculator text-amber-600 text-xs flex-shrink-0"></i>
                        <span class="text-xs font-bold uppercase tracking-wider text-surface-700 leading-none">Instant Counter Quote Estimator</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-surface-500">
                        <span>Give live price quotes to walk-in customers in seconds.</span>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-12">
                    <!-- Left: Input Controls (7 cols) -->
                    <div class="space-y-3.5 md:col-span-7">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <!-- Metal & Purity Selector -->
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-surface-700">Metal & Purity</label>
                                <select
                                    v-model="estimator.selectedPurity"
                                    class="w-full rounded-md border border-surface-300 bg-white px-3 py-2 text-xs font-semibold text-surface-900 shadow-2xs focus:border-surface-900 focus:outline-none"
                                >
                                    <option v-for="p in estimatorPurities" :key="p.value" :value="p.value">
                                        {{ p.label }} (₹{{ p.rate.value.toLocaleString('en-IN') }}/g)
                                    </option>
                                </select>
                            </div>

                            <!-- Gross Weight in Grams -->
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-surface-700">Gross Weight (grams)</label>
                                <InputNumber
                                    v-model="estimator.grossWeight"
                                    :minFractionDigits="3"
                                    :maxFractionDigits="3"
                                    :min="0.001"
                                    suffix=" g"
                                    class="w-full"
                                    placeholder="e.g. 10.500"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <!-- Making Charge -->
                            <div>
                                <div class="mb-1 flex items-center justify-between">
                                    <label class="text-xs font-semibold text-surface-700">Making Charges</label>
                                    <div class="flex items-center gap-1.5 text-[10px]">
                                        <button
                                            type="button"
                                            class="rounded px-1.5 py-0.5 font-bold transition cursor-pointer"
                                            :class="estimator.makingChargeType === 'PERCENT' ? 'bg-surface-900 text-white' : 'bg-surface-100 text-surface-600'"
                                            @click="estimator.makingChargeType = 'PERCENT'"
                                        >
                                            % Rate
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded px-1.5 py-0.5 font-bold transition cursor-pointer"
                                            :class="estimator.makingChargeType === 'PER_GRAM' ? 'bg-surface-900 text-white' : 'bg-surface-100 text-surface-600'"
                                            @click="estimator.makingChargeType = 'PER_GRAM'"
                                        >
                                            ₹/gram
                                        </button>
                                    </div>
                                </div>
                                <InputNumber
                                    v-model="estimator.makingChargeValue"
                                    :suffix="estimator.makingChargeType === 'PERCENT' ? ' %' : ' ₹/g'"
                                    :min="0"
                                    class="w-full"
                                />
                            </div>

                            <!-- GST Toggle -->
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-surface-700">Tax Breakdown</label>
                                <div class="flex h-[38px] items-center justify-between rounded-md border border-surface-300 bg-surface-50 px-3">
                                    <span class="text-xs font-medium text-surface-700">Include GST (3%)</span>
                                    <input
                                        v-model="estimator.includeGst"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-surface-300 text-surface-900 focus:ring-surface-900 cursor-pointer"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Live Quote Summary Card (5 cols) -->
                    <div class="flex flex-col justify-between rounded-xl border border-amber-200 bg-amber-50/60 p-4.5 md:col-span-5 shadow-xs">
                        <div>
                            <div class="flex items-center justify-between border-b border-amber-200/80 pb-2.5">
                                <span class="text-xs font-bold text-amber-900 uppercase tracking-wider">Estimated Retail Quote</span>
                                <span class="rounded bg-amber-200/80 px-2 py-0.5 font-mono text-[10px] font-bold text-amber-950">LIVE</span>
                            </div>

                            <div class="mt-3 space-y-1.5 text-xs">
                                <div class="flex items-center justify-between text-surface-600">
                                    <span>Metal Cost ({{ Number(estimator.grossWeight || 0).toFixed(3) }}g &times; ₹{{ currentEstimatorRate.toLocaleString('en-IN') }})</span>
                                    <span class="font-semibold text-surface-900">₹{{ estimatedMetalAmount.toLocaleString('en-IN') }}</span>
                                </div>
                                <div class="flex items-center justify-between text-surface-600">
                                    <span>Making Charges ({{ estimator.makingChargeType === 'PERCENT' ? `${estimator.makingChargeValue}%` : `₹${estimator.makingChargeValue}/g` }})</span>
                                    <span class="font-semibold text-surface-900">₹{{ estimatedMakingAmount.toLocaleString('en-IN') }}</span>
                                </div>
                                <div v-if="estimator.includeGst" class="flex items-center justify-between text-surface-600">
                                    <span>GST (3% Standard)</span>
                                    <span class="font-semibold text-surface-900">₹{{ estimatedGstAmount.toLocaleString('en-IN') }}</span>
                                </div>

                                <div class="border-t border-amber-200/80 pt-2 flex items-center justify-between">
                                    <span class="text-sm font-bold text-surface-900">Estimated Total:</span>
                                    <span class="text-lg font-extrabold text-amber-900">₹{{ estimatedTotalQuote.toLocaleString('en-IN') }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Quote Actions -->
                        <div class="mt-4 flex items-center gap-2 pt-2 border-t border-amber-200/60">
                            <Button
                                :label="quoteCopied ? 'Copied to Clipboard!' : 'Copy Quote'"
                                :icon="quoteCopied ? 'pi pi-check' : 'pi pi-copy'"
                                size="small"
                                outlined
                                severity="secondary"
                                class="!border-amber-400 !text-amber-900 hover:!bg-amber-100 flex-1 text-xs"
                                @click="copyQuoteToClipboard"
                            />
                            <Link v-if="can.manage_invoices" :href="route('invoices.create')" class="flex-1">
                                <Button label="Create Bill" icon="pi pi-arrow-right" size="small" class="!bg-surface-900 !text-white !border-surface-900 hover:!bg-surface-800 w-full text-xs" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 6. 2-COLUMN MAIN WORKFLOW LAYOUT           -->
            <!-- ========================================== -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
                <!-- ========================================== -->
                <!-- LEFT COLUMN (7 cols)                       -->
                <!-- ========================================== -->
                <div class="space-y-4 lg:col-span-7">
                    <!-- Desk 1: Orders Ready for Customer Collection -->
                    <div class="erp-panel border border-surface-200 bg-white shadow-2xs">
                        <div class="flex items-center justify-between border-b border-surface-200 bg-surface-50/50 px-5 py-3.5">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-gift text-emerald-600 text-xs flex-shrink-0"></i>
                                <span class="text-xs font-bold uppercase tracking-wider text-surface-700 leading-none">Ready for Customer Pickup</span>
                            </div>
                            <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-200">
                                {{ ready_for_delivery?.length || 0 }} items finished
                            </span>
                        </div>

                        <div class="divide-y divide-surface-100">
                            <template v-if="ready_for_delivery?.length">
                                <div
                                    v-for="item in ready_for_delivery"
                                    :key="item.id"
                                    class="flex items-center justify-between gap-3 px-5 py-3.5 transition hover:bg-surface-50/70"
                                >
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-surface-900 text-xs">{{ item.design_name }}</span>
                                            <span class="inline-flex items-center gap-1 rounded-md border border-emerald-300 bg-emerald-50 px-2 py-0.5 text-[9.5px] font-bold text-emerald-700">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                READY
                                            </span>
                                        </div>
                                        <div class="mt-0.5 flex flex-wrap items-center gap-2 text-[11px] text-surface-500">
                                            <span class="font-medium text-surface-700">{{ item.customer_name }}</span>
                                            <span v-if="item.finished_weight > 0">&bull; {{ formatWeight(item.finished_weight) }}</span>
                                            <span v-else-if="item.target_weight > 0">&bull; {{ formatWeight(item.target_weight) }}</span>
                                            <span v-if="item.customer_phone">&bull; {{ item.customer_phone }}</span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-1.5">
                                        <Button
                                            v-if="item.customer_phone"
                                            icon="pi pi-whatsapp"
                                            label="Notify"
                                            size="small"
                                            severity="success"
                                            class="!h-8 !px-2.5 !text-xs !bg-emerald-600 hover:!bg-emerald-700"
                                            title="Send WhatsApp pickup reminder"
                                            @click="sendOrderReadyWhatsApp(item)"
                                        />
                                        <Link v-if="can.manage_invoices" :href="route('invoices.create')">
                                            <Button label="Bill" icon="pi pi-file-edit" size="small" outlined severity="secondary" class="!h-8 !px-2.5 !text-xs" />
                                        </Link>
                                    </div>
                                </div>
                            </template>

                            <div v-else class="py-8 text-center text-xs text-surface-400">
                                <i class="pi pi-check-circle text-2xl text-surface-300 block mb-1"></i>
                                No completed custom orders waiting for pickup right now.
                            </div>
                        </div>
                    </div>

                    <!-- Desk 2: Workshop / In-Production Pipeline -->
                    <div class="erp-panel border border-surface-200 bg-white shadow-2xs">
                        <div class="flex items-center justify-between border-b border-surface-200 bg-surface-50/50 px-5 py-3.5">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-wrench text-amber-600 text-xs flex-shrink-0"></i>
                                <span class="text-xs font-bold uppercase tracking-wider text-surface-700 leading-none">Workshop & Custom Orders Pipeline</span>
                            </div>
                            <Link v-if="can.create_order || can.manage_orders" :href="route('orders.index')" class="text-xs font-bold text-surface-700 hover:text-surface-900 flex items-center gap-1">
                                Open Orders <i class="pi pi-arrow-right text-[10px]"></i>
                            </Link>
                        </div>

                        <div class="divide-y divide-surface-100">
                            <template v-if="attention_items?.length">
                                <div
                                    v-for="item in attention_items"
                                    :key="item.id"
                                    class="flex items-center justify-between gap-3 px-5 py-3.5 transition hover:bg-surface-50/70"
                                >
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-surface-900 text-xs">{{ item.design_name }}</span>
                                            <Tag :value="item.status" :severity="statusSeverity(item.status)" class="!text-[9.5px] !font-bold" />
                                            <span v-if="item.is_overdue" class="rounded bg-rose-50 px-1.5 py-0.2 text-[9px] font-bold text-rose-700 border border-rose-300">
                                                OVERDUE
                                            </span>
                                        </div>
                                        <div class="mt-0.5 text-[11px] text-surface-500">
                                            <span>{{ item.customer_name }}</span>
                                            <span v-if="item.due_date">&bull; Promised: {{ item.due_date }}</span>
                                        </div>
                                    </div>

                                    <div class="text-right">
                                        <span class="font-mono text-xs text-surface-400">#{{ item.order_id || item.id }}</span>
                                    </div>
                                </div>
                            </template>

                            <div v-else class="py-8 text-center text-xs text-surface-400">
                                No custom orders needing follow-up at the moment.
                            </div>
                        </div>
                    </div>

                    <!-- Desk 3: My Recent Invoices Table -->
                    <div class="erp-panel border border-surface-200 bg-white shadow-2xs">
                        <div class="flex items-center justify-between border-b border-surface-200 bg-surface-50/50 px-5 py-3.5">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-receipt text-surface-500 text-xs flex-shrink-0"></i>
                                <span class="text-xs font-bold uppercase tracking-wider text-surface-700 leading-none">My Recent Bills</span>
                            </div>
                            <Link v-if="can.manage_invoices" :href="route('invoices.index')" class="text-xs font-bold text-surface-700 hover:text-surface-900 flex items-center gap-1">
                                View all bills <i class="pi pi-arrow-right text-[10px]"></i>
                            </Link>
                        </div>

                        <div class="erp-native-table !rounded-none !border-0 !shadow-none">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="border-b border-surface-200 bg-surface-50 text-[11px] font-semibold text-surface-500 uppercase tracking-wider">
                                        <th class="px-5 py-3 font-semibold">Invoice #</th>
                                        <th class="px-5 py-3 font-semibold">Customer</th>
                                        <th class="px-5 py-3 font-semibold">Status</th>
                                        <th class="px-5 py-3 font-semibold text-right">Amount</th>
                                        <th class="px-5 py-3 font-semibold text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-surface-100">
                                    <tr v-for="inv in recent_invoices" :key="inv.id" class="transition hover:bg-surface-50/70">
                                        <td class="px-5 py-3.5">
                                            <span class="inline-flex items-center rounded-md border border-surface-200 bg-surface-50 px-2 py-0.5 font-mono text-xs font-semibold text-surface-900">
                                                {{ inv.invoice_number }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <div class="font-bold text-surface-900">{{ inv.customer_name }}</div>
                                            <div class="text-[10px] text-surface-400">{{ formatReminderDate(inv.date) }}</div>
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <Tag :value="inv.status" :severity="inv.status === 'CANCELLED' ? 'danger' : 'success'" class="!text-[9.5px] !font-bold" />
                                        </td>
                                        <td class="px-5 py-3.5 text-right font-bold text-surface-900 text-sm">
                                            {{ formatCurrency(inv.total_amount) }}
                                        </td>
                                        <td class="px-5 py-3.5 text-right">
                                            <a
                                                :href="route('invoices.print', inv.id)"
                                                target="_blank"
                                                class="inline-flex items-center gap-1 rounded-md border border-surface-300 bg-white px-2.5 py-1 text-xs font-medium text-surface-800 shadow-2xs hover:bg-surface-900 hover:text-white transition"
                                            >
                                                <i class="pi pi-print text-[11px]"></i>
                                                <span>Print</span>
                                            </a>
                                        </td>
                                    </tr>

                                    <tr v-if="!recent_invoices?.length">
                                        <td colspan="5" class="px-5 py-8 text-center text-xs text-surface-400">
                                            You haven't generated any invoices yet today.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- RIGHT COLUMN (5 cols)                      -->
                <!-- ========================================== -->
                <div class="space-y-4 lg:col-span-5">
                    <!-- 1. CRM Customer Celebrations -->
                    <div class="erp-panel border border-surface-200 bg-white p-5 shadow-2xs">
                        <div class="flex items-center justify-between border-b border-surface-100 pb-3">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-heart text-rose-500 text-xs flex-shrink-0"></i>
                                <span class="text-xs font-bold uppercase tracking-wider text-surface-700 leading-none">Customer Celebrations</span>
                            </div>
                            <span class="rounded-md border border-surface-200 bg-surface-50 px-2 py-0.5 text-[10px] font-bold text-surface-700">
                                {{ customer_reminders?.length || 0 }} upcoming
                            </span>
                        </div>

                        <div class="mt-3 space-y-2">
                            <div
                                v-for="r in (customer_reminders || []).slice(0, 5)"
                                :key="`${r.customer_id}-${r.type}-${r.date}`"
                                class="flex items-center justify-between rounded-lg border p-3 text-xs transition shadow-xs"
                                :class="r.is_today ? 'bg-amber-50/80 border-amber-300' : 'bg-surface-50 border-surface-200'"
                            >
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="truncate font-bold text-surface-900">{{ r.customer_name }}</span>
                                        <Tag :value="r.is_today ? 'Today!' : r.type" :severity="r.is_today ? 'warn' : 'secondary'" class="!text-[9px] !font-bold" />
                                    </div>
                                    <div class="mt-0.5 text-[10px] text-surface-500">
                                        {{ formatReminderDate(r.date) }} &bull; {{ r.mobile || 'No mobile' }}
                                    </div>
                                </div>

                                <Button
                                    v-if="r.mobile"
                                    icon="pi pi-whatsapp"
                                    size="small"
                                    severity="success"
                                    class="!h-7 !w-7 !p-0 hover:scale-105 transition"
                                    title="Send WhatsApp Greeting"
                                    @click="sendWhatsAppWish(r)"
                                />
                            </div>

                            <div v-if="!customer_reminders?.length" class="py-6 text-center text-xs text-surface-400">
                                No customer birthdays or anniversaries in the next 7 days.
                            </div>
                        </div>
                    </div>

                    <!-- 2. Showroom Tasks & Duties -->
                    <div class="erp-panel border border-surface-200 bg-white p-5 shadow-2xs">
                        <div class="flex items-center justify-between border-b border-surface-100 pb-3">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-check-circle text-sky-600 text-xs flex-shrink-0"></i>
                                <span class="text-xs font-bold uppercase tracking-wider text-surface-700 leading-none">My Assigned Tasks</span>
                            </div>
                            <Link :href="route('tasks.index')" class="text-xs font-bold text-surface-700 hover:text-surface-900">
                                Task Board &rarr;
                            </Link>
                        </div>

                        <div class="mt-3 space-y-2.5">
                            <div
                                v-for="task in my_tasks"
                                :key="task.id"
                                class="rounded-lg border border-surface-200 bg-surface-50 p-3 text-xs transition hover:border-surface-300 shadow-xs"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-bold text-surface-900">{{ task.title }}</span>
                                            <span class="rounded px-1.5 py-0.2 text-[9px]" :class="taskPriorityClass(task.priority)">
                                                {{ task.priority }}
                                            </span>
                                        </div>
                                        <p v-if="task.description" class="mt-1 line-clamp-1 text-[10.5px] text-surface-500">
                                            {{ task.description }}
                                        </p>
                                    </div>

                                    <Button
                                        icon="pi pi-check"
                                        size="small"
                                        outlined
                                        severity="secondary"
                                        class="!h-6 !w-6 !p-0 flex-shrink-0"
                                        title="Mark Complete"
                                        @click="markTaskComplete(task)"
                                    />
                                </div>

                                <!-- Subtasks checklist if available -->
                                <div v-if="task.checklist?.length" class="mt-2.5 space-y-1.5 border-t border-surface-200/60 pt-2">
                                    <div
                                        v-for="sub in task.checklist.slice(0, 3)"
                                        :key="sub.id"
                                        class="flex items-center gap-2 text-[11px]"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="sub.is_completed"
                                            class="h-3.5 w-3.5 rounded border-surface-300 text-surface-900 cursor-pointer"
                                            @change="toggleTaskSubtask(task, sub.id)"
                                        />
                                        <span :class="sub.is_completed ? 'line-through text-surface-400' : 'text-surface-700'">
                                            {{ sub.text }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div v-if="!my_tasks?.length" class="py-6 text-center text-xs text-surface-400">
                                <i class="pi pi-check-circle text-lg text-emerald-500 block mb-1"></i>
                                All assigned tasks are complete! Great job.
                            </div>
                        </div>
                    </div>

                    <!-- 3. Gold Purity & Counter Reference Guide -->
                    <div class="erp-panel border border-surface-200 bg-white p-5 shadow-2xs">
                        <div class="flex items-center justify-between border-b border-surface-100 pb-3">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-info-circle text-surface-500 text-xs flex-shrink-0"></i>
                                <span class="text-xs font-bold uppercase tracking-wider text-surface-700 leading-none">Hallmark Purity Reference</span>
                            </div>
                            <span class="text-[10px] text-surface-400 font-semibold">BIS Standard</span>
                        </div>

                        <div class="mt-3 divide-y divide-surface-100 text-xs">
                            <div class="flex items-center justify-between py-2">
                                <span class="font-semibold text-surface-800">24 Karat (999)</span>
                                <span class="text-surface-500">99.9% Pure Gold</span>
                            </div>
                            <div class="flex items-center justify-between py-2">
                                <span class="font-semibold text-amber-800">22 Karat (916 Hallmark)</span>
                                <span class="text-surface-500">91.6% Fine Jewellery</span>
                            </div>
                            <div class="flex items-center justify-between py-2">
                                <span class="font-semibold text-surface-800">18 Karat (750 Hallmark)</span>
                                <span class="text-surface-500">75.0% Diamond Ornaments</span>
                            </div>
                            <div class="flex items-center justify-between py-2">
                                <span class="font-semibold text-surface-800">14 Karat (585 Hallmark)</span>
                                <span class="text-surface-500">58.5% Daily Wear</span>
                            </div>
                            <div class="flex items-center justify-between py-2">
                                <span class="font-semibold text-slate-700">Silver (925 Sterling)</span>
                                <span class="text-surface-500">92.5% Silver Articles</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODAL: INSTANT BARCODE & STOCK SCANNER     -->
        <!-- ========================================== -->
        <Dialog v-model:visible="showScanModal" modal header="Instant Barcode & Stock Price Lookup" :style="{ width: '520px' }">
            <div class="space-y-4 pt-2">
                <div class="flex gap-2">
                    <InputText
                        v-model="scanQuery"
                        placeholder="Scan or type barcode (e.g. G00001, S00001)..."
                        class="w-full text-sm"
                        autofocus
                        @keyup.enter="performBarcodeSearch()"
                    />
                    <Button
                        label="Lookup"
                        icon="pi pi-search"
                        size="small"
                        :loading="isSearchingBarcode"
                        class="!bg-surface-900 !text-white"
                        @click="performBarcodeSearch()"
                    />
                </div>

                <!-- Error Message -->
                <div v-if="scanError" class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800">
                    {{ scanError }}
                </div>

                <!-- Scanned Result Card -->
                <div v-if="scannedItem" class="rounded-xl border border-surface-200 bg-surface-50/70 p-4 space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <span class="rounded bg-surface-900 px-2 py-0.5 font-mono text-[10px] font-bold text-white uppercase">
                                {{ scannedItem.item?.barcode }}
                            </span>
                            <h4 class="mt-1 text-sm font-bold text-surface-900">{{ scannedItem.item?.name }}</h4>
                            <p class="text-xs text-surface-500">{{ scannedItem.item?.category?.name || 'Jewellery' }} &bull; {{ scannedItem.item?.purity?.name || '22K' }}</p>
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-surface-400 block">Current Metal Rate</span>
                            <span class="text-sm font-bold text-amber-800">₹{{ scannedItem.billing?.rate?.toLocaleString('en-IN') }}/g</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 border-t border-surface-200 pt-3 text-center text-xs">
                        <div class="rounded-lg bg-white p-2 border border-surface-200">
                            <span class="text-[10px] text-surface-400 block">Gross Weight</span>
                            <span class="font-bold text-surface-900">{{ formatWeight(scannedItem.item?.gross_weight) }}</span>
                        </div>
                        <div class="rounded-lg bg-white p-2 border border-surface-200">
                            <span class="text-[10px] text-surface-400 block">Net Weight</span>
                            <span class="font-bold text-surface-900">{{ formatWeight(scannedItem.item?.net_weight || scannedItem.item?.gross_weight) }}</span>
                        </div>
                        <div class="rounded-lg bg-white p-2 border border-surface-200">
                            <span class="text-[10px] text-surface-400 block">Status</span>
                            <Tag :value="scannedItem.item?.status || 'IN_STOCK'" severity="success" class="!text-[9px]" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <Link v-if="can.manage_invoices" :href="route('invoices.create')">
                            <Button label="Add to Retail Bill" icon="pi pi-plus" size="small" class="!bg-surface-900 !text-white !border-surface-900 text-xs" />
                        </Link>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end pt-2">
                    <Button label="Close" text severity="secondary" size="small" @click="showScanModal = false" />
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>

