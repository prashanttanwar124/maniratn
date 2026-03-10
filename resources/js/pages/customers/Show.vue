<script setup>
import AppLayout from '@/layouts/AppLayout.vue';

import { router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Paginator from 'primevue/paginator';
import Tag from 'primevue/tag';
import { computed } from 'vue';
import { route } from 'ziggy-js';
const onPageChange = (event) => {
    const newPage = event.page + 1;

    // Reload page with new data
    router.get(
        route('customers.show', props.customer.id),
        { page: newPage },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const tableData = computed(() => props.transactions.data);

const getSeverity = (type) => {
    if (type === 'SALE') return 'danger'; // Red
    if (type === 'PAYMENT') return 'success'; // Green
    if (type === 'VOID') return 'secondary'; // Gray
    return 'info'; // Default Blue
};
const props = defineProps({
    customer: Object,
    transactions: Object,
    stats: Object,
});
// Format Currency
const formatMoney = (value) => {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(value);
};

// Format Date
const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
};
</script>

<template>
    <AppLayout>
        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div class="flex flex-col justify-between rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800">{{ customer.name }}</h2>
                            <div class="mt-2 space-y-1 text-sm text-gray-500">
                                <p>📍 {{ customer.city || 'Unknown City' }}</p>
                                <p>📞 {{ customer.mobile }}</p>
                                <p>🆔 PAN: {{ customer.pan_no || '--' }}</p>
                            </div>
                        </div>
                        <div class="mt-4 flex gap-2 border-t border-gray-100 pt-4">
                            <Button icon="pi pi-pencil" label="Edit Profile" severity="secondary" size="small" outlined />
                            <Button icon="pi pi-whatsapp" label="WhatsApp" severity="success" size="small" />
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4 md:col-span-2">
                        <div class="rounded-xl border border-indigo-100 bg-indigo-50 p-6">
                            <span class="text-sm font-bold text-indigo-400 uppercase">Total Purchased</span>
                            <div class="mt-1 text-2xl font-bold text-indigo-700">{{ formatMoney(stats.total_sales) }}</div>
                        </div>
                        <div class="rounded-xl border border-green-100 bg-green-50 p-6">
                            <span class="text-sm font-bold text-green-400 uppercase">Total Paid</span>
                            <div class="mt-1 text-2xl font-bold text-green-700">{{ formatMoney(stats.total_paid) }}</div>
                        </div>
                        <div :class="['rounded-xl border p-6', stats.current_balance > 0 ? 'border-red-100 bg-red-50' : 'border-gray-100 bg-gray-50']">
                            <span :class="['text-sm font-bold uppercase', stats.current_balance > 0 ? 'text-red-400' : 'text-gray-400']">Current Due</span>
                            <div :class="['mt-1 text-3xl font-bold', stats.current_balance > 0 ? 'text-red-600' : 'text-gray-600']">
                                {{ formatMoney(stats.current_balance) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-100 p-4">
                        <h3 class="text-lg font-bold text-gray-700">Transaction History</h3>
                        <Button label="New Bill" icon="pi pi-plus" size="small" @click="$inertia.visit(route('invoices.create'))" />
                    </div>

                    <DataTable :value="tableData" stripedRows>
                        <Column field="date" header="Date">
                            <template #body="slotProps">
                                <span class="font-mono text-sm text-gray-600">{{ formatDate(slotProps.data.date) }}</span>
                            </template>
                        </Column>

                        <Column field="description" header="Description">
                            <template #body="slotProps">
                                <div class="flex flex-col">
                                    <span class="font-medium text-gray-800">{{ slotProps.data.description }}</span>
                                    <span v-if="slotProps.data.invoice_id" class="cursor-pointer text-xs text-indigo-500 hover:underline"> View Bill #{{ slotProps.data.invoice_id }} </span>
                                </div>
                            </template>
                        </Column>

                        <Column header="Staff / User">
                            <template #body="slotProps">
                                <div class="flex items-center gap-2">
                                    <i class="pi pi-user text-gray-400"></i>
                                    <span class="font-medium text-gray-700">
                                        {{ slotProps.data.user ? slotProps.data.user.name : 'System' }}
                                    </span>
                                </div>
                            </template>
                        </Column>

                        <Column field="type" header="Type">
                            <template #body="slotProps">
                                <Tag :value="slotProps.data.type" :severity="getSeverity(slotProps.data.type)" />
                            </template>
                        </Column>

                        <Column field="amount" header="Amount" sortable>
                            <template #body="slotProps">
                                <span v-if="slotProps.data.type === 'VOID'" class="font-bold text-gray-400 line-through">
                                    {{ slotProps.data.amount > 0 ? '+' : '-' }} {{ formatMoney(slotProps.data.amount) }}
                                </span>

                                <span v-else-if="slotProps.data.type === 'SALE'" class="font-bold text-red-600"> + {{ formatMoney(slotProps.data.amount) }} </span>

                                <span v-else class="font-bold text-green-600"> - {{ formatMoney(slotProps.data.amount) }} </span>
                            </template>
                        </Column>
                    </DataTable>
                    <Paginator
                        :rows="transactions.per_page"
                        :totalRecords="transactions.total"
                        :first="(transactions.current_page - 1) * transactions.per_page"
                        @page="onPageChange"
                        class="border-t border-gray-100"
                    ></Paginator>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
