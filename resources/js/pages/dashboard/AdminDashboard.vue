<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import Tag from 'primevue/tag';

const props = defineProps({
    rates: Object,
    vaults: Object,
    isDayOpen: Boolean,
    pending_tasks: Number,
    karigars: Array,
    activities: Array,
});

const rateForm = useForm({
    gold_sell: parseFloat(props.rates?.gold_sell || 0),
    gold_buy: parseFloat(props.rates?.gold_buy || 0),
    silver_sell: parseFloat(props.rates?.silver_sell || 0),
});

const dayForm = useForm({
    opening_cash: parseFloat(props.vaults?.cash || 0),
    opening_bank: parseFloat(props.vaults?.bank || 0),
    opening_gold: parseFloat(props.vaults?.gold || 0),
});

const closeForm = useForm({
    closing_cash: null,
    closing_bank: null,
    closing_gold: null,
});

const showRateDialog = ref(false);
const showDayDialog = ref(!props.isDayOpen);
const showCloseDialog = ref(false);

const totalKarigars = computed(() => props.karigars?.length || 0);

const formatCurrency = (val) =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 0,
    }).format(val || 0);

const formatWeight = (val) => `${Number(val || 0).toFixed(3)} g`;

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
</script>

<template>
    <AppLayout>
        <div class="">
            <div class="mx-auto max-w-7xl space-y-6">
                <!-- Header -->
                <div class="border-b border-surface-200 bg-white px-5 py-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <!-- Left -->
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-3">
                                <h2 class="text-2xl font-semibold tracking-tight text-surface-900">Dashboard</h2>

                                <Tag :value="isDayOpen ? 'Day Open' : 'Day Closed'" :severity="isDayOpen ? 'success' : 'danger'" />
                            </div>

                            <p class="mt-1 text-sm text-surface-500">Daily overview of shop operations, balances, and workshop activity</p>
                        </div>

                        <!-- Right -->
                        <div class="flex flex-wrap items-center gap-2">
                            <Button label="Update Rates" icon="pi pi-pencil" outlined size="small" @click="showRateDialog = true" />

                            <Button v-if="!isDayOpen" label="Open Day" icon="pi pi-lock-open" size="small" @click="showDayDialog = true" />

                            <Button v-else label="Close Day" icon="pi pi-lock" severity="danger" size="small" @click="showCloseDialog = true" />
                        </div>
                    </div>
                </div>

                <!-- KPI Row -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <div class="border border-surface-200 bg-white p-4">
                        <p class="text-sm text-surface-500">Gold Sell Rate</p>
                        <p class="mt-2 text-xl font-semibold text-surface-900">
                            {{ formatCurrency(rates?.gold_sell) }}
                        </p>
                    </div>

                    <div class="border border-surface-200 bg-white p-4">
                        <p class="text-sm text-surface-500">Silver Rate</p>
                        <p class="mt-2 text-xl font-semibold text-surface-900">
                            {{ formatCurrency(rates?.silver_sell) }}
                        </p>
                    </div>

                    <div class="border border-surface-200 bg-white p-4">
                        <p class="text-sm text-surface-500">Cash in Hand</p>
                        <p class="mt-2 text-xl font-semibold text-surface-900">
                            {{ formatCurrency(vaults?.cash) }}
                        </p>
                    </div>

                    <div class="border border-surface-200 bg-white p-4">
                        <p class="text-sm text-surface-500">Bank Balance</p>
                        <p class="mt-2 text-xl font-semibold text-surface-900">
                            {{ formatCurrency(vaults?.bank) }}
                        </p>
                    </div>

                    <div class="border border-surface-200 bg-white p-4">
                        <p class="text-sm text-surface-500">Pending Orders</p>
                        <p class="mt-2 text-xl font-semibold text-primary">
                            {{ pending_tasks || 0 }}
                        </p>
                    </div>
                </div>

                <!-- Secondary Summary -->
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div class="border border-surface-200 bg-white p-5 lg:col-span-2">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-base font-semibold text-surface-900">Gold Assets</h3>
                                <p class="mt-1 text-sm text-surface-500">Total tracked gold across safe and issued holdings</p>
                            </div>
                            <div class="text-right">
                                <p class="text-3xl font-bold text-surface-900">
                                    {{ formatWeight(vaults?.gold) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="border border-surface-200 bg-white p-5">
                        <h3 class="text-base font-semibold text-surface-900">Quick Snapshot</h3>
                        <div class="mt-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-surface-500">Active Karigars</span>
                                <span class="font-medium text-surface-900">{{ totalKarigars }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-surface-500">Activities Today</span>
                                <span class="font-medium text-surface-900">{{ activities?.length || 0 }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-surface-500">Gold Buy Rate</span>
                                <span class="font-medium text-surface-900">{{ formatCurrency(rates?.gold_buy) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                    <!-- Karigar Table -->
                    <div class="overflow-hidden border border-surface-200 bg-white xl:col-span-2">
                        <div class="flex items-center justify-between border-b border-surface-200 px-5 py-4">
                            <div>
                                <h3 class="text-base font-semibold text-surface-900">Karigar Holdings</h3>
                                <p class="mt-1 text-sm text-surface-500">Gold currently issued to workers</p>
                            </div>
                            <span class="text-sm text-surface-500">{{ totalKarigars }} records</span>
                        </div>

                        <div class="p-4">
                            <DataTable :value="karigars" size="small" stripedRows rowHover tableStyle="min-width: 100%">
                                <template #empty>
                                    <div class="py-12 text-center text-surface-500">No gold currently issued to workers.</div>
                                </template>

                                <Column field="name" header="Karigar">
                                    <template #body="{ data }">
                                        <div class="flex flex-col">
                                            <span class="font-medium text-surface-900">{{ data.name }}</span>
                                            <span class="text-xs text-surface-500">{{ data.phone }}</span>
                                        </div>
                                    </template>
                                </Column>

                                <Column field="gold_due" header="Gold With Them">
                                    <template #body="{ data }">
                                        <span class="font-semibold text-surface-900"> {{ Number(data.gold_due || 0).toFixed(3) }} g </span>
                                    </template>
                                </Column>

                                <Column header="Status">
                                    <template #body>
                                        <Tag value="Active" severity="warning" />
                                    </template>
                                </Column>

                                <Column header="">
                                    <template #body>
                                        <Button icon="pi pi-arrow-right" text rounded />
                                    </template>
                                </Column>
                            </DataTable>
                        </div>
                    </div>

                    <!-- Activity -->
                    <div class="overflow-hidden border border-surface-200 bg-white">
                        <div class="border-b border-surface-200 px-5 py-4">
                            <h3 class="text-base font-semibold text-surface-900">Recent Activity</h3>
                            <p class="mt-1 text-sm text-surface-500">Latest money movement and ledger activity</p>
                        </div>

                        <div class="max-h-[520px] space-y-3 overflow-y-auto p-4">
                            <template v-if="activities?.length">
                                <div v-for="act in activities" :key="act.id" class="border border-surface-200 p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-surface-900">
                                                {{ act.desc }}
                                            </p>
                                            <p class="mt-1 text-xs text-surface-500">By {{ act.user }}</p>
                                        </div>

                                        <div class="text-right">
                                            <p class="text-sm font-semibold" :class="act.type === 'RECEIPT' ? 'text-green-600' : 'text-red-600'">
                                                {{ act.type === 'RECEIPT' ? '+' : '-' }}
                                                {{ formatCurrency(act.amount) }}
                                            </p>
                                            <p class="mt-1 text-xs text-surface-400">{{ act.time }}</p>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <div v-else class="py-12 text-center text-sm text-surface-500">No transactions yet today.</div>
                        </div>

                        <div class="border-t border-surface-200 p-4">
                            <Button label="View Ledger" text class="w-full justify-center" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Open Day -->
        <Dialog v-model:visible="showDayDialog" header="Start Day" modal :closable="false" class="w-full max-w-md">
            <div class="space-y-4 pt-2">
                <p class="text-sm text-surface-500">Verify balances before opening the business day.</p>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Opening Cash</label>
                    <InputNumber v-model="dayForm.opening_cash" mode="currency" currency="INR" locale="en-IN" class="w-full" />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Opening Bank</label>
                    <InputNumber v-model="dayForm.opening_bank" mode="currency" currency="INR" locale="en-IN" class="w-full" />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Opening Gold</label>
                    <InputNumber v-model="dayForm.opening_gold" :minFractionDigits="3" suffix=" g" class="w-full" />
                </div>

                <Button label="Open Day" icon="pi pi-check" class="w-full" @click="openShop" :loading="dayForm.processing" />
            </div>
        </Dialog>

        <!-- Rates -->
        <Dialog v-model:visible="showRateDialog" header="Update Market Rates" modal class="w-full max-w-md">
            <div class="space-y-4 pt-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Gold Sell Rate</label>
                    <InputNumber v-model="rateForm.gold_sell" mode="currency" currency="INR" locale="en-IN" class="w-full" />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Gold Buy Rate</label>
                    <InputNumber v-model="rateForm.gold_buy" mode="currency" currency="INR" locale="en-IN" class="w-full" />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Silver Rate</label>
                    <InputNumber v-model="rateForm.silver_sell" mode="currency" currency="INR" locale="en-IN" class="w-full" />
                </div>

                <Button label="Save Rates" icon="pi pi-save" class="w-full" @click="saveRates" :loading="rateForm.processing" />
            </div>
        </Dialog>

        <!-- Close Day -->
        <Dialog v-model:visible="showCloseDialog" header="Close Day" modal class="w-full max-w-xl">
            <div class="space-y-5 pt-2">
                <!-- Intro -->
                <div class="border border-surface-200 bg-surface-50 px-4 py-3">
                    <p class="text-sm font-medium text-surface-800">End-of-day reconciliation</p>
                    <p class="mt-1 text-sm text-surface-500">Enter the physically counted cash, bank balance, and gold weight before closing the day.</p>
                </div>

                <!-- Expected Summary -->
                <div class="border border-surface-200 bg-white">
                    <div class="border-b border-surface-200 px-4 py-3">
                        <h4 class="text-sm font-semibold text-surface-900">System Summary</h4>
                    </div>

                    <div class="space-y-3 px-4 py-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-surface-500">Expected Cash</span>
                            <span class="text-sm font-semibold text-surface-900">
                                {{ formatCurrency(vaults.cash) }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-sm text-surface-500">Expected Bank</span>
                            <span class="text-sm font-semibold text-surface-900">
                                {{ formatCurrency(vaults.bank) }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-sm text-surface-500">Expected Gold</span>
                            <span class="text-sm font-semibold text-surface-900"> {{ Number(vaults.gold || 0).toFixed(3) }} g </span>
                        </div>
                    </div>
                </div>

                <!-- Inputs -->
                <div class="space-y-4">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700"> Closing Cash </label>
                        <InputNumber v-model="closeForm.closing_cash" mode="currency" currency="INR" locale="en-IN" class="w-full" placeholder="Enter counted cash" />
                        <small class="mt-1 block text-xs text-surface-400"> Count physical cash available in drawer and safe. </small>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700"> Closing Bank </label>
                        <InputNumber v-model="closeForm.closing_bank" mode="currency" currency="INR" locale="en-IN" class="w-full" placeholder="Enter bank closing balance" />
                        <small class="mt-1 block text-xs text-surface-400"> Include final bank balance for today after all entries. </small>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700"> Closing Gold </label>
                        <InputNumber v-model="closeForm.closing_gold" :minFractionDigits="3" suffix=" g" class="w-full" placeholder="Enter weighed gold" />
                        <small class="mt-1 block text-xs text-surface-400"> Enter total physical gold available at day close. </small>
                    </div>
                </div>

                <!-- Warning -->
                <div class="border border-red-200 bg-red-50 px-4 py-3">
                    <p class="text-sm font-medium text-red-700">Important</p>
                    <p class="mt-1 text-sm text-red-600">Once the day is closed, new transactions should not be added without reopening or admin correction.</p>
                </div>

                <!-- Action -->
                <div class="pt-1">
                    <Button label="Close Day & Save" icon="pi pi-lock" severity="danger" class="w-full" @click="closeShop" :loading="closeForm.processing" />
                </div>
            </div>
        </Dialog>
    </AppLayout>
</template>
