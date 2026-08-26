<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { formatIndianDate } from '@/utils/indiaTime';

const props = defineProps({
    user: Object,
    rates: Object,
    metrics: Object,
    isDayOpen: Boolean,
    recent_invoices: Array,
    attention_items: Array,
    customer_reminders: Array,
});

const page = usePage();
const can = computed(() => page.props.auth?.can || {});

const formatCurrency = (val) =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 0,
    }).format(val || 0);

const formatReminderDate = (val) =>
    formatIndianDate(val, {
        day: 'numeric',
        month: 'short',
    });

const statusSeverity = (status) => {
    if (status === 'READY') return 'success';
    if (status === 'ASSIGNED') return 'warning';
    return 'contrast';
};
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="erp-page-header border border-surface-200 bg-white px-5 py-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-3">
                            <h2 class="text-2xl font-semibold tracking-tight text-surface-900">Staff Dashboard</h2>
                            <Tag :value="isDayOpen ? 'Day Open' : 'Day Closed'" :severity="isDayOpen ? 'success' : 'danger'" />
                            <Tag v-if="metrics?.overdue_items" :value="`${metrics.overdue_items} overdue`" severity="warn" />
                        </div>
                        <p class="mt-1 text-sm text-surface-500">Billing, collections, and order follow-up for {{ user.name }}.</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Link v-if="can.manage_invoices" :href="route('invoices.create')">
                            <Button label="New Bill" icon="pi pi-file-edit" size="small" />
                        </Link>
                        <Link v-if="can.manage_customers" :href="route('customers.index')">
                            <Button label="Customers" icon="pi pi-users" outlined size="small" />
                        </Link>
                        <Link v-if="can.create_order || can.manage_orders" :href="route('orders.index')">
                            <Button label="Orders" icon="pi pi-briefcase" outlined size="small" />
                        </Link>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-4">
                <div class="erp-stat-card p-4">
                    <span class="erp-stat-card__label">My Billing Today</span>
                    <span class="erp-stat-card__value mt-2 block">{{ formatCurrency(metrics?.my_sales) }}</span>
                    <span class="erp-stat-card__meta">{{ metrics?.my_invoices || 0 }} invoices posted</span>
                </div>

                <div class="erp-stat-card p-4">
                    <span class="erp-stat-card__label">My Collections</span>
                    <span class="erp-stat-card__value mt-2 block !text-emerald-700">{{ formatCurrency(metrics?.my_collections) }}</span>
                    <span class="erp-stat-card__meta">Cash and digital receipts handled today</span>
                </div>

                <div class="erp-stat-card p-4">
                    <span class="erp-stat-card__label">Ready for Billing</span>
                    <span class="erp-stat-card__value mt-2 block">{{ metrics?.ready_items || 0 }}</span>
                    <span class="erp-stat-card__meta">Order items available to convert into bills</span>
                </div>

                <div class="erp-stat-card p-4">
                    <span class="erp-stat-card__label">Overdue Orders</span>
                    <span class="erp-stat-card__value mt-2 block" :class="metrics?.overdue_items ? '!text-amber-600' : ''">
                        {{ metrics?.overdue_items || 0 }}
                    </span>
                    <span class="erp-stat-card__meta">Items that need immediate follow-up</span>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div class="erp-stat-card p-4">
                    <span class="erp-stat-card__label">Gold Sell</span>
                    <span class="erp-stat-card__value mt-2 block text-lg font-semibold">{{ formatCurrency(rates?.gold_sell) }}</span>
                    <span class="erp-stat-card__meta">Standard sell benchmark</span>
                </div>

                <div class="erp-stat-card p-4">
                    <span class="erp-stat-card__label">Gold Buy</span>
                    <span class="erp-stat-card__value mt-2 block text-lg font-semibold">{{ formatCurrency(rates?.gold_buy) }}</span>
                    <span class="erp-stat-card__meta">Purchase exchange rate</span>
                </div>

                <div class="erp-stat-card p-4">
                    <span class="erp-stat-card__label">Silver</span>
                    <span class="erp-stat-card__value mt-2 block text-lg font-semibold">{{ formatCurrency(rates?.silver_sell) }}</span>
                    <span class="erp-stat-card__meta">Silver metal benchmark</span>
                </div>

                <div class="erp-stat-card p-4">
                    <span class="erp-stat-card__label">New Orders</span>
                    <span class="erp-stat-card__value mt-2 block text-lg font-semibold">{{ metrics?.new_orders || 0 }}</span>
                    <span class="erp-stat-card__meta">Unassigned custom jobs</span>
                </div>

                <div class="erp-stat-card p-4">
                    <span class="erp-stat-card__label">In Production</span>
                    <span class="erp-stat-card__value mt-2 block text-lg font-semibold">{{ metrics?.in_production || 0 }}</span>
                    <span class="erp-stat-card__meta">Currently at karigar workshop</span>
                </div>
            </div>

            <div class="erp-panel overflow-hidden border border-surface-200 bg-white">
                <div class="border-b border-surface-200 px-5 py-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-surface-900">Customer Reminders</h3>
                            <p class="mt-1 text-sm text-surface-500">Upcoming birthdays and anniversaries for follow-up.</p>
                        </div>
                        <Link v-if="can.manage_customers" :href="route('customers.index')" class="text-sm font-medium text-amber-700 hover:text-amber-800">
                            Open Customers
                        </Link>
                    </div>
                </div>

                <div class="divide-y divide-surface-200">
                    <template v-if="customer_reminders?.length">
                        <div v-for="reminder in customer_reminders" :key="`${reminder.type}-${reminder.customer_id}-${reminder.date}`" class="flex items-center justify-between gap-4 px-5 py-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-medium text-surface-900">{{ reminder.customer_name }}</p>
                                    <Tag :value="reminder.type" :severity="reminder.type === 'Birthday' ? 'success' : 'info'" />
                                    <Tag v-if="reminder.is_today" value="Today" severity="warn" />
                                </div>
                                <p class="mt-1 text-sm text-surface-500">{{ reminder.mobile || 'No mobile number' }}</p>
                            </div>

                            <div class="text-right">
                                <p class="text-sm font-medium text-surface-900">{{ formatReminderDate(reminder.date) }}</p>
                                <p class="mt-1 text-xs text-surface-400">
                                    {{ reminder.is_today ? 'Wish today' : `${reminder.days_until} day${reminder.days_until === 1 ? '' : 's'} left` }}
                                </p>
                            </div>
                        </div>
                    </template>

                    <div v-else class="py-12 text-center text-sm text-surface-500">
                        No birthday or anniversary reminders in the next 7 days.
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
                <div class="erp-panel overflow-hidden border border-surface-200 bg-white xl:col-span-7">
                    <div class="border-b border-surface-200 px-5 py-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-base font-semibold text-surface-900">Items Needing Attention</h3>
                                <p class="mt-1 text-sm text-surface-500">Order items waiting for action, follow-up, or billing.</p>
                            </div>
                            <Link v-if="can.create_order || can.manage_orders" :href="route('orders.index')" class="text-sm font-medium text-amber-700 hover:text-amber-800">
                                Open Orders
                            </Link>
                        </div>
                    </div>

                    <div class="divide-y divide-surface-200">
                        <template v-if="attention_items?.length">
                            <div v-for="item in attention_items" :key="item.id" class="flex items-start justify-between gap-4 px-5 py-4">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-medium text-surface-900">{{ item.design_name }}</p>
                                        <Tag :value="item.status" :severity="statusSeverity(item.status)" />
                                        <Tag v-if="item.is_overdue" value="Overdue" severity="danger" />
                                    </div>
                                    <p class="mt-1 text-sm text-surface-500">{{ item.customer_name }}</p>
                                </div>

                                <div class="text-right">
                                    <p class="text-sm font-medium text-surface-900">{{ item.due_date || 'No due date' }}</p>
                                    <p class="mt-1 text-xs text-surface-400">Item #{{ item.id }}</p>
                                </div>
                            </div>
                        </template>

                        <div v-else class="py-16 text-center text-sm text-surface-500">
                            No order items need attention right now.
                        </div>
                    </div>
                </div>

                <div class="erp-panel overflow-hidden border border-surface-200 bg-white xl:col-span-5">
                    <div class="border-b border-surface-200 px-5 py-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-base font-semibold text-surface-900">My Recent Bills</h3>
                                <p class="mt-1 text-sm text-surface-500">Latest invoices created by you.</p>
                            </div>
                            <Link v-if="can.manage_invoices" :href="route('invoices.index')" class="text-sm font-medium text-amber-700 hover:text-amber-800">
                                View all
                            </Link>
                        </div>
                    </div>

                    <div class="space-y-3 p-4">
                        <template v-if="recent_invoices?.length">
                            <div v-for="invoice in recent_invoices" :key="invoice.id" class="rounded-lg border border-surface-200 bg-surface-50/50 px-4 py-3 shadow-xs hover:bg-white transition-colors">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-medium text-surface-900">{{ invoice.invoice_number }}</p>
                                            <Tag :value="invoice.status" :severity="invoice.status === 'CANCELLED' ? 'danger' : 'success'" />
                                        </div>
                                        <p class="mt-1 truncate text-xs text-surface-500">{{ invoice.customer_name }}</p>
                                    </div>

                                    <div class="text-right">
                                        <p class="text-sm font-semibold text-surface-900">{{ formatCurrency(invoice.total_amount) }}</p>
                                        <p class="mt-1 text-xs text-surface-400">{{ invoice.date }}</p>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div v-else class="py-16 text-center text-sm text-surface-500">
                            You have not created any invoices yet.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
