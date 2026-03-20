<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const props = defineProps<{
    categories: Array<{
        id: number;
        name: string;
        code: string;
        metal_type: 'GOLD' | 'SILVER';
        items_count: number;
        created_at: string | null;
    }>;
    summary: {
        total_categories: number;
        gold_categories: number;
        silver_categories: number;
    };
}>();

const page = usePage();
const toast = useToast();
const isDayOpen = computed(() => Boolean(page.props.dayStatus?.is_open));
const dialogVisible = ref(false);
const deleteDialogVisible = ref(false);
const searchTerm = ref('');
const metalFilter = ref<'ALL' | 'GOLD' | 'SILVER'>('ALL');
const selectedCategory = ref<(typeof props.categories)[number] | null>(null);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
];

const metalTypeOptions = [
    { label: 'All Types', value: 'ALL' },
    { label: 'Gold Categories', value: 'GOLD' },
    { label: 'Silver Categories', value: 'SILVER' },
];

const form = useForm({
    id: null as number | null,
    name: '',
    code: '',
    metal_type: 'GOLD' as 'GOLD' | 'SILVER',
});

const filteredCategories = computed(() => {
    const term = searchTerm.value.trim().toLowerCase();

    return props.categories.filter((category) => {
        const matchesMetal = metalFilter.value === 'ALL' || category.metal_type === metalFilter.value;
        if (!matchesMetal) return false;
        if (!term) return true;

        return [category.name, category.code, category.metal_type].join(' ').toLowerCase().includes(term);
    });
});

const openCreateDialog = () => {
    form.reset();
    form.clearErrors();
    form.id = null;
    form.metal_type = 'GOLD';
    dialogVisible.value = true;
};

const openEditDialog = (category: (typeof props.categories)[number]) => {
    form.reset();
    form.clearErrors();
    form.id = category.id;
    form.name = category.name;
    form.code = category.code;
    form.metal_type = category.metal_type;
    dialogVisible.value = true;
};

const openDeleteDialog = (category: (typeof props.categories)[number]) => {
    selectedCategory.value = category;
    deleteDialogVisible.value = true;
};

const saveCategory = () => {
    if (!isDayOpen.value) {
        toast.add({ severity: 'warn', summary: 'Day Closed', detail: 'Open the shop day first from the dashboard.', life: 3000 });
        return;
    }

    if (form.id) {
        form.transform((data) => ({ ...data, _method: 'patch' })).post(route('categories.update', form.id), {
            preserveScroll: true,
            onSuccess: () => {
                dialogVisible.value = false;
                toast.add({ severity: 'success', summary: 'Updated', detail: 'Category updated successfully.', life: 2200 });
            },
        });

        return;
    }

    form.post(route('categories.store'), {
        preserveScroll: true,
        onSuccess: () => {
            dialogVisible.value = false;
            toast.add({ severity: 'success', summary: 'Created', detail: 'Category created successfully.', life: 2200 });
        },
    });
};

const deleteCategory = () => {
    if (!selectedCategory.value) return;

    form.delete(route('categories.destroy', selectedCategory.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deleteDialogVisible.value = false;
            selectedCategory.value = null;
            toast.add({ severity: 'success', summary: 'Deleted', detail: 'Category deleted successfully.', life: 2200 });
        },
    });
};
</script>

<template>
    <Head title="Categories" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <Toast />

        <div class="space-y-6">
            <section class="border border-surface-200 bg-white px-5 py-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl">
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-2xl font-semibold tracking-tight text-surface-900">Categories</h1>
                            <Tag value="Gold & Silver" severity="secondary" />
                        </div>
                        <p class="mt-2 text-sm leading-6 text-surface-600">
                            Manage gold and silver categories in one place. Gold products will only use gold categories, and silver products will only use silver categories.
                        </p>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Total Categories</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ summary.total_categories }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Gold Categories</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-700">{{ summary.gold_categories }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Silver Categories</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-700">{{ summary.silver_categories }}</p>
                </div>
            </section>

            <section class="overflow-hidden border border-surface-200 bg-white">
                <div class="border-b border-surface-200 px-5 py-4">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-surface-900">Category Register</h2>
                            <p class="mt-1 text-sm text-surface-500">Use one shared table, but keep the metal type explicit so stock screens stay clean.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,18rem)_12rem_auto]">
                            <IconField>
                                <InputIcon class="pi pi-search" />
                                <InputText v-model="searchTerm" placeholder="Search name or code" class="w-full" />
                            </IconField>

                            <Select v-model="metalFilter" :options="metalTypeOptions" optionLabel="label" optionValue="value" />

                            <Button label="New Category" icon="pi pi-plus" severity="contrast" @click="openCreateDialog" />
                        </div>
                    </div>
                </div>

                <div class="p-4">
                    <DataTable :value="filteredCategories" stripedRows rowHover tableStyle="min-width: 100%">
                        <template #empty>
                            <div class="py-12 text-center text-surface-500">No categories found for the current filter.</div>
                        </template>

                        <Column field="name" header="Category">
                            <template #body="{ data }">
                                <div>
                                    <p class="font-medium text-surface-900">{{ data.name }}</p>
                                    <p class="mt-1 text-xs text-surface-500">Code: {{ data.code }}</p>
                                </div>
                            </template>
                        </Column>

                        <Column header="Metal Type" style="width: 140px">
                            <template #body="{ data }">
                                <Tag :value="data.metal_type" :severity="data.metal_type === 'GOLD' ? 'warn' : 'secondary'" />
                            </template>
                        </Column>

                        <Column header="Linked Items" style="width: 140px">
                            <template #body="{ data }">
                                <span class="font-medium text-surface-900">{{ data.items_count }}</span>
                            </template>
                        </Column>

                        <Column field="created_at" header="Created" style="width: 140px" />

                        <Column header="Actions" style="width: 180px">
                            <template #body="{ data }">
                                <div class="flex justify-end gap-2">
                                    <Button label="Edit" size="small" text @click="openEditDialog(data)" />
                                    <Button label="Delete" size="small" text severity="danger" @click="openDeleteDialog(data)" />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </section>
        </div>

        <Dialog v-model:visible="dialogVisible" :header="form.id ? 'Edit Category' : 'New Category'" modal :style="{ width: '30rem' }">
            <form class="space-y-4 pt-2" @submit.prevent="saveCategory">
                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Category Name</label>
                    <InputText v-model="form.name" class="w-full" placeholder="Ring, Chain, Silver Coin" />
                    <small v-if="form.errors.name" class="mt-1 block text-xs text-red-500">{{ form.errors.name }}</small>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Code</label>
                        <InputText v-model="form.code" class="w-full uppercase" placeholder="RNG" />
                        <small v-if="form.errors.code" class="mt-1 block text-xs text-red-500">{{ form.errors.code }}</small>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Metal Type</label>
                        <Select
                            v-model="form.metal_type"
                            :options="[
                                { label: 'Gold', value: 'GOLD' },
                                { label: 'Silver', value: 'SILVER' },
                            ]"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                        />
                        <small v-if="form.errors.metal_type" class="mt-1 block text-xs text-red-500">{{ form.errors.metal_type }}</small>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Button type="button" label="Cancel" text @click="dialogVisible = false" />
                    <Button type="submit" :label="form.id ? 'Update Category' : 'Create Category'" :loading="form.processing" />
                </div>
            </form>
        </Dialog>

        <Dialog v-model:visible="deleteDialogVisible" header="Delete Category" modal :style="{ width: '26rem' }">
            <div class="space-y-4 pt-2">
                <p class="text-sm text-surface-600">
                    Delete <strong>{{ selectedCategory?.name }}</strong>? This works only when no gold or silver items are linked to the category.
                </p>
                <div class="flex justify-end gap-2">
                    <Button type="button" label="Cancel" text @click="deleteDialogVisible = false" />
                    <Button type="button" label="Delete" severity="danger" :loading="form.processing" @click="deleteCategory" />
                </div>
            </div>
        </Dialog>
    </AppLayout>
</template>
