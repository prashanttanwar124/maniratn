<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import throttle from 'lodash/throttle';
import { Search, ScanLine } from 'lucide-vue-next';
import { useToast } from 'primevue/usetoast';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';

import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Drawer from 'primevue/drawer';
import FileUpload from 'primevue/fileupload';
import Image from 'primevue/image';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Paginator from 'primevue/paginator';
import Select from 'primevue/select';
import Tag from 'primevue/tag';

const props = defineProps({
    products: Object,
    categories: Array,
    purities: Array,
    suppliers: Array,
    filters: Object,
    summary: Object,
    category_breakdown: Array,
});

const toast = useToast();
const productDialog = ref(false);
const deleteDialog = ref(false);
const duplicateDialog = ref(false);
const bulkActionDialog = ref(false);
const historyDrawerVisible = ref(false);
const historyLoading = ref(false);
const historyProduct = ref(null);
const historyTimeline = ref([]);
const product = ref({});
const isEditing = ref(false);
const previewImage = ref(null);
const batchMode = ref(false);
const batchRows = ref([{ gross_weight: null, net_weight: null }]);
const selectedProductIds = ref<number[]>([]);
const scanBarcode = ref('');
const isScanning = ref(false);

const search = ref(props.filters?.search || '');
const categoryFilter = ref(props.filters?.category_id ? Number(props.filters.category_id) : null);
const supplierFilter = ref(props.filters?.supplier_id ? Number(props.filters.supplier_id) : null);
const purityFilter = ref(props.filters?.purity_id ? Number(props.filters.purity_id) : null);
const stockStatusFilter = ref(props.filters?.stock_status || null);
const categoryOptions = computed(() => [{ id: null, name: 'All Categories' }, ...props.categories]);
const supplierOptions = computed(() => [{ id: null, company_name: 'All Suppliers' }, ...props.suppliers]);
const purityOptions = computed(() => [{ id: null, name: 'All Purities' }, ...props.purities]);
const stockStatusOptions = [
    { label: 'All Stock', value: null },
    { label: 'In Stock', value: 'available' },
    { label: 'Sold', value: 'sold' },
];
const activeFilterCount = computed(() => {
    return [
        Boolean(search.value),
        categoryFilter.value !== null,
        supplierFilter.value !== null,
        purityFilter.value !== null,
        stockStatusFilter.value !== null,
    ].filter(Boolean).length;
});

const applyFilters = (page = 1) => {
    router.get(
        route('products.index'),
        {
            page,
            search: search.value || undefined,
            category_id: categoryFilter.value || undefined,
            supplier_id: supplierFilter.value || undefined,
            purity_id: purityFilter.value || undefined,
            stock_status: stockStatusFilter.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const currentPageSelection = computed({
    get: () => {
        const selectedIds = new Set(selectedProductIds.value);

        return props.products.data.filter((item) => selectedIds.has(item.id));
    },
    set: (pageSelection) => {
        const currentPageIds = new Set(props.products.data.map((item) => item.id));
        const preservedSelections = selectedProductIds.value.filter((id) => !currentPageIds.has(id));
        const nextPageSelections = pageSelection.map((item) => item.id);

        selectedProductIds.value = Array.from(new Set([...preservedSelections, ...nextPageSelections]));
    },
});

watch(
    search,
    throttle(() => {
        applyFilters();
    }, 300),
);

watch(categoryFilter, () => {
    applyFilters();
});

watch([supplierFilter, purityFilter, stockStatusFilter], () => {
    applyFilters();
});

const form = useForm({
    id: null,
    name: '',
    category_id: null,
    purity_id: null,
    supplier_id: null,
    gross_weight: null,
    net_weight: null,
    making_charge: null,
    batch_items: [],
    image: null,
});

const bulkForm = useForm({
    product_ids: [],
    category_id: null,
    purity_id: null,
    supplier_id: null,
    making_charge: null,
});

const formatWeight = (val) => `${Number(val || 0).toFixed(3)} g`;

const formatDate = (val) => {
    if (!val) return '—';

    const date = new Date(val);

    if (Number.isNaN(date.getTime())) return '—';

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(date);
};

const formatDateTime = (val) => {
    if (!val) return '—';

    const date = new Date(val);

    if (Number.isNaN(date.getTime())) return '—';

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
};

const onPageChange = (event) => {
    const newPage = event.page + 1;
    applyFilters(newPage);
};

const resetFilters = () => {
    search.value = '';
    categoryFilter.value = null;
    supplierFilter.value = null;
    purityFilter.value = null;
    stockStatusFilter.value = null;
    applyFilters();
};

const onFileSelect = (event) => {
    const file = event.files[0];
    form.image = file;
    previewImage.value = URL.createObjectURL(file);
};

const openNew = () => {
    product.value = {};
    isEditing.value = false;
    batchMode.value = false;
    batchRows.value = [{ gross_weight: null, net_weight: null }];
    previewImage.value = null;

    form.reset();
    form.clearErrors();

    if (props.categories.length) form.category_id = props.categories[0].id;
    if (props.purities.length) form.purity_id = props.purities[0].id;
    if (props.suppliers.length) form.supplier_id = props.suppliers[0].id;

    productDialog.value = true;
};

const editProduct = (prod) => {
    product.value = { ...prod };
    isEditing.value = true;
    batchMode.value = false;
    batchRows.value = [{ gross_weight: null, net_weight: null }];

    previewImage.value = prod.image_path ? `/storage/${prod.image_path}` : null;

    form.clearErrors();
    form.id = prod.id;
    form.name = prod.name;
    form.category_id = prod.category_id;
    form.purity_id = prod.purity_id;
    form.supplier_id = prod.supplier_id;
    form.gross_weight = parseFloat(prod.gross_weight);
    form.net_weight = parseFloat(prod.net_weight);
    form.making_charge = parseFloat(prod.making_charge);
    form.image = null;

    productDialog.value = true;
};

const confirmDuplicateProduct = (prod) => {
    product.value = prod;
    duplicateDialog.value = true;
};

const duplicateProduct = () => {
    router.post(route('products.duplicate', product.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            duplicateDialog.value = false;
            toast.add({
                severity: 'success',
                summary: 'Duplicated',
                detail: 'Product duplicated successfully',
                life: 3000,
            });
        },
    });
};

const addBatchRow = () => {
    if (batchRows.value.length >= 10) return;
    batchRows.value.push({ gross_weight: null, net_weight: null });
};

const removeBatchRow = (index) => {
    if (batchRows.value.length === 1) return;
    batchRows.value.splice(index, 1);
};

const batchItemsPayload = () => {
    return batchRows.value
        .filter((row) => Number(row.gross_weight || 0) > 0 || Number(row.net_weight || 0) > 0)
        .map((row) => ({
            gross_weight: row.gross_weight,
            net_weight: row.net_weight,
        }));
};

const saveProduct = () => {
    const options = {
        forceFormData: true,
        onSuccess: () => {
            productDialog.value = false;
            batchMode.value = false;
            batchRows.value = [{ gross_weight: null, net_weight: null }];
            toast.add({
                severity: 'success',
                summary: 'Saved',
                detail: 'Product saved successfully',
                life: 3000,
            });
            form.reset();
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Please check the form fields',
                life: 3000,
            });
        },
    };

    if (isEditing.value) {
        form.transform((data) => ({
            ...data,
            batch_items: [],
            _method: 'put',
        })).post(route('products.update', form.id), options);
    } else {
        form.transform((data) => ({
            ...data,
            batch_items: batchMode.value ? batchItemsPayload() : null,
        })).post(route('products.store'), options);
    }
};

const confirmDeleteProduct = (prod) => {
    product.value = prod;
    deleteDialog.value = true;
};

const deleteProduct = () => {
    router.delete(route('products.destroy', product.value.id), {
        onSuccess: () => {
            deleteDialog.value = false;
            product.value = {};
            toast.add({
                severity: 'success',
                summary: 'Deleted',
                detail: 'Product deleted successfully',
                life: 3000,
            });
        },
    });
};

const printSelected = () => {
    if (selectedProductIds.value.length === 0) return;
    const ids = selectedProductIds.value.join(',');
    window.open(route('products.print_barcodes') + '?ids=' + ids, '_blank');
};

const openBulkActionDialog = () => {
    bulkForm.reset();
    bulkForm.clearErrors();
    bulkForm.product_ids = [...selectedProductIds.value];
    bulkActionDialog.value = true;
};

const applyBulkUpdate = () => {
    bulkForm.transform((data) => ({
        ...data,
        product_ids: [...selectedProductIds.value],
    })).post(route('products.bulk-update'), {
        preserveScroll: true,
        onSuccess: () => {
            bulkActionDialog.value = false;
            selectedProductIds.value = [];
            toast.add({
                severity: 'success',
                summary: 'Updated',
                detail: 'Selected products updated successfully',
                life: 3000,
            });
            bulkForm.reset();
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Bulk Update Failed',
                detail: 'Please choose at least one field to update.',
                life: 3000,
            });
        },
    });
};

const runQuickScan = async () => {
    const barcode = scanBarcode.value.trim();

    if (!barcode || isScanning.value) return;

    isScanning.value = true;

    try {
        const response = await fetch(route('products.scan', { barcode }), {
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('Not found');
        }

        const payload = await response.json();
        editProduct(payload.product);
        scanBarcode.value = '';

        toast.add({
            severity: 'success',
            summary: 'Product Found',
            detail: 'Opened product from barcode scan',
            life: 2500,
        });
    } catch {
        toast.add({
            severity: 'warn',
            summary: 'Not Found',
            detail: 'No gold product found for this barcode.',
            life: 3000,
        });
    } finally {
        isScanning.value = false;
    }
};

const openHistoryDrawer = async (prod) => {
    historyDrawerVisible.value = true;
    historyLoading.value = true;
    historyProduct.value = null;
    historyTimeline.value = [];

    try {
        const response = await fetch(route('products.history', prod.id), {
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('Unable to load history');
        }

        const payload = await response.json();
        historyProduct.value = payload.product;
        historyTimeline.value = payload.timeline || [];
    } catch {
        toast.add({
            severity: 'error',
            summary: 'History Unavailable',
            detail: 'Unable to load product history right now.',
            life: 3000,
        });
        historyDrawerVisible.value = false;
    } finally {
        historyLoading.value = false;
    }
};

const formatHistoryMeta = (meta) => {
    if (!meta) return [];

    return Object.entries(meta)
        .filter(([, value]) => value !== null && value !== undefined && value !== '')
        .map(([key, value]) => ({
            key: key.replaceAll('_', ' '),
            value,
        }));
};

const copyBarcode = async (barcode) => {
    if (!barcode) {
        toast.add({
            severity: 'warn',
            summary: 'No Barcode',
            detail: 'This product does not have a barcode to copy.',
            life: 2500,
        });
        return;
    }

    try {
        await navigator.clipboard.writeText(barcode);
        toast.add({
            severity: 'success',
            summary: 'Barcode Copied',
            detail: `${barcode} copied to clipboard`,
            life: 2500,
        });
    } catch {
        toast.add({
            severity: 'error',
            summary: 'Copy Failed',
            detail: 'Unable to copy barcode from this browser.',
            life: 2500,
        });
    }
};
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="border-b border-surface-200 bg-white px-5 py-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <!-- Left -->
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-2xl font-semibold tracking-tight text-surface-900">Product Inventory</h1>
                            <Tag value="Inventory" severity="secondary" />
                        </div>

                        <p class="mt-1 text-sm text-surface-500">Manage jewellery items, weights, purity, supplier details, and barcode printing</p>
                    </div>
                </div>
            </div>

            <!-- Search / Table -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Total Items</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ props.summary?.total_items || 0 }}</p>
                    <p class="mt-1 text-xs text-surface-500">Available: {{ props.summary?.available_items || 0 }}</p>
                </div>

                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Sold Items</p>
                    <p class="mt-2 text-2xl font-semibold text-red-600">{{ props.summary?.sold_items || 0 }}</p>
                    <p class="mt-1 text-xs text-surface-500">Net sold weight: {{ formatWeight(props.summary?.sold_weight) }}</p>
                </div>

                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Total Gross Weight</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ formatWeight(props.summary?.gross_weight) }}</p>
                    <p class="mt-1 text-xs text-surface-500">Across filtered product records</p>
                </div>

                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Total Net Weight</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ formatWeight(props.summary?.net_weight) }}</p>
                    <p class="mt-1 text-xs text-surface-500">Sale-weight basis</p>
                </div>
            </div>

            <div class="border border-surface-200 bg-white">
                <div class="border-b border-surface-200 px-5 py-4">
                    <h3 class="text-base font-semibold text-surface-900">Category Breakdown</h3>
                    <p class="mt-1 text-sm text-surface-500">Item counts and total weights by category</p>
                </div>

                <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="entry in category_breakdown" :key="entry.category" class="border border-surface-200 bg-surface-50 px-4 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-surface-900">{{ entry.category }}</p>
                                <p class="mt-1 text-xs text-surface-500">{{ entry.items_count }} item{{ entry.items_count === 1 ? '' : 's' }}</p>
                            </div>
                            <Tag :value="`${entry.sold_count} sold`" severity="warn" />
                        </div>

                        <div class="mt-4 space-y-2 text-sm">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-surface-500">Gross Weight</span>
                                <span class="font-medium text-surface-900">{{ formatWeight(entry.gross_weight) }}</span>
                            </div>

                            <div class="flex items-center justify-between gap-3">
                                <span class="text-surface-500">Net Weight</span>
                                <span class="font-medium text-surface-900">{{ formatWeight(entry.net_weight) }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="!category_breakdown?.length" class="border border-dashed border-surface-300 bg-white px-4 py-8 text-center text-sm text-surface-500">
                        No category inventory found for the current filter.
                    </div>
                </div>
            </div>

            <div class="card overflow-hidden !p-0">
                <div class="border-b border-surface-200 bg-white px-5 py-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-surface-900">Inventory List</h3>
                            <p class="mt-1 text-sm text-surface-500">Search products, select rows, and print barcode labels from one place.</p>
                        </div>

                        <div class="flex w-full flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                            <Button label="Print Selected Barcodes" severity="warn" outlined :disabled="selectedProductIds.length === 0" @click="printSelected" class="!w-auto shrink-0 whitespace-nowrap" />
                            <Button label="New Product" @click="openNew" class="!w-auto shrink-0 whitespace-nowrap" />
                        </div>
                    </div>
                </div>

                <div class="border-b border-surface-200 bg-surface-50 px-5 py-4">
                    <div class="flex flex-col gap-4">
                        <div class="grid gap-4 2xl:grid-cols-[minmax(0,1fr)_24rem]">
                            <div class="border border-surface-200 bg-white px-4 py-4">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-surface-900">Filters</p>
                                        <p class="mt-1 text-xs text-surface-500">Narrow inventory by stock, category, supplier, purity, or barcode search.</p>
                                    </div>

                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center lg:shrink-0">
                                        <Tag :value="`${activeFilterCount} active`" severity="secondary" />
                                        <Button label="Reset" text severity="secondary" :disabled="activeFilterCount === 0" @click="resetFilters" class="!w-full sm:!w-auto shrink-0 whitespace-nowrap" />
                                    </div>
                                </div>

                                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-[12rem_12rem_12rem_12rem_minmax(0,1fr)]">
                                    <Select v-model="stockStatusFilter" :options="stockStatusOptions" optionLabel="label" optionValue="value" placeholder="Filter by stock" class="w-full" />
                                    <Select v-model="categoryFilter" :options="categoryOptions" optionLabel="name" optionValue="id" placeholder="Filter by category" class="w-full" />
                                    <Select v-model="supplierFilter" :options="supplierOptions" optionLabel="company_name" optionValue="id" placeholder="Filter by supplier" class="w-full" />
                                    <Select v-model="purityFilter" :options="purityOptions" optionLabel="name" optionValue="id" placeholder="Filter by purity" class="w-full" />

                                    <div class="relative w-full">
                                        <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-surface-400" />
                                        <InputText v-model="search" placeholder="Search product by name or barcode..." class="w-full !pl-10" />
                                    </div>
                                </div>
                            </div>

                            <div class="border border-surface-200 bg-white px-4 py-4">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center border border-surface-200 bg-surface-50 text-surface-600">
                                        <ScanLine class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-sm font-medium text-surface-900">Quick Scan</p>
                                            <Tag value="Barcode" severity="contrast" />
                                        </div>
                                        <p class="mt-1 text-xs text-surface-500">Open matching gold product fast by barcode.</p>
                                    </div>
                                </div>

                                <div class="mt-4 space-y-3">
                                    <div class="flex flex-col gap-3 sm:flex-row">
                                        <div class="relative min-w-0 flex-1">
                                            <i class="pi pi-barcode pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-surface-400" />
                                            <InputText
                                                v-model="scanBarcode"
                                                placeholder="Scan barcode or type product code..."
                                                class="w-full !pl-10"
                                                @keydown.enter.prevent="runQuickScan"
                                            />
                                        </div>
                                        <Button label="Open" icon="pi pi-arrow-right" :loading="isScanning" @click="runQuickScan" class="!w-full sm:!w-auto shrink-0 whitespace-nowrap" />
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2 text-xs text-surface-500">
                                        <span class="border border-surface-200 bg-surface-50 px-2 py-1">Example: G00025</span>
                                        <span>Press Enter after scan</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div v-if="selectedProductIds.length > 0" class="border-b border-amber-200 bg-amber-50 px-5 py-3">
                    <div class="flex flex-col items-center justify-center gap-3 text-center text-sm lg:flex-row lg:justify-between lg:text-left">
                        <p class="font-medium text-amber-800">{{ selectedProductIds.length }} product{{ selectedProductIds.length === 1 ? '' : 's' }} selected for barcode printing.</p>
                        <div class="flex flex-wrap items-center justify-center gap-2 lg:justify-end">
                            <Button label="Bulk Update" severity="secondary" outlined @click="openBulkActionDialog" class="!w-auto shrink-0 whitespace-nowrap" />
                            <Button label="Print Selected Barcodes" severity="warn" outlined @click="printSelected" class="!w-auto shrink-0 whitespace-nowrap" />
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4">
                    <DataTable :value="products.data" v-model:selection="currentPageSelection" dataKey="id" stripedRows rowHover tableStyle="min-width: 76rem">
                        <template #empty>
                            <div class="py-12 text-center text-surface-500">No products found</div>
                        </template>

                        <Column selectionMode="multiple" headerStyle="width: 3rem" />

                        <Column header="Image" style="width: 90px">
                            <template #body="{ data }">
                                <div v-if="data.image_path">
                                    <Image :src="`/storage/${data.image_path}`" alt="Product image" width="52" preview />
                                </div>
                                <div v-else class="flex h-12 w-12 items-center justify-center border border-surface-200 bg-surface-50 text-xs text-surface-400">No Img</div>
                            </template>
                        </Column>

                        <Column header="Barcode" style="width: 170px">
                            <template #body="{ data }">
                                <button
                                    type="button"
                                    class="border border-surface-200 bg-surface-50 px-3 py-2 text-left text-xs font-medium tracking-wide text-surface-700 transition hover:bg-surface-100"
                                    @click.stop="copyBarcode(data.barcode)"
                                >
                                    {{ data.barcode || 'No Barcode' }}
                                </button>
                            </template>
                        </Column>

                        <Column header="Date" sortable style="width: 140px">
                            <template #body="{ data }">
                                <div class="text-sm font-medium text-surface-900">
                                    {{ formatDate(data.created_at) }}
                                </div>
                            </template>
                        </Column>

                        <Column header="Category" sortable style="min-width: 230px">
                            <template #body="{ data }">
                                <div>
                                    <p class="font-medium text-surface-900">{{ data.category?.name || '—' }}</p>
                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-surface-500">
                                        <span>{{ data.name }}</span>
                                        <template v-if="data.supplier?.company_name">
                                            <span class="text-surface-300">•</span>
                                            <span>{{ data.supplier.company_name }}</span>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </Column>

                        <Column field="purity.name" header="Purity" sortable style="width: 130px">
                            <template #body="{ data }">
                                <Tag :value="data.purity?.name || '—'" severity="info" />
                            </template>
                        </Column>

                        <Column header="Weight Position" style="width: 220px">
                            <template #body="{ data }">
                                <div class="space-y-1 text-sm">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-surface-500">Gross</span>
                                        <span class="font-medium text-surface-900">
                                            {{ formatWeight(data.gross_weight) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-surface-500">Net</span>
                                        <span class="font-medium text-surface-900">
                                            {{ formatWeight(data.net_weight) }}
                                        </span>
                                    </div>
                                </div>
                            </template>
                        </Column>

                        <Column header="Charges" style="width: 170px">
                            <template #body="{ data }">
                                <div class="text-sm">
                                    <p class="text-xs tracking-wide text-surface-500 uppercase">Making</p>
                                    <p class="mt-1 font-semibold text-surface-900">{{ Number(data.making_charge || 0).toFixed(2) }}%</p>
                                </div>
                            </template>
                        </Column>

                        <Column header="Status" style="width: 130px">
                            <template #body="{ data }">
                                <Tag :value="data.is_sold ? 'Sold' : 'In Stock'" :severity="data.is_sold ? 'danger' : 'success'" />
                            </template>
                        </Column>

                        <Column header="Action" style="width: 120px">
                            <template #body="{ data }">
                                <div class="flex justify-end gap-1">
                                    <Button icon="pi pi-clock" size="small" text severity="secondary" @click="openHistoryDrawer(data)" />
                                    <Button icon="pi pi-copy" size="small" text severity="secondary" @click="confirmDuplicateProduct(data)" />
                                    <Button icon="pi pi-pencil" size="small" text severity="secondary" @click="editProduct(data)" />
                                    <Button icon="pi pi-trash" size="small" text severity="danger" @click="confirmDeleteProduct(data)" />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <Paginator :rows="products.per_page" :totalRecords="products.total" :first="(products.current_page - 1) * products.per_page" @page="onPageChange" class="border-t border-surface-200" />
            </div>

            <!-- Product Dialog -->
            <Dialog v-model:visible="productDialog" :header="isEditing ? 'Edit Product' : 'Add New Product'" modal :style="{ width: '42rem' }">
                <form @submit.prevent="saveProduct" class="space-y-5 pt-2">
                    <div v-if="!isEditing" class="border border-surface-200 bg-surface-50 p-4">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p class="text-sm font-medium text-surface-900">Create Multiple Products</p>
                                <p class="mt-1 text-xs text-surface-500">Use this when the same product details have different gross/net weights. Maximum 10 rows.</p>
                            </div>
                            <Button
                                :label="batchMode ? 'Single Product' : 'Multiple Products'"
                                :severity="batchMode ? 'secondary' : 'primary'"
                                outlined
                                type="button"
                                @click="batchMode = !batchMode"
                            />
                        </div>
                    </div>

                    <div class="border border-surface-200 bg-surface-50 p-4">
                        <div class="flex items-start gap-4">
                            <div class="flex-1">
                                <label class="mb-2 block text-sm font-medium text-surface-700"> Product Image </label>

                                <FileUpload
                                    mode="basic"
                                    name="image"
                                    accept="image/*"
                                    :maxFileSize="2000000"
                                    @select="onFileSelect"
                                    :auto="false"
                                    chooseLabel="Choose Photo"
                                    class="p-button-outlined"
                                />

                                <small class="mt-2 block text-xs text-surface-400"> Max size: 2MB </small>
                                <small v-if="form.errors.image" class="mt-1 block text-xs text-red-500">
                                    {{ form.errors.image }}
                                </small>
                            </div>

                            <div v-if="previewImage">
                                <img :src="previewImage" class="h-16 w-16 border border-surface-200 object-cover" />
                            </div>
                            <div v-else class="flex h-16 w-16 items-center justify-center border border-dashed border-surface-300 bg-white text-xs text-surface-400">No Img</div>
                        </div>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700"> Product Name </label>
                        <InputText v-model="form.name" required autofocus class="w-full" :class="{ 'p-invalid': form.errors.name }" />
                        <small v-if="form.errors.name" class="mt-1 block text-xs text-red-500">
                            {{ form.errors.name }}
                        </small>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700"> Supplier </label>
                        <Select v-model="form.supplier_id" :options="suppliers" optionLabel="company_name" optionValue="id" placeholder="Select supplier" class="w-full" />
                        <small v-if="form.errors.supplier_id" class="mt-1 block text-xs text-red-500">
                            {{ form.errors.supplier_id }}
                        </small>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-surface-700"> Category </label>
                            <Select v-model="form.category_id" :options="categories" optionLabel="name" optionValue="id" placeholder="Select category" class="w-full" />
                            <small v-if="form.errors.category_id" class="mt-1 block text-xs text-red-500">
                                {{ form.errors.category_id }}
                            </small>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-surface-700"> Purity </label>
                            <Select v-model="form.purity_id" :options="purities" optionLabel="name" optionValue="id" placeholder="Select purity" class="w-full" />
                            <small v-if="form.errors.purity_id" class="mt-1 block text-xs text-red-500">
                                {{ form.errors.purity_id }}
                            </small>
                        </div>
                    </div>

                    <div v-if="!batchMode" class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-surface-700"> Gross Weight (g) </label>
                            <InputNumber v-model="form.gross_weight" :minFractionDigits="2" suffix=" g" class="w-full" />
                            <small v-if="form.errors.gross_weight" class="mt-1 block text-xs text-red-500">
                                {{ form.errors.gross_weight }}
                            </small>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-surface-700"> Net Weight (g) </label>
                            <InputNumber v-model="form.net_weight" :minFractionDigits="2" suffix=" g" class="w-full" />
                            <small v-if="form.errors.net_weight" class="mt-1 block text-xs text-red-500">
                                {{ form.errors.net_weight }}
                            </small>
                        </div>
                    </div>

                    <div v-else class="space-y-3 border border-surface-200 bg-surface-50 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-surface-900">Weight Rows</p>
                                <p class="mt-1 text-xs text-surface-500">Each row creates one gold product and gets its own barcode.</p>
                            </div>
                            <Button label="Add Row" icon="pi pi-plus" size="small" outlined type="button" :disabled="batchRows.length >= 10" @click="addBatchRow" />
                        </div>

                        <div class="space-y-3">
                            <div v-for="(row, index) in batchRows" :key="index" class="grid grid-cols-[2rem_1fr_1fr_auto] items-end gap-3">
                                <div class="pb-3 text-sm font-medium text-surface-500">#{{ index + 1 }}</div>
                                <div>
                                    <label class="mb-2 block text-xs font-medium text-surface-600">Gross Weight</label>
                                    <InputNumber v-model="row.gross_weight" :minFractionDigits="2" suffix=" g" class="w-full" />
                                </div>
                                <div>
                                    <label class="mb-2 block text-xs font-medium text-surface-600">Net Weight</label>
                                    <InputNumber v-model="row.net_weight" :minFractionDigits="2" suffix=" g" class="w-full" />
                                </div>
                                <Button icon="pi pi-trash" severity="danger" text type="button" :disabled="batchRows.length === 1" @click="removeBatchRow(index)" />
                            </div>
                        </div>

                        <small v-if="form.errors.batch_items" class="block text-xs text-red-500">{{ form.errors.batch_items }}</small>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700"> Making Charge (%) </label>
                        <InputNumber v-model="form.making_charge" mode="decimal" suffix=" %" :min="0" :max="100" :minFractionDigits="2" :maxFractionDigits="2" class="w-full" />
                        <small class="mt-1 block text-xs text-surface-500">Example: enter 10 for 10% making on the gold rate.</small>
                        <small v-if="form.errors.making_charge" class="mt-1 block text-xs text-red-500">
                            {{ form.errors.making_charge }}
                        </small>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-surface-200 pt-4">
                        <Button label="Cancel" text severity="secondary" type="button" @click="productDialog = false" />
                        <Button :label="isEditing ? 'Update Product' : 'Save Product'" type="submit" :loading="form.processing" />
                    </div>
                </form>
            </Dialog>

            <Dialog v-model:visible="bulkActionDialog" header="Bulk Update Products" modal :style="{ width: '34rem' }">
                <form @submit.prevent="applyBulkUpdate" class="space-y-5 pt-2">
                    <div class="border border-surface-200 bg-surface-50 p-4">
                        <p class="text-sm font-medium text-surface-900">{{ selectedProductIds.length }} product{{ selectedProductIds.length === 1 ? '' : 's' }} selected</p>
                        <p class="mt-1 text-xs text-surface-500">Choose only fields you want to change. Empty fields will be ignored.</p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Supplier</label>
                        <Select v-model="bulkForm.supplier_id" :options="suppliers" optionLabel="company_name" optionValue="id" placeholder="Leave unchanged" class="w-full" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-surface-700">Category</label>
                            <Select v-model="bulkForm.category_id" :options="categories" optionLabel="name" optionValue="id" placeholder="Leave unchanged" class="w-full" />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-surface-700">Purity</label>
                            <Select v-model="bulkForm.purity_id" :options="purities" optionLabel="name" optionValue="id" placeholder="Leave unchanged" class="w-full" />
                        </div>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Making Charge (%)</label>
                        <InputNumber v-model="bulkForm.making_charge" mode="decimal" suffix=" %" :min="0" :max="100" :minFractionDigits="2" :maxFractionDigits="2" class="w-full" />
                    </div>

                    <small v-if="bulkForm.errors.bulk_update" class="block text-xs text-red-500">{{ bulkForm.errors.bulk_update }}</small>

                    <div class="flex justify-end gap-2 border-t border-surface-200 pt-4">
                        <Button label="Cancel" text severity="secondary" type="button" @click="bulkActionDialog = false" />
                        <Button label="Apply Changes" type="submit" :loading="bulkForm.processing" />
                    </div>
                </form>
            </Dialog>

            <Dialog v-model:visible="duplicateDialog" header="Duplicate Product" modal :style="{ width: '30rem' }">
                <div class="space-y-4 pt-2">
                    <div class="border border-surface-200 bg-surface-50 p-4">
                        <p class="font-medium text-surface-900">Create copy of this product?</p>
                        <p class="mt-1 text-sm text-surface-500">
                            This will create new stock item from
                            <span class="font-medium text-surface-900">{{ product.name }}</span>.
                        </p>
                    </div>

                    <div class="space-y-3 border border-surface-200 bg-white p-4 text-sm">
                        <div class="flex items-start gap-3">
                            <i class="pi pi-check-circle mt-0.5 text-emerald-600" />
                            <p class="text-surface-700">New product will get its own new barcode.</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <i class="pi pi-check-circle mt-0.5 text-emerald-600" />
                            <p class="text-surface-700">Category, purity, supplier, weights, making charge will be copied.</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <i class="pi pi-check-circle mt-0.5 text-emerald-600" />
                            <p class="text-surface-700">Image will not be copied. Add fresh photo to duplicate if needed.</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <i class="pi pi-info-circle mt-0.5 text-surface-500" />
                            <p class="text-surface-700">Original product will stay unchanged.</p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-surface-200 pt-4">
                        <Button label="Cancel" text severity="secondary" @click="duplicateDialog = false" />
                        <Button label="Create Duplicate" icon="pi pi-copy" @click="duplicateProduct" />
                    </div>
                </div>
            </Dialog>

            <Drawer v-model:visible="historyDrawerVisible" position="right" class="!w-full md:!w-[34rem]" header="Product History">
                <div class="flex h-full min-h-0 flex-col">
                    <div v-if="historyLoading" class="flex flex-1 items-center justify-center text-sm text-surface-500">
                        Loading history...
                    </div>

                    <template v-else>
                        <div v-if="historyProduct" class="space-y-4">
                            <div class="border border-surface-200 bg-surface-50 px-4 py-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-medium text-surface-900">{{ historyProduct.name }}</p>
                                        <p class="mt-1 text-xs text-surface-500">{{ historyProduct.barcode }} <span class="text-surface-300">•</span> {{ historyProduct.status }}</p>
                                    </div>
                                    <Tag :value="historyProduct.status" :severity="historyProduct.status === 'Sold' ? 'danger' : 'success'" />
                                </div>

                                <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                    <div class="border border-surface-200 bg-white px-3 py-3">
                                        <p class="text-xs uppercase tracking-wide text-surface-500">Gross</p>
                                        <p class="mt-1 font-medium text-surface-900">{{ formatWeight(historyProduct.gross_weight) }}</p>
                                    </div>
                                    <div class="border border-surface-200 bg-white px-3 py-3">
                                        <p class="text-xs uppercase tracking-wide text-surface-500">Net</p>
                                        <p class="mt-1 font-medium text-surface-900">{{ formatWeight(historyProduct.net_weight) }}</p>
                                    </div>
                                    <div class="border border-surface-200 bg-white px-3 py-3">
                                        <p class="text-xs uppercase tracking-wide text-surface-500">Category</p>
                                        <p class="mt-1 font-medium text-surface-900">{{ historyProduct.category || '—' }}</p>
                                    </div>
                                    <div class="border border-surface-200 bg-white px-3 py-3">
                                        <p class="text-xs uppercase tracking-wide text-surface-500">Purity</p>
                                        <p class="mt-1 font-medium text-surface-900">{{ historyProduct.purity || '—' }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div v-for="(event, index) in historyTimeline" :key="`${event.type}-${event.occurred_at}-${index}`" class="border border-surface-200 bg-white px-4 py-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-medium text-surface-900">{{ event.title }}</p>
                                            <p class="mt-1 text-xs text-surface-500">{{ formatDateTime(event.occurred_at) }}</p>
                                        </div>
                                        <Tag :value="event.type.replaceAll('_', ' ')" severity="secondary" />
                                    </div>

                                    <div v-if="formatHistoryMeta(event.meta).length" class="mt-4 grid gap-2 text-sm">
                                        <div v-for="item in formatHistoryMeta(event.meta)" :key="item.key" class="flex items-center justify-between gap-3 border-t border-surface-100 pt-2 first:border-t-0 first:pt-0">
                                            <span class="capitalize text-surface-500">{{ item.key }}</span>
                                            <span class="text-right font-medium text-surface-900">{{ item.value }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="!historyTimeline.length" class="border border-dashed border-surface-300 bg-white px-4 py-8 text-center text-sm text-surface-500">
                                    No timeline events found for this product.
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </Drawer>

            <!-- Delete Dialog -->
            <Dialog v-model:visible="deleteDialog" header="Confirm Delete" modal :style="{ width: '28rem' }">
                <div class="space-y-4 pt-2">
                    <div class="flex items-start gap-3 border border-surface-200 bg-surface-50 p-4">
                        <i class="pi pi-exclamation-triangle mt-0.5 text-xl text-red-500" />
                        <div>
                            <p class="font-medium text-surface-900">Delete product</p>
                            <p class="mt-1 text-sm text-surface-500">
                                Are you sure you want to delete
                                <span class="font-medium text-surface-900">{{ product.name }}</span
                                >?
                            </p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-surface-200 pt-4">
                        <Button label="No" text severity="secondary" @click="deleteDialog = false" />
                        <Button label="Yes, Delete" severity="danger" @click="deleteProduct" />
                    </div>
                </div>
            </Dialog>
        </div>
    </AppLayout>
</template>
