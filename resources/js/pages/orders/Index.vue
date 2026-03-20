<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle, Clock, Hammer, Scale, Truck } from 'lucide-vue-next';
import { useToast } from 'primevue/usetoast';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';

import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Divider from 'primevue/divider';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tab from 'primevue/tab';
import TabList from 'primevue/tablist';
import TabPanel from 'primevue/tabpanel';
import TabPanels from 'primevue/tabpanels';
import Tabs from 'primevue/tabs';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import Toast from 'primevue/toast';

const props = defineProps({
    orders: Object,
    karigars: Array,
    suppliers: Array,
    customers: Array,
});

const page = usePage();
const toast = useToast();
const isDayOpen = computed(() => Boolean(page.props.dayStatus?.is_open));

const createDialog = ref(false);
const assignDialog = ref(false);
const transactionDialog = ref(false);
const completeDialog = ref(false);
const editDialog = ref(false);
const selectedItem = ref(null);

const createForm = useForm({
    customer_id: null,
    due_date: null,
    items: [{ item_name: '', metal_type: 'GOLD', target_weight: null, purity: 91.6, notes: '' }],
});

const editForm = useForm({
    id: null,
    item_name: '',
    metal_type: 'GOLD',
    target_weight: null,
    purity: 91.6,
    notes: '',
});

const assignForm = useForm({
    type: 'Karigar',
    id: null,
    issue_gold: null,
});

const transactionForm = useForm({
    metal_weight: null,
    date: new Date(),
    description: '',
});

const completeForm = useForm({
    received_weight: null,
    wastage: 0,
    extra_gold_added: 0,
    extra_gold_source: null,
    extra_gold_supplier_id: null,
    extra_gold_karigar_id: null,
    mismatch_note: '',
});

const totalInProduction = computed(() => props.orders?.ASSIGNED?.length || 0);
const totalReady = computed(() => props.orders?.READY?.length || 0);
const totalNew = computed(() => props.orders?.NEW?.length || 0);

const totalGoldInPipeline = computed(() => {
    return props.orders?.ASSIGNED?.reduce((acc, item) => acc + parseFloat(item.issued_gold || 0), 0) || 0;
});

const itemTransactionSummary = computed(() => {
    if (!selectedItem.value?.transactions) return { gold: 0 };

    return selectedItem.value.transactions.reduce(
        (acc, txn) => {
            if (txn.category === 'metal') acc.gold += parseFloat(txn.amount || 0);
            return acc;
        },
        { gold: 0 },
    );
});

const addItemRow = () => {
    createForm.items.push({
        item_name: '',
        metal_type: 'GOLD',
        target_weight: null,
        purity: 91.6,
        notes: '',
    });
};

const removeItemRow = (index) => {
    if (createForm.items.length > 1) createForm.items.splice(index, 1);
};

const resetCreateForm = () => {
    createForm.reset();
    createForm.clearErrors();
    createForm.customer_id = null;
    createForm.due_date = null;
    createForm.items = [{ item_name: '', metal_type: 'GOLD', target_weight: null, purity: 91.6, notes: '' }];
};

const openCreateDialog = () => {
    if (!isDayOpen.value) {
        toast.add({ severity: 'warn', summary: 'Day Closed', detail: 'Open the shop day first from the dashboard.', life: 3000 });
        return;
    }
    resetCreateForm();
    createDialog.value = true;
};

const closeCreateDialog = () => {
    createDialog.value = false;
    resetCreateForm();
};

const saveOrder = () => {
    createForm.post(route('orders.store'), {
        onSuccess: () => {
            closeCreateDialog();
            toast.add({
                severity: 'success',
                summary: 'Order Created',
                detail: 'New order has been placed successfully',
                life: 3000,
            });
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Please check the new order form',
                life: 3000,
            });
        },
    });
};

const openEdit = (item) => {
    editForm.reset();
    editForm.clearErrors();
    editForm.id = item.id;
    editForm.item_name = item.item_name;
    editForm.metal_type = item.metal_type || 'GOLD';
    editForm.target_weight = parseFloat(item.target_weight);
    editForm.purity = parseFloat(item.purity);
    editForm.notes = item.notes;
    editDialog.value = true;
};

const submitEdit = () => {
    editForm.put(route('orders.update-item', editForm.id), {
        onSuccess: () => {
            editDialog.value = false;
            toast.add({
                severity: 'info',
                summary: 'Updated',
                detail: 'Item details updated',
                life: 3000,
            });
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Unable to update the order item',
                life: 3000,
            });
        },
    });
};

const openAssign = (item) => {
    selectedItem.value = item;
    assignForm.reset();
    assignForm.clearErrors();
    assignForm.type = 'Karigar';
    assignForm.id = null;
    assignForm.issue_gold = parseFloat(item.target_weight || 0);
    assignDialog.value = true;
};

const submitAssignment = () => {
    if (!selectedItem.value) return;

    assignForm.post(route('orders.assign', selectedItem.value.id), {
        onSuccess: () => {
            assignDialog.value = false;
            toast.add({
                severity: 'info',
                summary: 'Assigned',
                detail: 'Sent to production',
                life: 3000,
            });
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Assignment could not be saved',
                life: 3000,
            });
        },
    });
};

const openTransactionModal = (item) => {
    selectedItem.value = item;
    transactionForm.reset();
    transactionForm.clearErrors();
    transactionForm.date = new Date();
    transactionDialog.value = true;
};

const submitTransaction = () => {
    if (!selectedItem.value) return;

    transactionForm.post(route('orders.transaction', selectedItem.value.id), {
        onSuccess: () => {
            transactionDialog.value = false;
            toast.add({
                severity: 'success',
                summary: 'Saved',
                detail: 'Transaction recorded',
                life: 3000,
            });
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Transaction could not be recorded',
                life: 3000,
            });
        },
    });
};

const openComplete = (item) => {
    selectedItem.value = item;
    completeForm.reset();
    completeForm.clearErrors();
    completeForm.received_weight = parseFloat(item.target_weight || 0);
    completeForm.wastage = 0;
    completeForm.extra_gold_added = 0;
    completeForm.extra_gold_source = null;
    completeForm.extra_gold_supplier_id = null;
    completeForm.extra_gold_karigar_id = item.assignee_type?.includes('Karigar') ? item.assignee_id : null;
    completeForm.mismatch_note = '';
    completeDialog.value = true;
};

const submitComplete = () => {
    if (!selectedItem.value) return;

    completeForm.post(route('orders.complete', selectedItem.value.id), {
        onSuccess: () => {
            completeDialog.value = false;
            toast.add({
                severity: 'success',
                summary: 'Received',
                detail: 'Item added to ready stock',
                life: 3000,
            });
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: completeForm.errors.extra_gold_added || completeForm.errors.mismatch_note || completeForm.errors.complete || 'Unable to receive the finished item',
                life: 3000,
            });
        },
    });
};

const formatWeight = (w) => (w ? parseFloat(w).toFixed(3) : '0.000');

const formatCurrency = (val) =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 0,
    }).format(val || 0);

const getDueDateClass = (date) => {
    if (!date) return 'text-surface-500';

    const due = new Date(date);
    const today = new Date();
    const diffDays = Math.ceil((due - today) / (1000 * 60 * 60 * 24));

    if (diffDays < 0) return 'text-red-600 font-semibold';
    if (diffDays <= 3) return 'text-orange-500 font-semibold';
    return 'text-surface-500';
};

const issueWeightForItem = (item) => {
    return parseFloat(item?.issued_gold || 0);
};

const metalTagSeverity = (metalType) => (metalType === 'SILVER' ? 'secondary' : 'warn');
const isSilverItem = (item) => item?.metal_type === 'SILVER';
const defaultPurityForMetal = (metalType) => (metalType === 'SILVER' ? 92.5 : 91.6);
const metalLabel = (metalType) => (metalType === 'SILVER' ? 'Silver' : 'Gold');

const completeIssuedGold = computed(() => parseFloat(selectedItem.value?.issued_gold || 0));

const completeReturnTotal = computed(() => parseFloat(completeForm.received_weight || 0) + parseFloat(completeForm.wastage || 0));

const completeRequiredExtraGold = computed(() => Math.max(completeReturnTotal.value - completeIssuedGold.value, 0));

const completeExtraGoldMatches = computed(() => {
    return Math.abs(parseFloat(completeForm.extra_gold_added || 0) - completeRequiredExtraGold.value) < 0.0001;
});

const canSubmitComplete = computed(() => {
    if (completeRequiredExtraGold.value <= 0) {
        return true;
    }

    if (!completeExtraGoldMatches.value || !completeForm.extra_gold_source) {
        return false;
    }

    if (completeForm.extra_gold_source === 'SUPPLIER' && !completeForm.extra_gold_supplier_id) {
        return false;
    }

    if (completeForm.extra_gold_source === 'KARIGAR' && !completeForm.extra_gold_karigar_id) {
        return false;
    }

    return Boolean((completeForm.mismatch_note || '').trim());
});

const extraGoldSourceOptions = computed(() => {
    const currentMetal = metalLabel(selectedItem.value?.metal_type);
    const options = [{ label: 'Shop Stock', value: 'SHOP' }];

    if (selectedItem.value?.order?.customer?.id) {
        options.push({
            label: `Customer ${currentMetal} (${selectedItem.value.order.customer.name})`,
            value: 'CUSTOMER',
        });
    }

    options.push({ label: `Supplier ${currentMetal}`, value: 'SUPPLIER' });
    options.push({ label: `Karigar ${currentMetal}`, value: 'KARIGAR' });

    return options;
});

watch(completeRequiredExtraGold, (value) => {
    completeForm.extra_gold_added = value > 0 ? Number(value.toFixed(3)) : 0;

    if (value <= 0) {
        completeForm.extra_gold_source = null;
        completeForm.extra_gold_supplier_id = null;
        completeForm.extra_gold_karigar_id = selectedItem.value?.assignee_type?.includes('Karigar') ? selectedItem.value.assignee_id : null;
    }
});

const closeEditDialog = () => {
    editDialog.value = false;
    editForm.reset();
    editForm.clearErrors();
};

const closeAssignDialog = () => {
    assignDialog.value = false;
    assignForm.reset();
    assignForm.clearErrors();
    selectedItem.value = null;
};

const closeTransactionDialog = () => {
    transactionDialog.value = false;
    transactionForm.reset();
    transactionForm.clearErrors();
    transactionForm.date = new Date();
    selectedItem.value = null;
};

const closeCompleteDialog = () => {
    completeDialog.value = false;
    completeForm.reset();
    completeForm.clearErrors();
    completeForm.extra_gold_added = 0;
    completeForm.extra_gold_source = null;
    completeForm.extra_gold_supplier_id = null;
    completeForm.extra_gold_karigar_id = null;
    completeForm.mismatch_note = '';
    selectedItem.value = null;
};

const transactionSeverity = (transaction) => {
    if (transaction.category === 'metal') {
        return transaction.type === 'ISSUE' ? 'warn' : 'success';
    }

    return transaction.type === 'PAYMENT' ? 'danger' : 'info';
};

const formatTransactionAmount = (transaction) => {
    if (transaction.category === 'metal') {
        return `${formatWeight(transaction.amount)} g`;
    }

    return formatCurrency(transaction.amount);
};

const itemFieldError = (index, field) => createForm.errors[`items.${index}.${field}`];
const formError = (form, field) => form.errors[field];

watch(
    () => assignForm.type,
    () => {
        assignForm.id = null;
        assignForm.clearErrors('id', 'assign');
    },
);

watch(
    () => completeForm.extra_gold_source,
    (source) => {
        if (source !== 'SUPPLIER') {
            completeForm.extra_gold_supplier_id = null;
            completeForm.clearErrors('extra_gold_supplier_id');
        }

        if (source !== 'KARIGAR') {
            completeForm.extra_gold_karigar_id = selectedItem.value?.assignee_type?.includes('Karigar') ? selectedItem.value.assignee_id : null;
            completeForm.clearErrors('extra_gold_karigar_id');
        }
    },
);
</script>

<template>
    <AppLayout>
        <Toast />
        <div class="space-y-6">
            <!-- Header -->
            <div class="border-b border-surface-200 bg-white px-5 py-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="flex items-center gap-3">
                            <h1 class="text-2xl font-semibold tracking-tight text-surface-900">Production Pipeline</h1>
                            <Tag value="Workshop" severity="secondary" />
                        </div>
                        <p class="mt-1 text-sm text-surface-500">Track order items from request to assignment, production, and ready stock</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Button label="New Order" icon="pi pi-plus" @click="openCreateDialog" :disabled="!isDayOpen" />
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm text-surface-500">Pending</p>
                            <p class="mt-2 text-2xl font-semibold text-surface-900">
                                {{ totalNew }}
                            </p>
                        </div>
                        <div class="flex h-10 w-10 items-center justify-center bg-blue-50 text-blue-600">
                            <Clock class="h-4 w-4" />
                        </div>
                    </div>
                </div>

                <div class="border border-surface-200 bg-white px-5 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm text-surface-500">In Production</p>
                            <p class="mt-2 text-2xl font-semibold text-surface-900">
                                {{ totalInProduction }}
                            </p>
                        </div>
                        <div class="flex h-10 w-10 items-center justify-center bg-orange-50 text-orange-600">
                            <Hammer class="h-4 w-4" />
                        </div>
                    </div>
                </div>

                <div class="border border-surface-200 bg-white px-5 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm text-surface-500">Metal Issued</p>
                            <p class="mt-2 text-2xl font-semibold text-surface-900">{{ formatWeight(totalGoldInPipeline) }} g</p>
                        </div>
                        <div class="flex h-10 w-10 items-center justify-center bg-yellow-50 text-yellow-700">
                            <Scale class="h-4 w-4" />
                        </div>
                    </div>
                </div>

                <div class="border border-surface-200 bg-white px-5 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm text-surface-500">Ready</p>
                            <p class="mt-2 text-2xl font-semibold text-surface-900">
                                {{ totalReady }}
                            </p>
                        </div>
                        <div class="flex h-10 w-10 items-center justify-center bg-green-50 text-green-600">
                            <CheckCircle class="h-4 w-4" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs / Tables -->
            <div class="card overflow-hidden !p-0">
                <Tabs value="0">
                    <TabList class="border-b border-surface-200">
                        <Tab value="0">
                            <div class="flex items-center gap-2">
                                <span>New</span>
                                <Tag v-if="orders.NEW.length" :value="orders.NEW.length" severity="secondary" />
                            </div>
                        </Tab>

                        <Tab value="1">
                            <div class="flex items-center gap-2">
                                <span>Production</span>
                                <Tag v-if="orders.ASSIGNED.length" :value="orders.ASSIGNED.length" severity="warn" />
                            </div>
                        </Tab>

                        <Tab value="2">
                            <div class="flex items-center gap-2">
                                <span>Ready</span>
                                <Tag v-if="orders.READY.length" :value="orders.READY.length" severity="success" />
                            </div>
                        </Tab>
                    </TabList>

                    <TabPanels>
                        <!-- New -->
                        <TabPanel value="0" class="!p-0">
                            <div class="border-b border-surface-200 bg-white py-4">
                                <h3 class="text-base font-semibold text-surface-900">New Order Items</h3>
                                <p class="mt-1 text-sm text-surface-500">Items waiting to be assigned to workshop or supplier</p>
                            </div>

                            <div class="bg-white">
                                <DataTable :value="orders.NEW" stripedRows rowHover paginator :rows="10" dataKey="id" tableStyle="min-width: 58rem">
                                    <template #empty>
                                        <div class="py-12 text-center text-surface-500">No pending orders</div>
                                    </template>

                                    <Column field="order.order_number" header="Order #" sortable style="width: 130px">
                                        <template #body="{ data }">
                                            <Tag :value="data.order?.order_number" severity="info" />
                                        </template>
                                    </Column>

                                    <Column field="order.customer.name" header="Customer" sortable />

                                    <Column field="item_name" header="Item">
                                        <template #body="{ data }">
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <p class="font-medium text-surface-900">{{ data.item_name }}</p>
                                                    <Tag :value="data.metal_type || 'GOLD'" :severity="metalTagSeverity(data.metal_type)" />
                                                </div>
                                                <p v-if="data.notes" class="mt-1 text-xs text-surface-500">
                                                    {{ data.notes }}
                                                </p>
                                            </div>
                                        </template>
                                    </Column>

                                    <Column field="target_weight" header="Weight" sortable style="width: 140px">
                                        <template #body="{ data }">
                                            <div>
                                                <span class="font-semibold text-surface-900"> {{ formatWeight(data.target_weight) }} g </span>
                                                <span class="ml-1 text-xs text-surface-500">
                                                    ({{ data.metal_type === 'SILVER' ? `${data.purity}%` : data.purity == 91.6 ? '22K' : '18K' }})
                                                </span>
                                            </div>
                                        </template>
                                    </Column>

                                    <Column field="order.due_date" header="Due" sortable style="width: 120px">
                                        <template #body="{ data }">
                                            <span :class="getDueDateClass(data.order?.due_date)">
                                                {{ data.order?.due_date || '—' }}
                                            </span>
                                        </template>
                                    </Column>

                                    <Column header="Actions" style="width: 180px">
                                        <template #body="{ data }">
                                            <div class="flex justify-end gap-1">
                                                <Button icon="pi pi-pencil" severity="secondary" text size="small" @click="openEdit(data)" />
                                                <Button label="Assign" icon="pi pi-arrow-right" size="small" @click="openAssign(data)" />
                                            </div>
                                        </template>
                                    </Column>
                                </DataTable>
                            </div>
                        </TabPanel>

                        <!-- Production -->
                        <TabPanel value="1" class="!p-0">
                            <div class="border-b border-surface-200 bg-white py-4">
                                <h3 class="text-base font-semibold text-surface-900">Items in Production</h3>
                                <p class="mt-1 text-sm text-surface-500">Track metal issue, production movement, and finished receipt</p>
                            </div>

                            <div class="bg-white">
                                <DataTable :value="orders.ASSIGNED" stripedRows rowHover paginator :rows="10" dataKey="id" tableStyle="min-width: 62rem">
                                    <template #empty>
                                        <div class="py-12 text-center text-surface-500">No items in production</div>
                                    </template>

                                    <Column field="item_name" header="Item" style="width: 260px">
                                        <template #body="{ data }">
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <p class="font-medium text-surface-900">{{ data.item_name }}</p>
                                                    <Tag :value="data.metal_type || 'GOLD'" :severity="metalTagSeverity(data.metal_type)" />
                                                </div>
                                                <div class="mt-1 flex items-center gap-2 text-xs text-surface-500">
                                                    <Tag :value="data.order?.order_number" severity="secondary" />
                                                    <span>{{ data.order?.customer?.name }}</span>
                                                </div>
                                            </div>
                                        </template>
                                    </Column>

                                    <Column header="Assigned To" style="width: 220px">
                                        <template #body="{ data }">
                                            <div class="flex items-center gap-3">
                                                <div class="flex h-8 w-8 items-center justify-center bg-surface-100 text-surface-600">
                                                    <Hammer v-if="data.assignee_type?.includes('Karigar')" class="h-4 w-4" />
                                                    <Truck v-else class="h-4 w-4" />
                                                </div>

                                                <div>
                                                    <p class="font-medium text-surface-900">
                                                        {{ data.assignee?.name || data.assignee?.company_name }}
                                                    </p>
                                                    <p class="text-xs text-surface-500">
                                                        {{ data.assignee_type?.includes('Karigar') ? 'Karigar' : 'Supplier' }}
                                                    </p>
                                                </div>
                                            </div>
                                        </template>
                                    </Column>

                                    <Column header="Metal Given" style="width: 150px">
                                        <template #body="{ data }">
                                            <div>
                                                <p class="font-semibold text-surface-900">{{ formatWeight(issueWeightForItem(data)) }} g</p>
                                                <p class="text-xs text-surface-500">of {{ formatWeight(data.target_weight) }} g</p>
                                            </div>
                                        </template>
                                    </Column>

                                    <Column header="Actions" style="width: 200px">
                                        <template #body="{ data }">
                                            <div class="flex justify-end gap-1">
                                                <Button label="Txn" icon="pi pi-history" severity="warn" outlined size="small" @click="openTransactionModal(data)" />
                                                <Button label="Receive" icon="pi pi-check" severity="success" size="small" @click="openComplete(data)" />
                                            </div>
                                        </template>
                                    </Column>
                                </DataTable>
                            </div>
                        </TabPanel>

                        <!-- Ready -->
                        <TabPanel value="2" class="!p-0">
                            <div class="border-b border-surface-200 bg-white py-4">
                                <h3 class="text-base font-semibold text-surface-900">Ready for Billing</h3>
                                <p class="mt-1 text-sm text-surface-500">Completed items available for invoicing</p>
                            </div>

                            <div class="bg-white">
                                <DataTable :value="orders.READY" stripedRows rowHover paginator :rows="10" dataKey="id" tableStyle="min-width: 56rem">
                                    <template #empty>
                                        <div class="py-12 text-center text-surface-500">No items ready for billing</div>
                                    </template>

                                    <Column field="item_name" header="Item" sortable>
                                        <template #body="{ data }">
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <p class="font-medium text-surface-900">{{ data.item_name }}</p>
                                                    <Tag :value="data.metal_type || 'GOLD'" :severity="metalTagSeverity(data.metal_type)" />
                                                </div>
                                                <p class="mt-1 text-xs text-surface-500">
                                                    {{ data.order?.customer?.name }}
                                                </p>
                                            </div>
                                        </template>
                                    </Column>

                                    <Column field="finished_weight" header="Final Wt" sortable style="width: 130px">
                                        <template #body="{ data }">
                                            <span class="font-semibold text-green-600"> {{ formatWeight(data.finished_weight) }} g </span>
                                        </template>
                                    </Column>

                                    <Column field="wastage" header="Wastage" style="width: 130px">
                                        <template #body="{ data }">
                                            <span class="text-sm text-red-500"> +{{ formatWeight(data.wastage) }} g </span>
                                        </template>
                                    </Column>

                                    <Column header="Status" style="width: 110px">
                                        <template #body>
                                            <Tag severity="success" value="Ready" />
                                        </template>
                                    </Column>

                                    <Column header="" style="width: 160px">
                                        <template #body="{ data }">
                                            <div class="flex justify-end">
                                                <Button label="Add to Invoice" icon="pi pi-file" outlined size="small" @click="router.get('/invoices/create', { order_id: data.order_id })" />
                                            </div>
                                        </template>
                                    </Column>
                                </DataTable>
                            </div>
                        </TabPanel>
                    </TabPanels>
                </Tabs>
            </div>
        </div>

        <!-- Create Order -->
        <Dialog v-model:visible="createDialog" header="Create New Order" modal :style="{ width: '52rem' }" :breakpoints="{ '640px': '95vw' }" @hide="closeCreateDialog">
            <div class="space-y-5 pt-2">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Customer</label>
                        <Select v-model="createForm.customer_id" :options="customers" optionLabel="name" optionValue="id" filter placeholder="Search customer..." class="w-full" />
                        <p v-if="formError(createForm, 'customer_id')" class="mt-2 text-sm text-red-600">{{ formError(createForm, 'customer_id') }}</p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Due Date</label>
                        <Calendar v-model="createForm.due_date" showIcon dateFormat="yy-mm-dd" class="w-full" inputClass="w-full" placeholder="Select date" />
                        <p v-if="formError(createForm, 'due_date')" class="mt-2 text-sm text-red-600">{{ formError(createForm, 'due_date') }}</p>
                    </div>
                </div>

                <p v-if="formError(createForm, 'items')" class="text-sm text-red-600">{{ formError(createForm, 'items') }}</p>

                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <label class="text-sm font-medium text-surface-700">Items</label>
                        <Button label="Add Item" icon="pi pi-plus" size="small" text @click="addItemRow" />
                    </div>

                    <div class="max-h-[360px] space-y-3 overflow-y-auto pr-1">
                        <div v-for="(item, idx) in createForm.items" :key="idx" class="border border-surface-200 bg-white p-4">
                            <div class="mb-3 flex items-center justify-between">
                                <span class="text-xs font-semibold tracking-wide text-surface-500 uppercase"> Item {{ idx + 1 }} </span>

                                <Button v-if="createForm.items.length > 1" icon="pi pi-trash" text severity="danger" size="small" @click="removeItemRow(idx)" />
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-12">
                                <div class="sm:col-span-5">
                                    <InputText v-model="item.item_name" class="w-full" placeholder="Item name" />
                                    <p v-if="itemFieldError(idx, 'item_name')" class="mt-2 text-sm text-red-600">{{ itemFieldError(idx, 'item_name') }}</p>
                                </div>

                                <div class="sm:col-span-3">
                                    <Select
                                        v-model="item.metal_type"
                                        :options="[
                                            { l: 'Gold', v: 'GOLD' },
                                            { l: 'Silver', v: 'SILVER' },
                                        ]"
                                        optionLabel="l"
                                        optionValue="v"
                                        class="w-full"
                                        @change="item.purity = defaultPurityForMetal(item.metal_type)"
                                    />
                                    <p v-if="itemFieldError(idx, 'metal_type')" class="mt-2 text-sm text-red-600">{{ itemFieldError(idx, 'metal_type') }}</p>
                                </div>

                                <div class="sm:col-span-3">
                                    <InputNumber v-model="item.target_weight" :minFractionDigits="2" class="w-full" placeholder="Weight (g)" />
                                    <p v-if="itemFieldError(idx, 'target_weight')" class="mt-2 text-sm text-red-600">{{ itemFieldError(idx, 'target_weight') }}</p>
                                </div>

                                <div class="sm:col-span-4">
                                    <Select
                                        v-model="item.purity"
                                        :options="
                                            item.metal_type === 'SILVER'
                                                ? [
                                                      { l: 'Pure Silver (99.9)', v: 99.9 },
                                                      { l: 'Sterling Silver (92.5)', v: 92.5 },
                                                  ]
                                                : [
                                                      { l: '22K (91.6)', v: 91.6 },
                                                      { l: '18K (75.0)', v: 75 },
                                                  ]
                                        "
                                        optionLabel="l"
                                        optionValue="v"
                                        class="w-full"
                                    />
                                    <p v-if="itemFieldError(idx, 'purity')" class="mt-2 text-sm text-red-600">{{ itemFieldError(idx, 'purity') }}</p>
                                </div>

                                <div class="sm:col-span-12">
                                    <InputText v-model="item.notes" class="w-full" placeholder="Notes, sizes, design code..." />
                                    <p v-if="itemFieldError(idx, 'notes')" class="mt-2 text-sm text-red-600">{{ itemFieldError(idx, 'notes') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-surface-200 pt-4">
                    <Button label="Cancel" severity="secondary" text @click="closeCreateDialog" />
                    <Button label="Create Order" icon="pi pi-check" @click="saveOrder" :loading="createForm.processing" />
                </div>
            </div>
        </Dialog>

        <!-- Edit -->
        <Dialog v-model:visible="editDialog" header="Edit Item" modal :style="{ width: '30rem' }" @hide="closeEditDialog">
            <div class="space-y-4 pt-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Item Name</label>
                    <InputText v-model="editForm.item_name" class="w-full" />
                    <p v-if="formError(editForm, 'item_name')" class="mt-2 text-sm text-red-600">{{ formError(editForm, 'item_name') }}</p>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Metal Type</label>
                        <Select
                            v-model="editForm.metal_type"
                            :options="[
                                { l: 'Gold', v: 'GOLD' },
                                { l: 'Silver', v: 'SILVER' },
                            ]"
                            optionLabel="l"
                            optionValue="v"
                            class="w-full"
                            @change="editForm.purity = defaultPurityForMetal(editForm.metal_type)"
                        />
                        <p v-if="formError(editForm, 'metal_type')" class="mt-2 text-sm text-red-600">{{ formError(editForm, 'metal_type') }}</p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Weight (g)</label>
                        <InputNumber v-model="editForm.target_weight" :minFractionDigits="3" class="w-full" />
                        <p v-if="formError(editForm, 'target_weight')" class="mt-2 text-sm text-red-600">{{ formError(editForm, 'target_weight') }}</p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Purity</label>
                        <Select
                            v-model="editForm.purity"
                            :options="
                                editForm.metal_type === 'SILVER'
                                    ? [
                                          { l: 'Pure Silver (99.9)', v: 99.9 },
                                          { l: 'Sterling Silver (92.5)', v: 92.5 },
                                      ]
                                    : [
                                          { l: '22K', v: 91.6 },
                                          { l: '18K', v: 75 },
                                      ]
                            "
                            optionLabel="l"
                            optionValue="v"
                            class="w-full"
                        />
                        <p v-if="formError(editForm, 'purity')" class="mt-2 text-sm text-red-600">{{ formError(editForm, 'purity') }}</p>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Notes</label>
                    <Textarea v-model="editForm.notes" rows="3" class="w-full" />
                    <p v-if="formError(editForm, 'notes')" class="mt-2 text-sm text-red-600">{{ formError(editForm, 'notes') }}</p>
                    <p v-if="formError(editForm, 'item')" class="mt-2 text-sm text-red-600">{{ formError(editForm, 'item') }}</p>
                </div>

                <div class="flex justify-end gap-2 border-t border-surface-200 pt-4">
                    <Button label="Cancel" severity="secondary" text @click="closeEditDialog" />
                    <Button label="Save" icon="pi pi-save" @click="submitEdit" :loading="editForm.processing" />
                </div>
            </div>
        </Dialog>

        <!-- Assign -->
        <Dialog v-model:visible="assignDialog" header="Assign to Production" modal :style="{ width: '28rem' }" @hide="closeAssignDialog">
            <div class="space-y-4 pt-2">
                <div class="border border-surface-200 bg-surface-50">
                    <div class="grid grid-cols-2">
                        <button
                            class="border-r border-surface-200 px-4 py-3 text-sm font-medium transition"
                            :class="assignForm.type === 'Karigar' ? 'bg-white text-surface-900' : 'text-surface-500'"
                            @click="assignForm.type = 'Karigar'"
                        >
                            Karigar
                        </button>

                        <button
                            class="px-4 py-3 text-sm font-medium transition"
                            :class="assignForm.type === 'Supplier' ? 'bg-white text-surface-900' : 'text-surface-500'"
                            @click="assignForm.type = 'Supplier'"
                        >
                            Supplier
                        </button>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700"> Select {{ assignForm.type }} </label>

                    <Select v-if="assignForm.type === 'Karigar'" v-model="assignForm.id" :options="karigars" optionLabel="name" optionValue="id" placeholder="Choose karigar..." class="w-full" />

                    <Select v-else v-model="assignForm.id" :options="suppliers" optionLabel="company_name" optionValue="id" placeholder="Choose supplier..." class="w-full" />
                    <p v-if="formError(assignForm, 'id')" class="mt-2 text-sm text-red-600">{{ formError(assignForm, 'id') }}</p>
                </div>

                <div class="border border-surface-200 bg-surface-50 p-4">
                    <label class="mb-2 block text-sm font-medium text-surface-700"> Initial {{ metalLabel(selectedItem?.metal_type) }} Issue (g) </label>
                    <InputNumber v-model="assignForm.issue_gold" :minFractionDigits="3" suffix=" g" class="w-full" />
                    <p class="mt-2 text-xs text-surface-500">Leave this empty or 0 if you are only assigning the item now. Additional {{ metalLabel(selectedItem?.metal_type).toLowerCase() }} can be issued later from transactions.</p>
                    <p v-if="formError(assignForm, 'issue_gold')" class="mt-2 text-sm text-red-600">{{ formError(assignForm, 'issue_gold') }}</p>
                    <p v-if="formError(assignForm, 'assign')" class="mt-2 text-sm text-red-600">{{ formError(assignForm, 'assign') }}</p>
                </div>

                <div class="flex justify-end gap-2 border-t border-surface-200 pt-4">
                    <Button label="Cancel" severity="secondary" text @click="closeAssignDialog" />
                    <Button label="Assign" icon="pi pi-arrow-right" @click="submitAssignment" :loading="assignForm.processing" />
                </div>
            </div>
        </Dialog>

        <!-- Transactions -->
        <Dialog
            v-model:visible="transactionDialog"
            :header="`${metalLabel(selectedItem?.metal_type)} Issue History`"
            modal
            :style="{ width: '40rem' }"
            :breakpoints="{ '640px': '95vw' }"
            @hide="closeTransactionDialog"
        >
            <div class="space-y-5 pt-2">
                <div class="border border-surface-200 bg-surface-50 px-4 py-3">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="font-medium text-surface-900">{{ selectedItem?.item_name }}</p>
                            <p class="mt-1 text-xs text-surface-500">
                                {{ selectedItem?.assignee?.name || selectedItem?.assignee?.company_name }}
                            </p>
                        </div>

                        <div class="text-right">
                            <p class="text-xs text-surface-500">Target Weight</p>
                            <p class="font-semibold text-surface-900">{{ formatWeight(selectedItem?.target_weight) }} g</p>
                        </div>
                    </div>
                </div>

                <div class="border border-surface-200 bg-white">
                    <div class="border-b border-surface-200 px-4 py-3">
                        <div class="flex items-center justify-between gap-3">
                            <h4 class="text-sm font-semibold text-surface-900">Transaction History</h4>
                            <span class="text-xs text-surface-500">{{ metalLabel(selectedItem?.metal_type) }} moved: {{ formatWeight(itemTransactionSummary.gold) }} g</span>
                        </div>
                    </div>

                    <div class="p-4">
                        <DataTable :value="selectedItem?.transactions || []" size="small" stripedRows scrollable scrollHeight="160px">
                            <template #empty>
                                <div class="p-6 text-center text-sm text-surface-500">No transactions yet</div>
                            </template>

                            <Column field="date" header="Date" />
                            <Column field="type" header="Type">
                                <template #body="{ data }">
                                    <Tag :value="data.type" :severity="transactionSeverity(data)" />
                                </template>
                            </Column>
                            <Column field="amount" header="Amount">
                                <template #body="{ data }">
                                    <span class="font-medium text-surface-900">{{ formatTransactionAmount(data) }}</span>
                                </template>
                            </Column>
                            <Column field="description" header="Note" />
                        </DataTable>
                    </div>
                </div>

                <div class="border border-surface-200 bg-white">
                    <div class="border-b border-surface-200 px-4 py-3">
                        <h4 class="text-sm font-semibold text-surface-900">Issue Additional {{ metalLabel(selectedItem?.metal_type) }}</h4>
                    </div>

                    <div class="space-y-4 p-4">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-surface-700">Transaction Date</label>
                                <Calendar v-model="transactionForm.date" showIcon dateFormat="yy-mm-dd" class="w-full" inputClass="w-full" />
                                <p v-if="formError(transactionForm, 'date')" class="mt-2 text-sm text-red-600">{{ formError(transactionForm, 'date') }}</p>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-surface-700">{{ metalLabel(selectedItem?.metal_type) }} Weight (g)</label>
                                <InputNumber v-model="transactionForm.metal_weight" :minFractionDigits="3" suffix=" g" placeholder="0.000" class="w-full" />
                                <p v-if="formError(transactionForm, 'metal_weight')" class="mt-2 text-sm text-red-600">{{ formError(transactionForm, 'metal_weight') }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-surface-700">Description</label>
                            <InputText v-model="transactionForm.description" :placeholder="`Optional note for this ${metalLabel(selectedItem?.metal_type).toLowerCase()} issue`" class="w-full" />
                            <p v-if="formError(transactionForm, 'description')" class="mt-2 text-sm text-red-600">{{ formError(transactionForm, 'description') }}</p>
                            <p v-if="formError(transactionForm, 'transaction')" class="mt-2 text-sm text-red-600">{{ formError(transactionForm, 'transaction') }}</p>
                        </div>

                        <div class="flex justify-end border-t border-surface-200 pt-4">
                            <Button :label="`Save ${metalLabel(selectedItem?.metal_type)} Issue`" icon="pi pi-check" @click="submitTransaction" :loading="transactionForm.processing" />
                        </div>
                    </div>
                </div>
            </div>
        </Dialog>

        <!-- Receive -->
        <Dialog v-model:visible="completeDialog" header="Receive Finished Item" modal :style="{ width: '26rem' }" @hide="closeCompleteDialog">
            <div class="space-y-4 pt-2">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="border border-surface-200 bg-surface-50 px-4 py-4 text-center">
                        <p class="text-xs font-medium tracking-wide text-surface-500 uppercase">Target Weight</p>
                        <p class="mt-1 text-xl font-semibold text-surface-900">{{ formatWeight(selectedItem?.target_weight) }} g</p>
                    </div>

                    <div class="border border-surface-200 bg-surface-50 px-4 py-4 text-center">
                        <p class="text-xs font-medium tracking-wide text-surface-500 uppercase">Issued {{ metalLabel(selectedItem?.metal_type) }}</p>
                        <p class="mt-1 text-xl font-semibold text-surface-900">{{ formatWeight(completeIssuedGold) }} g</p>
                    </div>

                    <div class="border border-surface-200 bg-surface-50 px-4 py-4 text-center">
                        <p class="text-xs font-medium tracking-wide text-surface-500 uppercase">Return Total</p>
                        <p class="mt-1 text-xl font-semibold text-surface-900">{{ formatWeight(completeReturnTotal) }} g</p>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Received Weight (g)</label>
                    <InputNumber v-model="completeForm.received_weight" :minFractionDigits="3" class="w-full" autofocus />
                    <p v-if="formError(completeForm, 'received_weight')" class="mt-2 text-sm text-red-600">{{ formError(completeForm, 'received_weight') }}</p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Wastage (g)</label>
                    <InputNumber v-model="completeForm.wastage" :minFractionDigits="3" class="w-full" />
                    <p v-if="formError(completeForm, 'wastage')" class="mt-2 text-sm text-red-600">{{ formError(completeForm, 'wastage') }}</p>
                </div>

                <div v-if="completeRequiredExtraGold > 0" class="border border-amber-300 bg-amber-50 px-4 py-3">
                    <p class="text-sm font-semibold text-amber-900">Weight mismatch detected</p>
                    <p class="mt-1 text-sm text-amber-800">
                        Return total is {{ formatWeight(completeReturnTotal) }} g, but only {{ formatWeight(completeIssuedGold) }} g was issued.
                    </p>
                    <p v-if="completeIssuedGold <= 0" class="mt-1 text-sm text-amber-800">
                        No {{ metalLabel(selectedItem?.metal_type).toLowerCase() }} issue is recorded for this item. Record the issued {{ metalLabel(selectedItem?.metal_type).toLowerCase() }} first, or explicitly declare the full {{ formatWeight(completeRequiredExtraGold) }} g as extra metal added.
                    </p>
                    <p v-else class="mt-1 text-sm text-amber-800">Record {{ formatWeight(completeRequiredExtraGold) }} g as extra {{ metalLabel(selectedItem?.metal_type).toLowerCase() }} added before receiving this item.</p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Extra {{ metalLabel(selectedItem?.metal_type) }} Added (g)</label>
                    <InputNumber
                        v-model="completeForm.extra_gold_added"
                        :minFractionDigits="3"
                        class="w-full"
                        :placeholder="completeRequiredExtraGold > 0 ? formatWeight(completeRequiredExtraGold) : '0.000'"
                    />
                    <p class="mt-1 text-xs text-surface-500">
                        Required mismatch amount: {{ formatWeight(completeRequiredExtraGold) }} g. This field is auto-filled from the difference.
                    </p>
                    <p v-if="completeRequiredExtraGold > 0 && !completeExtraGoldMatches" class="mt-2 text-sm text-red-600">
                        Extra {{ metalLabel(selectedItem?.metal_type).toLowerCase() }} added must match the required mismatch amount of {{ formatWeight(completeRequiredExtraGold) }} g.
                    </p>
                    <p v-if="completeForm.errors.extra_gold_added" class="mt-2 text-sm text-red-600">
                        {{ completeForm.errors.extra_gold_added }}
                    </p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Extra {{ metalLabel(selectedItem?.metal_type) }} Source</label>
                    <Select
                        v-model="completeForm.extra_gold_source"
                        :options="extraGoldSourceOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Select source of extra gold"
                        class="w-full"
                    />
                    <p class="mt-1 text-xs text-surface-500">This tells the system where the additional {{ metalLabel(selectedItem?.metal_type).toLowerCase() }} actually came from.</p>
                    <p v-if="completeForm.errors.extra_gold_source" class="mt-2 text-sm text-red-600">
                        {{ completeForm.errors.extra_gold_source }}
                    </p>
                </div>

                <div v-if="completeForm.extra_gold_source === 'SUPPLIER'">
                    <label class="mb-2 block text-sm font-medium text-surface-700">Supplier</label>
                    <Select
                        v-model="completeForm.extra_gold_supplier_id"
                        :options="suppliers"
                        optionLabel="company_name"
                        optionValue="id"
                        placeholder="Select supplier"
                        class="w-full"
                    />
                    <p v-if="completeForm.errors.extra_gold_supplier_id" class="mt-2 text-sm text-red-600">
                        {{ completeForm.errors.extra_gold_supplier_id }}
                    </p>
                </div>

                <div v-if="completeForm.extra_gold_source === 'KARIGAR'">
                    <label class="mb-2 block text-sm font-medium text-surface-700">Karigar</label>
                    <Select
                        v-model="completeForm.extra_gold_karigar_id"
                        :options="karigars"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Select karigar"
                        class="w-full"
                    />
                    <p class="mt-1 text-xs text-surface-500">Choose the karigar who provided the extra {{ metalLabel(selectedItem?.metal_type).toLowerCase() }}. The assigned karigar is preselected when available.</p>
                    <p v-if="completeForm.errors.extra_gold_karigar_id" class="mt-2 text-sm text-red-600">
                        {{ completeForm.errors.extra_gold_karigar_id }}
                    </p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Mismatch / Extra {{ metalLabel(selectedItem?.metal_type) }} Note</label>
                    <Textarea
                        v-model="completeForm.mismatch_note"
                        rows="3"
                        class="w-full"
                        :placeholder="`Why was extra ${metalLabel(selectedItem?.metal_type).toLowerCase()} added or why does finished return differ from issued ${metalLabel(selectedItem?.metal_type).toLowerCase()}?`"
                    />
                    <p v-if="completeForm.errors.mismatch_note" class="mt-2 text-sm text-red-600">
                        {{ completeForm.errors.mismatch_note }}
                    </p>
                </div>

                <Divider />

                <p v-if="formError(completeForm, 'complete')" class="text-sm text-red-600">{{ formError(completeForm, 'complete') }}</p>

                <Button
                    label="Mark as Ready"
                    severity="success"
                    icon="pi pi-check-circle"
                    class="w-full"
                    @click="submitComplete"
                    :loading="completeForm.processing"
                    :disabled="!canSubmitComplete"
                />
            </div>
        </Dialog>
    </AppLayout>
</template>
