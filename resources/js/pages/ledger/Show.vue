<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';

import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { todayIndianDate } from '@/utils/indiaTime';

const props = defineProps({
    party: Object,
    party_type_class: String,
    transactions: Array,
});

const page = usePage();
const toast = useToast();
const isDayOpen = computed(() => Boolean(page.props.dayStatus?.is_open));
const showDialog = ref(false);
const editingRow = ref(null);

const entryOptions = [
    { label: 'Issue Gold', value: 'ISSUE_GOLD' },
    { label: 'Receive Gold', value: 'RECEIVE_GOLD' },
    { label: 'Issue Silver', value: 'ISSUE_SILVER' },
    { label: 'Receive Silver', value: 'RECEIVE_SILVER' },
    { label: 'Pay Cash', value: 'PAY_CASH' },
    { label: 'Receive Cash', value: 'RECEIVE_CASH' },
    { label: 'Gold → Cash Adjustment', value: 'GOLD_TO_CASH' },
    { label: 'Cash → Gold Adjustment', value: 'CASH_TO_GOLD' },
    { label: 'Silver → Cash Adjustment', value: 'SILVER_TO_CASH' },
    { label: 'Cash → Silver Adjustment', value: 'CASH_TO_SILVER' },
];

const form = useForm({
    party_type: props.party_type_class,
    party_id: props.party?.id,
    entry_type: 'ISSUE_GOLD',
    gold_weight: null,
    purity: 91.6,
    cash_amount: null,
    payment_method: 'CASH',
    rate: null,
    description: '',
    date: todayIndianDate(),
});

const partyTypeName = computed(() => props.party_type_class.split('\\').pop());

const partyDisplayName = computed(() => {
    return props.party?.company_name || props.party?.name || 'Unknown Party';
});

const partySubInfo = computed(() => {
    return props.party?.contact_person || props.party?.phone || '';
});

const needsGold = computed(() =>
    ['ISSUE_GOLD', 'RECEIVE_GOLD', 'ISSUE_SILVER', 'RECEIVE_SILVER', 'GOLD_TO_CASH', 'SILVER_TO_CASH'].includes(form.entry_type),
);

const needsCash = computed(() => ['PAY_CASH', 'RECEIVE_CASH', 'CASH_TO_GOLD', 'CASH_TO_SILVER'].includes(form.entry_type));
const needsPaymentMethod = computed(() => ['PAY_CASH', 'RECEIVE_CASH'].includes(form.entry_type));

const needsRate = computed(() => ['GOLD_TO_CASH', 'CASH_TO_GOLD', 'SILVER_TO_CASH', 'CASH_TO_SILVER'].includes(form.entry_type));

const calculatedAdjustmentValue = computed(() => {
    if (['GOLD_TO_CASH', 'SILVER_TO_CASH'].includes(form.entry_type)) {
        const weight = Number(form.gold_weight || 0);
        const rate = Number(form.rate || 0);
        return weight * rate;
    }

    if (['CASH_TO_GOLD', 'CASH_TO_SILVER'].includes(form.entry_type)) {
        const cash = Number(form.cash_amount || 0);
        const rate = Number(form.rate || 0);
        return rate > 0 ? cash / rate : 0;
    }

    return 0;
});

const formatCurrency = (val) =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 0,
    }).format(val || 0);

const formatWeight = (w) => `${Number(w || 0).toFixed(3)} g`;

const openEntryModal = () => {
    if (!isDayOpen.value) {
        toast.add({ severity: 'warn', summary: 'Day Closed', detail: 'Open the shop day first from the dashboard.', life: 3000 });
        return;
    }
    editingRow.value = null;
    form.reset();
    form.clearErrors();
    form.party_type = props.party_type_class;
    form.party_id = props.party?.id;
    form.entry_type = 'ISSUE_GOLD';
    form.payment_method = 'CASH';
    form.date = todayIndianDate();
    showDialog.value = true;
};

const openEditModal = (row) => {
    editingRow.value = row;
    form.reset();
    form.clearErrors();
    form.party_type = props.party_type_class;
    form.party_id = props.party?.id;
    form.entry_type = row.entry_type_code;
    form.gold_weight = row.category === 'METAL' ? Number(row.amount || 0) : null;
    form.purity = row.category === 'METAL' ? Number(row.purity || 91.6) : 91.6;
    form.cash_amount = row.category === 'CASH' ? Number(row.amount || 0) : null;
    form.payment_method = row.payment_method || 'CASH';
    form.rate = null;
    form.description = row.description || '';
    form.date = row.date;
    showDialog.value = true;
};

const defaultPurityForEntryType = (entryType) => (String(entryType).includes('SILVER') ? 92.5 : 91.6);

const purityOptions = computed(() =>
    String(form.entry_type).includes('SILVER')
        ? [
              { label: 'Pure Silver (99.9)', value: 99.9 },
              { label: 'Sterling Silver (92.5)', value: 92.5 },
          ]
        : [
              { label: '24K (99.9)', value: 99.9 },
              { label: '22K (91.6)', value: 91.6 },
              { label: '18K (75.0)', value: 75.0 },
          ],
);

const paymentMethodOptions = [
    { label: 'Cash', value: 'CASH' },
    { label: 'Bank', value: 'BANK' },
    { label: 'UPI', value: 'UPI' },
];

const entryTypeLabel = (entryType) => {
    return entryOptions.find((x) => x.value === entryType)?.label || entryType;
};

const selectedMetalLabel = computed(() => (String(form.entry_type).includes('SILVER') ? 'Silver' : 'Gold'));

watch(
    () => form.entry_type,
    (entryType) => {
        const allowedPurities = purityOptions.value.map((option) => option.value);
        if (!allowedPurities.includes(Number(form.purity))) {
            form.purity = defaultPurityForEntryType(entryType);
        }
    },
);

const calculatedFineWeight = computed(() => {
    const gross = Number(form.gold_weight || 0);
    const purity = Number(form.purity || 0);
    return (gross * purity) / 100;
});

const ledgerData = computed(() => {
    let goldBalance = 0;
    let silverBalance = 0;
    let cashBalance = 0;

    const sorted = [...props.transactions].sort((a, b) => {
        const dateDiff = Number(a.sort_date || 0) - Number(b.sort_date || 0);
        if (dateDiff !== 0) {
            return dateDiff;
        }

        const createdDiff = Number(a.sort_created_at || 0) - Number(b.sort_created_at || 0);
        if (createdDiff !== 0) {
            return createdDiff;
        }

        return String(a.id || '').localeCompare(String(b.id || ''));
    });

    const computedList = sorted.map((txn) => {
        let metalIn = 0;
        let metalOut = 0;
        let cashIn = 0;
        let cashOut = 0;

        if (txn.category === 'METAL') {
            const metalType = txn.metal_type || 'GOLD';

            if (txn.type === 'ISSUE') {
                metalOut = Number(txn.amount || 0);
                if (metalType === 'SILVER') {
                    silverBalance += metalOut;
                } else {
                    goldBalance += metalOut;
                }
            } else if (txn.type === 'RECEIPT') {
                metalIn = Number(txn.amount || 0);
                if (metalType === 'SILVER') {
                    silverBalance -= metalIn;
                } else {
                    goldBalance -= metalIn;
                }
            }
        }

        if (txn.category === 'CASH') {
            const amount = Number(txn.amount || 0);

            if (txn.type === 'VOID') {
                return {
                    ...txn,
                    metal_in: metalIn,
                    metal_out: metalOut,
                    cash_in: cashIn,
                    cash_out: cashOut,
                    run_gold: goldBalance,
                    run_silver: silverBalance,
                    run_cash: cashBalance,
                };
            }

            if (partyTypeName.value === 'Customer') {
                if (txn.type === 'SALE') {
                    cashOut = amount;
                    cashBalance += amount;
                } else {
                    cashIn = amount;
                    cashBalance -= amount;
                }
            } else if (txn.type === 'SALE' || txn.type === 'PAYMENT') {
                cashOut = amount;
                cashBalance += amount;
            } else if (txn.type === 'RECEIPT') {
                cashIn = amount;
                cashBalance -= amount;
            }
        }

        return {
            ...txn,
            metal_in: metalIn,
            metal_out: metalOut,
            cash_in: cashIn,
            cash_out: cashOut,
            run_gold: goldBalance,
            run_silver: silverBalance,
            run_cash: cashBalance,
        };
    });

    return computedList.reverse();
});

const currentGoldBal = computed(() => (ledgerData.value.length ? ledgerData.value[0].run_gold : 0));
const currentSilverBal = computed(() => (ledgerData.value.length ? ledgerData.value[0].run_silver : 0));
const currentCashBal = computed(() => (ledgerData.value.length ? ledgerData.value[0].run_cash : 0));

const goldBalanceText = computed(() => {
    if (partyTypeName.value === 'Karigar') {
        return currentGoldBal.value > 0 ? 'Gold currently with karigar' : 'Gold returned to shop / extra received';
    }

    if (partyTypeName.value === 'Supplier') {
        return currentGoldBal.value > 0 ? 'Gold currently with supplier' : 'Gold received back / extra in shop';
    }

    return currentGoldBal.value > 0 ? 'Gold with party' : 'Gold with shop';
});

const silverBalanceText = computed(() => {
    if (partyTypeName.value === 'Karigar') {
        return currentSilverBal.value > 0 ? 'Silver currently with karigar' : 'Silver returned to shop / extra received';
    }

    if (partyTypeName.value === 'Supplier') {
        return currentSilverBal.value > 0 ? 'Silver currently with supplier' : 'Silver received back / extra in shop';
    }

    return currentSilverBal.value > 0 ? 'Silver with party' : 'Silver with shop';
});

const cashBalanceText = computed(() => {
    if (partyTypeName.value === 'Customer') {
        return currentCashBal.value > 0 ? 'Customer pending amount' : 'Customer account settled / advance';
    }

    if (partyTypeName.value === 'Karigar') {
        return currentCashBal.value > 0 ? 'Cash paid to karigar / recoverable balance' : 'Cash received back / settled';
    }

    if (partyTypeName.value === 'Supplier') {
        return currentCashBal.value > 0 ? 'Cash paid to supplier / debit balance' : 'Cash received from supplier / settled';
    }

    return currentCashBal.value > 0 ? 'Cash paid / debit balance' : 'Cash receivable cleared / credit side';
});

const submitTransaction = () => {
    const isEditing = Boolean(editingRow.value);
    const endpoint = isEditing
        ? route('ledger.update-entry', {
              category: editingRow.value.category.toLowerCase(),
              id: editingRow.value.row_id,
          })
        : route('ledger.store-entry');

    form.transform((data) => ({
        ...data,
        _method: isEditing ? 'patch' : 'post',
    })).post(endpoint, {
        preserveScroll: true,

        onSuccess: () => {
            // close modal
            showDialog.value = false;
            editingRow.value = null;

            // reset form
            form.reset();

            // clear validation errors
            form.clearErrors();

            // restore defaults
            form.party_type = props.party_type_class;
            form.party_id = props.party?.id;
            form.entry_type = 'ISSUE_GOLD';
            form.purity = 91.6;
            form.payment_method = 'CASH';
            form.date = todayIndianDate();

            toast.add({
                severity: 'success',
                summary: 'Saved',
                detail: isEditing ? 'Ledger entry updated successfully' : 'Ledger entry added successfully',
                life: 3000,
            });
        },

        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Please check the form',
                life: 3000,
            });
        },
    });
};
</script>

<template>
    <AppLayout>
        <Toast />

        <div class="space-y-6">
            <!-- Header -->
            <div class="border-b border-surface-200 bg-white px-5 py-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-2xl font-semibold tracking-tight text-surface-900">
                                {{ partyDisplayName }}
                            </h1>
                            <Tag :value="partyTypeName" severity="secondary" />
                        </div>

                        <p class="mt-1 text-sm text-surface-500">
                            Ledger for cash, gold, and silver movement
                            <span v-if="partySubInfo"> • {{ partySubInfo }}</span>
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <Button label="Add Entry" icon="pi pi-plus" class="!w-auto shrink-0 whitespace-nowrap" @click="openEntryModal" :disabled="!isDayOpen" />
                    </div>
                </div>
            </div>

            <!-- Summary -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <div class="text-xs tracking-wide text-surface-500 uppercase">Gold Balance</div>
                    <div class="mt-2 text-3xl font-semibold text-surface-900">
                        {{ formatWeight(currentGoldBal) }}
                    </div>
                    <div class="mt-1 text-sm text-surface-500">
                        {{ goldBalanceText }}
                    </div>
                </div>

                <div class="border border-surface-200 bg-white px-5 py-4">
                    <div class="text-xs tracking-wide text-surface-500 uppercase">Silver Balance</div>
                    <div class="mt-2 text-3xl font-semibold text-surface-900">
                        {{ formatWeight(currentSilverBal) }}
                    </div>
                    <div class="mt-1 text-sm text-surface-500">
                        {{ silverBalanceText }}
                    </div>
                </div>

                <div class="border border-surface-200 bg-white px-5 py-4">
                    <div class="text-xs tracking-wide text-surface-500 uppercase">Cash Balance</div>
                    <div class="mt-2 text-3xl font-semibold text-surface-900">
                        {{ formatCurrency(currentCashBal) }}
                    </div>
                    <div class="mt-1 text-sm text-surface-500">
                        {{ cashBalanceText }}
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="card overflow-hidden !p-0">
                <div class="border-b border-surface-200 bg-white px-5 py-4">
                    <h3 class="text-base font-semibold text-surface-900">Ledger Entries</h3>
                    <p class="mt-1 text-sm text-surface-500">Latest transactions with running metal and cash balances</p>
                </div>

                <div class="bg-white p-4">
                    <DataTable :value="ledgerData" stripedRows rowHover scrollable scrollHeight="60vh" tableStyle="min-width: 72rem" class="text-sm">
                        <template #empty>
                            <div class="py-12 text-center text-surface-500">No transactions found</div>
                        </template>

                        <Column field="date" header="Date" style="width: 120px" />

                        <Column field="description" header="Particulars" style="min-width: 240px">
                            <template #body="{ data }">
                                <div>
                                    <span class="text-surface-900">{{ data.description || '—' }}</span>
                                    <div v-if="data.category === 'CASH' && data.payment_method" class="mt-1 text-xs text-surface-500">
                                        {{ data.payment_method }}
                                    </div>
                                </div>
                            </template>
                        </Column>

                        <Column header="Metal" style="width: 100px">
                            <template #body="{ data }">
                                <Tag v-if="data.category === 'METAL'" :value="data.metal_type || 'GOLD'" :severity="(data.metal_type || 'GOLD') === 'SILVER' ? 'secondary' : 'warn'" />
                            </template>
                        </Column>

                        <Column header="Metal In" style="width: 120px">
                            <template #body="{ data }">
                                <span v-if="data.metal_in" class="font-medium text-green-600">
                                    {{ Number(data.metal_in).toFixed(3) }}
                                </span>
                            </template>
                        </Column>

                        <Column header="Metal Out" style="width: 120px">
                            <template #body="{ data }">
                                <span v-if="data.metal_out" class="font-medium text-red-600">
                                    {{ Number(data.metal_out).toFixed(3) }}
                                </span>
                            </template>
                        </Column>

                        <Column header="Metal Bal" style="width: 120px">
                            <template #body="{ data }">
                                <span v-if="data.category === 'METAL'" class="font-medium text-surface-900">
                                    {{ Number((data.metal_type || 'GOLD') === 'SILVER' ? data.run_silver : data.run_gold).toFixed(3) }}
                                </span>
                            </template>
                        </Column>

                        <Column header="Credit" style="width: 140px">
                            <template #body="{ data }">
                                <span v-if="data.cash_in" class="font-medium text-green-600">
                                    {{ formatCurrency(data.cash_in) }}
                                </span>
                            </template>
                        </Column>

                        <Column header="Debit" style="width: 140px">
                            <template #body="{ data }">
                                <span v-if="data.cash_out" class="font-medium text-red-600">
                                    {{ formatCurrency(data.cash_out) }}
                                </span>
                            </template>
                        </Column>

                        <Column header="Balance" style="width: 140px">
                            <template #body="{ data }">
                                <span class="font-medium text-surface-900">
                                    {{ formatCurrency(data.run_cash) }}
                                </span>
                            </template>
                        </Column>

                        <Column field="category" header="Type" style="width: 100px">
                            <template #body="{ data }">
                                <Tag :value="data.category === 'CASH' ? data.type : data.category" severity="secondary" />
                            </template>
                        </Column>

                        <Column header="" style="width: 110px">
                            <template #body="{ data }">
                                <Button
                                    v-if="data.is_editable"
                                    label="Edit"
                                    icon="pi pi-pencil"
                                    text
                                    size="small"
                                    @click="openEditModal(data)"
                                />
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
        </div>

        <!-- Entry Dialog -->
        <Dialog v-model:visible="showDialog" :header="editingRow ? 'Edit Ledger Entry' : 'Add Ledger Entry'" modal class="w-full max-w-md">
            <div class="space-y-5 pt-2">
                <div class="border border-surface-200 bg-surface-50 px-4 py-3">
                    <p class="text-sm font-medium text-surface-900">
                        {{ partyDisplayName }}
                    </p>
                    <p class="mt-1 text-sm text-surface-500">Select the entry type and enter only the fields needed for that action.</p>
                </div>

                <div v-if="form.errors.entry" class="border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">
                    {{ form.errors.entry }}
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700"> Entry Type </label>
                    <Select v-model="form.entry_type" :options="entryOptions" optionLabel="label" optionValue="value" class="w-full" />
                    <small class="mt-1 block text-xs text-surface-500">
                        {{ entryTypeLabel(form.entry_type) }}
                    </small>
                </div>

                <div v-if="needsGold">
                    <label class="mb-2 block text-sm font-medium text-surface-700"> {{ selectedMetalLabel }} Weight (g) </label>
                    <InputNumber v-model="form.gold_weight" :minFractionDigits="3" :maxFractionDigits="3" suffix=" g" class="w-full" />
                    <small v-if="form.errors.gold_weight" class="mt-1 block text-xs text-red-500">
                        {{ form.errors.gold_weight }}
                    </small>
                </div>

                <div v-if="needsGold || ['CASH_TO_GOLD', 'CASH_TO_SILVER'].includes(form.entry_type)">
                    <label class="mb-2 block text-sm font-medium text-surface-700"> Purity </label>
                    <Select v-model="form.purity" :options="purityOptions" optionLabel="label" optionValue="value" class="w-full" />
                    <small v-if="form.errors.purity" class="mt-1 block text-xs text-red-500">
                        {{ form.errors.purity }}
                    </small>
                </div>

                <div v-if="needsGold" class="border border-surface-200 bg-surface-50 px-4 py-3">
                    <p class="text-sm text-surface-500">Fine {{ selectedMetalLabel }}</p>
                    <p class="mt-1 text-lg font-semibold text-surface-900">{{ calculatedFineWeight.toFixed(3) }} g</p>
                </div>

                <div v-if="needsCash">
                    <label class="mb-2 block text-sm font-medium text-surface-700"> Cash Amount </label>
                    <InputNumber v-model="form.cash_amount" mode="currency" currency="INR" locale="en-IN" class="w-full" />
                    <small v-if="form.errors.cash_amount" class="mt-1 block text-xs text-red-500">
                        {{ form.errors.cash_amount }}
                    </small>
                </div>

                <div v-if="needsPaymentMethod">
                    <label class="mb-2 block text-sm font-medium text-surface-700"> Payment Method </label>
                    <Select v-model="form.payment_method" :options="paymentMethodOptions" optionLabel="label" optionValue="value" class="w-full" />
                    <small v-if="form.errors.payment_method" class="mt-1 block text-xs text-red-500">
                        {{ form.errors.payment_method }}
                    </small>
                </div>

                <div v-if="needsRate">
                    <label class="mb-2 block text-sm font-medium text-surface-700"> Rate Used </label>
                    <InputNumber v-model="form.rate" mode="currency" currency="INR" locale="en-IN" class="w-full" />
                    <small v-if="form.errors.rate" class="mt-1 block text-xs text-red-500">
                        {{ form.errors.rate }}
                    </small>
                </div>

                <div v-if="['GOLD_TO_CASH', 'SILVER_TO_CASH'].includes(form.entry_type)" class="border border-surface-200 bg-surface-50 px-4 py-3">
                    <p class="text-sm text-surface-500">Adjustment Value</p>
                    <p class="mt-1 text-lg font-semibold text-surface-900">
                        {{ formatCurrency(calculatedAdjustmentValue) }}
                    </p>
                    <p class="mt-1 text-xs text-surface-500">{{ selectedMetalLabel }} received will reduce cash balance by this amount.</p>
                </div>

                <div v-if="['CASH_TO_GOLD', 'CASH_TO_SILVER'].includes(form.entry_type)" class="border border-surface-200 bg-surface-50 px-4 py-3">
                    <p class="text-sm text-surface-500">{{ selectedMetalLabel }} Equivalent</p>
                    <p class="mt-1 text-lg font-semibold text-surface-900">
                        {{ formatWeight(calculatedAdjustmentValue) }}
                    </p>
                    <p class="mt-1 text-xs text-surface-500">Cash paid will be converted into this {{ selectedMetalLabel.toLowerCase() }} quantity.</p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700"> Description </label>
                    <Textarea v-model="form.description" rows="2" class="w-full" />
                    <small v-if="form.errors.description" class="mt-1 block text-xs text-red-500">
                        {{ form.errors.description }}
                    </small>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700"> Date </label>
                    <InputText type="date" v-model="form.date" class="w-full" />
                    <small v-if="form.errors.date" class="mt-1 block text-xs text-red-500">
                        {{ form.errors.date }}
                    </small>
                </div>

                <div class="flex justify-end gap-2 border-t border-surface-200 pt-4">
                    <Button
                        label="Cancel"
                        text
                        severity="secondary"
                        type="button"
                        @click="
                            showDialog = false;
                            editingRow = null;
                        "
                    />
                    <Button :label="editingRow ? 'Update Entry' : 'Save Entry'" :loading="form.processing" :disabled="form.processing" @click="submitTransaction" />
                </div>
            </div>
        </Dialog>
    </AppLayout>
</template>
