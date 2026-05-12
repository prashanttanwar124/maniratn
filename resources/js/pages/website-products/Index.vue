<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import throttle from 'lodash/throttle';
import { Search } from 'lucide-vue-next';
import { useToast } from 'primevue/usetoast';
import { ref, watch } from 'vue';
import { route } from 'ziggy-js';

import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import Paginator from 'primevue/paginator';
import Tag from 'primevue/tag';

const props = defineProps({
    products: Object,
    filters: Object,
    endpointUrl: String,
    summary: Object,
});

const toast = useToast();
const search = ref(props.filters?.search || '');
const updatingId = ref<number | null>(null);

const applyFilters = (page = 1) => {
    router.get(
        route('website-products.manage'),
        {
            page,
            search: search.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

watch(
    search,
    throttle(() => {
        applyFilters();
    }, 300),
);

const onPageChange = (event) => {
    applyFilters(event.page + 1);
};

const toggleVisibility = (product) => {
    updatingId.value = product.id;

    router.patch(
        route('website-products.update', product.id),
        {
            is_visible_on_website: !product.is_visible_on_website,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Updated',
                    detail: 'Website visibility updated',
                    life: 2500,
                });
            },
            onFinish: () => {
                updatingId.value = null;
            },
        },
    );
};

const copyEndpoint = async () => {
    try {
        await navigator.clipboard.writeText(props.endpointUrl || '');
        toast.add({
            severity: 'success',
            summary: 'Copied',
            detail: 'API endpoint copied',
            life: 2500,
        });
    } catch {
        toast.add({
            severity: 'error',
            summary: 'Copy Failed',
            detail: 'Unable to copy endpoint from this browser.',
            life: 2500,
        });
    }
};
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <div class="border-b border-surface-200 bg-white px-5 py-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-2xl font-semibold tracking-tight text-surface-900">Website Product Catalog</h1>
                            <Tag value="Website API" severity="info" />
                        </div>

                        <p class="mt-1 text-sm text-surface-500">Manage which gold products appear in website API feed.</p>
                    </div>

                    <Button label="Copy API Endpoint" icon="pi pi-copy" outlined @click="copyEndpoint" class="!w-auto shrink-0 whitespace-nowrap" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Total Products</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ props.summary?.total_items || 0 }}</p>
                </div>

                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Visible on Website</p>
                    <p class="mt-2 text-2xl font-semibold text-emerald-600">{{ props.summary?.visible_items || 0 }}</p>
                </div>

                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Hidden from Website</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ props.summary?.hidden_items || 0 }}</p>
                </div>

                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Sold Products</p>
                    <p class="mt-2 text-2xl font-semibold text-red-600">{{ props.summary?.sold_items || 0 }}</p>
                </div>
            </div>

            <div class="border border-surface-200 bg-surface-50 px-5 py-4">
                <p class="text-sm font-medium text-surface-900">Public API Endpoint</p>
                <code class="mt-2 block overflow-x-auto bg-white px-3 py-2 text-sm text-surface-700">{{ endpointUrl }}</code>
            </div>

            <div class="card overflow-hidden !p-0">
                <div class="border-b border-surface-200 bg-white px-5 py-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-surface-900">Website Visibility</h3>
                            <p class="mt-1 text-sm text-surface-500">Search gold products and choose what website API should publish.</p>
                        </div>

                        <div class="relative w-full lg:w-80">
                            <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-surface-400" />
                            <InputText v-model="search" placeholder="Search product by name or barcode..." class="w-full !pl-10" />
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4">
                    <DataTable :value="products.data" dataKey="id" stripedRows rowHover tableStyle="min-width: 70rem">
                        <template #empty>
                            <div class="py-12 text-center text-surface-500">No products found</div>
                        </template>

                        <Column header="Barcode" style="width: 160px">
                            <template #body="{ data }">
                                <span class="font-medium text-surface-900">{{ data.barcode }}</span>
                            </template>
                        </Column>

                        <Column header="Product" style="min-width: 280px">
                            <template #body="{ data }">
                                <div>
                                    <p class="font-medium text-surface-900">{{ data.name }}</p>
                                    <p class="mt-1 text-xs text-surface-500">{{ data.category?.name || '—' }} <span class="text-surface-300">•</span> {{ data.purity?.name || '—' }}</p>
                                </div>
                            </template>
                        </Column>

                        <Column header="Weight" style="width: 180px">
                            <template #body="{ data }">
                                <div class="space-y-1 text-sm">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-surface-500">Gross</span>
                                        <span class="font-medium text-surface-900">{{ Number(data.gross_weight || 0).toFixed(3) }} g</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-surface-500">Net</span>
                                        <span class="font-medium text-surface-900">{{ Number(data.net_weight || 0).toFixed(3) }} g</span>
                                    </div>
                                </div>
                            </template>
                        </Column>

                        <Column header="Stock" style="width: 120px">
                            <template #body="{ data }">
                                <Tag :value="data.is_sold ? 'Sold' : 'In Stock'" :severity="data.is_sold ? 'danger' : 'success'" />
                            </template>
                        </Column>

                        <Column header="Website" style="width: 180px">
                            <template #body="{ data }">
                                <div class="flex items-center justify-between gap-3">
                                    <Tag :value="data.is_visible_on_website ? 'Visible' : 'Hidden'" :severity="data.is_visible_on_website ? 'info' : 'secondary'" />
                                    <Checkbox
                                        :modelValue="Boolean(data.is_visible_on_website)"
                                        binary
                                        :disabled="Boolean(data.is_sold) || updatingId === data.id"
                                        @update:modelValue="toggleVisibility(data)"
                                    />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <Paginator :rows="products.per_page" :totalRecords="products.total" :first="(products.current_page - 1) * products.per_page" @page="onPageChange" class="border-t border-surface-200" />
            </div>
        </div>
    </AppLayout>
</template>
