<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { printLabelsViaQz } from '@/utils/qzTray';
import { router, useForm } from '@inertiajs/vue3';
import throttle from 'lodash/throttle';
import { Search } from 'lucide-vue-next';
import { useToast } from 'primevue/usetoast';
import { computed, ref, watch } from 'vue';
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
import Textarea from 'primevue/textarea';

const props = defineProps({
    silverProducts: Object,
    categories: Array,
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
        router.get(route('silver-products.index'), { search: value }, { preserveState: true, preserveScroll: true, replace: true });
    }, 300),
);

const pricingModes = [
    { label: 'Per Piece', value: 'PIECE' },
    { label: 'By Weight', value: 'WEIGHT' },
];

const form = useForm({
    id: null,
    name: '',
    category_id: null,
    supplier_id: null,
    pricing_mode: 'PIECE',
    quantity: 1,
    gross_weight: null,
    net_weight: null,
    piece_price: null,
    making_charge: 0,
    notes: '',
    image: null,
});

const isWeightMode = computed(() => form.pricing_mode === 'WEIGHT');
const formatWeight = (val) => `${Number(val || 0).toFixed(3)} g`;
const formatCurrency = (val) =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 0,
    }).format(val || 0);

const onPageChange = (event) => {
    router.get(route('silver-products.index'), { page: event.page + 1, search: search.value }, { preserveScroll: true, preserveState: true });
};

const onFileSelect = (event) => {
    const file = event.files[0];
    form.image = file;
    previewImage.value = URL.createObjectURL(file);
};

const resetForm = () => {
    form.reset();
    form.clearErrors();
    form.pricing_mode = 'PIECE';
    form.quantity = 1;
    form.making_charge = 0;
    if (props.categories.length) form.category_id = props.categories[0].id;
    if (props.suppliers.length) form.supplier_id = props.suppliers[0].id;
    previewImage.value = null;
};

const openNew = () => {
    product.value = {};
    isEditing.value = false;
    resetForm();
    productDialog.value = true;
};

const editProduct = (item) => {
    product.value = { ...item };
    isEditing.value = true;
    form.clearErrors();
    form.id = item.id;
    form.name = item.name;
    form.category_id = item.category_id;
    form.supplier_id = item.supplier_id;
    form.pricing_mode = item.pricing_mode;
    form.quantity = Number(item.quantity);
    form.gross_weight = item.gross_weight ? Number(item.gross_weight) : null;
    form.net_weight = item.net_weight ? Number(item.net_weight) : null;
    form.piece_price = item.piece_price ? Number(item.piece_price) : null;
    form.making_charge = Number(item.making_charge || 0);
    form.notes = item.notes || '';
    form.image = null;
    previewImage.value = item.image_path ? `/storage/${item.image_path}` : null;
    productDialog.value = true;
};

const saveProduct = () => {
    const options = {
        forceFormData: true,
        onSuccess: () => {
            productDialog.value = false;
            resetForm();
            toast.add({ severity: 'success', summary: 'Saved', detail: 'Silver product saved successfully', life: 3000 });
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Please check the silver product form', life: 3000 });
        },
    };

    if (isEditing.value) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(route('silver-products.update', form.id), options);
        return;
    }

    form.post(route('silver-products.store'), options);
};

const confirmDeleteProduct = (item) => {
    product.value = item;
    deleteDialog.value = true;
};

const deleteProduct = () => {
    router.delete(route('silver-products.destroy', product.value.id), {
        onSuccess: () => {
            deleteDialog.value = false;
            product.value = {};
            toast.add({ severity: 'success', summary: 'Deleted', detail: 'Silver product deleted successfully', life: 3000 });
        },
    });
};

const printSelected = () => {
    if (selectedProducts.value.length === 0) return;
    const ids = selectedProducts.value.map((item) => item.id).join(',');
    window.open(route('silver-products.print_barcodes') + '?ids=' + ids, '_blank');
};

const printSelectedViaQz = async () => {
    if (selectedProducts.value.length === 0) return;

    try {
        const printer = await printLabelsViaQz(
            selectedProducts.value.map((product) => ({
                name: product.name,
                weight: product.net_weight || product.gross_weight,
                purity: 'Silver',
                code: product.barcode,
            })),
        );

        toast.add({
            severity: 'success',
            summary: 'QZ Print Sent',
            detail: `Sent ${selectedProducts.value.length} label(s) to ${printer}.`,
            life: 3500,
        });
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'QZ Print Failed',
            detail: error?.message || 'QZ Tray is not ready. Start QZ Tray and try again.',
            life: 4500,
        });
    }
};

const copyBarcode = async (barcode) => {
    if (!barcode) {
        toast.add({
            severity: 'warn',
            summary: 'No Barcode',
            detail: 'This silver product does not have a barcode to copy.',
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
            <div class="border-b border-surface-200 bg-white px-5 py-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-2xl font-semibold tracking-tight text-surface-900">Silver Inventory</h1>
                            <Tag value="Separate Module" severity="secondary" />
                        </div>
                        <p class="mt-1 text-sm text-surface-500">Track silver items separately from gold inventory, with per-piece or weight-based pricing.</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Total Silver SKUs</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ summary?.total_items || 0 }}</p>
                    <p class="mt-1 text-xs text-surface-500">Available: {{ summary?.available_items || 0 }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Total Quantity</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ summary?.total_quantity || 0 }}</p>
                    <p class="mt-1 text-xs text-surface-500">Piece-based and stock-count view</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Total Gross Weight</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ formatWeight(summary?.gross_weight) }}</p>
                    <p class="mt-1 text-xs text-surface-500">Across filtered silver products</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Total Net Weight</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ formatWeight(summary?.net_weight) }}</p>
                    <p class="mt-1 text-xs text-surface-500">Useful for weight-based silver stock</p>
                </div>
            </div>

            <div class="border border-surface-200 bg-white">
                <div class="border-b border-surface-200 px-5 py-4">
                    <h3 class="text-base font-semibold text-surface-900">Silver Category Breakdown</h3>
                    <p class="mt-1 text-sm text-surface-500">Counts, quantity, and weight split by silver category.</p>
                </div>

                <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="entry in category_breakdown" :key="entry.category" class="border border-surface-200 bg-surface-50 px-4 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-surface-900">{{ entry.category }}</p>
                                <p class="mt-1 text-xs text-surface-500">{{ entry.items_count }} sku{{ entry.items_count === 1 ? '' : 's' }} · {{ entry.quantity }} qty</p>
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
                        No silver inventory found for the current filter.
                    </div>
                </div>
            </div>

            <div class="card overflow-hidden !p-0">
                <div class="border-b border-surface-200 bg-white px-5 py-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-surface-900">Silver Product List</h3>
                            <p class="mt-1 text-sm text-surface-500">Search silver products, copy barcodes, and manage stock from one place.</p>
                        </div>

                        <div class="flex w-full flex-col gap-3 lg:w-auto lg:flex-row lg:items-center">
                            <div class="relative w-full lg:w-80">
                                <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-surface-400" />
                                <InputText v-model="search" placeholder="Search silver product by name..." class="w-full !pl-10" />
                            </div>

                            <Button label="Print via TSC" severity="contrast" :disabled="selectedProducts.length === 0" @click="printSelectedViaQz" class="!w-auto shrink-0 whitespace-nowrap" />
                            <Button label="New Silver Product" @click="openNew" class="!w-auto shrink-0 whitespace-nowrap" />
                        </div>
                    </div>
                </div>

                <div v-if="selectedProducts.length > 0" class="border-b border-amber-200 bg-amber-50 px-5 py-3">
                    <div class="flex flex-col items-center justify-center gap-3 text-center text-sm lg:flex-row lg:justify-between lg:text-left">
                        <p class="font-medium text-amber-800">{{ selectedProducts.length }} silver product{{ selectedProducts.length === 1 ? '' : 's' }} selected for barcode printing.</p>
                        <div class="flex flex-wrap items-center justify-center gap-2 lg:justify-end">
                            <Button label="Print via TSC" severity="contrast" @click="printSelectedViaQz" class="!w-auto shrink-0 whitespace-nowrap" />
                            <Button label="Print Selected Barcodes" severity="warn" outlined @click="printSelected" class="!w-auto shrink-0 whitespace-nowrap" />
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4">
                    <DataTable :value="silverProducts.data" v-model:selection="selectedProducts" dataKey="id" stripedRows rowHover tableStyle="min-width: 76rem">
                        <template #empty>
                            <div class="py-12 text-center text-surface-500">No silver products found</div>
                        </template>

                        <Column selectionMode="multiple" headerStyle="width: 3rem" />

                        <Column header="Image" style="width: 90px">
                            <template #body="{ data }">
                                <div v-if="data.image_path">
                                    <Image :src="`/storage/${data.image_path}`" alt="Silver product image" width="52" preview />
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

                        <Column field="name" header="Silver Product" sortable style="min-width: 230px">
                            <template #body="{ data }">
                                <div>
                                    <p class="font-medium text-surface-900">{{ data.name }}</p>
                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-surface-500">
                                        <span>{{ data.category?.name || '—' }}</span>
                                        <span class="text-surface-300">•</span>
                                        <span>{{ data.notes || 'Silver stock item' }}</span>
                                    </div>
                                </div>
                            </template>
                        </Column>

                        <Column header="Pricing" style="width: 220px">
                            <template #body="{ data }">
                                <div class="space-y-2">
                                    <Tag :value="data.pricing_mode === 'PIECE' ? 'Per Piece' : 'By Weight'" :severity="data.pricing_mode === 'PIECE' ? 'contrast' : 'info'" />
                                    <div class="text-sm">
                                        <p class="text-xs tracking-wide text-surface-500 uppercase">
                                            {{ data.pricing_mode === 'PIECE' ? 'Piece Price' : 'Weight Rate Input' }}
                                        </p>
                                        <p class="mt-1 font-semibold text-surface-900">
                                            {{ data.pricing_mode === 'PIECE' ? formatCurrency(data.piece_price) : 'Uses invoice silver rate' }}
                                        </p>
                                    </div>
                                </div>
                            </template>
                        </Column>

                        <Column header="Stock Position" style="width: 230px">
                            <template #body="{ data }">
                                <div class="space-y-1 text-sm">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-surface-500">Qty</span>
                                        <span class="font-medium text-surface-900">{{ data.quantity }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-surface-500">Gross</span>
                                        <span class="font-medium text-surface-900">{{ formatWeight(data.gross_weight) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-surface-500">Net</span>
                                        <span class="font-medium text-surface-900">{{ formatWeight(data.net_weight) }}</span>
                                    </div>
                                </div>
                            </template>
                        </Column>

                        <Column header="Charges" style="width: 150px">
                            <template #body="{ data }">
                                <div class="text-sm">
                                    <p class="text-xs tracking-wide text-surface-500 uppercase">Making</p>
                                    <p class="mt-1 font-semibold text-surface-900">{{ formatCurrency(data.making_charge) }}</p>
                                </div>
                            </template>
                        </Column>

                        <Column header="Status" style="width: 130px">
                            <template #body="{ data }">
                                <div class="space-y-2">
                                    <Tag :value="data.is_sold ? 'Sold' : 'In Stock'" :severity="data.is_sold ? 'danger' : 'success'" />
                                    <p class="text-xs text-surface-500">
                                        {{ data.is_sold ? 'No saleable stock left' : `${data.quantity} unit${data.quantity === 1 ? '' : 's'} available` }}
                                    </p>
                                </div>
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

                <Paginator
                    :rows="silverProducts.per_page"
                    :totalRecords="silverProducts.total"
                    :first="(silverProducts.current_page - 1) * silverProducts.per_page"
                    @page="onPageChange"
                    class="border-t border-surface-200"
                />
            </div>

            <Dialog v-model:visible="productDialog" :header="isEditing ? 'Edit Silver Product' : 'Add Silver Product'" modal :style="{ width: '38rem' }">
                <form @submit.prevent="saveProduct" class="space-y-5 pt-2">
                    <div class="border border-surface-200 bg-surface-50 p-4">
                        <div class="flex items-start gap-4">
                            <div class="flex-1">
                                <label class="mb-2 block text-sm font-medium text-surface-700">Silver Product Image</label>
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
                                <small class="mt-2 block text-xs text-surface-400">Max size: 2MB</small>
                                <small v-if="form.errors.image" class="mt-1 block text-xs text-red-500">{{ form.errors.image }}</small>
                            </div>

                            <div v-if="previewImage">
                                <img :src="previewImage" class="h-16 w-16 border border-surface-200 object-cover" />
                            </div>
                            <div v-else class="flex h-16 w-16 items-center justify-center border border-dashed border-surface-300 bg-white text-xs text-surface-400">No Img</div>
                        </div>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Silver Product Name</label>
                        <InputText v-model="form.name" class="w-full" :class="{ 'p-invalid': form.errors.name }" />
                        <small v-if="form.errors.name" class="mt-1 block text-xs text-red-500">{{ form.errors.name }}</small>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-surface-700">Supplier</label>
                            <Select v-model="form.supplier_id" :options="suppliers" optionLabel="company_name" optionValue="id" placeholder="Select supplier" class="w-full" />
                            <small v-if="form.errors.supplier_id" class="mt-1 block text-xs text-red-500">{{ form.errors.supplier_id }}</small>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-surface-700">Category</label>
                            <Select v-model="form.category_id" :options="categories" optionLabel="name" optionValue="id" placeholder="Select category" class="w-full" />
                            <small v-if="form.errors.category_id" class="mt-1 block text-xs text-red-500">{{ form.errors.category_id }}</small>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-surface-700">Pricing Mode</label>
                            <Select v-model="form.pricing_mode" :options="pricingModes" optionLabel="label" optionValue="value" class="w-full" />
                            <small v-if="form.errors.pricing_mode" class="mt-1 block text-xs text-red-500">{{ form.errors.pricing_mode }}</small>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-surface-700">Quantity</label>
                            <InputNumber v-model="form.quantity" :min="1" class="w-full" />
                            <small v-if="form.errors.quantity" class="mt-1 block text-xs text-red-500">{{ form.errors.quantity }}</small>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-surface-700">Gross Weight (g)</label>
                            <InputNumber v-model="form.gross_weight" :min="0" :minFractionDigits="3" suffix=" g" class="w-full" />
                            <small v-if="form.errors.gross_weight" class="mt-1 block text-xs text-red-500">{{ form.errors.gross_weight }}</small>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-surface-700">Net Weight (g)</label>
                            <InputNumber v-model="form.net_weight" :min="0" :minFractionDigits="3" suffix=" g" class="w-full" />
                            <small v-if="form.errors.net_weight" class="mt-1 block text-xs text-red-500">{{ form.errors.net_weight }}</small>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-surface-700">Piece Price</label>
                            <InputNumber v-model="form.piece_price" mode="currency" currency="INR" locale="en-IN" class="w-full" />
                            <small class="mt-1 block text-xs text-surface-400">{{ isWeightMode ? 'Optional for weight-based silver items.' : 'Used for per-piece silver billing.' }}</small>
                            <small v-if="form.errors.piece_price" class="mt-1 block text-xs text-red-500">{{ form.errors.piece_price }}</small>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-surface-700">Making Charge</label>
                            <InputNumber v-model="form.making_charge" mode="currency" currency="INR" locale="en-IN" class="w-full" />
                            <small v-if="form.errors.making_charge" class="mt-1 block text-xs text-red-500">{{ form.errors.making_charge }}</small>
                        </div>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Notes</label>
                        <Textarea v-model="form.notes" rows="3" class="w-full" />
                        <small v-if="form.errors.notes" class="mt-1 block text-xs text-red-500">{{ form.errors.notes }}</small>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-surface-200 pt-4">
                        <Button label="Cancel" text severity="secondary" type="button" @click="productDialog = false" />
                        <Button :label="isEditing ? 'Update Silver Product' : 'Save Silver Product'" type="submit" :loading="form.processing" />
                    </div>
                </form>
            </Dialog>

            <Dialog v-model:visible="deleteDialog" header="Confirm Delete" modal :style="{ width: '28rem' }">
                <div class="space-y-4 pt-2">
                    <div class="flex items-start gap-3 border border-surface-200 bg-surface-50 p-4">
                        <i class="pi pi-exclamation-triangle mt-0.5 text-xl text-red-500" />
                        <div>
                            <p class="font-medium text-surface-900">Delete silver product</p>
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
