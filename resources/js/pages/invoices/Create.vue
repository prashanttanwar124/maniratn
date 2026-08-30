<script setup>
import CustomerSelector from '@/components/CustomerSelector.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
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
import { Coins, Plus, Scale, Sparkles, Trash2, ArrowLeftRight, Check, Zap } from 'lucide-vue-next';

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
    defaultGoldBuyRate: {
        type: Number,
        default: 0,
    },
    defaultSilverRate: {
        type: Number,
        default: 0,
    },
    defaultSilverBuyRate: {
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

const metalTypeOptions = [
    { label: 'Gold', value: 'GOLD' },
    { label: 'Silver', value: 'SILVER' },
];

const oldGoldPurityOptions = [
    { label: '24K (99.9%)', value: '24K', multiplier: 1.0, metal: 'GOLD' },
    { label: '22K (91.6%)', value: '22K', multiplier: 0.916, metal: 'GOLD' },
    { label: '18K (75.0%)', value: '18K', multiplier: 0.750, metal: 'GOLD' },
    { label: '14K (58.5%)', value: '14K', multiplier: 0.585, metal: 'GOLD' },
    { label: 'Silver 999 (99.9%)', value: 'Silver 999', multiplier: 1.0, metal: 'SILVER' },
    { label: 'Silver 925 (92.5%)', value: 'Silver 925', multiplier: 0.925, metal: 'SILVER' },
    { label: 'Silver 800 (80.0%)', value: 'Silver 800', multiplier: 0.800, metal: 'SILVER' },
    { label: 'Custom Purity', value: 'Custom', multiplier: 1.0, metal: 'ANY' },
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
    old_golds: [],
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

const invalidDraftItemsCount = computed(() => {
    return form.items.filter((item) => item.draft_valid === false).length;
});

const hasInvalidDraftItems = computed(() => {
    return invalidDraftItemsCount.value > 0;
});

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
    form.old_golds = (d.old_golds || []).map((og) => ({
        metal_type: og.metal_type || 'GOLD',
        description: og.description || '',
        gross_weight: og.gross_weight !== undefined && og.gross_weight !== null ? Number(og.gross_weight) : null,
        wastage_weight: Number(og.wastage_weight || 0),
        net_weight: Number(og.net_weight || 0),
        purity: og.purity || '22K',
        rate: Number(og.rate || 0),
        final_price: Number(og.final_price || 0),
    }));
    form.payment_cash = Number(d.payment_cash || 0);
    form.payment_card = Number(d.payment_card || 0);
    form.card_note = d.card_note || '';
    currentDraftId.value = draft.id;
    selectedCustomerObj.value = props.lockCustomer ? props.prefilledCustomer || null : draft.customerObj || null;

    await validateDraftItems({ showToast: true });
};

const saveCurrentDraft = async () => {
    if (form.items.length === 0 && form.old_golds.length === 0 && !form.customer_id) {
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
            old_golds: form.old_golds,
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
    item.final_price = roundMoney(calculateRawRowTotal(item));
};

// --- Old Gold / Metal Exchange Handlers ---
const addOldGoldRow = () => {
    const metalType = 'GOLD';
    const baseBuyRate = Number(props.defaultGoldBuyRate || form.gold_rate || 0);
    const purity = '22K';
    const rate = roundMoney(baseBuyRate * 0.916);

    form.old_golds.push({
        metal_type: metalType,
        description: 'Old Gold',
        gross_weight: null,
        wastage_weight: 0,
        net_weight: 0,
        purity: purity,
        rate: rate,
        final_price: 0,
    });
};

const removeOldGoldRow = (index) => {
    form.old_golds.splice(index, 1);
};

const onOldGoldInput = (item) => {
    const gross = parseFloat(item.gross_weight) || 0;
    const wastage = parseFloat(item.wastage_weight) || 0;
    item.net_weight = Number(Math.max(0, gross - wastage).toFixed(3));
    const rate = parseFloat(item.rate) || 0;
    item.final_price = roundMoney(item.net_weight * rate);
};

const updateOldGoldNumber = (item, field, value) => {
    item[field] = value;
    onOldGoldInput(item);
};

const onOldGoldMetalChange = (item) => {
    if (item.metal_type === 'SILVER') {
        item.description = 'Old Silver';
        item.purity = 'Silver 925';
        const baseBuyRate = Number(props.defaultSilverBuyRate || form.silver_rate || 0);
        item.rate = roundMoney(baseBuyRate * 0.925);
    } else {
        item.description = 'Old Gold';
        item.purity = '22K';
        const baseBuyRate = Number(props.defaultGoldBuyRate || form.gold_rate || 0);
        item.rate = roundMoney(baseBuyRate * 0.916);
    }
    onOldGoldInput(item);
};

const onOldGoldPurityChange = (item) => {
    const option = oldGoldPurityOptions.find((p) => p.value === item.purity);
    const isSilver = item.metal_type === 'SILVER';
    const baseBuyRate = isSilver
        ? Number(props.defaultSilverBuyRate || form.silver_rate || 0)
        : Number(props.defaultGoldBuyRate || form.gold_rate || 0);
    const mult = option?.multiplier ?? 1;
    if (item.purity !== 'Custom') {
        item.rate = roundMoney(baseBuyRate * mult);
    }
    onOldGoldInput(item);
};

const totalOldGoldValue = computed(() =>
    roundMoney(form.old_golds.reduce((acc, og) => acc + (parseFloat(og.final_price) || 0), 0))
);

const totalOldGoldGrossWeight = computed(() =>
    Number(form.old_golds.reduce((acc, og) => acc + (parseFloat(og.gross_weight) || 0), 0).toFixed(3))
);

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
const netPayable = computed(() => roundMoney(Math.max(0, grandTotal.value - totalOldGoldValue.value)));
const totalCashCardReceived = computed(() => roundMoney(Number(form.payment_cash || 0) + Number(form.payment_card || 0)));
const totalReceived = computed(() => roundMoney(totalCashCardReceived.value + totalOldGoldValue.value));
const balanceDue = computed(() => roundMoney(grandTotal.value - totalReceived.value));
const paymentState = computed(() => {
    if (grandTotal.value <= 0) return 'empty';
    if (balanceDue.value <= 0) return 'paid';
    if (totalReceived.value > 0) return 'partial';
    return 'unpaid';
});

const checkoutBlocker = computed(() => {
    if (!isDayOpen.value) return 'Open the shop day from the dashboard to start billing.';
    if (form.items.length === 0) return 'Add at least one item to the bill.';
    if (!form.customer_id) return 'Select a customer before generating the invoice.';
    if (form.items.some((item) => Number(item.rate || 0) <= 0)) return 'Enter a valid rate for every item.';
    if (form.items.some((item) => item.type === 'silver_product' && item.pricing_mode === 'PIECE' && Number(item.quantity || 0) > Number(item.quantity_available || 0))) {
        return 'Reduce the silver quantity to the available stock.';
    }
    if (isValidatingDraftItems.value) return 'Checking saved draft items against live stock.';
    if (draftValidationFailed.value) return 'Recheck the saved draft against current stock.';
    if (hasInvalidDraftItems.value) return 'Fix or remove the flagged draft items.';
    if (form.discount_type === 'percentage' && Number(form.discount_value || 0) > 100) return 'Discount percentage cannot be greater than 100.';
    if (form.old_golds.some((item) => Number(item.gross_weight || 0) <= 0 || Number(item.rate || 0) <= 0)) return 'Enter gross weight and buy rate for every old-metal item.';
    if (form.old_golds.some((item) => Number(item.wastage_weight || 0) > Number(item.gross_weight || 0))) return 'Old-metal deduction cannot be greater than gross weight.';
    if (totalCashCardReceived.value > netPayable.value) return 'Cash and digital payment cannot be more than the net payable.';
    return '';
});

const canGenerateInvoice = computed(() => !checkoutBlocker.value && !form.processing);

const setCashToNetPayable = () => {
    form.payment_cash = netPayable.value;
    form.payment_card = 0;
};

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
    if (form.old_golds.some((og) => Number(og.gross_weight || 0) <= 0 || Number(og.rate || 0) <= 0)) {
        toast.add({ severity: 'error', summary: 'Invalid Old Metal', detail: 'Gross weight and buy rate are required for all old metal rows.', life: 3000 });
        return;
    }
    if (form.old_golds.some((og) => Number(og.net_weight || 0) > Number(og.gross_weight || 0))) {
        toast.add({ severity: 'error', summary: 'Invalid Old Metal Weight', detail: 'Net weight cannot be greater than gross weight.', life: 3000 });
        return;
    }
    if (totalCashCardReceived.value > netPayable.value) {
        toast.add({ severity: 'error', summary: 'Overpayment', detail: `Received cash/card (${formatCurrency(totalCashCardReceived.value)}) cannot exceed net payable of ${formatCurrency(netPayable.value)} after Old Gold deduction.`, life: 3500 });
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
        old_golds: data.old_golds.map((og) => ({
            metal_type: og.metal_type || 'GOLD',
            description: og.description || `Old ${og.metal_type || 'Gold'} Exchange`,
            gross_weight: Number(og.gross_weight || 0),
            wastage_weight: Number(og.wastage_weight || 0),
            net_weight: Number(og.net_weight || 0),
            purity: og.purity || '22K',
            rate: Number(og.rate || 0),
            final_price: Number(og.final_price || 0),
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
            <!-- Header follows the shared ERP page pattern. -->
            <section class="erp-page-header border border-surface-200 bg-white px-5 py-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl">
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-2xl font-semibold tracking-tight text-surface-900">New Invoice</h1>
                            <Tag value="Sales POS" severity="secondary" />
                            <Tag
                                :value="paymentState === 'paid' ? 'Fully Paid' : paymentState === 'partial' ? 'Partially Paid' : paymentState === 'unpaid' ? 'Payment Pending' : 'Draft'"
                                :severity="paymentState === 'paid' ? 'success' : paymentState === 'partial' ? 'warn' : paymentState === 'unpaid' ? 'danger' : 'secondary'"
                            />
                            <Tag v-if="currentDraftId" value="Editing Draft" severity="warn" />
                        </div>
                        <p class="mt-2 text-sm leading-6 text-surface-600">Select a customer, add jewellery, collect payment, and generate the final bill.</p>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        <Button label="Save Draft" icon="pi pi-save" severity="secondary" outlined @click="saveCurrentDraft" />
                        <Button
                            v-if="draftList.length > 0"
                            :label="`Saved Drafts (${draftList.length})`"
                            icon="pi pi-folder-open"
                            severity="secondary"
                            text
                            @click="showDraftsDialog = true"
                        />
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2" :class="totalOldGoldValue > 0 ? 'xl:grid-cols-4' : 'xl:grid-cols-3'">
                <div class="erp-stat-card">
                    <span class="erp-stat-card__label">Items</span>
                    <span class="erp-stat-card__value">{{ form.items.length }}</span>
                    <span class="erp-stat-card__meta">In current bill</span>
                </div>

                <div class="erp-stat-card">
                    <span class="erp-stat-card__label">Gross Bill</span>
                    <span class="erp-stat-card__value">{{ formatCurrency(grandTotal) }}</span>
                    <span class="erp-stat-card__meta">Including 3% GST</span>
                </div>

                <div v-if="totalOldGoldValue > 0" class="erp-stat-card">
                    <span class="erp-stat-card__label flex items-center gap-1 !text-amber-800">
                        <Coins class="h-3 w-3 text-amber-600" />
                        Trade-in Credit
                    </span>
                    <span class="erp-stat-card__value !text-amber-800">{{ formatCurrency(totalOldGoldValue) }}</span>
                    <span class="erp-stat-card__meta !text-amber-700">{{ totalOldGoldGrossWeight.toFixed(3) }} g old metal</span>
                </div>

                <div class="erp-stat-card">
                    <span class="erp-stat-card__label">Balance Due</span>
                    <span class="erp-stat-card__value" :class="balanceDue <= 0 && grandTotal > 0 ? '!text-emerald-700' : ''">{{ formatCurrency(balanceDue) }}</span>
                    <span class="erp-stat-card__meta" :class="balanceDue <= 0 && grandTotal > 0 ? '!text-emerald-700' : ''">
                        {{ balanceDue <= 0 && grandTotal > 0 ? 'Fully settled' : 'Pending collection' }}
                    </span>
                </div>
            </section>

            <div v-if="!isDayOpen" class="erp-alert-row flex flex-col gap-3 border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-2.5">
                    <i class="pi pi-lock mt-0.5 text-sm text-amber-700"></i>
                    <div>
                        <p class="text-xs font-bold">Billing is locked while the shop day is closed.</p>
                        <p class="mt-0.5 text-[11px] text-amber-800">Open the day from the dashboard, then return here to create an invoice.</p>
                    </div>
                </div>
                <Button label="Go to Dashboard" icon="pi pi-arrow-right" size="small" severity="warn" outlined class="shrink-0 !text-xs" @click="router.visit(route('dashboard'))" />
            </div>

            <!-- TOP ROW: Customer, Rate, Date, Discount Toolbar -->
            <div class="erp-panel overflow-hidden !p-0 border border-surface-200 bg-white shadow-xs rounded-xl">
                <div class="border-b border-surface-200 px-5 py-4">
                    <h2 class="text-lg font-semibold text-surface-900">Invoice Details</h2>
                    <p class="mt-1 text-sm text-surface-500">Choose the customer and confirm live metal rates before adding items.</p>
                </div>
                <div class="p-4 sm:p-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
                        <!-- Customer (2 cols) -->
                        <div class="sm:col-span-2 lg:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium text-surface-700">
                                Customer <span class="text-red-500">*</span>
                            </label>

                            <div v-if="prefilledCustomer && lockCustomer">
                                <InputText :modelValue="lockedCustomerName" readonly class="w-full !text-sm font-medium" />
                                <div class="mt-1 flex items-center gap-1.5 text-[11px] text-primary">
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
                                    placeholder="Search customer by name or mobile..."
                                />
                            </div>
                        </div>

                        <!-- Gold Rate (22K) -->
                        <div class="lg:col-span-1">
                            <label class="mb-1.5 block text-sm font-medium text-surface-700">
                                Gold Rate (22K) <span class="text-red-500">*</span>
                            </label>
                            <InputNumber
                                v-model="form.gold_rate"
                                mode="currency"
                                currency="INR"
                                locale="en-IN"
                                placeholder="₹0.00"
                                class="w-full"
                                inputClass="w-full !text-sm font-medium"
                            />
                        </div>

                        <!-- Silver Rate -->
                        <div class="lg:col-span-1">
                            <label class="mb-1.5 block text-sm font-medium text-surface-700">
                                Silver Rate
                            </label>
                            <InputNumber
                                v-model="form.silver_rate"
                                mode="currency"
                                currency="INR"
                                locale="en-IN"
                                placeholder="₹0.00"
                                class="w-full"
                                inputClass="w-full !text-sm font-medium"
                            />
                        </div>

                        <!-- Invoice Date -->
                        <div class="lg:col-span-1">
                            <label class="mb-1.5 block text-sm font-medium text-surface-700">
                                Invoice Date
                            </label>
                            <InputText type="date" v-model="form.date" class="w-full !text-sm font-medium" />
                        </div>

                        <!-- Discount: the mode switch stays inside the input so it shares the date field baseline. -->
                        <div class="lg:col-span-1">
                            <label class="mb-1.5 block text-sm font-medium text-surface-700">Discount</label>
                            <div class="invoice-discount-field relative">
                                <InputNumber
                                    v-model="form.discount_value"
                                    :mode="form.discount_type === 'percentage' ? 'decimal' : 'currency'"
                                    :currency="form.discount_type === 'percentage' ? undefined : 'INR'"
                                    locale="en-IN"
                                    :maxFractionDigits="2"
                                    placeholder="0"
                                    class="w-full"
                                    inputClass="w-full !pr-[4.75rem] !text-sm font-medium"
                                />
                                <div class="absolute inset-y-1 right-1 z-10 inline-flex items-center rounded-md border border-surface-200 bg-surface-50 p-0.5" role="group" aria-label="Discount type">
                                    <button
                                        type="button"
                                        class="h-6 min-w-6 rounded px-1.5 text-[10px] font-bold transition-colors cursor-pointer"
                                        :class="form.discount_type === 'percentage' ? 'bg-primary text-white shadow-2xs' : 'text-surface-600 hover:text-surface-900'"
                                        :aria-pressed="form.discount_type === 'percentage'"
                                        @click="form.discount_type = 'percentage'"
                                    >%</button>
                                    <button
                                        type="button"
                                        class="h-6 min-w-6 rounded px-1.5 text-[10px] font-bold transition-colors cursor-pointer"
                                        :class="form.discount_type === 'amount' ? 'bg-primary text-white shadow-2xs' : 'text-surface-600 hover:text-surface-900'"
                                        :aria-pressed="form.discount_type === 'amount'"
                                        @click="form.discount_type = 'amount'"
                                    >₹</button>
                                </div>
                                <small v-if="form.errors.discount_value" class="mt-0.5 block text-[11px] text-red-500">{{ form.errors.discount_value }}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MAIN CONTENT: Items Table + Old Gold + Bill Summary -->
            <div class="grid grid-cols-1 items-start gap-5 lg:grid-cols-3">
                <!-- LEFT COLUMN: ITEMS TABLE + OLD METAL TRADE-IN -->
                <div class="flex flex-col gap-5 lg:col-span-2">
                    <!-- ITEMS TABLE -->
                    <div class="erp-panel flex flex-col overflow-hidden !p-0 border border-surface-200 bg-white shadow-xs rounded-xl">
                        <!-- Header -->
                        <div class="flex items-center justify-between border-b border-surface-200 bg-white px-5 py-3.5">
                            <div>
                                <h3 class="text-base font-semibold text-surface-900">Sale Jewellery Items</h3>
                                <p class="mt-1 text-sm text-surface-500">Add jewellery from stock or a custom order.</p>
                            </div>

                            <Tag :value="`${form.items?.length || 0} Item${form.items?.length === 1 ? '' : 's'}`" severity="secondary" class="!text-xs font-semibold" />
                        </div>

                        <!-- Fast scanner / manual SKU entry -->
                        <div class="scanner-entry border-b border-surface-200 bg-surface-50 px-5 py-4">
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <label for="invoice-barcode" class="text-sm font-medium text-surface-700">Scan barcode or SKU</label>
                                <span class="text-xs text-surface-500">Press <kbd class="rounded border border-surface-200 bg-white px-1.5 py-0.5 font-mono text-[11px] text-surface-700">Enter</kbd> to add</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="relative flex-1">
                                    <i class="pi pi-barcode absolute left-3 top-1/2 z-10 -translate-y-1/2 text-surface-400"></i>
                                    <InputText
                                        id="invoice-barcode"
                                        ref="barcodeInput"
                                        v-model="scannedBarcode"
                                        @keydown.enter="fetchProduct"
                                        placeholder="Scan or type a product code"
                                        class="w-full !h-11 !pl-10 !text-base"
                                    />
                                </div>

                                <Button
                                    label="Add Item"
                                    icon="pi pi-plus"
                                    @click="fetchProduct"
                                    :loading="isProcessing"
                                    class="!h-11 !px-4 !font-semibold shrink-0"
                                />
                            </div>
                        </div>

                        <div v-if="hasInvalidDraftItems" class="erp-alert-row mx-3.5 mb-3 rounded-lg border border-red-200 bg-red-50 px-3.5 py-2.5 shadow-xs">
                            <div class="flex items-start gap-2.5 text-xs text-red-700">
                                <i class="pi pi-exclamation-triangle mt-0.5"></i>
                                <div>
                                    <p class="font-medium">Some draft items are no longer billable.</p>
                                    <p class="mt-0.5 text-[11px] text-red-600">Review the flagged rows below and remove them or adjust quantities before billing.</p>
                                </div>
                            </div>
                        </div>

                        <div v-if="draftValidationFailed" class="erp-alert-row mx-3.5 mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3.5 py-2.5 shadow-xs">
                            <div class="flex items-start justify-between gap-3 text-xs text-amber-800">
                                <div class="flex items-start gap-2 text-xs">
                                    <i class="pi pi-exclamation-circle mt-0.5"></i>
                                    <div>
                                        <p class="font-medium">Live draft validation did not complete.</p>
                                        <p class="mt-0.5 text-[11px] text-amber-700">Please recheck the draft against current stock before billing.</p>
                                    </div>
                                </div>
                                <Button label="Recheck Draft" icon="pi pi-refresh" size="small" severity="warn" outlined class="!text-xs !py-0.5 !px-2" @click="validateDraftItems({ showToast: true })" />
                            </div>
                        </div>

                        <!-- Table -->
                        <DataTable
                            :value="form.items"
                            scrollable
                            scrollHeight="360px"
                            stripedRows
                            rowHover
                            size="small"
                            dataKey="id"
                            class="erp-flush-table erp-line-items !rounded-none !border-0 !shadow-none text-xs"
                        >
                            <!-- Empty -->
                            <template #empty>
                                <div class="flex flex-col items-center justify-center py-10 text-center text-surface-400">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-surface-100/80 text-surface-400 mb-1.5">
                                        <i class="pi pi-barcode text-lg"></i>
                                    </div>
                                    <p class="font-semibold text-surface-700 text-xs">No items added to bill yet</p>
                                    <span class="text-[11px] text-surface-400 mt-0.5">Scan a jewellery barcode above to start billing</span>
                                </div>
                            </template>

                            <!-- Type -->
                            <Column header="Type" style="width: 80px">
                                <template #body="{ data }">
                                    <Tag
                                        :value="data.type === 'order_item' ? 'ORDER' : data.type === 'silver_product' ? 'SILVER' : 'STOCK'"
                                        :severity="data.type === 'order_item' ? 'info' : data.type === 'silver_product' ? 'warn' : 'success'"
                                        class="!text-[10px] !font-bold"
                                    />
                                </template>
                            </Column>

                            <!-- Item Details -->
                            <Column field="description" header="Item Details" style="min-width: 250px">
                                <template #body="{ data }">
                                    <div class="flex min-w-0 items-start gap-2.5">
                                        <div
                                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md border text-xs"
                                            :class="data.type === 'silver_product' ? 'border-slate-200 bg-slate-50 text-slate-600' : data.type === 'order_item' ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-amber-200 bg-amber-50 text-amber-700'"
                                        >
                                            <i :class="data.type === 'order_item' ? 'pi pi-wrench' : 'pi pi-tag'"></i>
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <div class="truncate text-xs font-semibold leading-tight text-surface-900" :title="itemIdentity(data).name">
                                                {{ itemIdentity(data).name }}
                                            </div>

                                            <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                                <span
                                                    v-if="itemIdentity(data).barcode"
                                                    class="inline-flex items-center gap-1 rounded border border-surface-200 bg-surface-50 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-surface-700"
                                                >
                                                    <i class="pi pi-barcode text-[9px] text-surface-400"></i>
                                                    {{ itemIdentity(data).barcode }}
                                                </span>

                                                <span
                                                    v-if="itemPurityLabel(data)"
                                                    class="inline-flex items-center gap-1 rounded border px-1.5 py-0.5 text-[10px] font-semibold"
                                                    :class="data.type === 'silver_product' ? 'border-slate-200 bg-slate-50 text-slate-700' : 'border-amber-200 bg-amber-50 text-amber-800'"
                                                >
                                                    <span class="h-1.5 w-1.5 rounded-full" :class="data.type === 'silver_product' ? 'bg-slate-400' : 'bg-amber-500'"></span>
                                                    {{ itemPurityLabel(data) }}
                                                </span>

                                                <span v-if="data.type === 'order_item'" class="inline-flex rounded border border-blue-200 bg-blue-50 px-1.5 py-0.5 text-[10px] font-semibold text-blue-700">
                                                    Custom Order
                                                </span>
                                                <span v-else-if="data.type === 'silver_product'" class="text-[10px] font-medium text-surface-500">
                                                    {{ data.pricing_mode === 'PIECE' ? 'Per piece' : 'By weight' }}
                                                </span>
                                            </div>

                                            <p v-if="data.draft_valid === false" class="mb-0 mt-1 text-[11px] font-medium text-red-600">
                                                {{ data.draft_issue }}
                                            </p>
                                        </div>
                                    </div>
                                </template>
                            </Column>

                            <!-- Qty -->
                            <Column header="Qty" headerClass="erp-th-right" style="width: 65px">
                                <template #body="{ data }">
                                    <InputNumber
                                        v-if="data.type === 'silver_product' && data.pricing_mode === 'PIECE'"
                                        v-model="data.quantity"
                                        inputClass="w-full text-right"
                                        class="erp-line-control w-full"
                                        :min="1"
                                        :max="data.quantity_available || 1"
                                        @input="onRowInput($event, data, 'quantity')"
                                    />
                                    <div v-else class="text-right font-medium text-surface-800">1</div>
                                </template>
                            </Column>

                            <!-- Weight -->
                            <Column header="Wt (g)" headerClass="erp-th-right" style="width: 85px">
                                <template #body="{ data }">
                                    <div class="text-right font-mono font-semibold text-surface-900">
                                        {{ data.weight }}
                                    </div>
                                </template>
                            </Column>

                            <!-- Rate -->
                            <Column header="Rate (₹/g)" headerClass="erp-th-right" style="width: 125px">
                                <template #body="{ data }">
                                    <InputNumber
                                        v-model="data.rate"
                                        inputClass="w-full text-right font-medium"
                                        class="erp-line-control w-full"
                                        mode="decimal"
                                        :minFractionDigits="2"
                                        :maxFractionDigits="2"
                                        @input="onRowInput($event, data, 'rate')"
                                    />
                                </template>
                            </Column>

                            <!-- Making Charges -->
                            <Column header="Making Charges" headerClass="erp-th-center" style="width: 215px">
                                <template #body="{ data }">
                                    <div class="flex items-center gap-1.5">
                                        <InputNumber
                                            v-model="data.making_charges"
                                            inputClass="w-full text-right font-medium"
                                            class="erp-line-control w-full"
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
                                            class="erp-line-control shrink-0"
                                            style="width: 5.75rem"
                                            :panelClass="'!min-w-[190px]'"
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
                            <Column header="Amount (₹)" headerClass="erp-th-right" style="width: 135px">
                                <template #body="{ data }">
                                    <div class="text-right font-mono font-bold text-surface-900">
                                        {{ formatCurrency(data.final_price) }}
                                    </div>
                                </template>
                            </Column>

                            <!-- Delete -->
                            <Column style="width: 48px">
                                <template #body="{ index }">
                                    <div class="flex justify-center">
                                        <Button icon="pi pi-trash" text severity="danger" rounded size="small" @click="removeItem(index)" />
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </div>

                    <!-- OLD METAL & GOLD EXCHANGE PANEL -->
                    <div class="erp-panel flex flex-col overflow-hidden !p-0 border border-surface-200 bg-white shadow-xs rounded-xl">
                        <!-- Header -->
                        <div class="flex items-center justify-between border-b border-surface-200 bg-white px-5 py-3.5">
                            <div>
                                <h3 class="text-base font-semibold text-surface-900">Old Metal & Gold Exchange</h3>
                                <p class="text-xs text-surface-500">Customer trade-in credited to Vault and deducted from net payable</p>
                            </div>

                            <div class="flex items-center gap-2">
                                <Tag v-if="form.old_golds.length > 0" :value="`${form.old_golds.length} Item${form.old_golds.length === 1 ? '' : 's'}`" severity="secondary" class="!text-xs font-semibold" />
                                <Button label="Add Old Metal" icon="pi pi-plus" size="small" outlined severity="primary" class="!text-xs !py-1 !px-2.5" @click="addOldGoldRow" />
                            </div>
                        </div>

                        <div v-if="form.old_golds.length > 0" class="border-b border-surface-200 bg-surface-50/80 px-5 py-2.5">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs text-surface-600">
                                <span class="inline-flex items-center gap-1.5 font-semibold text-surface-800">
                                    <i class="pi pi-info-circle text-primary-600"></i>
                                    Valuation
                                </span>
                                <span class="hidden h-4 w-px bg-surface-300 sm:block"></span>
                                <span><strong class="font-semibold text-surface-800">Net weight</strong> = Gross − Deduction</span>
                                <span class="text-surface-300">•</span>
                                <span>
                                    Purity auto-adjusts the <strong class="font-semibold text-amber-800">buy rate</strong>
                                    <span class="text-surface-400">(editable)</span>
                                </span>
                                <span class="text-surface-300">•</span>
                                <span><strong class="font-semibold text-emerald-700">Credit</strong> = Net weight × Rate</span>
                            </div>
                        </div>

                        <!-- DataTable with .erp-line-items -->
                        <DataTable :value="form.old_golds" scrollable stripedRows rowHover size="small" class="erp-flush-table invoice-old-metal-table erp-line-items !rounded-none !border-0 !shadow-none text-sm">
                            <template #empty>
                                <div class="flex flex-col items-center justify-center py-7 text-center text-surface-400">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600 mb-1.5 border border-amber-200/50">
                                        <Coins class="h-5 w-5" />
                                    </div>
                                    <p class="font-semibold text-surface-700 text-xs">No old metal exchange added</p>
                                    <p class="text-[11px] text-surface-400 mt-0.5">If customer is exchanging old gold or silver towards this bill, click "Add Old Metal" above</p>
                                </div>
                            </template>

                            <!-- Metal -->
                            <Column header="Metal" style="width: 105px">
                                <template #body="{ data }">
                                    <Select
                                        v-model="data.metal_type"
                                        :options="metalTypeOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        class="erp-line-control w-full"
                                        @change="onOldGoldMetalChange(data)"
                                    />
                                </template>
                            </Column>

                            <!-- Description -->
                            <Column header="Description" style="min-width: 150px">
                                <template #body="{ data }">
                                    <InputText
                                        v-model="data.description"
                                        placeholder="e.g. Old Ring"
                                        class="erp-line-control w-full"
                                    />
                                </template>
                            </Column>

                            <!-- Gross Weight -->
                            <Column header="Gross Wt (g)" headerClass="erp-th-right" style="width: 110px">
                                <template #body="{ data }">
                                    <InputNumber
                                        v-model="data.gross_weight"
                                        mode="decimal"
                                        :min="0"
                                        :minFractionDigits="3"
                                        :maxFractionDigits="3"
                                        placeholder="0.000"
                                        class="erp-line-control w-full"
                                        inputClass="w-full text-right font-medium"
                                        @update:modelValue="updateOldGoldNumber(data, 'gross_weight', $event)"
                                    />
                                </template>
                            </Column>

                            <!-- Deduction -->
                            <Column header="Deduction (g)" headerClass="erp-th-right" style="width: 110px">
                                <template #body="{ data }">
                                    <InputNumber
                                        v-model="data.wastage_weight"
                                        mode="decimal"
                                        :min="0"
                                        :max="Number(data.gross_weight || 0)"
                                        :minFractionDigits="3"
                                        :maxFractionDigits="3"
                                        placeholder="0.000"
                                        class="erp-line-control w-full"
                                        inputClass="w-full text-right text-surface-500"
                                        @update:modelValue="updateOldGoldNumber(data, 'wastage_weight', $event)"
                                    />
                                </template>
                            </Column>

                            <!-- Net Weight -->
                            <Column header="Net Wt (g)" headerClass="erp-th-right" style="width: 90px">
                                <template #body="{ data }">
                                    <div class="text-right font-mono font-bold text-surface-900">
                                        {{ Number(data.net_weight || 0).toFixed(3) }}
                                    </div>
                                </template>
                            </Column>

                            <!-- Purity -->
                            <Column header="Purity" style="width: 140px">
                                <template #body="{ data }">
                                    <Select
                                        v-model="data.purity"
                                        :options="oldGoldPurityOptions.filter(p => p.metal === 'ANY' || p.metal === data.metal_type)"
                                        optionLabel="label"
                                        optionValue="value"
                                        class="erp-line-control w-full"
                                        @change="onOldGoldPurityChange(data)"
                                    />
                                </template>
                            </Column>

                            <!-- Buy Rate -->
                            <Column header="Rate (₹/g)" headerClass="erp-th-right" style="width: 120px">
                                <template #body="{ data }">
                                    <InputNumber
                                        v-model="data.rate"
                                        mode="decimal"
                                        :min="0"
                                        :minFractionDigits="2"
                                        :maxFractionDigits="2"
                                        class="erp-line-control w-full"
                                        inputClass="w-full text-right font-medium"
                                        @update:modelValue="updateOldGoldNumber(data, 'rate', $event)"
                                    />
                                </template>
                            </Column>

                            <!-- Credit Value -->
                            <Column header="Credit (₹)" headerClass="erp-th-right" style="width: 125px">
                                <template #body="{ data }">
                                    <div class="text-right font-mono font-bold text-emerald-700">
                                        {{ formatCurrency(data.final_price) }}
                                    </div>
                                </template>
                            </Column>

                            <!-- Delete Action -->
                            <Column style="width: 48px">
                                <template #body="{ index }">
                                    <div class="flex justify-center">
                                        <Button icon="pi pi-trash" text severity="danger" rounded size="small" @click="removeOldGoldRow(index)" />
                                    </div>
                                </template>
                            </Column>
                        </DataTable>

                        <!-- Footer Summary Bar -->
                        <div v-if="form.old_golds.length > 0" class="flex flex-wrap items-center justify-between gap-4 border-t border-surface-200 bg-surface-50 px-5 py-2.5 text-xs">
                            <div class="flex items-center gap-6">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-surface-500">Gross Wt:</span>
                                    <span class="font-mono font-bold text-surface-900">{{ totalOldGoldGrossWeight.toFixed(3) }} g</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-surface-500">Net Wt:</span>
                                    <span class="font-mono font-bold text-surface-900">
                                        {{ form.old_golds.reduce((acc, r) => acc + Number(r.net_weight || 0), 0).toFixed(3) }} g
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-surface-700">Total Trade-In Credit:</span>
                                <span class="font-mono text-sm font-bold text-emerald-700">{{ formatCurrency(totalOldGoldValue) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: BILL SUMMARY & PAYMENT -->
                <div class="invoice-checkout-panel erp-panel flex flex-col justify-between overflow-hidden !p-0 sticky top-4 border border-surface-200 bg-white shadow-xs rounded-xl">
                    <!-- Summary -->
                    <div class="p-4 sm:p-5">
                        <div class="mb-3.5 flex items-center justify-between border-b border-surface-100 pb-3">
                            <div>
                                <h3 class="text-base font-semibold text-surface-900">Bill Summary</h3>
                                <p class="text-[11px] text-surface-400">Live breakdown of charges & taxes</p>
                            </div>
                            <Tag :value="paymentState === 'paid' ? 'Paid' : paymentState === 'partial' ? 'Partial' : paymentState === 'unpaid' ? 'Unpaid' : 'Draft'" :severity="paymentState === 'paid' ? 'success' : paymentState === 'partial' ? 'warn' : paymentState === 'unpaid' ? 'danger' : 'secondary'" class="!text-[10px] font-bold" />
                        </div>

                        <div class="space-y-2 text-xs">
                            <!-- Items -->
                            <div class="flex items-center justify-between">
                                <span class="text-surface-500"> Items Subtotal ({{ form.items.length }}) </span>
                                <span class="font-mono font-semibold text-surface-900">
                                    {{ formatCurrency(subTotal) }}
                                </span>
                            </div>

                            <!-- Discount -->
                            <div v-if="discountAmount > 0" class="flex items-center justify-between">
                                <span class="text-surface-500">
                                    Discount
                                    <span v-if="Number(form.discount_value || 0) > 0" class="text-[10.5px] text-surface-400">
                                        ({{ form.discount_type === 'percentage' ? `${Number(form.discount_value || 0)}%` : 'Flat' }})
                                    </span>
                                </span>
                                <span class="font-mono font-semibold text-red-600">
                                    - {{ formatCurrency(discountAmount) }}
                                </span>
                            </div>

                            <!-- Taxable -->
                            <div class="flex items-center justify-between">
                                <span class="text-surface-500">Taxable Value</span>
                                <span class="font-mono font-semibold text-surface-900">
                                    {{ formatCurrency(taxableTotal) }}
                                </span>
                            </div>

                            <!-- GST -->
                            <div class="flex items-center justify-between">
                                <span class="text-surface-500">GST (3%)</span>
                                <span class="font-mono font-semibold text-surface-900">
                                    {{ formatCurrency(gstAmount) }}
                                </span>
                            </div>

                            <!-- Gross Total -->
                            <div class="flex items-center justify-between pt-2 border-t border-surface-200 text-xs">
                                <span class="font-bold text-surface-800 uppercase tracking-wide">Gross Bill</span>
                                <span class="font-mono font-bold text-surface-900 text-sm">
                                    {{ formatCurrency(grandTotal) }}
                                </span>
                            </div>

                            <!-- Old Metal Deduction Card -->
                            <div v-if="totalOldGoldValue > 0" class="flex items-center justify-between rounded-lg border border-amber-200/80 bg-amber-50/60 p-2 text-xs text-amber-950">
                                <div class="flex items-center gap-1.5 font-medium">
                                    <i class="pi pi-minus-circle text-amber-700"></i>
                                    <span>Old Metal ({{ totalOldGoldGrossWeight.toFixed(3) }}g)</span>
                                </div>
                                <span class="font-mono font-bold text-amber-900">
                                    - {{ formatCurrency(totalOldGoldValue) }}
                                </span>
                            </div>
                        </div>

                        <Divider class="!my-3" />

                        <!-- Net Payable Spotlight -->
                        <div class="rounded-xl border border-surface-200/80 bg-gradient-to-br from-surface-50 to-white p-3 shadow-2xs">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-surface-500">Net Payable</span>
                                    <p class="text-[10px] text-surface-400">After trade-in deduction</p>
                                </div>
                                <span class="font-mono text-xl font-bold tracking-tight text-surface-900">
                                    {{ formatCurrency(netPayable) }}
                                </span>
                            </div>
                        </div>

                        <!-- Settled & Due Mini Badges -->
                        <div class="mt-2.5 grid grid-cols-2 gap-2">
                            <div class="rounded-lg border border-surface-200/70 bg-white p-2 text-center">
                                <span class="block text-[10px] font-bold uppercase tracking-wider text-surface-400">Total Settled</span>
                                <span class="mt-0.5 block font-mono text-sm font-bold text-emerald-700">{{ formatCurrency(totalReceived) }}</span>
                            </div>
                            <div class="rounded-lg border border-surface-200/70 bg-white p-2 text-center">
                                <span class="block text-[10px] font-bold uppercase tracking-wider" :class="balanceDue > 0 ? 'text-amber-700' : 'text-emerald-700'">Ledger Due</span>
                                <span class="mt-0.5 block font-mono text-sm font-bold" :class="balanceDue > 0 ? 'text-amber-700' : 'text-emerald-700'">{{ formatCurrency(balanceDue) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Collection -->
                    <div class="border-t border-surface-200 bg-surface-50/70 p-4 sm:p-5">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-base font-semibold text-surface-900">Payment Collection</h3>
                            <button
                                v-if="netPayable > 0 && Number(form.payment_cash || 0) !== netPayable"
                                type="button"
                                class="inline-flex items-center gap-1 rounded-md bg-emerald-100 hover:bg-emerald-200 text-emerald-900 border border-emerald-300/80 px-2 py-0.5 text-[10.5px] font-semibold transition-colors cursor-pointer"
                                @click="setCashToNetPayable"
                            >
                                <span>⚡ Pay Full Cash</span>
                            </button>
                        </div>

                        <div class="space-y-2.5">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-surface-700">Cash Received</label>
                                <InputNumber v-model="form.payment_cash" mode="currency" currency="INR" locale="en-IN" inputClass="w-full !text-sm font-medium" class="w-full" placeholder="₹0.00" />
                            </div>

                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-surface-700">UPI / Card / Bank</label>
                                <InputNumber v-model="form.payment_card" mode="currency" currency="INR" locale="en-IN" inputClass="w-full !text-sm font-medium" class="w-full" placeholder="₹0.00" />
                            </div>

                            <div v-if="form.payment_card > 0">
                                <label class="mb-1.5 block text-sm font-medium text-surface-700">Payment Ref Note</label>
                                <Textarea v-model="form.card_note" rows="2" class="w-full !text-sm" placeholder="UPI txn ID, card last 4 digits, or bank transfer ref..." />
                            </div>
                        </div>

                        <Divider class="!my-3" />

                        <!-- Balance -->
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-surface-600">Balance Pending</span>
                            <span class="font-mono text-base font-bold" :class="balanceDue <= 0 && grandTotal > 0 ? 'text-emerald-700' : 'text-surface-900'">
                                {{ formatCurrency(balanceDue) }} {{ balanceDue <= 0 && grandTotal > 0 ? '✓' : '' }}
                            </span>
                        </div>
                        <small v-if="balanceDue > 0" class="mt-1 block text-[10.5px] text-surface-500">Unpaid balance is added to customer ledger.</small>
                    </div>

                    <!-- Action -->
                    <div class="border-t border-surface-200 bg-white p-4">
                        <div class="invoice-readiness mb-3 flex items-start gap-2 rounded-md px-2.5 py-2 text-[11px]" :class="canGenerateInvoice ? 'bg-emerald-50 text-emerald-800' : 'bg-surface-50 text-surface-600'">
                            <i :class="canGenerateInvoice ? 'pi pi-check-circle text-emerald-600' : 'pi pi-info-circle text-surface-400'" class="mt-0.5"></i>
                            <span>{{ canGenerateInvoice ? 'Ready to generate and print.' : checkoutBlocker }}</span>
                        </div>
                        <Button
                            :label="isValidatingDraftItems ? 'Checking Draft Stock...' : 'Generate & Print Invoice'"
                            icon="pi pi-print"
                            severity="success"
                            class="w-full !py-3 !text-sm !font-bold shadow-xs hover:shadow-md transition-all"
                            @click="submitInvoice"
                            :loading="form.processing || isValidatingDraftItems"
                            :disabled="!canGenerateInvoice"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Saved Drafts Dialog -->
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

<style>
.invoice-discount-field .p-inputnumber-input {
    padding-right: 4.75rem !important;
}

#invoice-barcode {
    font-size: 1rem !important;
}

.invoice-old-metal-table .p-inputnumber-input,
.invoice-old-metal-table .p-inputtext,
.invoice-old-metal-table .p-select-label {
    font-size: 0.9375rem !important;
}
</style>
