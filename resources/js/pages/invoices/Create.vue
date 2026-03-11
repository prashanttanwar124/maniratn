<script setup>
import CustomerSelector from '@/components/CustomerSelector.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Divider from 'primevue/divider';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';
import Textarea from 'primevue/textarea';
import { computed, onMounted, ref, watch } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    customers: Array,
    products: Array,
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

const form = useForm({
    customer_id: props.prefilledCustomer?.id || null,
    date: new Date().toISOString().split('T')[0],
    gold_rate: Number(props.defaultGoldRate || 0),
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
                  rate: Number(props.defaultGoldRate || 0),
                  making_charges: 0,
                  final_price: parseFloat(item.finished_weight || 0) * Number(props.defaultGoldRate || 0),
              }))
            : [],
    payment_cash: 0,
    payment_card: 0,
    card_note: '',
});

const scannedBarcode = ref('');
const isProcessing = ref(false);

const lockedCustomerName = computed(() => {
    return props.prefilledCustomer ? props.prefilledCustomer.name : '';
});

onMounted(() => {
    if (barcodeInput.value) barcodeInput.value.$el.focus();
});

const fetchProduct = async () => {
    if (!scannedBarcode.value) return;
    isProcessing.value = true;

    try {
        const response = await axios.get(`/api/products/${scannedBarcode.value}`);
        const product = response.data;

        if (form.items.find((p) => p.type === 'product' && p.id === product.id)) {
            toast.add({ severity: 'warn', summary: 'Duplicate', detail: 'Item is already in the list.', life: 2000 });
            scannedBarcode.value = '';
            return;
        }

        if (product.is_sold) {
            toast.add({ severity: 'error', summary: 'Sold Out', detail: 'This item is already sold!', life: 3000 });
            scannedBarcode.value = '';
            return;
        }

        const currentRate = form.gold_rate || 0;
        const price = parseFloat(product.net_weight) * currentRate + parseFloat(product.making_charge);

        form.items.push({
            type: 'product',
            id: product.id,
            description: product.name + (product.barcode ? ` (${product.barcode})` : ''),
            weight: parseFloat(product.net_weight),
            rate: currentRate,
            making_charges: parseFloat(product.making_charge),
            final_price: price,
        });

        scannedBarcode.value = '';
        toast.add({ severity: 'success', summary: 'Added', detail: product.name, life: 1000 });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Not Found', detail: 'Invalid Barcode', life: 3000 });
    } finally {
        isProcessing.value = false;
        if (barcodeInput.value) barcodeInput.value.$el.focus();
    }
};

const removeItem = (index) => form.items.splice(index, 1);

const onRowInput = (event, item, field) => {
    item[field] = event.value;
    recalculateRow(item);
};

const recalculateRow = (item) => {
    const w = parseFloat(item.weight) || 0;
    const r = parseFloat(item.rate) || 0;
    const m = parseFloat(item.making_charges) || 0;
    item.final_price = Number((w * r + m).toFixed(2));
};

const roundMoney = (value) => Number((Number(value || 0)).toFixed(2));
const subTotal = computed(() => roundMoney(form.items.reduce((acc, item) => acc + (item.final_price || 0), 0)));
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

watch(
    () => form.gold_rate,
    (rate) => {
        form.items.forEach((item) => {
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
    if (!form.gold_rate) {
        toast.add({ severity: 'error', summary: 'Missing Rate', detail: "Enter today's Gold Rate", life: 3000 });
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
        date: new Date(data.date).toISOString().split('T')[0],
        discount_value: Number(data.discount_value || 0),
        items: data.items.map((item) => ({
            type: item.type,
            id: item.id,
            making_charges: item.making_charges || 0,
        })),
    })).post(route('invoices.store'), {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Success', detail: 'Invoice Generated!', life: 3000 });
            form.reset();
            form.customer_id = props.prefilledCustomer?.id || null;
            form.date = new Date().toISOString().split('T')[0];
            form.gold_rate = Number(props.defaultGoldRate || 0);
            form.discount_type = 'amount';
            form.discount_value = 0;
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
            <section class="relative overflow-hidden border border-surface-200 bg-white">
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
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="min-w-40 border border-surface-200 bg-surface-0 px-4 py-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-surface-500">Items</p>
                            <p class="mt-2 text-2xl font-semibold text-surface-900">{{ form.items.length }}</p>
                        </div>
                        <div class="min-w-40 border border-emerald-200 bg-emerald-50 px-4 py-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-emerald-700">Received</p>
                            <p class="mt-2 text-2xl font-semibold text-emerald-700">{{ formatCurrency(totalReceived) }}</p>
                        </div>
                        <div class="min-w-40 border border-amber-200 bg-amber-50 px-4 py-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-amber-700">Balance Due</p>
                            <p class="mt-2 text-2xl font-semibold" :class="balanceDue > 0 ? 'text-amber-700' : 'text-emerald-700'">{{ formatCurrency(balanceDue) }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- TOP ROW: Customer, Rate, Date -->
            <div class="card overflow-hidden !p-0">
                <div class="border-b border-surface-200 bg-white px-5 py-4">
                    <h3 class="text-lg font-semibold text-surface-900">Invoice Details</h3>
                    <p class="mt-1 text-sm text-surface-500">Select customer, rate, and invoice date</p>
                </div>

                <div class="bg-white p-5">
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-4">
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
                                <CustomerSelector v-model="form.customer_id" class="w-full" :errorMessage="form.errors.customer_id" />
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
                <div class="card flex h-full flex-col overflow-hidden !p-0 lg:col-span-2">
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-surface-200 bg-white px-5 py-4">
                        <div>
                            <h3 class="text-lg font-semibold text-surface-900">Invoice Items</h3>
                            <p class="mt-1 text-sm text-surface-500">Scan barcode or enter product code</p>
                        </div>

                        <span class="text-sm font-medium text-surface-500"> {{ form.items?.length || 0 }} items </span>
                    </div>

                    <!-- Scanner Input -->
                    <div class="flex gap-3 border-b border-surface-200 bg-surface-50 p-4">
                        <IconField class="flex-1">
                            <InputIcon class="pi pi-barcode" />
                            <InputText ref="barcodeInput" v-model="scannedBarcode" @keydown.enter="fetchProduct" placeholder="Scan barcode or enter product code..." class="w-full" />
                        </IconField>

                        <Button label="Add Item" icon="pi pi-plus" @click="fetchProduct" :loading="isProcessing" />
                    </div>

                    <!-- Table -->
                    <DataTable :value="form.items" scrollable scrollHeight="420px" stripedRows rowHover size="small" dataKey="id" class="text-sm">
                        <!-- Empty -->
                        <template #empty>
                            <div class="flex flex-col items-center py-16 text-center text-surface-500">
                                <i class="pi pi-barcode mb-4 text-4xl opacity-30"></i>
                                <p class="font-medium">No items added</p>
                                <span class="text-sm">Scan barcode to start billing</span>
                            </div>
                        </template>

                        <!-- Type -->
                        <Column header="Type" style="width: 90px">
                            <template #body="{ data }">
                                <Tag :value="data.type === 'order_item' ? 'ORDER' : 'STOCK'" :severity="data.type === 'order_item' ? 'info' : 'success'" />
                            </template>
                        </Column>

                        <!-- Item -->
                        <Column field="description" header="Item" style="min-width: 220px">
                            <template #body="{ data }">
                                <div class="flex flex-col">
                                    <span class="font-medium text-surface-900">
                                        {{ data.description }}
                                    </span>

                                    <span v-if="data.type === 'order_item'" class="mt-1 text-xs text-primary"> Custom Work </span>
                                </div>
                            </template>
                        </Column>

                        <!-- Weight -->
                        <Column header="Wt (g)" style="width: 90px">
                            <template #body="{ data }">
                                <div class="text-right font-medium">
                                    {{ data.weight }}
                                </div>
                            </template>
                        </Column>

                        <!-- Rate -->
                        <Column header="Rate/g" style="width: 130px">
                            <template #body="{ data }">
                                <InputNumber
                                    v-model="data.rate"
                                    inputClass="w-full text-right"
                                    class="w-full"
                                    mode="decimal"
                                    :minFractionDigits="2"
                                    :maxFractionDigits="2"
                                    @input="onRowInput($event, data, 'rate')"
                                />
                            </template>
                        </Column>

                        <!-- Making -->
                        <Column header="Making" style="width: 130px">
                            <template #body="{ data }">
                                <InputNumber
                                    v-model="data.making_charges"
                                    inputClass="w-full text-right"
                                    class="w-full"
                                    mode="decimal"
                                    :minFractionDigits="2"
                                    :maxFractionDigits="2"
                                    @input="onRowInput($event, data, 'making_charges')"
                                />
                            </template>
                        </Column>

                        <!-- Total -->
                        <Column header="Total" style="width: 140px">
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
                <div class="card flex h-full flex-col justify-between overflow-hidden !p-0">
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
                            <div class="border border-emerald-200 bg-emerald-50 px-4 py-3">
                                <p class="text-xs font-medium uppercase tracking-wide text-emerald-700">Received</p>
                                <p class="mt-2 text-lg font-semibold text-emerald-700">{{ formatCurrency(totalReceived) }}</p>
                            </div>
                            <div class="border px-4 py-3" :class="balanceDue > 0 ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50'">
                                <p class="text-xs font-medium uppercase tracking-wide" :class="balanceDue > 0 ? 'text-amber-700' : 'text-emerald-700'">Ledger Due</p>
                                <p class="mt-2 text-lg font-semibold" :class="balanceDue > 0 ? 'text-amber-700' : 'text-emerald-700'">{{ formatCurrency(balanceDue) }}</p>
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
                        <Button label="Generate Invoice" icon="pi pi-print" severity="success" class="w-full" size="large" @click="submitInvoice" :loading="form.processing" :disabled="!isDayOpen" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
