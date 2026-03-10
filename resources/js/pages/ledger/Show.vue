<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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

const props = defineProps({
    party: Object,
    party_type_class: String,
    transactions: Array,
});

const toast = useToast();
const showDialog = ref(false);

const entryOptions = [
    { label: 'Issue Gold', value: 'ISSUE_GOLD' },
    { label: 'Receive Gold', value: 'RECEIVE_GOLD' },
    { label: 'Pay Cash', value: 'PAY_CASH' },
    { label: 'Receive Cash', value: 'RECEIVE_CASH' },
    { label: 'Gold → Cash Adjustment', value: 'GOLD_TO_CASH' },
    { label: 'Cash → Gold Adjustment', value: 'CASH_TO_GOLD' },
];

const form = useForm({
    party_type: props.party_type_class,
    party_id: props.party?.id,
    entry_type: 'ISSUE_GOLD',
    gold_weight: null,
    purity: 91.6,
    cash_amount: null,
    rate: null,
    description: '',
    date: new Date().toISOString().split('T')[0],
});

const partyTypeName = computed(() => props.party_type_class.split('\\').pop());

const partyDisplayName = computed(() => {
    return props.party?.company_name || props.party?.name || 'Unknown Party';
});

const partySubInfo = computed(() => {
    return props.party?.contact_person || props.party?.phone || '';
});

const needsGold = computed(() => ['ISSUE_GOLD', 'RECEIVE_GOLD', 'GOLD_TO_CASH'].includes(form.entry_type));

const needsCash = computed(() => ['PAY_CASH', 'RECEIVE_CASH', 'CASH_TO_GOLD'].includes(form.entry_type));

const needsRate = computed(() => ['GOLD_TO_CASH', 'CASH_TO_GOLD'].includes(form.entry_type));

const calculatedAdjustmentValue = computed(() => {
    if (form.entry_type === 'GOLD_TO_CASH') {
        const weight = Number(form.gold_weight || 0);
        const rate = Number(form.rate || 0);
        return weight * rate;
    }

    if (form.entry_type === 'CASH_TO_GOLD') {
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
    form.reset();
    form.clearErrors();
    form.party_type = props.party_type_class;
    form.party_id = props.party?.id;
    form.entry_type = 'ISSUE_GOLD';
    form.date = new Date().toISOString().split('T')[0];
    showDialog.value = true;
};

const purityOptions = [
    { label: '24K (99.9)', value: 99.9 },
    { label: '22K (91.6)', value: 91.6 },
    { label: '18K (75.0)', value: 75.0 },
];

const entryTypeLabel = (entryType) => {
    return entryOptions.find((x) => x.value === entryType)?.label || entryType;
};

const calculatedFineWeight = computed(() => {
    const gross = Number(form.gold_weight || 0);
    const purity = Number(form.purity || 0);
    return (gross * purity) / 100;
});

const ledgerData = computed(() => {
    let metalBalance = 0;
    let cashBalance = 0;

    const sorted = [...props.transactions].sort((a, b) => {
        return Number(a.sort_at || 0) - Number(b.sort_at || 0);
    });

    const computedList = sorted.map((txn) => {
        let metalIn = 0;
        let metalOut = 0;
        let cashIn = 0;
        let cashOut = 0;

        if (txn.category === 'METAL') {
            if (txn.type === 'ISSUE') {
                metalOut = Number(txn.amount || 0);
                metalBalance += metalOut;
            } else if (txn.type === 'RECEIPT') {
                metalIn = Number(txn.amount || 0);
                metalBalance -= metalIn;
            }
        }

        if (txn.category === 'CASH') {
            if (txn.type === 'PAYMENT') {
                cashOut = Number(txn.amount || 0);
                cashBalance += cashOut;
            } else if (txn.type === 'RECEIPT') {
                cashIn = Number(txn.amount || 0);
                cashBalance -= cashIn;
            }
        }

        return {
            ...txn,
            metal_in: metalIn,
            metal_out: metalOut,
            cash_in: cashIn,
            cash_out: cashOut,
            run_metal: metalBalance,
            run_cash: cashBalance,
        };
    });

    return computedList.reverse();
});

const currentMetalBal = computed(() => (ledgerData.value.length ? ledgerData.value[0].run_metal : 0));
const currentCashBal = computed(() => (ledgerData.value.length ? ledgerData.value[0].run_cash : 0));

const metalBalanceText = computed(() => {
    return currentMetalBal.value > 0 ? 'Gold with party' : 'Gold with shop';
});

const cashBalanceText = computed(() => {
    return currentCashBal.value > 0 ? 'Cash paid / debit balance' : 'Cash receivable cleared / credit side';
});

const submitTransaction = () => {
    form.post(route('ledger.store-entry'), {
        preserveScroll: true,

        onSuccess: () => {
            // close modal
            showDialog.value = false;

            // reset form
            form.reset();

            // clear validation errors
            form.clearErrors();

            // restore defaults
            form.party_type = props.party_type_class;
            form.party_id = props.party?.id;
            form.entry_type = 'ISSUE_GOLD';
            form.purity = 91.6;
            form.date = new Date().toISOString().split('T')[0];

            toast.add({
                severity: 'success',
                summary: 'Saved',
                detail: 'Ledger entry added successfully',
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
                            Ledger for cash and gold movement
                            <span v-if="partySubInfo"> • {{ partySubInfo }}</span>
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <Button label="Add Entry" icon="pi pi-plus" class="!w-auto shrink-0 whitespace-nowrap" @click="openEntryModal" />
                    </div>
                </div>
            </div>

            <!-- Summary -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <div class="text-xs tracking-wide text-surface-500 uppercase">Gold Balance</div>
                    <div class="mt-2 text-3xl font-semibold text-surface-900">
                        {{ formatWeight(currentMetalBal) }}
                    </div>
                    <div class="mt-1 text-sm text-surface-500">
                        {{ metalBalanceText }}
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
                    <p class="mt-1 text-sm text-surface-500">Latest transactions with running gold and cash balances</p>
                </div>

                <div class="bg-white p-4">
                    <DataTable :value="ledgerData" stripedRows rowHover scrollable scrollHeight="60vh" tableStyle="min-width: 72rem" class="text-sm">
                        <template #empty>
                            <div class="py-12 text-center text-surface-500">No transactions found</div>
                        </template>

                        <Column field="date" header="Date" style="width: 120px" />

                        <Column field="description" header="Particulars" style="min-width: 240px">
                            <template #body="{ data }">
                                <span class="text-surface-900">{{ data.description || '—' }}</span>
                            </template>
                        </Column>

                        <Column header="Gold In" style="width: 120px">
                            <template #body="{ data }">
                                <span v-if="data.metal_in" class="font-medium text-green-600">
                                    {{ Number(data.metal_in).toFixed(3) }}
                                </span>
                            </template>
                        </Column>

                        <Column header="Gold Out" style="width: 120px">
                            <template #body="{ data }">
                                <span v-if="data.metal_out" class="font-medium text-red-600">
                                    {{ Number(data.metal_out).toFixed(3) }}
                                </span>
                            </template>
                        </Column>

                        <Column header="Gold Bal" style="width: 120px">
                            <template #body="{ data }">
                                <span class="font-medium text-surface-900">
                                    {{ Number(data.run_metal).toFixed(3) }}
                                </span>
                            </template>
                        </Column>

                        <Column header="Cash In" style="width: 140px">
                            <template #body="{ data }">
                                <span v-if="data.cash_in" class="font-medium text-green-600">
                                    {{ formatCurrency(data.cash_in) }}
                                </span>
                            </template>
                        </Column>

                        <Column header="Cash Out" style="width: 140px">
                            <template #body="{ data }">
                                <span v-if="data.cash_out" class="font-medium text-red-600">
                                    {{ formatCurrency(data.cash_out) }}
                                </span>
                            </template>
                        </Column>

                        <Column header="Cash Bal" style="width: 140px">
                            <template #body="{ data }">
                                <span class="font-medium text-surface-900">
                                    {{ formatCurrency(data.run_cash) }}
                                </span>
                            </template>
                        </Column>

                        <Column field="category" header="Type" style="width: 100px">
                            <template #body="{ data }">
                                <Tag :value="data.category" severity="secondary" />
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
        </div>

        <!-- Entry Dialog -->
        <Dialog v-model:visible="showDialog" header="Add Ledger Entry" modal class="w-full max-w-md">
            <div class="space-y-5 pt-2">
                <div class="border border-surface-200 bg-surface-50 px-4 py-3">
                    <p class="text-sm font-medium text-surface-900">
                        {{ partyDisplayName }}
                    </p>
                    <p class="mt-1 text-sm text-surface-500">Select the entry type and enter only the fields needed for that action.</p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700"> Entry Type </label>
                    <Select v-model="form.entry_type" :options="entryOptions" optionLabel="label" optionValue="value" class="w-full" />
                    <small class="mt-1 block text-xs text-surface-500">
                        {{ entryTypeLabel(form.entry_type) }}
                    </small>
                </div>

                <div v-if="needsGold">
                    <label class="mb-2 block text-sm font-medium text-surface-700"> Gold Weight (g) </label>
                    <InputNumber v-model="form.gold_weight" :minFractionDigits="3" :maxFractionDigits="3" suffix=" g" class="w-full" />
                    <small v-if="form.errors.gold_weight" class="mt-1 block text-xs text-red-500">
                        {{ form.errors.gold_weight }}
                    </small>
                </div>

                <div v-if="needsGold || form.entry_type === 'CASH_TO_GOLD'">
                    <label class="mb-2 block text-sm font-medium text-surface-700"> Purity </label>
                    <Select v-model="form.purity" :options="purityOptions" optionLabel="label" optionValue="value" class="w-full" />
                    <small v-if="form.errors.purity" class="mt-1 block text-xs text-red-500">
                        {{ form.errors.purity }}
                    </small>
                </div>

                <div v-if="needsGold" class="border border-surface-200 bg-surface-50 px-4 py-3">
                    <p class="text-sm text-surface-500">Fine Gold</p>
                    <p class="mt-1 text-lg font-semibold text-surface-900">{{ calculatedFineWeight.toFixed(3) }} g</p>
                </div>

                <div v-if="needsCash">
                    <label class="mb-2 block text-sm font-medium text-surface-700"> Cash Amount </label>
                    <InputNumber v-model="form.cash_amount" mode="currency" currency="INR" locale="en-IN" class="w-full" />
                    <small v-if="form.errors.cash_amount" class="mt-1 block text-xs text-red-500">
                        {{ form.errors.cash_amount }}
                    </small>
                </div>

                <div v-if="needsRate">
                    <label class="mb-2 block text-sm font-medium text-surface-700"> Rate Used </label>
                    <InputNumber v-model="form.rate" mode="currency" currency="INR" locale="en-IN" class="w-full" />
                    <small v-if="form.errors.rate" class="mt-1 block text-xs text-red-500">
                        {{ form.errors.rate }}
                    </small>
                </div>

                <div v-if="form.entry_type === 'GOLD_TO_CASH'" class="border border-surface-200 bg-surface-50 px-4 py-3">
                    <p class="text-sm text-surface-500">Adjustment Value</p>
                    <p class="mt-1 text-lg font-semibold text-surface-900">
                        {{ formatCurrency(calculatedAdjustmentValue) }}
                    </p>
                    <p class="mt-1 text-xs text-surface-500">Gold received will reduce cash balance by this amount.</p>
                </div>

                <div v-if="form.entry_type === 'CASH_TO_GOLD'" class="border border-surface-200 bg-surface-50 px-4 py-3">
                    <p class="text-sm text-surface-500">Gold Equivalent</p>
                    <p class="mt-1 text-lg font-semibold text-surface-900">
                        {{ formatWeight(calculatedAdjustmentValue) }}
                    </p>
                    <p class="mt-1 text-xs text-surface-500">Cash paid will be converted into this gold quantity.</p>
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
                    <Button label="Cancel" text severity="secondary" type="button" @click="showDialog = false" />
                    <Button label="Save Entry" :loading="form.processing" :disabled="form.processing" @click="submitTransaction" />
                </div>
            </div>
        </Dialog>
    </AppLayout>
</template>
