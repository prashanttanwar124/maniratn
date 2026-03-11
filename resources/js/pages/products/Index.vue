<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import throttle from 'lodash/throttle';
import { Search } from 'lucide-vue-next';
import { useToast } from 'primevue/usetoast';
import { ref, watch } from 'vue';
import { route } from 'ziggy-js';

import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import FileUpload from 'primevue/fileupload';
import Image from 'primevue/image';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Paginator from 'primevue/paginator';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';

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
const product = ref({});
const isEditing = ref(false);
const previewImage = ref(null);
const selectedProducts = ref([]);

const search = ref(props.filters?.search || '');

watch(
    search,
    throttle((value) => {
        router.get(
            route('products.index'),
            { search: value },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }, 300),
);

const form = useForm({
    id: null,
    name: '',
    category_id: null,
    purity_id: null,
    supplier_id: null,
    gross_weight: null,
    net_weight: null,
    making_charge: null,
    image: null,
});

const formatCurrency = (val) =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 0,
    }).format(val || 0);

const formatWeight = (val) => `${Number(val || 0).toFixed(3)} g`;

const onPageChange = (event) => {
    const newPage = event.page + 1;
    router.get(route('products.index'), { page: newPage, search: search.value }, { preserveScroll: true, preserveState: true });
};

const onFileSelect = (event) => {
    const file = event.files[0];
    form.image = file;
    previewImage.value = URL.createObjectURL(file);
};

const openNew = () => {
    product.value = {};
    isEditing.value = false;
    previewImage.value = null;

    form.reset();
    form.clearErrors();

    if (props.categories.length) form.category_id = props.categories[0].id;
    if (props.purities.length) form.purity_id = props.purities[0].id;

    productDialog.value = true;
};

const editProduct = (prod) => {
    product.value = { ...prod };
    isEditing.value = true;

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

const saveProduct = () => {
    const options = {
        forceFormData: true,
        onSuccess: () => {
            productDialog.value = false;
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
            _method: 'put',
        })).post(route('products.update', form.id), options);
    } else {
        form.post(route('products.store'), options);
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
    if (selectedProducts.value.length === 0) return;
    const ids = selectedProducts.value.map((p) => p.id).join(',');
    window.open(route('products.print_barcodes') + '?ids=' + ids, '_blank');
};
</script>

<template>
    <AppLayout>
        <Toast />

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

                    <!-- Right -->
                    <div class="flex shrink-0 items-center gap-2">
                        <Button v-if="selectedProducts.length > 0" label="Print Barcodes" severity="warn" outlined @click="printSelected" class="!w-auto shrink-0 whitespace-nowrap" />

                        <Button label="New Product" @click="openNew" class="!w-auto shrink-0 whitespace-nowrap" />
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
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-surface-900">Inventory List</h3>
                            <p class="mt-1 text-sm text-surface-500">Search and manage all product records</p>
                        </div>

                        <div class="relative w-full lg:w-80">
                            <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-surface-400" />
                            <InputText v-model="search" placeholder="Search product by name..." class="w-full !pl-10" />
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4">
                    <DataTable :value="products.data" v-model:selection="selectedProducts" dataKey="id" stripedRows rowHover tableStyle="min-width: 62rem">
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

                        <Column field="name" header="Product" sortable>
                            <template #body="{ data }">
                                <div>
                                    <p class="font-medium text-surface-900">{{ data.name }}</p>
                                    <p class="mt-1 text-xs text-surface-500">
                                        {{ data.category?.name || '—' }}
                                    </p>
                                </div>
                            </template>
                        </Column>

                        <Column field="purity.name" header="Purity" sortable style="width: 130px">
                            <template #body="{ data }">
                                <Tag :value="data.purity?.name || '—'" severity="info" />
                            </template>
                        </Column>

                        <Column header="Weights" style="width: 170px">
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

                        <Column header="Supplier" style="min-width: 180px">
                            <template #body="{ data }">
                                <span class="text-surface-700">
                                    {{ data.supplier?.company_name || '—' }}
                                </span>
                            </template>
                        </Column>

                        <Column header="Making Charge" style="width: 160px">
                            <template #body="{ data }">
                                <span class="font-medium text-surface-900">
                                    {{ formatCurrency(data.making_charge) }}
                                </span>
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
            <Dialog v-model:visible="productDialog" :header="isEditing ? 'Edit Product' : 'Add New Product'" modal :style="{ width: '34rem' }">
                <form @submit.prevent="saveProduct" class="space-y-5 pt-2">
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

                    <div class="grid grid-cols-2 gap-4">
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

                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700"> Making Charge </label>
                        <InputNumber v-model="form.making_charge" mode="currency" currency="INR" locale="en-IN" class="w-full" />
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
