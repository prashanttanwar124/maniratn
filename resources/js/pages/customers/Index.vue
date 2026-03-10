<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Avatar from 'primevue/avatar';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Paginator from 'primevue/paginator';
import Tag from 'primevue/tag';
import { route } from 'ziggy-js';
const props = defineProps({
    customers: Object,
    topSpenders: Array,
    topDebtors: Array,
    totalCount: Number,
    newThisWeek: Number,
});

const onPageChange = (event) => {
    const newPage = event.page + 1;

    // Reload page with new data
    router.get(
        route('customers.index'),
        { page: newPage },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

// Helper to format currency (₹ 50,000)
const formatMoney = (value) => {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(value);
};

const breadcrumbs = [
    {
        title: 'Dashboard',
        href: '/customers',
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <div class="overflow-x-auto">
                <div class="min-h-screen">
                    <div class="mb-6 grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div class="flex flex-col rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                            <span class="text-sm font-medium text-gray-500">Total Customers</span>
                            <span class="mt-2 text-4xl font-bold text-gray-800">{{ totalCount }}</span>
                            <span class="mt-1 text-sm text-green-500">↗ {{ newThisWeek }} New this week</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        <div class="space-y-6 lg:col-span-2">
                            <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                                <h2 class="mb-4 px-2 text-lg font-bold text-gray-800">All Customers</h2>

                                <DataTable :value="props.customers.data" stripedRows tableStyle="min-width: 40rem">
                                    <Column header="Customer" sortable field="name">
                                        <template #body="slotProps">
                                            <div class="flex items-center gap-3">
                                                <Avatar :label="slotProps.data.name.charAt(0)" shape="circle" class="bg-indigo-50 text-indigo-600" />
                                                <div class="flex flex-col">
                                                    <Link :href="'customers/' + slotProps.data.id">
                                                        <span class="font-bold text-gray-700">{{ slotProps.data.name }}</span>
                                                    </Link>
                                                    <span class="text-xs text-gray-400">{{ slotProps.data.city }}</span>
                                                </div>
                                            </div>
                                        </template>
                                    </Column>

                                    <Column header="Contact" field="mobile">
                                        <template #body="slotProps">
                                            <div class="flex flex-col text-sm">
                                                <span>📞 {{ slotProps.data.mobile }}</span>
                                                <span class="text-xs text-gray-400">{{ slotProps.data.pan_no || 'No PAN' }}</span>
                                            </div>
                                        </template>
                                    </Column>

                                    <Column field="total_spend" header="Total Spend" sortable>
                                        <template #body="slotProps">
                                            <span class="font-semibold text-gray-600">
                                                {{ formatMoney(slotProps.data.total_spend) }}
                                            </span>
                                        </template>
                                    </Column>

                                    <Column field="balance" header="Total Due" sortable>
                                        <template #body="slotProps">
                                            <Tag :severity="slotProps.data.balance > 0 ? 'danger' : 'success'" :value="slotProps.data.balance > 0 ? 'Due' : 'Paid'" class="mr-2" />
                                            <span :class="slotProps.data.balance > 0 ? 'font-bold text-red-600' : 'text-green-600'">
                                                {{ formatMoney(slotProps.data.balance) }}
                                            </span>
                                        </template>
                                    </Column>
                                </DataTable>

                                <Paginator
                                    v-if="customers.total > 0"
                                    :rows="customers.per_page"
                                    :totalRecords="customers.total"
                                    :first="(customers.current_page - 1) * customers.per_page"
                                    @page="onPageChange"
                                    class="mt-4 border-t border-gray-100"
                                ></Paginator>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                                <h3 class="mb-4 font-bold text-gray-700">🏆 Highest Spenders</h3>
                                <ul class="space-y-4">
                                    <li v-for="(vip, index) in topSpenders" :key="vip.id" class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <span class="font-mono text-sm text-gray-400">{{ index + 1 }}.</span>
                                            <Avatar :image="vip.avatar_url" :label="vip.name[0]" shape="circle" size="small" />
                                            <span class="text-sm font-medium text-gray-700">{{ vip.name }}</span>
                                        </div>
                                        <span class="text-sm font-bold text-indigo-600">{{ formatMoney(vip.total_spend) }}</span>
                                    </li>
                                </ul>
                            </div>

                            <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                                <h3 class="mb-4 font-bold text-red-600">⚠️ Highest Due Customers</h3>
                                <ul class="space-y-4">
                                    <li v-for="(debtor, index) in topDebtors" :key="debtor.id" class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <span class="font-mono text-sm text-gray-400">{{ index + 1 }}.</span>
                                            <span class="text-sm font-medium text-gray-700">{{ debtor.name }}</span>
                                        </div>
                                        <span class="text-sm font-bold text-red-500">{{ formatMoney(debtor.balance) }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div></AppLayout
    >
</template>
