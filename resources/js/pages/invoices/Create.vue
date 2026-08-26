<script setup>
import CustomerSelector from '@/components/CustomerSelector.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Divider from 'primevue/divider';
import InputGroup from 'primevue/inputgroup';
import InputNumber from 'primevue/inputnumber';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';
import Textarea from 'primevue/textarea';
import { computed, onMounted, ref, watch } from 'vue';
import { route } from 'ziggy-js';
import { formatIndianDateTime, toIndianDateInput, todayIndianDate } from '@/utils/indiaTime';

const props = defineProps({
    prefilledItems: {
        type: Array,
        default: () => [],
    },
    prefilledCustomer: {
        type: Object,
        default: () => null,
    },
    defaultGoldRate: {
        type: Number,
        default: 0,
    },
    defaultSilverRate: {
        type: Number,
        default: 0,
    },
    drafts: {
        type: Array,
        default: () => [],
    },
    draftToLoad: {
        type: Object,
        default: null,
    },
    lockCustomer: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const toast = useToast();
const isDayOpen = computed(() => Boolean(page.props.dayStatus?.is_open));
const barcodeInput = ref(null);
const discountTypeOptions = [
    { label: 'Amount', value: 'amount' },
    { label: 'Percentage', value: 'percentage' },
];

const makingChargeTypeOptions = [
    { label: '% (Percentage)', value: 'percentage' },
    { label: '₹ Flat (Lump sum)', value: 'flat' },
    { label: '₹/g (Per gram)', value: 'per_gram' },
];

const form = useForm({
    customer_id: props.prefilledCustomer?.id || null,
    date: todayIndianDate(),
    gold_rate: Number(props.defaultGoldRate || 0),
    silver_rate: Number(props.defaultSilverRate || 0),
    discount_type: 'amount',
    discount_value: 0,
    items:
        props.prefilledItems && props.prefilledItems.length > 0
            ? props.prefilledItems.map((item) => ({
                  type: 'order_item',
                  id: item.id,
                  description: item.item_name,
                  weight: parseFloat(item.finished_weight),
                  purity: item.purity,
                  metal_type: String(item.metal_type || 'GOLD').toUpperCase(),
                  rate_multiplier: 1,
                  rate: String(item.metal_type || 'GOLD').toUpperCase() === 'SILVER' ? Number(props.defaultSilverRate || 0) : Number(props.defaultGoldRate || 0),
                  making_charges: 0,
                  making_charge_type: 'per_gram',
                  final_price:
                      parseFloat(item.finished_weight || 0) *
                      (String(item.metal_type || 'GOLD').toUpperCase() === 'SILVER' ? Number(props.defaultSilverRate || 0) : Number(props.defaultGoldRate || 0)),
              }))
            : [],
    payment_cash: 0,
    payment_card: 0,
    card_note: '',
});

const scannedBarcode = ref('');
const isProcessing = ref(false);
const showDraftsDialog = ref(false);
const currentDraftId = ref(props.draftToLoad?.id || null);
const draftList = ref(props.drafts || []);
const selectedCustomerObj = ref(props.draftToLoad?.customerObj || props.prefilledCustomer || null);
const isValidatingDraftItems = ref(false);
const draftValidationFailed = ref(false);

const lockedCustomerName = computed(() => {
    return props.prefilledCustomer ? props.prefilledCustomer.name : '';
});

const onCustomerSelect = (customer) => {
    selectedCustomerObj.value = customer;
};

const isSilverOrderItem = (item) => {
    return item?.type === 'order_item' && String(item.metal_type || 'GOLD').toUpperCase() === 'SILVER';
};

const isSilverRateDependentItem = (item) => {
    if (!item) return false;
    if (item.type === 'silver_product') return item.pricing_mode === 'WEIGHT';
    return isSilverOrderItem(item);
};

const invalidDraftItemsCount = computed(() => form.items.filter((item) => item.draft_valid === false).length);
const hasInvalidDraftItems = computed(() => invalidDraftItemsCount.value > 0);

const validateDraftItems = async ({ showToast = false } = {}) => {
    if (form.items.length === 0) {
        draftValidationFailed.value = false;
        return;
    }

    isValidatingDraftItems.value = true;

    try {
        const response = await axios.post(route('invoices.drafts.validate'), {
            items: form.items,
        });

        form.items = response.data.items || [];
        form.items.forEach((item) => recalculateRow(item));
        draftValidationFailed.value = false;

        if (showToast && response.data.has_invalid_items) {
            toast.add({
                severity: 'warn',
                summary: 'Draft Needs Review',
                detail: `${invalidDraftItemsCount.value} drafted item(s) need attention before billing.`,
                life: 3000,
            });
        }
    } catch {
        draftValidationFailed.value = true;
        toast.add({
            severity: 'error',
            summary: 'Draft Check Failed',
            detail: 'We could not recheck draft items against live stock right now.',
            life: 2500,
        });
    } finally {
        isValidatingDraftItems.value = false;
    }
};

const hydrateDraft = async (draft) => {
    if (!draft) return;

    const d = draft.data || {};
    form.customer_id = props.lockCustomer ? props.prefilledCustomer?.id || null : d.customer_id ?? null;
    form.date = d.date || todayIndianDate();
    form.gold_rate = Number(d.gold_rate || 0);
    form.silver_rate = Number(d.silver_rate || 0);
    form.discount_type = d.discount_type || 'amount';
    form.discount_value = Number(d.discount_value || 0);
    form.items = (d.items || []).map((item) => ({
        ...item,
        making_charge_type: item.making_charge_type || (item.type === 'product' ? 'percentage' : 'per_gram'),
        draft_valid: true,
        draft_issue: null,
    }));
    form.payment_cash = Number(d.payment_cash || 0);
    form.payment_card = Number(d.payment_card || 0);
    form.card_note = d.card_note || '';
    currentDraftId.value = draft.id;
    selectedCustomerObj.value = props.lockCustomer ? props.prefilledCustomer || null : draft.customerObj || null;

    await validateDraftItems({ showToast: true });
};

const saveCurrentDraft = async () => {
    if (form.items.length === 0 && !form.customer_id) {
        toast.add({ severity: 'warn', summary: 'Nothing to save', detail: 'Add items or select a customer first.', life: 2000 });
        return;
    }

    try {
        const response = await axios.post(route('invoices.drafts.store'), {
            draft_id: currentDraftId.value,
            customer_id: form.customer_id,
            customer_name: selectedCustomerObj.value?.name || '',
            customer_obj: selectedCustomerObj.value
                ? {
                      id: selectedCustomerObj.value.id,
                      name: selectedCustomerObj.value.name,
                      mobile: selectedCustomerObj.value.mobile,
                  }
                : null,
            date: form.date,
            gold_rate: form.gold_rate,
            silver_rate: form.silver_rate,
            discount_type: form.discount_type,
            discount_value: form.discount_value,
            items: form.items.map(({ draft_valid, draft_issue, ...item }) => item),
            payment_cash: form.payment_cash,
            payment_card: form.payment_card,
            card_note: form.card_note,
            grand_total: grandTotal.value,
        });

        const savedDraft = response.data.draft;
        currentDraftId.value = savedDraft.id;
        const existingIndex = draftList.value.findIndex((draft) => draft.id === savedDraft.id);

        if (existingIndex >= 0) {
            draftList.value[existingIndex] = savedDraft;
        } else {
            draftList.value.unshift(savedDraft);
        }

        toast.add({ severity: 'success', summary: 'Draft Saved', detail: 'Invoice draft saved on the server.', life: 2000 });
    } catch {
        toast.add({ severity: 'error', summary: 'Draft Failed', detail: 'Unable to save invoice draft right now.', life: 2500 });
    }
};

const loadDraft = async (draftId) => {
    const draft = draftList.value.find((item) => item.id === draftId);
    if (!draft) return;

    await hydrateDraft(draft);
    showDraftsDialog.value = false;
    toast.add({ severity: 'info', summary: 'Draft Loaded', detail: `Resumed: ${draft.customerName}`, life: 2000 });
};

const deleteDraft = async (draftId) => {
    try {
        await axios.delete(route('invoices.drafts.destroy', draftId));
        draftList.value = draftList.value.filter((draft) => draft.id !== draftId);
        if (currentDraftId.value === draftId) currentDraftId.value = null;
        toast.add({ severity: 'info', summary: 'Draft Deleted', life: 1500 });
    } catch {
        toast.add({ severity: 'error', summary: 'Delete Failed', detail: 'Unable to delete invoice draft.', life: 2000 });
    }
};

const formatDraftTime = (iso) => formatIndianDateTime(iso);

onMounted(async () => {
    if (props.draftToLoad) {
        await hydrateDraft(props.draftToLoad);
    }

    if (barcodeInput.value) barcodeInput.value.$el.focus();
});

const fetchProduct = async () => {
    if (!scannedBarcode.value) return;
    isProcessing.value = true;

    try {
        const endpoint = `/api/inventory/${encodeURIComponent(scannedBarcode.value)}`;
        const response = await axios.get(endpoint);
        const product = response.data.item;
        const inventoryType = response.data.inventory_type;
        const billing = response.data.billing || {};
        draftValidationFailed.value = false;

        if (form.items.find((p) => p.type === inventoryType && p.id === product.id)) {
            toast.add({ severity: 'warn', summary: 'Duplicate', detail: 'Item is already in the list.', life: 2000 });
            scannedBarcode.value = '';
            return;
        }

        if (product.is_sold) {
            toast.add({ severity: 'error', summary: 'Sold Out', detail: 'This item is already sold!', life: 3000 });
            scannedBarcode.value = '';
            return;
        }

        if (inventoryType === 'silver_product') {
            const quantity = Number(product.quantity || 1);
            const silverRate = Number(form.silver_rate || 0);
            const silverWeight = parseFloat(product.net_weight || product.gross_weight || 0);

            const piecePrice = parseFloat(product.piece_price || 0);
            const makingCharge = parseFloat(product.making_charge || 0);
            const makingType = product.making_charge_type || 'per_gram';

            const metalBase = product.pricing_mode === 'PIECE' ? piecePrice * quantity : silverWeight * silverRate;
            let makingTotal = 0;

            if (makingType === 'flat') {
                makingTotal = makingCharge;
            } else if (makingType === 'percentage') {
                makingTotal = metalBase * (makingCharge / 100);
            } else {
                makingTotal = silverWeight * makingCharge;
            }

            const price = metalBase + makingTotal;

            form.items.push({
                type: 'silver_product',
                id: product.id,
                description: product.name + (product.barcode ? ` (${product.barcode})` : ''),
                purity: 'Silver 925',
                weight: silverWeight,
                quantity,
                quantity_available: Number(product.quantity || 0),
                pricing_mode: product.pricing_mode,
                rate_multiplier: Number(billing.rate_multiplier || 1),
                rate: product.pricing_mode === 'PIECE' ? piecePrice : silverRate,
                making_charges: makingCharge,
                making_charge_type: makingType,
                final_price: price,
            });
        } else {
            const rateMultiplier = Number(billing.rate_multiplier || 1);
            const currentRate = roundMoney(Number(form.gold_rate || billing.gold_rate || 0) * rateMultiplier);
            const weight = parseFloat(product.net_weight || product.gross_weight || 0);
            const makingCharge = parseFloat(product.making_charge || 0);
            const makingType = product.making_charge_type || 'percentage';

            const metalBase = weight * currentRate;
            let makingTotal = 0;

            if (makingType === 'flat') {
                makingTotal = makingCharge;
            } else if (makingType === 'per_gram') {
                makingTotal = weight * makingCharge;
            } else {
                makingTotal = metalBase * (makingCharge / 100);
            }

            const price = metalBase + makingTotal;

            form.items.push({
                type: 'product',
                id: product.id,
                description: product.name + (product.barcode ? ` (${product.barcode})` : ''),
                purity: product.purity?.name || 'Gold',
                rate_multiplier: rateMultiplier,
                weight,
                quantity: 1,
                rate: currentRate,
                making_charges: makingCharge,
                making_charge_type: makingType,
                final_price: price,
            });
        }

        scannedBarcode.value = '';
        toast.add({
            severity: 'success',
            summary: inventoryType === 'silver_product' ? 'Silver Item Added' : 'Gold Item Added',
            detail: product.name,
            life: 1200,
        });
    } catch {
        toast.add({ severity: 'error', summary: 'Barcode Error', detail: 'Failed to look up scanned item details.', life: 2500 });
    } finally {
        isProcessing.value = false;
        if (barcodeInput.value) barcodeInput.value.$el.focus();
    }
};

const removeItem = (index) => {
    form.items.splice(index, 1);

    if (form.items.length === 0) {
        draftValidationFailed.value = false;
    }
};

const onRowInput = (event, item, field) => {
    item[field] = event.value;
    draftValidationFailed.value = false;

    if (item.type === 'silver_product' && item.pricing_mode === 'PIECE' && field === 'quantity') {
        const requested = Number(item.quantity || 0);
        const available = Number(item.quantity_available || 0);

        if (requested > available) {
            item.draft_valid = false;
            item.draft_issue = available > 0 ? `Only ${available} piece(s) left in stock.` : 'This silver piece item is now out of stock.';
        } else {
            item.draft_valid = true;
            item.draft_issue = null;
        }
    }

    recalculateRow(item);
};

const onMakingTypeChange = (item) => {
    draftValidationFailed.value = false;
    recalculateRow(item);
};

const calculateRawRowTotal = (item) => {
    const making = parseFloat(item?.making_charges) || 0;
    const makingType = item?.making_charge_type || (item?.type === 'product' ? 'percentage' : 'per_gram');
    const weight = parseFloat(item?.weight) || 0;
    const rate = parseFloat(item?.rate) || 0;

    if (item?.type === 'silver_product' && item.pricing_mode === 'PIECE') {
        const quantity = Math.max(1, parseInt(item.quantity || 1, 10));
        const base = rate * quantity;
        let makingAmount = 0;
        if (makingType === 'percentage') {
            makingAmount = base * (making / 100);
        } else if (makingType === 'flat' || makingType === 'lump_sum') {
            makingAmount = making;
        } else {
            makingAmount = weight * quantity * making;
        }
        return base + makingAmount;
    }

    const metalValue = weight * rate;
    let makingAmount = 0;

    if (makingType === 'flat' || makingType === 'lump_sum') {
        makingAmount = making;
    } else if (makingType === 'per_gram') {
        makingAmount = weight * making;
    } else {
        makingAmount = metalValue * (making / 100);
    }

    return metalValue + makingAmount;
};

const calculateRowMakingAmount = (item) => {
    const making = parseFloat(item?.making_charges) || 0;
    const makingType = item?.making_charge_type || (item?.type === 'product' ? 'percentage' : 'per_gram');
    const weight = parseFloat(item?.weight) || 0;
    const rate = parseFloat(item?.rate) || 0;

    if (item?.type === 'silver_product' && item.pricing_mode === 'PIECE') {
        const quantity = Math.max(1, parseInt(item.quantity || 1, 10));
        const base = rate * quantity;
        if (makingType === 'percentage') {
            return base * (making / 100);
        } else if (makingType === 'flat' || makingType === 'lump_sum') {
            return making;
        } else {
            return weight * quantity * making;
        }
    }

    const metalValue = weight * rate;
    if (makingType === 'flat' || makingType === 'lump_sum') {
        return making;
    } else if (makingType === 'per_gram') {
        return weight * making;
    } else {
        return metalValue * (making / 100);
    }
};

const recalculateRow = (item) => {
    item.final_price = calculateRawRowTotal(item);
};

const roundMoney = (value) => Number((Number(value || 0)).toFixed(2));
const subTotal = computed(() => roundMoney(form.items.reduce((acc, item) => acc + calculateRawRowTotal(item), 0)));
const discountAmount = computed(() => {
    const rawValue = Number(form.discount_value || 0);

    if (rawValue <= 0) return 0;

    if (form.discount_type === 'percentage') {
        return roundMoney(Math.min(subTotal.value, subTotal.value * (rawValue / 100)));
    }

    return roundMoney(Math.min(subTotal.value, rawValue));
});
const taxableTotal = computed(() => roundMoney(Math.max(subTotal.value - discountAmount.value, 0)));
const gstAmount = computed(() => roundMoney(taxableTotal.value * 0.03));
const grandTotal = computed(() => roundMoney(taxableTotal.value + gstAmount.value));
const totalReceived = computed(() => roundMoney(Number(form.payment_cash || 0) + Number(form.payment_card || 0)));
const balanceDue = computed(() => roundMoney(grandTotal.value - totalReceived.value));
const paymentState = computed(() => {
    if (grandTotal.value <= 0) return 'empty';
    if (balanceDue.value <= 0) return 'paid';
    if (totalReceived.value > 0) return 'partial';
    return 'unpaid';
});

const formatCurrency = (val) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val || 0);

const itemIdentity = (item) => {
    const description = String(item?.description || 'Unnamed item').trim();
    const match = description.match(/^(.*?)\s*\(([^()]+)\)\s*$/);
    const barcode = match?.[2]?.trim() || '';
    let name = match?.[1]?.trim() || description;

    if (barcode && name.toUpperCase().endsWith(barcode.toUpperCase())) {
        name = name
            .slice(0, -barcode.length)
            .replace(/\s*[-–—|:/]\s*$/, '')
            .trim();
    }

    return {
        name: name || description,
        barcode,
    };
};

const itemPurityLabel = (item) => {
    if (typeof item?.purity === 'object') return item.purity?.name || '';
    return String(item?.purity || '').trim();
};

watch(
    () => form.gold_rate,
    (rate) => {
        form.items.filter((item) => !isSilverRateDependentItem(item)).forEach((item) => {
            item.rate = roundMoney(Number(rate || 0) * Number(item.rate_multiplier || 1));
            recalculateRow(item);
        });
    },
);

watch(
    () => form.silver_rate,
    (rate) => {
        form.items
            .filter((item) => isSilverRateDependentItem(item))
            .forEach((item) => {
                item.rate = Number(rate || 0);
                recalculateRow(item);
            });
    },
);

const submitInvoice = () => {
    if (!isDayOpen.value) {
        toast.add({ severity: 'warn', summary: 'Day Closed', detail: 'Open the shop day first from the dashboard.', life: 3000 });
        return;
    }
    if (form.items.length === 0) {
        toast.add({ severity: 'warn', summary: 'Empty Cart', detail: 'Add items first!', life: 3000 });
        return;
    }
    if (!form.customer_id) {
        toast.add({ severity: 'error', summary: 'Missing Customer', detail: 'Select a customer', life: 3000 });
        return;
    }
    if (form.items.some((item) => Number(item.rate || 0) <= 0)) {
        toast.add({ severity: 'error', summary: 'Missing Rate', detail: 'Enter a valid rate for every invoice item.', life: 3000 });
        return;
    }
    if (form.items.some((item) => item.type === 'silver_product' && item.pricing_mode === 'PIECE' && Number(item.quantity || 0) > Number(item.quantity_available || 0))) {
        toast.add({ severity: 'error', summary: 'Invalid Quantity', detail: 'Silver invoice quantity cannot exceed available stock.', life: 3000 });
        return;
    }
    if (draftValidationFailed.value) {
        toast.add({ severity: 'error', summary: 'Draft Check Required', detail: 'Please reload the draft check before generating the invoice.', life: 3000 });
        return;
    }
    if (hasInvalidDraftItems.value) {
        toast.add({ severity: 'error', summary: 'Draft Items Invalid', detail: 'Remove or fix the flagged draft items before generating the invoice.', life: 3000 });
        return;
    }
    if (form.discount_type === 'percentage' && Number(form.discount_value || 0) > 100) {
        toast.add({ severity: 'error', summary: 'Invalid Discount', detail: 'Percentage discount cannot be greater than 100', life: 3000 });
        return;
    }
    if (discountAmount.value > subTotal.value) {
        toast.add({ severity: 'error', summary: 'Invalid Discount', detail: 'Discount cannot be greater than item subtotal', life: 3000 });
        return;
    }
    if (balanceDue.value < 0) {
        toast.add({ severity: 'error', summary: 'Overpayment', detail: 'Received amount cannot exceed invoice total', life: 3000 });
        return;
    }

    form.transform((data) => ({
        ...data,
        draft_id: currentDraftId.value,
        date: toIndianDateInput(data.date),
        discount_value: Number(data.discount_value || 0),
        items: data.items.map((item) => ({
            type: item.type,
            id: item.id,
            quantity: item.type === 'silver_product' ? Number(item.quantity || 1) : 1,
            rate: Number(item.rate || 0),
            making_charges: item.making_charges || 0,
            making_charge_type: item.making_charge_type || (item.type === 'product' ? 'percentage' : 'per_gram'),
        })),
    })).post(route('invoices.store'), {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Success', detail: 'Invoice generated. Opening invoice register...', life: 2500 });
        },
        onError: (errors) => {
            console.error(errors);
            toast.add({ severity: 'error', summary: 'Error', detail: 'Please check form inputs', life: 3000 });
        },
    });
};
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <section class="erp-panel relative overflow-hidden border border-surface-200 bg-white">
                <div class="absolute inset-y-0 right-0 hidden w-80 bg-[radial-gradient(circle_at_top_right,_rgba(245,158,11,0.16),_transparent_62%)] lg:block" />
                <div class="relative flex flex-col gap-6 px-5 py-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl">
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-2xl font-semibold tracking-tight text-surface-900">New Invoice</h1>
                            <Tag value="Sales Billing" severity="secondary" />
                            <Tag
                                :value="paymentState === 'paid' ? 'Fully Paid' : paymentState === 'partial' ? 'Partially Paid' : paymentState === 'unpaid' ? 'Payment Pending' : 'Draft'"
                                :severity="paymentState === 'paid' ? 'success' : paymentState === 'partial' ? 'warn' : paymentState === 'unpaid' ? 'danger' : 'secondary'"
                            />
                        </div>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-surface-600">
                            Build the bill, collect payment, and keep the customer ledger aligned with sale amount and received amount on the same screen.
                        </p>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <Button label="Save Draft" icon="pi pi-save" severity="secondary" outlined size="small" @click="saveCurrentDraft" />
                            <Button
                                v-if="draftList.length > 0"
                                :label="`Load Draft (${draftList.length})`"
                                icon="pi pi-folder-open"
                                severity="secondary"
                                text
                                size="small"
                                @click="showDraftsDialog = true"
                            />
                            <Tag v-if="currentDraftId" value="Editing Draft" severity="warn" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="erp-stat-card min-w-40 p-4">
                            <span class="erp-stat-card__label">Items</span>
                            <span class="erp-stat-card__value">{{ form.items.length }}</span>
                            <span class="erp-stat-card__meta">In current invoice</span>
                        </div>
                        <div class="erp-stat-card min-w-40 p-4">
                            <span class="erp-stat-card__label">Received</span>
                            <span class="erp-stat-card__value !text-emerald-700">{{ formatCurrency(totalReceived) }}</span>
                            <span class="erp-stat-card__meta">Total collected</span>
                        </div>
                        <div class="erp-stat-card min-w-40 p-4">
                            <span class="erp-stat-card__label">Balance Due</span>
                            <span class="erp-stat-card__value" :class="balanceDue > 0 ? '!text-amber-700' : '!text-emerald-700'">{{ formatCurrency(balanceDue) }}</span>
                            <span class="erp-stat-card__meta">{{ balanceDue > 0 ? 'Pending collection' : 'Fully settled' }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- TOP ROW: Customer, Rate, Date -->
            <div class="erp-panel overflow-hidden !p-0">
                <div class="border-b border-surface-200 bg-white px-5 py-4">
                    <h3 class="text-lg font-semibold text-surface-900">Invoice Details</h3>
                    <p class="mt-1 text-sm text-surface-500">Select customer, rate, and invoice date</p>
                </div>

                <div class="bg-white p-5">
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-5">
                        <!-- Customer -->
                        <div class="md:col-span-2">
                            <label class="mb-2 block text-sm font-medium text-surface-700"> Customer </label>

                            <div v-if="prefilledCustomer && lockCustomer">
                                <InputText :modelValue="lockedCustomerName" readonly class="w-full" />

                                <div class="mt-2 flex items-center gap-2 text-xs text-primary">
                                    <i class="pi pi-info-circle"></i>
                                    <span>Locked to Custom Order #{{ prefilledItems[0]?.order_id || 'Ref' }}</span>
                                </div>
                            </div>

                            <div v-else>
                                <CustomerSelector
                                    v-model="form.customer_id"
                                    class="w-full"
                                    :errorMessage="form.errors.customer_id"
                                    :selectedOption="selectedCustomerObj"
                                    @select="onCustomerSelect"
                                    helperText="Pick the billing customer before scanning stock or adding custom-order items."
                                    placeholder="Search billing customer by name or mobile..."
                                />
                            </div>
                        </div>

                        <!-- Gold Rate -->
                        <div>
                            <label class="mb-2 block text-sm font-medium text-surface-700">
                                Today's Rate (22k)
                                <span class="text-red-500">*</span>
                            </label>

                            <InputNumber v-model="form.gold_rate" mode="currency" currency="INR" locale="en-IN" placeholder="₹0.00" class="w-full" inputClass="w-full font-medium" />
                            <small class="mt-1 block text-xs text-surface-500">
                                Auto-filled from today's stored rate. Changing it updates all invoice rows automatically.
                            </small>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-surface-700">Today's Silver Rate</label>
                            <InputNumber v-model="form.silver_rate" mode="currency" currency="INR" locale="en-IN" placeholder="₹0.00" class="w-full" inputClass="w-full font-medium" />
                            <small class="mt-1 block text-xs text-surface-500">Used only for weight-based silver items.</small>
                        </div>

                        <!-- Invoice Date -->
                        <div>
                            <label class="mb-2 block text-sm font-medium text-surface-700"> Invoice Date </label>

                            <InputText type="date" v-model="form.date" class="w-full" />
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-surface-700">Discount Type</label>
                            <Select
                                v-model="form.discount_type"
                                :options="discountTypeOptions"
                                optionLabel="label"
                                optionValue="value"
                                class="w-full"
                            />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-surface-700">
                                Discount {{ form.discount_type === 'percentage' ? '(%)' : '(₹)' }}
                            </label>
                            <InputNumber
                                v-model="form.discount_value"
                                :mode="form.discount_type === 'percentage' ? 'decimal' : 'currency'"
                                :currency="form.discount_type === 'percentage' ? undefined : 'INR'"
                                locale="en-IN"
                                :maxFractionDigits="2"
                                class="w-full"
                                inputClass="w-full"
                            />
                            <small class="mt-1 block text-xs text-surface-500">Discount is applied before GST and stored on the invoice.</small>
                            <small v-if="form.errors.discount_value" class="mt-1 block text-xs text-red-500">{{ form.errors.discount_value }}</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MAIN CONTENT: Items Table + Bill Summary -->
            <div class="grid grid-cols-1 items-stretch gap-4 lg:grid-cols-3">
                <!-- ITEMS TABLE -->
                <div class="erp-panel flex h-full flex-col overflow-hidden !p-0 lg:col-span-2">
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-surface-200 bg-white px-5 py-4">
                        <div>
                            <h3 class="text-lg font-semibold text-surface-900">Invoice Items</h3>
                            <p class="mt-1 text-sm text-surface-500">Scan any stock barcode and the invoice will detect gold or silver automatically.</p>
                        </div>

                        <span class="text-sm font-medium text-surface-500"> {{ form.items?.length || 0 }} items </span>
                    </div>

                    <!-- Scanner Input -->
                    <div class="erp-subpanel m-4 flex gap-3 rounded-lg border border-surface-200 bg-surface-50 p-3">
                        <IconField class="erp-icon-field flex-1">
                            <InputIcon class="pi pi-barcode" />
                            <InputText ref="barcodeInput" v-model="scannedBarcode" @keydown.enter="fetchProduct" placeholder="Scan barcode or enter product code..." class="w-full" />
                        </IconField>

                        <Button label="Add Item" icon="pi pi-plus" @click="fetchProduct" :loading="isProcessing" />
                    </div>

                    <div v-if="hasInvalidDraftItems" class="erp-alert-row mx-4 mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 shadow-xs">
                        <div class="flex items-start gap-3 text-sm text-red-700">
                            <i class="pi pi-exclamation-triangle mt-0.5"></i>
                            <div>
                                <p class="font-medium">Some draft items are no longer billable.</p>
                                <p class="mt-1 text-xs text-red-600">Review the flagged rows below and remove them or adjust quantities before generating the invoice.</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="draftValidationFailed" class="erp-alert-row mx-4 mb-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 shadow-xs">
                        <div class="flex items-start justify-between gap-3 text-sm text-amber-800">
                            <div class="flex items-start gap-3">
                                <i class="pi pi-exclamation-circle mt-0.5"></i>
                                <div>
                                    <p class="font-medium">Live draft validation did not complete.</p>
                                    <p class="mt-1 text-xs text-amber-700">Please recheck the draft against current stock before billing.</p>
                                </div>
                            </div>
                            <Button label="Recheck Draft" icon="pi pi-refresh" size="small" severity="warn" outlined @click="validateDraftItems({ showToast: true })" />
                        </div>
                    </div>

                    <!-- Table -->
                    <DataTable :value="form.items" scrollable scrollHeight="420px" stripedRows rowHover size="small" dataKey="id" class="erp-line-items text-sm">
                        <!-- Empty -->
                        <template #empty>
                            <div class="flex flex-col items-center py-16 text-center text-surface-500">
                                <i class="pi pi-barcode mb-4 text-4xl opacity-30"></i>
                                <p class="font-medium">No items added</p>
                                <span class="text-sm">Scan barcode to start billing</span>
                            </div>
                        </template>

                        <!-- Type -->
                        <Column header="Type" style="width: 88px">
                            <template #body="{ data }">
                                <Tag :value="data.type === 'order_item' ? 'ORDER' : data.type === 'silver_product' ? 'SILVER' : 'STOCK'" :severity="data.type === 'order_item' ? 'info' : data.type === 'silver_product' ? 'warn' : 'success'" />
                            </template>
                        </Column>

                        <!-- Item -->
                        <Column field="description" header="Item details" style="min-width: 320px">
                            <template #body="{ data }">
                                <div class="flex min-w-0 items-start gap-2">
                                    <span
                                        class="flex h-8 w-8 shrink-0 items-center justify-center border"
                                        :class="data.type === 'silver_product' ? 'border-slate-200 bg-slate-50 text-slate-600' : data.type === 'order_item' ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-amber-200 bg-amber-50 text-amber-700'"
                                    >
                                        <i :class="data.type === 'order_item' ? 'pi pi-wrench' : 'pi pi-tag'" class="text-sm"></i>
                                    </span>

                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-semibold leading-5 text-surface-900" :title="itemIdentity(data).name">
                                            {{ itemIdentity(data).name }}
                                        </div>

                                        <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                                            <span
                                                v-if="itemIdentity(data).barcode"
                                                class="inline-flex items-center gap-1 border border-surface-200 bg-surface-50 px-1.5 py-0.5 font-mono text-[10.5px] font-semibold tracking-wide text-surface-700"
                                                title="Product barcode"
                                            >
                                                <i class="pi pi-barcode text-[10px] text-surface-500"></i>
                                                {{ itemIdentity(data).barcode }}
                                            </span>

                                            <span
                                                v-if="itemPurityLabel(data)"
                                                class="inline-flex items-center gap-1 border px-1.5 py-0.5 text-[10.5px] font-semibold"
                                                :class="data.type === 'silver_product' ? 'border-slate-200 bg-slate-50 text-slate-700' : 'border-amber-200 bg-amber-50 text-amber-800'"
                                                title="Metal purity"
                                            >
                                                <span class="h-1.5 w-1.5 rounded-full" :class="data.type === 'silver_product' ? 'bg-slate-400' : 'bg-amber-500'"></span>
                                                {{ itemPurityLabel(data) }} purity
                                            </span>

                                            <span v-if="data.type === 'order_item'" class="inline-flex border border-blue-200 bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-700">
                                                Custom work
                                            </span>
                                            <span v-else-if="data.type === 'silver_product'" class="text-[11px] font-medium text-surface-500">
                                                {{ data.pricing_mode === 'PIECE' ? 'Per piece' : 'By weight' }}
                                            </span>
                                        </div>

                                        <p v-if="data.draft_valid === false" class="mb-0 mt-1.5 text-xs font-medium text-red-600">
                                            {{ data.draft_issue }}
                                        </p>
                                    </div>
                                </div>
                            </template>
                        </Column>

                        <Column header="Qty" headerClass="erp-th-right" style="width: 68px">
                            <template #body="{ data }">
                                <InputNumber
                                    v-if="data.type === 'silver_product' && data.pricing_mode === 'PIECE'"
                                    v-model="data.quantity"
                                    inputClass="w-full text-right"
                                    class="w-full"
                                    :min="1"
                                    :max="data.quantity_available || 1"
                                    @input="onRowInput($event, data, 'quantity')"
                                />
                                <div v-else class="text-right font-medium">1</div>
                            </template>
                        </Column>

                        <!-- Weight -->
                        <Column header="Wt (g)" headerClass="erp-th-right" style="width: 82px">
                            <template #body="{ data }">
                                <div class="text-right font-medium">
                                    {{ data.weight }}
                                </div>
                            </template>
                        </Column>

                        <!-- Rate -->
                        <Column header="Rate" headerClass="erp-th-right" style="width: 132px">
                            <template #body="{ data }">
                                <InputNumber
                                    v-model="data.rate"
                                    inputClass="w-full text-right"
                                    class="erp-line-control erp-line-rate-control"
                                    mode="decimal"
                                    :minFractionDigits="2"
                                    :maxFractionDigits="2"
                                    @input="onRowInput($event, data, 'rate')"
                                />
                            </template>
                        </Column>

                        <!-- Making -->
                        <Column header="Making" headerClass="erp-th-center" style="width: 218px">
                            <template #body="{ data }">
                                <div class="flex items-center gap-1.5">
                                    <InputNumber
                                        v-model="data.making_charges"
                                        inputClass="w-full text-right"
                                        class="erp-line-control erp-line-making-control"
                                        mode="decimal"
                                        :max="data.making_charge_type === 'percentage' ? 100 : undefined"
                                        :minFractionDigits="0"
                                        :maxFractionDigits="2"
                                        placeholder="0"
                                        @input="onRowInput($event, data, 'making_charges')"
                                    />
                                    <Select
                                        v-model="data.making_charge_type"
                                        :options="makingChargeTypeOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        class="erp-line-control erp-line-making-type shrink-0"
                                        :panelClass="'!min-w-[190px]'"
                                        title="Making charge type: % (Percentage), ₹ Flat (Lump sum), ₹/g (Per gram)"
                                        @change="onMakingTypeChange(data)"
                                    >
                                        <template #value="slotProps">
                                            <span class="text-xs font-semibold text-surface-800">
                                                {{ slotProps.value === 'percentage' ? '%' : slotProps.value === 'flat' ? '₹ Flat' : '₹/g' }}
                                            </span>
                                        </template>
                                    </Select>
                                </div>
                            </template>
                        </Column>

                        <!-- Total -->
                        <Column header="Total" headerClass="erp-th-right" style="width: 140px">
                            <template #body="{ data }">
                                <div class="text-right font-semibold text-surface-900">
                                    {{ formatCurrency(data.final_price) }}
                                </div>
                            </template>
                        </Column>

                        <!-- Delete -->
                        <Column style="width: 70px">
                            <template #body="{ index }">
                                <div class="flex justify-center">
                                    <Button icon="pi pi-trash" text severity="danger" rounded @click="removeItem(index)" />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <!-- BILL SUMMARY -->
                <div class="erp-panel flex h-full flex-col justify-between overflow-hidden !p-0">
                    <!-- Summary -->
                    <div class="p-5">
                        <div class="mb-5">
                            <h3 class="text-lg font-semibold text-surface-900">Bill Summary</h3>
                            <p class="mt-1 text-sm text-surface-500">Overview of charges and received payment</p>
                        </div>

                        <div class="space-y-3">
                            <!-- Items -->
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-surface-500"> Items ({{ form.items.length }}) </span>
                                <span class="text-sm font-semibold text-surface-900">
                                    {{ formatCurrency(subTotal) }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-sm text-surface-500">
                                    Discount
                                    <span v-if="Number(form.discount_value || 0) > 0" class="text-xs text-surface-400">
                                        ({{ form.discount_type === 'percentage' ? `${Number(form.discount_value || 0)}%` : 'manual' }})
                                    </span>
                                </span>
                                <span class="text-sm font-semibold text-red-600">
                                    - {{ formatCurrency(discountAmount) }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-sm text-surface-500">Taxable Amount</span>
                                <span class="text-sm font-semibold text-surface-900">
                                    {{ formatCurrency(taxableTotal) }}
                                </span>
                            </div>

                            <!-- GST -->
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-surface-500">GST (3%)</span>
                                <span class="text-sm font-semibold text-surface-900">
                                    {{ formatCurrency(gstAmount) }}
                                </span>
                            </div>
                        </div>

                        <Divider class="my-4" />

                        <!-- Grand Total -->
                        <div class="flex items-end justify-between">
                            <div>
                                <p class="text-sm text-surface-500">Grand Total</p>
                                <p class="mt-1 text-xs text-surface-400">Rounded final payable amount</p>
                            </div>

                            <span class="text-3xl font-bold text-primary">
                                {{ formatCurrency(grandTotal) }}
                            </span>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div class="erp-subpanel rounded-lg border border-surface-200 bg-white p-3.5 shadow-xs">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-surface-500">Received</span>
                                <span class="mt-1 block font-mono text-xl font-bold text-emerald-700">{{ formatCurrency(totalReceived) }}</span>
                            </div>
                            <div class="erp-subpanel rounded-lg border border-surface-200 bg-white p-3.5 shadow-xs">
                                <span class="block text-[11px] font-bold uppercase tracking-wider" :class="balanceDue > 0 ? 'text-amber-700' : 'text-emerald-700'">Ledger Due</span>
                                <span class="mt-1 block font-mono text-xl font-bold" :class="balanceDue > 0 ? 'text-amber-700' : 'text-emerald-700'">{{ formatCurrency(balanceDue) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment -->
                    <div class="border-t border-surface-200 bg-surface-50 p-5">
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold tracking-wide text-surface-700 uppercase">Payment Received</h3>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-surface-700">Cash</label>
                                <InputNumber v-model="form.payment_cash" mode="currency" currency="INR" locale="en-IN" inputClass="w-full" class="w-full" placeholder="₹0.00" />
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-surface-700">UPI / Card / Bank</label>
                                <InputNumber v-model="form.payment_card" mode="currency" currency="INR" locale="en-IN" inputClass="w-full" class="w-full" placeholder="₹0.00" />
                            </div>

                            <div v-if="form.payment_card > 0">
                                <label class="mb-2 block text-sm font-medium text-surface-700">Payment Note</label>
                                <Textarea v-model="form.card_note" rows="2" class="w-full" placeholder="Optional UPI / card / bank reference" />
                            </div>
                        </div>

                        <Divider class="my-4" />

                        <!-- Balance -->
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-surface-700">Balance Due</span>
                            <span class="text-xl font-bold" :class="balanceDue > 0 ? 'text-red-500' : 'text-green-600'">
                                {{ formatCurrency(balanceDue) }}
                            </span>
                        </div>
                        <small v-if="balanceDue > 0" class="mt-2 block text-xs text-surface-500">This due amount remains outstanding in the customer ledger after invoice creation.</small>
                    </div>

                    <!-- Action -->
                    <div class="border-t border-surface-200 bg-white p-4">
                        <Button
                            :label="isValidatingDraftItems ? 'Checking Draft...' : 'Generate Invoice'"
                            icon="pi pi-print"
                            severity="success"
                            class="w-full"
                            size="large"
                            @click="submitInvoice"
                            :loading="form.processing || isValidatingDraftItems"
                            :disabled="!isDayOpen || hasInvalidDraftItems || isValidatingDraftItems || draftValidationFailed"
                        />
                    </div>
                </div>
            </div>
        </div>

        <Dialog v-model:visible="showDraftsDialog" header="Saved Drafts" modal :style="{ width: '36rem' }">
            <div v-if="draftList.length === 0" class="py-8 text-center text-sm text-surface-500">
                No saved drafts.
            </div>

            <div v-else class="flex flex-col gap-3 pt-2">
                <div
                    v-for="draft in draftList"
                    :key="draft.id"
                    class="flex items-center justify-between gap-4 rounded-lg border border-surface-200 px-4 py-3 shadow-xs"
                    :class="currentDraftId === draft.id ? 'border-primary bg-primary/5' : 'bg-white'"
                >
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-surface-900">
                            {{ draft.customerName }}
                            <Tag v-if="currentDraftId === draft.id" value="Current" severity="info" class="ml-2" />
                        </p>
                        <p class="mt-1 text-xs text-surface-500">
                            {{ draft.itemCount }} item{{ draft.itemCount === 1 ? '' : 's' }}
                            <span class="mx-1 text-surface-300">&middot;</span>
                            {{ formatCurrency(draft.grandTotal) }}
                            <span class="mx-1 text-surface-300">&middot;</span>
                            {{ formatDraftTime(draft.savedAt) }}
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        <Button icon="pi pi-upload" text severity="primary" size="small" v-tooltip.top="'Load draft'" @click="loadDraft(draft.id)" />
                        <Button icon="pi pi-trash" text severity="danger" size="small" v-tooltip.top="'Delete draft'" @click="deleteDraft(draft.id)" />
                    </div>
                </div>
            </div>

            <template #footer>
                <Button label="Close" severity="secondary" text @click="showDraftsDialog = false" />
            </template>
        </Dialog>
    </AppLayout>
</template>
