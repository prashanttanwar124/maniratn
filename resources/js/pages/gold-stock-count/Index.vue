<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { ScanLine } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { route } from 'ziggy-js';

import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import FileUpload from 'primevue/fileupload';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    dayOpen: Boolean,
    isToday: Boolean,
    selectedDate: String,
    session: Object,
    summary: Object,
    categoryBreakdown: Array,
    recentCounted: Array,
    missingProducts: Array,
    categories: Array,
    purities: Array,
    suppliers: Array,
    counters: Array,
    selectedCategoryId: Number,
});

const toast = useToast();
const scanInput = ref('');
const scanInputRef = ref(null);
const scanning = ref(false);
const completing = ref(false);
const summary = ref(props.summary);
const session = ref(props.session);
const categoryBreakdown = ref(props.categoryBreakdown || []);
const recentCounted = ref(props.recentCounted || []);
const missingProducts = ref(props.missingProducts || []);
const selectedCategoryId = ref(props.selectedCategoryId || null);
const selectedDate = ref(props.selectedDate || new Date().toISOString().split('T')[0]);
const datePickerValue = ref(props.selectedDate ? new Date(props.selectedDate) : new Date());

const categoryOptions = computed(() => [{ id: null, name: 'All Gold Categories' }, ...(props.categories || [])]);

const isToday = computed(() => props.isToday ?? (selectedDate.value === new Date().toISOString().split('T')[0]));
const isCompleteReady = computed(() => isToday.value && props.dayOpen && Number(summary.value?.overall_remaining_items || 0) === 0 && Number(summary.value?.overall_expected_items || 0) > 0);
const selectedCategoryName = computed(() => props.categories?.find((category) => category.id === selectedCategoryId.value)?.name || 'All Gold Categories');
const canScan = computed(() => isToday.value && props.dayOpen && session.value?.status !== 'COMPLETED');

// Edit & Delete product state
const editDialog = ref(false);
const deleteDialog = ref(false);
const selectedProduct = ref(null);
const previewImage = ref(null);
const deleting = ref(false);

const form = useForm({
    id: null,
    name: '',
    category_id: null,
    purity_id: null,
    supplier_id: null,
    counter_id: null,
    gross_weight: null,
    net_weight: null,
    making_charge: null,
    image: null,
});

watch(() => props.recentCounted, (val) => {
    recentCounted.value = val || [];
});

watch(() => props.missingProducts, (val) => {
    missingProducts.value = val || [];
});

watch(() => props.summary, (val) => {
    summary.value = val;
});

watch(() => props.session, (val) => {
    session.value = val;
});

watch(() => props.categoryBreakdown, (val) => {
    categoryBreakdown.value = val || [];
});

watch(() => props.selectedDate, (val) => {
    if (val) {
        selectedDate.value = val;
        datePickerValue.value = new Date(val);
    }
});

const focusScanInput = async () => {
    if (!canScan.value) return;

    await nextTick();
    const input = scanInputRef.value?.$el || scanInputRef.value;
    input?.focus?.();
};

onMounted(focusScanInput);

const onDatePickerChange = (val) => {
    if (!val) return;
    const d = new Date(val);
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    selectedDate.value = `${year}-${month}-${day}`;
    changeDate();
};

const changeDate = () => {
    router.get(
        route('gold-stock-count.index'),
        {
            date: selectedDate.value || undefined,
            category_id: selectedCategoryId.value || undefined,
        },
        { preserveScroll: true, replace: true }
    );
};

const goToToday = () => {
    const today = new Date().toISOString().split('T')[0];
    selectedDate.value = today;
    datePickerValue.value = new Date();
    changeDate();
};

const selectCategory = (categoryId) => {
    selectedCategoryId.value = selectedCategoryId.value === categoryId ? null : categoryId;
    changeCategory();
};

const changeCategory = () => {
    router.get(
        route('gold-stock-count.index'),
        {
            date: selectedDate.value || undefined,
            category_id: selectedCategoryId.value || undefined,
        },
        { preserveScroll: true, replace: true }
    );
};

const formatWeight = (value) => `${Number(value || 0).toFixed(3)} g`;
const formatDateTime = (value) => {
    if (!value) return '—';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return '—';

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
};

const formatDate = (value) => {
    if (!value) return '—';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return value;

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(date);
};

const onFileSelect = (event) => {
    const file = event.files[0];
    form.image = file;
    if (file) {
        previewImage.value = URL.createObjectURL(file);
    }
};

const openEditProduct = (prod) => {
    selectedProduct.value = { ...prod };
    form.clearErrors();
    form.id = prod.id;
    form.name = prod.name || '';
    form.category_id = prod.category_id || null;
    form.purity_id = prod.purity_id || null;
    form.supplier_id = prod.supplier_id || null;
    form.counter_id = prod.counter_id || null;
    form.gross_weight = parseFloat(prod.gross_weight || 0);
    form.net_weight = parseFloat(prod.net_weight || 0);
    form.making_charge = parseFloat(prod.making_charge || 0);
    form.image = null;
    previewImage.value = prod.image_path ? `/storage/${prod.image_path}` : null;
    editDialog.value = true;
};

const saveProduct = () => {
    form.transform((data) => ({
        ...data,
        batch_items: [],
        _method: 'put',
    })).post(route('products.update', form.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            editDialog.value = false;
            toast.add({
                severity: 'success',
                summary: 'Product Updated',
                detail: 'Gold product updated successfully.',
                life: 3000,
            });
            form.reset();
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Update Failed',
                detail: 'Please check the form fields.',
                life: 3000,
            });
        },
    });
};

const confirmDeleteProduct = (prod) => {
    selectedProduct.value = { ...prod };
    deleteDialog.value = true;
};

const deleteProduct = () => {
    if (!selectedProduct.value?.id) return;

    deleting.value = true;
    router.delete(route('products.destroy', selectedProduct.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deleteDialog.value = false;
            deleting.value = false;
            toast.add({
                severity: 'success',
                summary: 'Deleted',
                detail: `${selectedProduct.value?.barcode || 'Product'} deleted successfully.`,
                life: 3000,
            });
            selectedProduct.value = null;
        },
        onError: (err) => {
            deleting.value = false;
            toast.add({
                severity: 'error',
                summary: 'Delete Failed',
                detail: err?.product || 'Unable to delete this product.',
                life: 3500,
            });
        },
    });
};

const scanBarcode = async () => {
    const barcode = scanInput.value.trim();

    if (!barcode || scanning.value || !canScan.value) return;

    scanInput.value = '';
    scanning.value = true;

    try {
        const response = await axios.post(route('gold-stock-count.scan'), {
            barcode,
            category_id: selectedCategoryId.value,
        });

        const payload = response.data || {};

        summary.value = payload.summary;
        categoryBreakdown.value = payload.categoryBreakdown || [];
        recentCounted.value = payload.recentCounted || [];
        missingProducts.value = payload.missingProducts || [];
        toast.add({
            severity: 'success',
            summary: 'Counted',
            detail: `${payload.countedProduct?.barcode} added to counted stock.`,
            life: 2500,
        });
    } catch (error) {
        const message = error?.response?.data?.message || 'Unable to count this barcode.';
        toast.add({
            severity: 'warn',
            summary: 'Scan Stopped',
            detail: message,
            life: 3000,
        });
    } finally {
        scanning.value = false;
        scanInput.value = '';
        await focusScanInput();
    }
};

const markComplete = async () => {
    if (!isCompleteReady.value || completing.value) return;

    completing.value = true;

    try {
        const response = await axios.post(route('gold-stock-count.complete'));
        const payload = response.data || {};

        session.value = {
            ...(session.value || {}),
            ...(payload.session || {}),
        };

        toast.add({
            severity: 'success',
            summary: 'Count Complete',
            detail: 'Gold stock count marked complete for today.',
            life: 3000,
        });
    } catch (error) {
        const message = error?.response?.data?.message || 'Unable to complete gold stock count.';
        toast.add({
            severity: 'error',
            summary: 'Complete Failed',
            detail: message,
            life: 3000,
        });
    } finally {
        completing.value = false;
    }
};
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <!-- Header section -->
            <div class="border-b border-surface-200 bg-white px-5 py-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-2xl font-semibold tracking-tight text-surface-900">Gold Stock Count</h1>
                            <Tag :value="isToday ? 'Night Count' : 'Archive'" :severity="isToday ? 'warn' : 'secondary'" />
                            <Tag v-if="session?.status === 'COMPLETED'" value="Completed" severity="success" />
                            <Tag v-else-if="session?.status === 'OPEN'" value="In Progress" severity="info" />
                            <Tag v-else-if="!session && !isToday" value="No Session on this Date" severity="secondary" />
                        </div>
                        <p class="mt-1 text-sm text-surface-500">
                            {{ isToday ? 'Scan unsold gold stock before close day and compare counted items with system stock.' : `Reviewing past gold stock count records for ${formatDate(selectedDate)}.` }}
                        </p>
                    </div>

                    <div v-if="isToday">
                        <Button label="Mark Complete" icon="pi pi-check" :disabled="!isCompleteReady" :loading="completing" @click="markComplete" class="!w-auto shrink-0 whitespace-nowrap" />
                    </div>
                </div>
            </div>

            <!-- Top Filter Controls Bar with PrimeVue DatePicker -->
            <div class="border border-surface-200 bg-white px-5 py-4">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex flex-wrap items-end gap-6">
                        <div class="flex flex-col">
                            <label class="mb-2 block text-sm font-medium text-surface-700">Count Date</label>
                            <div class="flex items-center gap-3">
                                <DatePicker
                                    v-model="datePickerValue"
                                    dateFormat="dd/mm/yy"
                                    showIcon
                                    iconDisplay="input"
                                    class="!h-10 !w-44"
                                    inputClass="!h-10 !w-full"
                                    @update:modelValue="onDatePickerChange"
                                />
                                <Button
                                    v-if="!isToday"
                                    label="Today"
                                    icon="pi pi-calendar"
                                    severity="secondary"
                                    outlined
                                    type="button"
                                    @click="goToToday"
                                    class="!h-10 shrink-0 whitespace-nowrap"
                                    title="Jump to today's count"
                                />
                            </div>
                        </div>

                        <div class="flex flex-col">
                            <label class="mb-2 block text-sm font-medium text-surface-700">Filter by Category</label>
                            <Select
                                v-model="selectedCategoryId"
                                :options="categoryOptions"
                                optionLabel="name"
                                optionValue="id"
                                placeholder="All Gold Categories"
                                showClear
                                filter
                                class="!h-10 w-64 sm:w-72"
                                @change="changeCategory"
                            />
                        </div>
                    </div>

                    <div class="text-sm text-surface-500">
                        <span v-if="!isToday" class="mr-2 inline-flex items-center gap-1 rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">
                            <i class="pi pi-history text-xs" /> Viewing Past Count
                        </span>
                        Viewing <span class="font-medium text-surface-900">{{ selectedCategoryName }}</span>
                        <span v-if="selectedCategoryId"> · {{ summary?.overall_remaining_items || 0 }} item(s) remain across all categories</span>
                    </div>
                </div>
            </div>

            <!-- All Categories Live Breakdown Grid -->
            <div v-if="dayOpen || categoryBreakdown.length > 0" class="border border-surface-200 bg-white p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-surface-900">Count by Category</h3>
                    <span class="text-xs text-surface-500">Click any category card to filter</span>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                    <!-- All Categories card -->
                    <div
                        @click="selectCategory(null)"
                        class="cursor-pointer rounded-lg border p-3 transition-all"
                        :class="selectedCategoryId === null ? 'border-primary-600 bg-primary-50/50 shadow-sm ring-1 ring-primary-500' : 'border-surface-200 bg-surface-50/50 hover:border-surface-300 hover:bg-white'"
                    >
                        <div class="flex items-center justify-between gap-1">
                            <p class="truncate text-xs font-semibold text-surface-900">All Categories</p>
                            <span
                                class="rounded px-1.5 py-0.5 text-[10px] font-bold"
                                :class="Number(summary?.overall_remaining_items || 0) === 0 && Number(summary?.overall_expected_items || 0) > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-surface-200 text-surface-700'"
                            >
                                {{ Number(summary?.overall_remaining_items || 0) === 0 && Number(summary?.overall_expected_items || 0) > 0 ? 'Done' : `${summary?.overall_remaining_items || 0} left` }}
                            </span>
                        </div>
                        <p class="mt-2 text-lg font-bold text-surface-900">
                            {{ summary?.counted_items || 0 }} <span class="text-xs font-normal text-surface-500">/ {{ summary?.expected_items || 0 }}</span>
                        </p>
                        <p class="mt-1 text-[11px] text-surface-500">{{ formatWeight(summary?.counted_net_weight) }}</p>
                    </div>

                    <!-- Individual Category Cards -->
                    <div
                        v-for="cat in categoryBreakdown"
                        :key="cat.id"
                        @click="selectCategory(cat.id)"
                        class="cursor-pointer rounded-lg border p-3 transition-all"
                        :class="selectedCategoryId === cat.id ? 'border-primary-600 bg-primary-50/50 shadow-sm ring-1 ring-primary-500' : 'border-surface-200 bg-white hover:border-surface-300 hover:shadow-xs'"
                    >
                        <div class="flex items-center justify-between gap-1">
                            <p class="truncate text-xs font-semibold text-surface-900" :title="cat.name">{{ cat.name }}</p>
                            <span
                                class="rounded px-1.5 py-0.5 text-[10px] font-bold"
                                :class="cat.is_complete ? 'bg-emerald-100 text-emerald-800' : (cat.remaining_items > 0 ? 'bg-amber-100 text-amber-800' : 'bg-surface-100 text-surface-600')"
                            >
                                {{ cat.is_complete ? 'Done' : `${cat.remaining_items} left` }}
                            </span>
                        </div>
                        <p class="mt-2 text-lg font-bold" :class="cat.is_complete ? 'text-emerald-600' : 'text-surface-900'">
                            {{ cat.counted_items }} <span class="text-xs font-normal text-surface-500">/ {{ cat.expected_items }}</span>
                        </p>
                        <p class="mt-1 text-[11px] text-surface-500">{{ formatWeight(cat.counted_net_weight) }}</p>
                    </div>
                </div>
            </div>

            <!-- Day Open Warning (if viewing today and day not open) -->
            <div v-if="isToday && !dayOpen" class="border border-amber-200 bg-amber-50 px-5 py-4">
                <p class="text-sm font-medium text-amber-900">Open day required</p>
                <p class="mt-1 text-sm text-amber-800">Open today’s shop day before scanning gold stock count.</p>
            </div>

            <!-- Summary KPI Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Expected Items</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ summary?.expected_items || 0 }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Counted Items</p>
                    <p class="mt-2 text-2xl font-semibold text-emerald-600">{{ summary?.counted_items || 0 }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Remaining</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-600">{{ summary?.remaining_items || 0 }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Match %</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ summary?.match_percentage || 0 }}%</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Counted Net Weight</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ formatWeight(summary?.counted_net_weight) }}</p>
                    <p class="mt-1 text-xs text-surface-500">Expected: {{ formatWeight(summary?.expected_net_weight) }}</p>
                </div>
            </div>

            <!-- Scanner & Session Info Grid -->
            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_24rem]">
                <!-- Scanner block (only active for today) -->
                <div v-if="isToday" class="border border-surface-200 bg-white px-5 py-5">
                    <div class="flex items-start gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center border border-surface-200 bg-surface-50 text-surface-600">
                            <ScanLine class="h-5 w-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-surface-900">Scanner</p>
                            <p class="mt-1 text-xs text-surface-500">Scan gold barcode and it will be added to counted list once only.</p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                        <div class="relative min-w-0 flex-1">
                            <i class="pi pi-barcode pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-surface-400" />
                            <InputText
                                ref="scanInputRef"
                                v-model="scanInput"
                                placeholder="Scan barcode or enter gold product code..."
                                class="w-full !pl-10"
                                :disabled="scanning || !canScan"
                                autofocus
                                @keydown.enter.prevent="scanBarcode"
                            />
                        </div>
                        <Button label="Count Item" icon="pi pi-plus" :loading="scanning" :disabled="!canScan" @click="scanBarcode" class="!w-full shrink-0 whitespace-nowrap sm:!w-auto" />
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-2 text-xs text-surface-500">
                        <span class="border border-surface-200 bg-surface-50 px-2 py-1">Example: G00025</span>
                        <span>Duplicate scan will be blocked</span>
                    </div>
                </div>

                <!-- Past date notice -->
                <div v-else class="flex items-center gap-4 border border-surface-200 bg-surface-50 p-5">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center border border-surface-200 bg-white text-surface-500">
                        <i class="pi pi-history text-lg" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-surface-900">Viewing Historical Count Record</p>
                        <p class="mt-1 text-xs text-surface-500">Date: <strong class="text-surface-700">{{ formatDate(selectedDate) }}</strong>. Scanning new barcodes is only active on today's session.</p>
                    </div>
                    <Button label="Go to Today" icon="pi pi-arrow-right" size="small" outlined @click="goToToday" class="shrink-0" />
                </div>

                <!-- Session info card -->
                <div class="border border-surface-200 bg-white px-5 py-5">
                    <p class="text-sm font-medium text-surface-900">Count Session</p>
                    <div class="mt-4 space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-surface-500">Count Date</span>
                            <span class="font-medium text-surface-900">{{ formatDate(summary?.register_date || selectedDate) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-surface-500">Status</span>
                            <Tag :value="session?.status || (isToday ? 'Not Started' : 'No Session')" :severity="session?.status === 'COMPLETED' ? 'success' : (session?.status === 'OPEN' ? 'warn' : 'secondary')" />
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-surface-500">Started</span>
                            <span class="font-medium text-surface-900">{{ formatDateTime(session?.started_at) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-surface-500">Completed</span>
                            <span class="font-medium text-surface-900">{{ formatDateTime(session?.completed_at) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tables Grid -->
            <div class="grid gap-4 xl:grid-cols-2">
                <!-- Recently Counted Table -->
                <div class="border border-surface-200 bg-white">
                    <div class="border-b border-surface-200 px-5 py-4">
                        <h3 class="text-base font-semibold text-surface-900">Counted Items</h3>
                        <p class="mt-1 text-sm text-surface-500">Gold items counted for {{ formatDate(selectedDate) }}.</p>
                    </div>

                    <div class="p-4">
                        <DataTable :value="recentCounted" stripedRows rowHover tableStyle="min-width: 48rem">
                            <template #empty>
                                <div class="py-12 text-center text-surface-500">No gold items counted for this date</div>
                            </template>

                            <Column field="barcode" header="Barcode" style="width: 130px" />
                            <Column header="Product" style="min-width: 170px">
                                <template #body="{ data }">
                                    <div>
                                        <p class="font-medium text-surface-900">{{ data.name }}</p>
                                        <p class="mt-1 text-xs text-surface-500">{{ data.category || '—' }}</p>
                                    </div>
                                </template>
                            </Column>
                            <Column field="purity" header="Purity" style="width: 90px">
                                <template #body="{ data }">
                                    <Tag :value="data.purity || '—'" severity="info" />
                                </template>
                            </Column>
                            <Column header="Net Weight" style="width: 120px">
                                <template #body="{ data }">
                                    <span class="font-medium text-surface-900">{{ formatWeight(data.net_weight) }}</span>
                                </template>
                            </Column>
                            <Column header="Scanned" style="width: 140px">
                                <template #body="{ data }">
                                    <div class="text-xs font-medium text-surface-900">{{ formatDateTime(data.scanned_at) }}</div>
                                    <div class="text-[11px] text-surface-500">{{ data.scanned_by || '' }}</div>
                                </template>
                            </Column>
                            <Column header="Actions" style="width: 90px; text-align: right">
                                <template #body="{ data }">
                                    <div class="flex items-center justify-end gap-1">
                                        <Button icon="pi pi-pencil" size="small" text severity="secondary" @click="openEditProduct(data)" title="Edit Product" />
                                        <Button icon="pi pi-trash" size="small" text severity="danger" @click="confirmDeleteProduct(data)" title="Delete Product" />
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>

                <!-- Missing Gold Stock Table -->
                <div class="border border-surface-200 bg-white">
                    <div class="border-b border-surface-200 px-5 py-4">
                        <h3 class="text-base font-semibold text-surface-900">Missing Gold Stock</h3>
                        <p class="mt-1 text-sm text-surface-500">Open stock not counted in this session. Showing first 100 items.</p>
                    </div>

                    <div class="p-4">
                        <DataTable :value="missingProducts" stripedRows rowHover tableStyle="min-width: 44rem">
                            <template #empty>
                                <div class="py-12 text-center text-emerald-600">All open gold stock counted</div>
                            </template>

                            <Column field="barcode" header="Barcode" style="width: 130px" />
                            <Column header="Product" style="min-width: 170px">
                                <template #body="{ data }">
                                    <div>
                                        <p class="font-medium text-surface-900">{{ data.name }}</p>
                                        <p class="mt-1 text-xs text-surface-500">{{ data.category || '—' }}</p>
                                    </div>
                                </template>
                            </Column>
                            <Column field="purity" header="Purity" style="width: 90px">
                                <template #body="{ data }">
                                    <Tag :value="data.purity || '—'" severity="info" />
                                </template>
                            </Column>
                            <Column header="Net Weight" style="width: 120px">
                                <template #body="{ data }">
                                    <span class="font-medium text-surface-900">{{ formatWeight(data.net_weight) }}</span>
                                </template>
                            </Column>
                            <Column header="Actions" style="width: 90px; text-align: right">
                                <template #body="{ data }">
                                    <div class="flex items-center justify-end gap-1">
                                        <Button icon="pi pi-pencil" size="small" text severity="secondary" @click="openEditProduct(data)" title="Edit Product" />
                                        <Button icon="pi pi-trash" size="small" text severity="danger" @click="confirmDeleteProduct(data)" title="Delete Product" />
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Product Dialog -->
        <Dialog v-model:visible="editDialog" header="Edit Product" modal :style="{ width: '42rem' }">
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
                            <small v-if="form.errors.image" class="mt-1 block text-xs text-red-500">{{ form.errors.image }}</small>
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
                    <small v-if="form.errors.name" class="mt-1 block text-xs text-red-500">{{ form.errors.name }}</small>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700"> Supplier </label>
                    <Select v-model="form.supplier_id" :options="suppliers" optionLabel="company_name" optionValue="id" placeholder="Select supplier" class="w-full" />
                    <small v-if="form.errors.supplier_id" class="mt-1 block text-xs text-red-500">{{ form.errors.supplier_id }}</small>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700"> Counter Name </label>
                    <Select v-model="form.counter_id" :options="counters" optionLabel="name" optionValue="id" placeholder="Select counter" showClear class="w-full" />
                    <small v-if="form.errors.counter_id" class="mt-1 block text-xs text-red-500">{{ form.errors.counter_id }}</small>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700"> Category </label>
                        <Select v-model="form.category_id" :options="categories" optionLabel="name" optionValue="id" placeholder="Select category" class="w-full" />
                        <small v-if="form.errors.category_id" class="mt-1 block text-xs text-red-500">{{ form.errors.category_id }}</small>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700"> Purity </label>
                        <Select v-model="form.purity_id" :options="purities" optionLabel="name" optionValue="id" placeholder="Select purity" class="w-full" />
                        <small v-if="form.errors.purity_id" class="mt-1 block text-xs text-red-500">{{ form.errors.purity_id }}</small>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700"> Gross Weight (g) </label>
                        <InputNumber v-model="form.gross_weight" :minFractionDigits="2" suffix=" g" class="w-full" />
                        <small v-if="form.errors.gross_weight" class="mt-1 block text-xs text-red-500">{{ form.errors.gross_weight }}</small>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700"> Net Weight (g) </label>
                        <InputNumber v-model="form.net_weight" :minFractionDigits="2" suffix=" g" class="w-full" />
                        <small v-if="form.errors.net_weight" class="mt-1 block text-xs text-red-500">{{ form.errors.net_weight }}</small>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700"> Making Charge (%) </label>
                    <InputNumber v-model="form.making_charge" mode="decimal" suffix=" %" :min="0" :max="100" :minFractionDigits="2" :maxFractionDigits="2" class="w-full" />
                    <small class="mt-1 block text-xs text-surface-500">Example: enter 10 for 10% making on the gold rate.</small>
                    <small v-if="form.errors.making_charge" class="mt-1 block text-xs text-red-500">{{ form.errors.making_charge }}</small>
                </div>

                <div class="flex justify-end gap-2 border-t border-surface-200 pt-4">
                    <Button label="Cancel" text severity="secondary" type="button" @click="editDialog = false" />
                    <Button label="Update Product" type="submit" :loading="form.processing" />
                </div>
            </form>
        </Dialog>

        <!-- Delete Product Confirmation Dialog -->
        <Dialog v-model:visible="deleteDialog" header="Confirm Delete" modal :style="{ width: '28rem' }">
            <div class="flex items-start gap-4">
                <i class="pi pi-exclamation-triangle text-3xl text-red-500" />
                <div>
                    <p class="text-sm font-medium text-surface-900">Are you sure you want to delete this product?</p>
                    <p class="mt-1 text-xs text-surface-500">
                        <span class="font-semibold text-surface-900">{{ selectedProduct?.barcode }}</span> — {{ selectedProduct?.name }}
                    </p>
                    <p class="mt-2 text-xs text-red-600">This action cannot be undone.</p>
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" text severity="secondary" @click="deleteDialog = false" :disabled="deleting" />
                <Button label="Delete" severity="danger" :loading="deleting" @click="deleteProduct" />
            </template>
        </Dialog>
    </AppLayout>
</template>
