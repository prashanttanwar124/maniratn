<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm, usePage, router } from '@inertiajs/vue3';
import { computed, ref, reactive, watch } from 'vue';
import axios from 'axios';

import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
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
const toast = useToast();
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

const hasZeroRates = computed(() => goldSellRate.value <= 0 && silverSellRate.value <= 0);

// Rate Update Dialog & Form
const showRateDialog = ref(false);
const rateForm = useForm({
    gold_sell: parseFloat(props.rates?.gold_sell || 0),
    gold_buy: parseFloat(props.rates?.gold_buy || 0),
    silver_sell: parseFloat(props.rates?.silver_sell || 0),
});

const saveRates = () => {
    rateForm.post(route('dashboard.update-rates'), {
        preserveScroll: true,
        onSuccess: () => {
            showRateDialog.value = false;
        },
    });
};

// Quick Launchpad Items for Counter Staff (6 Balanced Symmetrical Cards)
const quickLinks = computed(() => [
    {
        label: 'New Retail Bill',
        href: route('invoices.create'),
        icon: 'pi pi-plus',
        badge: 'POS',
        badgeClass: 'bg-surface-900 text-white',
        iconBoxClass: 'bg-surface-900 text-white',
        desc: 'Fast billing & tax invoice',
    },
    {
        label: 'Sales Invoices',
        href: route('invoices.index'),
        icon: 'pi pi-file-check',
        badge: `${props.metrics?.my_invoices || 0} today`,
        badgeClass: 'bg-sky-50 text-sky-700 border border-sky-200',
        iconBoxClass: 'bg-sky-50 text-sky-700 border border-sky-200',
        desc: 'Browse & print bills',
    },
    {
        label: 'Custom Orders',
        href: route('orders.index'),
        icon: 'pi pi-wrench',
        badge: `${props.metrics?.ready_items || 0} Ready`,
        badgeClass: props.metrics?.ready_items > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-300 font-bold' : 'bg-surface-100 text-surface-700 border border-surface-200',
        iconBoxClass: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        desc: 'Pickup & karigar status',
    },
    {
        label: 'Customer Khata',
        href: route('customers.index'),
        icon: 'pi pi-users',
        badge: 'CRM',
        badgeClass: 'bg-amber-50 text-amber-800 border border-amber-200',
        iconBoxClass: 'bg-amber-50 text-amber-700 border border-amber-200',
        desc: 'Phonebook & balances',
    },
    {
        label: 'Showroom Tasks',
        href: route('tasks.index'),
        icon: 'pi pi-check-square',
        badge: `${props.my_tasks?.length || 0} Open`,
        badgeClass: props.my_tasks?.length > 0 ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-surface-100 text-surface-700 border border-surface-200',
        iconBoxClass: 'bg-rose-50 text-rose-700 border border-rose-200',
        desc: 'Daily store checklist',
    },
    {
        label: 'Attendance Terminal',
        href: route('attendance-terminal.show'),
        icon: 'pi pi-clock',
        badge: props.my_attendance?.status === 'PRESENT' ? 'In' : 'Shift',
        badgeClass: props.my_attendance?.status === 'PRESENT' ? 'bg-teal-50 text-teal-700 border border-teal-300 font-bold' : 'bg-amber-50 text-amber-700 border border-amber-300',
        iconBoxClass: 'bg-teal-50 text-teal-700 border border-teal-200',
        desc: 'Shift check-in / out',
    },
]);

// ----------------------------------------------------
// 🧮 LIVE COUNTER PRICE & QUOTE ESTIMATOR
// ----------------------------------------------------
const estimatorPurities = [
    { label: 'Gold 22K (916 Hallmark)', value: '22K', defaultRate: 7200 },
    { label: 'Gold 24K (999 Fine)', value: '24K', defaultRate: 7850 },
    { label: 'Gold 18K (750 Hallmark)', value: '18K', defaultRate: 5900 },
    { label: 'Silver 925 (Sterling)', value: 'SILVER', defaultRate: 95 },
];

const estimator = reactive({
    selectedPurity: '22K',
    grossWeight: 10.0,
    customRate: null,
    useCustomRate: false,
    makingChargeType: 'PERCENT', // 'PERCENT' | 'PER_GRAM'
    makingChargeValue: 12, // 12% or ₹450/g
    includeGst: true,
});

// Calculate official benchmark rate for chosen purity
const systemRateForPurity = computed(() => {
    if (estimator.selectedPurity === '24K') return goldSellRate.value;
    if (estimator.selectedPurity === '22K') return gold22kRate.value;
    if (estimator.selectedPurity === '18K') return gold18kRate.value;
    if (estimator.selectedPurity === 'SILVER') return silverSellRate.value;
    return gold22kRate.value;
});

// Effective rate: uses official rate if > 0, otherwise custom rate or fallback
const effectiveEstimatorRate = computed(() => {
    if (estimator.useCustomRate && Number(estimator.customRate || 0) > 0) {
        return Number(estimator.customRate);
    }
    if (systemRateForPurity.value > 0) {
        return systemRateForPurity.value;
    }
    if (Number(estimator.customRate || 0) > 0) {
        return Number(estimator.customRate);
    }
    // Default fallback if system rate is 0
    const fallback = estimatorPurities.find((p) => p.value === estimator.selectedPurity)?.defaultRate || 7200;
    return fallback;
});

const estimatedMetalAmount = computed(() => {
    const wt = Number(estimator.grossWeight || 0);
    return Math.round(wt * effectiveEstimatorRate.value);
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

const setMakingPreset = (type, val) => {
    estimator.makingChargeType = type;
    estimator.makingChargeValue = val;
};

const quoteCopied = ref(false);
const copyQuoteToClipboard = () => {
    const purityLabel = estimatorPurities.find((p) => p.value === estimator.selectedPurity)?.label || estimator.selectedPurity;
    const text = `*MANIRATN JEWELLERS - INSTANT QUOTE*\n✨ Metal: ${purityLabel}\n⚖️ Weight: ${Number(estimator.grossWeight || 0).toFixed(3)} g\n💰 Metal Rate: ₹${effectiveEstimatorRate.value.toLocaleString('en-IN')}/g\n💵 Metal Cost: ₹${estimatedMetalAmount.value.toLocaleString('en-IN')}\n🔨 Making Charges (${estimator.makingChargeType === 'PERCENT' ? `${estimator.makingChargeValue}%` : `₹${estimator.makingChargeValue}/g`}): ₹${estimatedMakingAmount.value.toLocaleString('en-IN')}\n🧾 GST (3%): ₹${estimatedGstAmount.value.toLocaleString('en-IN')}\n\n*⭐ Estimated Total: ₹${estimatedTotalQuote.value.toLocaleString('en-IN')}*\n\n_Note: Rate subject to market daily revision._`;

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

const clearScan = () => {
    scanQuery.value = '';
    scannedItem.value = null;
    scanError.value = '';
};

// ----------------------------------------------------
// 📱 WHATSAPP MESSAGING HELPERS
// ----------------------------------------------------
const sendWhatsAppWish = (reminder) => {
    const cleanMobile = String(reminder.mobile || '').replace(/\D/g, '');
    if (!cleanMobile) {
        toast.add({
            severity: 'warn',
            summary: 'Missing Mobile Number',
            detail: 'Customer does not have a valid mobile number for WhatsApp messaging.',
            life: 3000,
        });
        return;
    }
    const phone = cleanMobile.startsWith('91') ? cleanMobile : `91${cleanMobile}`;
    const greetingText = reminder.type === 'Birthday' ? 'Happy Birthday' : 'Happy Anniversary';
    const text = encodeURIComponent(
        `Dear ${reminder.customer_name},\n\nWarmest wishes on your ${greetingText} from all of us at Maniratn Jewellers! ✨🎂\n\nMay this special day bring you abundant happiness, health, and prosperity.\n\nVisit us to explore our latest fine jewellery collection!\n\nWarm regards,\nManiratn Jewellers`
    );
    window.open(`https://wa.me/${phone}?text=${text}`, '_blank');
};

const sendOrderReadyWhatsApp = (item) => {
    const cleanMobile = String(item.customer_phone || '').replace(/\D/g, '');
    if (!cleanMobile) {
        toast.add({
            severity: 'warn',
            summary: 'Missing Mobile Number',
            detail: 'Customer mobile number is missing for this custom order.',
            life: 3000,
        });
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
                            v-if="can.manage_daily_rates"
                            label="Market Rates"
                            icon="pi pi-pencil"
                            size="small"
                            outlined
                            severity="secondary"
                            class="!border-surface-300 hover:!border-surface-900"
                            @click="showRateDialog = true"
                        />

                        <Link v-if="can.manage_invoices" :href="route('invoices.create')">
                            <Button label="New Bill" icon="pi pi-plus" size="small" class="!bg-surface-900 !text-white !border-surface-900 hover:!bg-surface-800 shadow-xs" />
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Zero Rates Warning Banner -->
            <div v-if="hasZeroRates" class="flex items-center justify-between rounded-lg border border-amber-300 bg-amber-50/90 px-4 py-3 text-xs text-amber-900 shadow-xs">
                <div class="flex items-center gap-2.5">
                    <i class="pi pi-exclamation-triangle text-amber-700 text-sm flex-shrink-0"></i>
                    <span>
                        <strong>Market Rates Not Set:</strong> Today's live gold & silver benchmark rates are currently ₹0.
                        <span v-if="can.manage_daily_rates">Click <strong>Update Rates</strong> to publish today's gold rates.</span>
                        <span v-else>Estimator will use standard benchmark estimates until updated.</span>
                    </span>
                </div>
                <Button
                    v-if="can.manage_daily_rates"
                    label="Update Rates"
                    icon="pi pi-pencil"
                    size="small"
                    class="!h-7 !text-xs !bg-amber-800 !text-white !border-amber-800 hover:!bg-amber-900 flex-shrink-0"
                    @click="showRateDialog = true"
                />
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
                        ₹{{ goldSellRate > 0 ? goldSellRate.toLocaleString('en-IN') : '7,850*' }}<span class="text-xs font-normal text-surface-400">/g</span>
                    </div>
                    <div class="mt-1 text-[11px] text-surface-400">
                        Buy (Old Gold): <strong class="text-surface-700">₹{{ goldBuyRate > 0 ? goldBuyRate.toLocaleString('en-IN') : '7,690*' }}/g</strong>
                    </div>
                </div>

                <!-- Gold 22K (916 Hallmark) -->
                <div class="rounded-lg border border-surface-200 border-t-2 border-t-[#c4922a] bg-white p-4 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-surface-500">Gold 22K (916)</span>
                        <span class="rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-800 border border-amber-200">Hallmark</span>
                    </div>
                    <div class="mt-2 text-xl font-bold tracking-tight text-amber-700">
                        ₹{{ gold22kRate > 0 ? gold22kRate.toLocaleString('en-IN') : '7,195*' }}<span class="text-xs font-normal text-surface-400">/g</span>
                    </div>
                    <div class="mt-1 text-[11px] text-surface-400">
                        Daily retail hallmark standard
                    </div>
                </div>

                <!-- Gold 18K (750 Hallmark) -->
                <div class="rounded-lg border border-surface-200 border-t-2 border-t-amber-600 bg-white p-4 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-surface-500">Gold 18K (750)</span>
                        <span class="rounded bg-surface-100 px-1.5 py-0.5 text-[10px] font-bold text-surface-700">Diamonds</span>
                    </div>
                    <div class="mt-2 text-xl font-bold tracking-tight text-surface-900">
                        ₹{{ gold18kRate > 0 ? gold18kRate.toLocaleString('en-IN') : '5,888*' }}<span class="text-xs font-normal text-surface-400">/g</span>
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
                        ₹{{ silverSellRate > 0 ? silverSellRate.toLocaleString('en-IN') : '95*' }}<span class="text-xs font-normal text-surface-400">/g</span>
                    </div>
                    <div class="mt-1 text-[11px] text-surface-400">
                        ₹{{ (silverSellRate > 0 ? silverSellRate * 1000 : 95000).toLocaleString('en-IN') }}/kg
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

                <!-- Symmetrical 6-Column Grid: Always 100% full width with zero gaps -->
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    <Link
                        v-for="item in quickLinks"
                        :key="item.label"
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
            <!-- 5. INSTANT COUNTER QUOTE ESTIMATOR         -->
            <!-- ========================================== -->
            <div class="erp-panel border border-surface-200 bg-white p-5 shadow-2xs">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-surface-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="flex h-6 w-6 items-center justify-center rounded-md bg-amber-500 text-white text-xs">
                            <i class="pi pi-calculator"></i>
                        </div>
                        <span class="text-xs font-bold uppercase tracking-wider text-surface-800">Instant Counter Quote Estimator</span>
                    </div>
                    <div class="text-[11.5px] text-surface-500">
                        Instant walk-in customer pricing &bull; Auto-calculates metal, making & GST
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-5 lg:grid-cols-12">
                    <!-- Left Column: Input Form (7 cols) -->
                    <div class="space-y-4 lg:col-span-7">
                        <!-- 1. Purity Selector (Segmented Cards) -->
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-surface-700">Select Metal & Purity</label>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                <button
                                    v-for="p in estimatorPurities"
                                    :key="p.value"
                                    type="button"
                                    class="flex flex-col items-start rounded-lg border p-2.5 text-left transition cursor-pointer"
                                    :class="estimator.selectedPurity === p.value ? 'border-surface-900 bg-surface-900 text-white shadow-xs' : 'border-surface-200 bg-surface-50/70 hover:border-surface-300 text-surface-800'"
                                    @click="estimator.selectedPurity = p.value"
                                >
                                    <span class="text-xs font-bold">{{ p.value === 'SILVER' ? 'Silver 925' : p.value }}</span>
                                    <span class="text-[10px] mt-0.5" :class="estimator.selectedPurity === p.value ? 'text-amber-300' : 'text-amber-800 font-semibold'">
                                        ₹{{ (systemRateForPurity > 0 && estimator.selectedPurity === p.value ? systemRateForPurity : p.defaultRate).toLocaleString('en-IN') }}/g
                                    </span>
                                </button>
                            </div>
                        </div>

                        <!-- 2. Gross Weight & Custom Rate -->
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-surface-700">Gross Weight</label>
                                <InputNumber
                                    v-model="estimator.grossWeight"
                                    :minFractionDigits="3"
                                    :maxFractionDigits="3"
                                    :min="0.001"
                                    suffix=" g"
                                    class="w-full text-xs font-semibold"
                                    placeholder="e.g. 10.000 g"
                                />
                            </div>

                            <div>
                                <div class="mb-1.5 flex items-center justify-between">
                                    <label class="text-xs font-semibold text-surface-700">Effective Rate per Gram</label>
                                    <button
                                        type="button"
                                        class="text-[10px] font-bold text-amber-800 hover:underline cursor-pointer"
                                        @click="estimator.useCustomRate = !estimator.useCustomRate"
                                    >
                                        {{ estimator.useCustomRate ? 'Use Official' : 'Edit Rate' }}
                                    </button>
                                </div>
                                <div v-if="estimator.useCustomRate">
                                    <InputNumber
                                        v-model="estimator.customRate"
                                        mode="currency"
                                        currency="INR"
                                        locale="en-IN"
                                        class="w-full text-xs font-semibold"
                                        placeholder="Enter custom rate/g"
                                    />
                                </div>
                                <div v-else class="flex h-[42px] items-center justify-between rounded-lg border border-surface-200 bg-surface-50 px-3 text-xs font-bold text-surface-900">
                                    <span>₹{{ effectiveEstimatorRate.toLocaleString('en-IN') }}/g</span>
                                    <span class="text-[10px] text-surface-400 font-normal">
                                        {{ systemRateForPurity > 0 ? 'Live Daily' : 'Standard Ref' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Making Charges & Quick Presets -->
                        <div>
                            <div class="mb-1.5 flex items-center justify-between">
                                <label class="text-xs font-semibold text-surface-700">Making Charges</label>
                                <div class="erp-segmented-control !h-6">
                                    <button
                                        type="button"
                                        :class="{ active: estimator.makingChargeType === 'PERCENT' }"
                                        class="!px-2 !py-0 !text-[10px]"
                                        @click="estimator.makingChargeType = 'PERCENT'"
                                    >
                                        % of Metal
                                    </button>
                                    <button
                                        type="button"
                                        :class="{ active: estimator.makingChargeType === 'PER_GRAM' }"
                                        class="!px-2 !py-0 !text-[10px]"
                                        @click="estimator.makingChargeType = 'PER_GRAM'"
                                    >
                                        ₹/gram
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <InputNumber
                                    v-model="estimator.makingChargeValue"
                                    :suffix="estimator.makingChargeType === 'PERCENT' ? ' %' : ' ₹/g'"
                                    :min="0"
                                    class="w-full text-xs font-semibold"
                                />

                                <!-- Quick Presets -->
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <template v-if="estimator.makingChargeType === 'PERCENT'">
                                        <button
                                            v-for="pct in [8, 10, 12, 15]"
                                            :key="pct"
                                            type="button"
                                            class="rounded-md border border-surface-200 bg-surface-50 px-2 py-1.5 text-[11px] font-semibold text-surface-700 hover:border-surface-900 hover:bg-surface-900 hover:text-white transition cursor-pointer"
                                            :class="estimator.makingChargeValue === pct ? '!bg-surface-900 !text-white !border-surface-900' : ''"
                                            @click="setMakingPreset('PERCENT', pct)"
                                        >
                                            {{ pct }}%
                                        </button>
                                    </template>
                                    <template v-else>
                                        <button
                                            v-for="amt in [350, 450, 550, 650]"
                                            :key="amt"
                                            type="button"
                                            class="rounded-md border border-surface-200 bg-surface-50 px-2 py-1.5 text-[11px] font-semibold text-surface-700 hover:border-surface-900 hover:bg-surface-900 hover:text-white transition cursor-pointer"
                                            :class="estimator.makingChargeValue === amt ? '!bg-surface-900 !text-white !border-surface-900' : ''"
                                            @click="setMakingPreset('PER_GRAM', amt)"
                                        >
                                            ₹{{ amt }}
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- 4. Tax / GST Toggle -->
                        <div class="flex items-center justify-between rounded-lg border border-surface-200 bg-surface-50/70 p-3">
                            <div>
                                <span class="text-xs font-bold text-surface-900">Include GST (3%)</span>
                                <span class="text-[10px] text-surface-500 block">Standard precious jewellery tax breakdown</span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input v-model="estimator.includeGst" type="checkbox" class="sr-only peer" />
                                <div class="w-9 h-5 bg-surface-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-surface-900"></div>
                            </label>
                        </div>
                    </div>

                    <!-- Right Column: Live Luxury Receipt Breakdown (5 cols) -->
                    <div class="flex flex-col justify-between rounded-xl border border-amber-300/80 bg-linear-to-b from-amber-50/70 to-amber-100/40 p-5 lg:col-span-5 shadow-xs">
                        <div>
                            <div class="flex items-center justify-between border-b border-amber-200 pb-3">
                                <div>
                                    <span class="text-xs font-extrabold text-amber-950 uppercase tracking-wider">Estimated Retail Quote</span>
                                    <div class="text-[10px] text-amber-800">{{ Number(estimator.grossWeight || 0).toFixed(3) }}g &bull; {{ estimator.selectedPurity }}</div>
                                </div>
                                <span class="rounded bg-amber-200/90 px-2 py-0.5 font-mono text-[10px] font-bold text-amber-950">
                                    ₹{{ effectiveEstimatorRate.toLocaleString('en-IN') }}/g
                                </span>
                            </div>

                            <div class="mt-4 space-y-2 text-xs">
                                <div class="flex items-center justify-between text-surface-600">
                                    <span>Metal Value ({{ Number(estimator.grossWeight || 0).toFixed(3) }}g &times; ₹{{ effectiveEstimatorRate.toLocaleString('en-IN') }})</span>
                                    <span class="font-bold text-surface-900">₹{{ estimatedMetalAmount.toLocaleString('en-IN') }}</span>
                                </div>
                                <div class="flex items-center justify-between text-surface-600">
                                    <span>Making Charges ({{ estimator.makingChargeType === 'PERCENT' ? `${estimator.makingChargeValue}%` : `₹${estimator.makingChargeValue}/g` }})</span>
                                    <span class="font-bold text-surface-900">₹{{ estimatedMakingAmount.toLocaleString('en-IN') }}</span>
                                </div>
                                <div v-if="estimator.includeGst" class="flex items-center justify-between text-surface-600">
                                    <span>GST (3% Standard)</span>
                                    <span class="font-bold text-surface-900">₹{{ estimatedGstAmount.toLocaleString('en-IN') }}</span>
                                </div>

                                <div class="border-t border-amber-200/90 pt-3 mt-3 flex items-baseline justify-between">
                                    <div>
                                        <span class="text-xs font-bold text-amber-950 uppercase tracking-wide">Estimated Total</span>
                                        <span class="text-[10px] text-surface-400 block">Incl. metal + making + tax</span>
                                    </div>
                                    <span class="text-2xl font-black text-amber-950">
                                        ₹{{ estimatedTotalQuote.toLocaleString('en-IN') }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="mt-5 space-y-2 pt-3 border-t border-amber-200/70">
                            <Button
                                :label="quoteCopied ? 'Copied WhatsApp Quote!' : 'Copy WhatsApp Quote'"
                                :icon="quoteCopied ? 'pi pi-check' : 'pi pi-copy'"
                                size="small"
                                outlined
                                severity="secondary"
                                class="!border-amber-400 !text-amber-950 hover:!bg-amber-100 w-full text-xs font-bold !h-9"
                                @click="copyQuoteToClipboard"
                            />
                            <Link v-if="can.manage_invoices" :href="route('invoices.create')" class="block">
                                <Button label="Create Retail Bill" icon="pi pi-arrow-right" size="small" class="!bg-surface-900 !text-white !border-surface-900 hover:!bg-surface-800 w-full text-xs font-bold !h-9" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 6. INLINE BARCODE & INVENTORY SCANNER      -->
            <!-- ========================================== -->
            <div class="erp-panel border border-surface-200 bg-white p-5 shadow-2xs">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-surface-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="flex h-6 w-6 items-center justify-center rounded-md bg-surface-900 text-white text-xs">
                            <i class="pi pi-qrcode"></i>
                        </div>
                        <span class="text-xs font-bold uppercase tracking-wider text-surface-800">Quick Barcode & Inventory Price Scanner</span>
                    </div>
                    <span class="text-[11px] text-surface-400">Scan or type product tag barcode to view live details</span>
                </div>

                <div class="mt-3">
                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <i class="pi pi-barcode absolute left-3 top-1/2 -translate-y-1/2 text-surface-400"></i>
                            <InputText
                                v-model="scanQuery"
                                placeholder="Scan with barcode scanner or enter tag number (e.g. G00001, S00001)..."
                                class="w-full !pl-9 text-xs"
                                @keyup.enter="performBarcodeSearch()"
                            />
                        </div>
                        <Button
                            label="Lookup"
                            icon="pi pi-search"
                            size="small"
                            :loading="isSearchingBarcode"
                            class="!bg-surface-900 !text-white text-xs"
                            @click="performBarcodeSearch()"
                        />
                        <Button
                            v-if="scannedItem || scanError"
                            label="Clear"
                            icon="pi pi-times"
                            size="small"
                            outlined
                            severity="secondary"
                            class="text-xs"
                            @click="clearScan()"
                        />
                    </div>

                    <!-- Error Alert -->
                    <div v-if="scanError" class="mt-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800">
                        {{ scanError }}
                    </div>

                    <!-- Scanned Product Result Card -->
                    <div v-if="scannedItem" class="mt-3 rounded-xl border border-surface-200 bg-surface-50/70 p-4 space-y-3 shadow-xs">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <span class="rounded bg-surface-900 px-2 py-0.5 font-mono text-[10px] font-bold text-white uppercase">
                                    {{ scannedItem.item?.barcode }}
                                </span>
                                <h4 class="mt-1.5 text-sm font-bold text-surface-900">{{ scannedItem.item?.name }}</h4>
                                <p class="text-xs text-surface-500">
                                    {{ scannedItem.item?.category?.name || 'Jewellery' }} &bull; {{ scannedItem.item?.purity?.name || '22K' }}
                                    <span v-if="scannedItem.item?.supplier">&bull; Supplier: {{ scannedItem.item?.supplier?.name }}</span>
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="text-[10px] text-surface-400 block">Benchmark Rate</span>
                                <span class="text-sm font-bold text-amber-800">₹{{ scannedItem.billing?.rate?.toLocaleString('en-IN') }}/g</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 border-t border-surface-200 pt-3 text-center text-xs">
                            <div class="rounded-lg bg-white p-2 border border-surface-200">
                                <span class="text-[10px] text-surface-400 block">Gross Weight</span>
                                <span class="font-bold text-surface-900">{{ formatWeight(scannedItem.item?.gross_weight) }}</span>
                            </div>
                            <div class="rounded-lg bg-white p-2 border border-surface-200">
                                <span class="text-[10px] text-surface-400 block">Net Weight</span>
                                <span class="font-bold text-surface-900">{{ formatWeight(scannedItem.item?.net_weight || scannedItem.item?.gross_weight) }}</span>
                            </div>
                            <div class="rounded-lg bg-white p-2 border border-surface-200">
                                <span class="text-[10px] text-surface-400 block">Making Charge</span>
                                <span class="font-bold text-surface-900">
                                    {{ scannedItem.item?.making_charge ? (scannedItem.item.making_charge_type === 'PERCENT' ? `${scannedItem.item.making_charge}%` : `₹${scannedItem.item.making_charge}/g`) : 'N/A' }}
                                </span>
                            </div>
                            <div class="rounded-lg bg-white p-2 border border-surface-200">
                                <span class="text-[10px] text-surface-400 block">Stock Status</span>
                                <Tag :value="scannedItem.item?.status || 'IN_STOCK'" severity="success" class="!text-[9px]" />
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-1">
                            <Link v-if="can.manage_invoices" :href="route('invoices.create')">
                                <Button label="Add to Retail Bill" icon="pi pi-plus" size="small" class="!bg-surface-900 !text-white text-xs" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 7. 2-COLUMN MAIN WORKFLOW LAYOUT           -->
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
                                        class="!h-6 !w-6 !p-0 flex-shrink-0 cursor-pointer"
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
        <!-- MODAL: UPDATE DAILY RATES                  -->
        <!-- ========================================== -->
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
                    <Button label="Save Rates" icon="pi pi-check" size="small" :loading="rateForm.processing" class="!bg-surface-900 !text-white" @click="saveRates" />
                </div>
            </template>
        </Dialog>

        <Toast position="top-right" />
    </AppLayout>
</template>

