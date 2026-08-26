<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, CreditCard, Landmark, MapPin, Phone, ShieldCheck, WalletCards } from 'lucide-vue-next';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Paginator from 'primevue/paginator';
import Tag from 'primevue/tag';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';
import { formatIndianDate } from '@/utils/indiaTime';

const props = defineProps({
    customer: Object,
    transactions: Object,
    stats: Object,
    vault: Object,
});

const copied = ref(false);

const copyVaultLink = async () => {
    if (!props.vault?.vault_url) return;
    try {
        await navigator.clipboard.writeText(props.vault.vault_url);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    } catch {
        // ignore
    }
};

const issueCard = () => {
    router.post(route('customers.vault.issue', props.customer.id), {}, {
        preserveScroll: true,
    });
};

const lockCard = () => {
    router.patch(route('customers.vault.lock', props.customer.id), {}, {
        preserveScroll: true,
    });
};

const deactivateCard = () => {
    if (confirm('Are you sure you want to deactivate this customer smart card?')) {
        router.patch(route('customers.vault.deactivate', props.customer.id), {}, {
            preserveScroll: true,
        });
    }
};

const reissueCard = () => {
    if (confirm('Reissuing will generate a new token. The previous physical card will stop working until reprogrammed. Continue?')) {
        router.post(route('customers.vault.reissue', props.customer.id), {}, {
            preserveScroll: true,
        });
    }
};

const openWriter = () => {
    window.open(route('customers.vault.writer', props.customer.id), '_blank', 'noopener');
};

const openVault = () => {
    if (props.vault?.vault_url) {
        window.open(props.vault.vault_url, '_blank', 'noopener');
    }
};

const shareOnWhatsapp = () => {
    if (!props.vault?.vault_url) return;
    const phone = props.customer.mobile ? props.customer.mobile.replace(/[^0-9]/g, '') : '';
    let text = `Hello ${props.customer.name},\n\nHere is your personal *Maniratn Digital Jewellery Vault* link:\n${props.vault.vault_url}\n\nYou can access your jewellery certificates, purchase invoices, and gold schemes anytime!`;
    if (props.vault?.google_review_url) {
        text += `\n\n*We would love your feedback! Rate your experience on Google:*\n${props.vault.google_review_url}`;
    }
    text += `\n\nWarm regards,\n*Maniratn Jewellers*`;
    const url = phone ? `https://wa.me/${phone}?text=${encodeURIComponent(text)}` : `https://wa.me/?text=${encodeURIComponent(text)}`;
    window.open(url, '_blank', 'noopener');
};

const cardStatusSeverity = (status) => {
    if (status === 'LOCKED') return 'success';
    if (status === 'WRITTEN' || status === 'ISSUED') return 'info';
    if (status === 'DISABLED') return 'contrast';
    return 'warn';
};

const breadcrumbs = [
    {
        title: 'Customers',
        href: '/customers',
    },
    {
        title: props.customer.name,
        href: route('customers.show', props.customer.id),
    },
];

const tableData = computed(() => props.transactions.data);

const onPageChange = (event) => {
    router.get(
        route('customers.show', props.customer.id),
        { page: event.page + 1 },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const formatMoney = (value) =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
    }).format(Number(value || 0));

const formatDate = (dateString) =>
    formatIndianDate(dateString, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });

const balanceTone = computed(() => (Number(props.stats.current_balance || 0) > 0 ? 'text-red-600' : 'text-emerald-600'));
const balanceLabel = computed(() => (Number(props.stats.current_balance || 0) > 0 ? 'Receivable from customer' : 'Account settled / advance'));

const getSeverity = (type) => {
    if (type === 'SALE') return 'danger';
    if (type === 'PAYMENT') return 'success';
    if (type === 'VOID') return 'secondary';
    return 'info';
};
</script>

<template>
    <Head :title="customer.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4">
            <section class="erp-panel relative overflow-hidden border border-surface-200 bg-white">
                <div class="absolute inset-y-0 right-0 hidden w-96 bg-[radial-gradient(circle_at_top_right,_rgba(245,158,11,0.16),_transparent_62%)] lg:block" />
                <div class="relative flex flex-col gap-6 px-5 py-6 lg:flex-row lg:items-start lg:justify-between">
                    <div class="max-w-3xl">
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-2xl font-semibold tracking-tight text-surface-900">{{ customer.name }}</h1>
                            <Tag value="Customer Profile" severity="secondary" />
                            <Tag :value="Number(stats.current_balance || 0) > 0 ? 'Due Pending' : 'Healthy Account'" :severity="Number(stats.current_balance || 0) > 0 ? 'danger' : 'success'" />
                        </div>

                        <p class="mt-3 max-w-2xl text-sm leading-6 text-surface-600">
                            Review purchase history, payment movement, and move into the full ledger when you need account-level cash entries or settlement context.
                        </p>

                        <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div class="erp-stat-card flex items-center gap-3 p-3.5">
                                <div class="erp-icon-tile flex h-9 w-9 shrink-0 items-center justify-center border border-surface-200 bg-surface-50 text-surface-600">
                                    <MapPin class="h-4 w-4" />
                                </div>
                                <div class="min-w-0">
                                    <span class="erp-stat-card__label">City</span>
                                    <p class="mt-0.5 text-sm font-semibold text-surface-900 truncate">{{ customer.city || 'Unknown city' }}</p>
                                </div>
                            </div>
                            <div class="erp-stat-card flex items-center gap-3 p-3.5">
                                <div class="erp-icon-tile flex h-9 w-9 shrink-0 items-center justify-center border border-surface-200 bg-surface-50 text-surface-600">
                                    <Phone class="h-4 w-4" />
                                </div>
                                <div class="min-w-0">
                                    <span class="erp-stat-card__label">Mobile</span>
                                    <p class="mt-0.5 font-mono text-sm font-semibold text-surface-900 truncate">{{ customer.mobile || 'No mobile' }}</p>
                                </div>
                            </div>
                            <div class="erp-stat-card flex items-center gap-3 p-3.5">
                                <div class="erp-icon-tile flex h-9 w-9 shrink-0 items-center justify-center border border-surface-200 bg-surface-50 text-surface-600">
                                    <ShieldCheck class="h-4 w-4" />
                                </div>
                                <div class="min-w-0">
                                    <span class="erp-stat-card__label">PAN</span>
                                    <p class="mt-0.5 font-mono text-sm font-semibold text-surface-900 truncate">{{ customer.pan_no || '--' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Link :href="route('ledger.show', { type: 'customers', id: customer.id })">
                            <Button label="Open Ledger" icon="pi pi-book" outlined />
                        </Link>
                        <Button label="New Bill" icon="pi pi-plus" @click="$inertia.visit(route('invoices.create', { customer_id: customer.id }))" />
                    </div>
                </div>
            </section>

            <!-- Customer Digital Vault & NFC Smart Card Section -->
            <section class="erp-panel overflow-hidden !p-0">
                <div class="flex flex-col gap-3 border-b border-surface-200 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <span class="erp-icon-tile flex h-9 w-9 shrink-0 items-center justify-center border border-amber-200 bg-amber-50 text-amber-700">
                            <CreditCard class="h-4 w-4" />
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-bold text-surface-900">Digital Vault & NFC Smart Card</h3>
                                <Tag :value="vault?.card_status || 'NOT_ISSUED'" :severity="cardStatusSeverity(vault?.card_status)" class="!text-[10px] !px-1.5 !py-0.5" />
                            </div>
                            <p class="mt-0.5 text-xs text-surface-500">One-tap NFC Smart Card for real-time certificates, bills, and schemes.</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 shrink-0">
                        <Button
                            v-if="!vault?.has_card || vault?.card_status === 'DISABLED'"
                            label="Issue Smart Card"
                            icon="pi pi-plus"
                            size="small"
                            @click="issueCard"
                        />
                        <template v-else>
                            <Button
                                label="Desktop Writer"
                                icon="pi pi-desktop"
                                size="small"
                                @click="openWriter"
                            />
                            <Button
                                label="View Vault"
                                icon="pi pi-external-link"
                                outlined
                                size="small"
                                @click="openVault"
                            />
                            <Button
                                label="WhatsApp"
                                icon="pi pi-whatsapp"
                                severity="success"
                                outlined
                                size="small"
                                @click="shareOnWhatsapp"
                            />
                            <Button
                                v-if="vault?.card_status !== 'LOCKED'"
                                icon="pi pi-lock"
                                severity="warn"
                                text
                                size="small"
                                v-tooltip.top="'Lock Card'"
                                @click="lockCard"
                            />
                            <Button
                                icon="pi pi-refresh"
                                severity="secondary"
                                text
                                size="small"
                                v-tooltip.top="'Re-issue Card'"
                                @click="reissueCard"
                            />
                            <Button
                                icon="pi pi-ban"
                                severity="danger"
                                text
                                size="small"
                                v-tooltip.top="'Deactivate Card'"
                                @click="deactivateCard"
                            />
                        </template>
                    </div>
                </div>

                <div v-if="vault?.has_card && vault?.card_status !== 'DISABLED'" class="p-4 sm:p-5">
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1.2fr_1fr]">
                        <div class="erp-subpanel flex flex-col justify-between border border-surface-200 bg-surface-50 p-4">
                            <div>
                                <label class="block text-xs font-semibold tracking-wider text-surface-600 uppercase">Public Customer Vault URL</label>
                                <div class="mt-2 flex items-center gap-2">
                                    <input
                                        type="text"
                                        :value="vault?.vault_url"
                                        readonly
                                        class="erp-form-control w-full border border-surface-200 bg-white px-3 py-2 text-xs font-mono text-surface-800 focus:outline-none"
                                    />
                                    <Button
                                        :icon="copied ? 'pi pi-check' : 'pi pi-copy'"
                                        :label="copied ? 'Copied' : 'Copy'"
                                        :severity="copied ? 'success' : 'secondary'"
                                        size="small"
                                        outlined
                                        class="shrink-0"
                                        @click="copyVaultLink"
                                    />
                                </div>
                            </div>
                            <p class="mt-3 text-xs text-surface-500">
                                This link is permanently assigned to this customer. New purchases automatically reflect in their vault.
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="erp-stat-card border border-surface-200 bg-white p-3">
                                <p class="text-xs font-medium text-surface-500">Card Scans / Taps</p>
                                <p class="mt-1 font-mono text-lg font-bold text-surface-900">{{ vault?.card_access_count || 0 }}</p>
                            </div>
                            <div class="erp-stat-card border border-surface-200 bg-white p-3">
                                <p class="text-xs font-medium text-surface-500">Last Tapped</p>
                                <p class="mt-1 text-xs font-semibold text-surface-800">{{ vault?.card_last_accessed_at ? formatDate(vault.card_last_accessed_at) : 'Never' }}</p>
                            </div>
                            <div class="erp-stat-card border border-surface-200 bg-white p-3">
                                <p class="text-xs font-medium text-surface-500">Invoices In Vault</p>
                                <p class="mt-1 font-mono text-lg font-bold text-surface-900">{{ vault?.invoices_count || 0 }}</p>
                            </div>
                            <div class="erp-stat-card border border-surface-200 bg-white p-3">
                                <p class="text-xs font-medium text-surface-500">Active Schemes</p>
                                <p class="mt-1 font-mono text-lg font-bold text-surface-900">{{ vault?.schemes_count || 0 }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="p-6 text-center text-surface-500">
                    <p class="text-sm">No Smart Card issued to {{ customer.name }} yet.</p>
                    <p class="mt-1 text-xs text-surface-400">Click <strong>Issue Smart Card</strong> above to generate a unique vault token and program an NFC card.</p>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 xl:grid-cols-4">
                <div class="erp-stat-card p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <span class="erp-stat-card__label">Total purchased</span>
                            <span class="erp-stat-card__value mt-2 block">{{ formatMoney(stats.total_sales) }}</span>
                        </div>
                        <span class="erp-icon-tile flex h-9 w-9 items-center justify-center border border-surface-200 bg-surface-50 text-surface-600">
                            <Landmark class="h-4 w-4" />
                        </span>
                    </div>
                </div>

                <div class="erp-stat-card p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <span class="erp-stat-card__label">Total paid</span>
                            <span class="erp-stat-card__value mt-2 block !text-emerald-600">{{ formatMoney(stats.total_paid) }}</span>
                        </div>
                        <span class="erp-icon-tile flex h-9 w-9 items-center justify-center border border-emerald-200 bg-emerald-50 text-emerald-700">
                            <WalletCards class="h-4 w-4" />
                        </span>
                    </div>
                </div>

                <div class="erp-stat-card p-5 xl:col-span-2">
                    <span class="erp-stat-card__label">Current balance</span>
                    <div class="mt-2 flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                        <span :class="['font-mono text-3xl font-bold', balanceTone]">{{ formatMoney(stats.current_balance) }}</span>
                        <span class="text-sm font-medium text-surface-500">{{ balanceLabel }}</span>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1.6fr_1fr]">
                <div class="erp-panel overflow-hidden border border-surface-200 bg-white">
                    <div class="border-b border-surface-200 px-5 py-4">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h3 class="text-base font-semibold text-surface-900">Recent transaction history</h3>
                                <p class="mt-1 text-sm text-surface-500">Quick view of invoice and payment records on the customer account.</p>
                            </div>

                            <Link :href="route('ledger.show', { type: 'customers', id: customer.id })" class="inline-flex">
                                <Button label="View Full Ledger" icon="pi pi-arrow-right" size="small" text />
                            </Link>
                        </div>
                    </div>

                    <div class="p-4">
                        <DataTable :value="tableData" stripedRows rowHover tableStyle="min-width: 54rem">
                            <template #empty>
                                <div class="py-12 text-center text-surface-500">No transactions found for this customer.</div>
                            </template>

                            <Column field="date" header="Date" style="width: 130px">
                                <template #body="{ data }">
                                    <span class="font-mono text-sm text-surface-600">{{ formatDate(data.date) }}</span>
                                </template>
                            </Column>

                            <Column field="description" header="Description">
                                <template #body="{ data }">
                                    <div>
                                        <p class="font-medium text-surface-900">{{ data.description }}</p>
                                        <p v-if="data.invoice_id" class="mt-1 text-xs text-surface-500">Bill reference #{{ data.invoice_id }}</p>
                                    </div>
                                </template>
                            </Column>

                            <Column header="Handled By" style="width: 170px">
                                <template #body="{ data }">
                                    <span class="text-sm font-medium text-surface-700">{{ data.user ? data.user.name : 'System' }}</span>
                                </template>
                            </Column>

                            <Column field="type" header="Type" style="width: 120px">
                                <template #body="{ data }">
                                    <Tag :value="data.type" :severity="getSeverity(data.type)" />
                                </template>
                            </Column>

                            <Column field="amount" header="Amount" sortable style="width: 150px">
                                <template #body="{ data }">
                                    <span v-if="data.type === 'VOID'" class="font-semibold text-surface-400 line-through">
                                        {{ formatMoney(data.amount) }}
                                    </span>
                                    <span v-else-if="data.type === 'SALE'" class="font-semibold text-red-600">
                                        + {{ formatMoney(data.amount) }}
                                    </span>
                                    <span v-else class="font-semibold text-emerald-600">
                                        - {{ formatMoney(data.amount) }}
                                    </span>
                                </template>
                            </Column>
                        </DataTable>
                    </div>

                    <Paginator
                        :rows="transactions.per_page"
                        :totalRecords="transactions.total"
                        :first="(transactions.current_page - 1) * transactions.per_page"
                        @page="onPageChange"
                        class="border-t border-surface-200"
                    />
                </div>

                <div class="space-y-6">
                    <div class="erp-panel overflow-hidden border border-surface-200 bg-white">
                        <div class="border-b border-surface-200 px-5 py-4">
                            <h3 class="text-base font-semibold text-surface-900">Account actions</h3>
                            <p class="mt-1 text-sm text-surface-500">Most common next steps for this customer account.</p>
                        </div>

                        <div class="space-y-3 p-5">
                            <Link :href="route('ledger.show', { type: 'customers', id: customer.id })" class="erp-action-button flex items-center justify-between border border-surface-200 bg-white px-4 py-3">
                                <div>
                                    <p class="font-medium text-surface-900">Open customer ledger</p>
                                    <p class="mt-1 text-xs text-surface-500">Post manual cash entries or review full balance flow</p>
                                </div>
                                <ArrowRight class="h-4 w-4 text-surface-500" />
                            </Link>

                            <Link :href="route('invoices.create', { customer_id: customer.id })" class="erp-action-button flex items-center justify-between border border-surface-200 bg-white px-4 py-3">
                                <div>
                                    <p class="font-medium text-surface-900">Prepare new bill</p>
                                    <p class="mt-1 text-xs text-surface-500">Start a new invoice for this customer</p>
                                </div>
                                <ArrowRight class="h-4 w-4 text-surface-500" />
                            </Link>
                        </div>
                    </div>

                    <div class="erp-panel overflow-hidden border border-surface-200 bg-white">
                        <div class="border-b border-surface-200 px-5 py-4">
                            <h3 class="text-base font-semibold text-surface-900">Recovery note</h3>
                            <p class="mt-1 text-sm text-surface-500">Use this at-a-glance summary during collection follow-up.</p>
                        </div>

                        <div class="p-5">
                            <div class="erp-subpanel px-4 py-4">
                                <p class="text-sm text-surface-500">Outstanding amount</p>
                                <p :class="['mt-2 text-2xl font-semibold', balanceTone]">{{ formatMoney(stats.current_balance) }}</p>
                                <p class="mt-2 text-sm text-surface-500">{{ balanceLabel }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
