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
    purities: Array<{
        id: number;
        name: string;
        products_count: number;
        created_at: string | null;
    }>;
    summary: {
        total_purities: number;
        linked_purities: number;
        unused_purities: number;
    };
}>();

const page = usePage();
const toast = useToast();
const isDayOpen = computed(() => Boolean(page.props.dayStatus?.is_open));
const dialogVisible = ref(false);
const deleteDialogVisible = ref(false);
const searchTerm = ref('');
const usageFilter = ref<'ALL' | 'LINKED' | 'UNUSED'>('ALL');
const selectedPurity = ref<(typeof props.purities)[number] | null>(null);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
];

const usageOptions = [
    { label: 'All Purities', value: 'ALL' },
    { label: 'Linked to Products', value: 'LINKED' },
    { label: 'Unused Purities', value: 'UNUSED' },
];

const form = useForm({
    id: null as number | null,
    name: '',
});

const filteredPurities = computed(() => {
    const term = searchTerm.value.trim().toLowerCase();

    return props.purities.filter((purity) => {
        const matchesUsage =
            usageFilter.value === 'ALL' ||
            (usageFilter.value === 'LINKED' && purity.products_count > 0) ||
            (usageFilter.value === 'UNUSED' && purity.products_count === 0);

        if (!matchesUsage) return false;
        if (!term) return true;

        return purity.name.toLowerCase().includes(term);
    });
});

const openCreateDialog = () => {
    form.reset();
    form.clearErrors();
    form.id = null;
    dialogVisible.value = true;
};

const openEditDialog = (purity: (typeof props.purities)[number]) => {
    form.reset();
    form.clearErrors();
    form.id = purity.id;
    form.name = purity.name;
    dialogVisible.value = true;
};

const openDeleteDialog = (purity: (typeof props.purities)[number]) => {
    selectedPurity.value = purity;
    deleteDialogVisible.value = true;
};

const savePurity = () => {
    if (!isDayOpen.value) {
        toast.add({ severity: 'warn', summary: 'Day Closed', detail: 'Open the shop day first from the dashboard.', life: 3000 });
        return;
    }

    if (form.id) {
        form.transform((data) => ({ ...data, _method: 'patch' })).post(route('purities.update', form.id), {
            preserveScroll: true,
            onSuccess: () => {
                dialogVisible.value = false;
                toast.add({ severity: 'success', summary: 'Updated', detail: 'Purity updated successfully.', life: 2200 });
            },
        });

        return;
    }

    form.post(route('purities.store'), {
        preserveScroll: true,
        onSuccess: () => {
            dialogVisible.value = false;
            toast.add({ severity: 'success', summary: 'Created', detail: 'Purity created successfully.', life: 2200 });
        },
    });
};

const deletePurity = () => {
    if (!selectedPurity.value) return;

    form.delete(route('purities.destroy', selectedPurity.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deleteDialogVisible.value = false;
            selectedPurity.value = null;
            toast.add({ severity: 'success', summary: 'Deleted', detail: 'Purity deleted successfully.', life: 2200 });
        },
    });
};
</script>

<template>
    <Head title="Purities" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <Toast />

        <div class="space-y-6">
            <section class="border border-surface-200 bg-white px-5 py-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl">
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-2xl font-semibold tracking-tight text-surface-900">Purities</h1>
                            <Tag value="Gold Master Data" severity="secondary" />
                        </div>
                        <p class="mt-2 text-sm leading-6 text-surface-600">
                            Manage the purity labels used by gold products. Keep names consistent here so product counters and invoices show the same purity language everywhere.
                        </p>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Total Purities</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ summary.total_purities }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Linked to Products</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-700">{{ summary.linked_purities }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Unused Purities</p>
                    <p class="mt-2 text-2xl font-semibold text-emerald-700">{{ summary.unused_purities }}</p>
                </div>
            </section>

            <section class="overflow-hidden border border-surface-200 bg-white">
                <div class="border-b border-surface-200 px-5 py-4">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-surface-900">Purity Register</h2>
                            <p class="mt-1 text-sm text-surface-500">Use short, clear names like 22K, 18K, or 916 Hallmark so product teams can pick the right option fast.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,18rem)_14rem_auto]">
                            <IconField>
                                <InputIcon class="pi pi-search" />
                                <InputText v-model="searchTerm" placeholder="Search purity name" class="w-full" />
                            </IconField>

                            <div>
                                <label class="sr-only">Usage Filter</label>
                                <Select v-model="usageFilter" :options="usageOptions" optionLabel="label" optionValue="value" />
                            </div>

                            <Button label="New Purity" icon="pi pi-plus" severity="contrast" @click="openCreateDialog" />
                        </div>
                    </div>
                </div>

                <div class="p-4">
                    <DataTable :value="filteredPurities" stripedRows rowHover tableStyle="min-width: 100%">
                        <template #empty>
                            <div class="py-12 text-center text-surface-500">No purities found for the current filter.</div>
                        </template>

                        <Column field="name" header="Purity" />

                        <Column header="Usage" style="width: 160px">
                            <template #body="{ data }">
                                <Tag :value="data.products_count > 0 ? 'Linked' : 'Unused'" :severity="data.products_count > 0 ? 'warn' : 'success'" />
                            </template>
                        </Column>

                        <Column header="Linked Products" style="width: 160px">
                            <template #body="{ data }">
                                <span class="font-medium text-surface-900">{{ data.products_count }}</span>
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

        <Dialog v-model:visible="dialogVisible" :header="form.id ? 'Edit Purity' : 'New Purity'" modal :style="{ width: '28rem' }">
            <form class="space-y-4 pt-2" @submit.prevent="savePurity">
                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Purity Name</label>
                    <InputText v-model="form.name" class="w-full" placeholder="22K, 18K, 916 Hallmark" />
                    <small v-if="form.errors.name" class="mt-1 block text-xs text-red-500">{{ form.errors.name }}</small>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Button type="button" label="Cancel" text @click="dialogVisible = false" />
                    <Button type="submit" :label="form.id ? 'Update Purity' : 'Create Purity'" :loading="form.processing" />
                </div>
            </form>
        </Dialog>

        <Dialog v-model:visible="deleteDialogVisible" header="Delete Purity" modal :style="{ width: '26rem' }">
            <div class="space-y-4 pt-2">
                <p class="text-sm text-surface-600">
                    Delete <strong>{{ selectedPurity?.name }}</strong>? This works only when no products are linked to the purity.
                </p>
                <div class="flex justify-end gap-2">
                    <Button type="button" label="Cancel" text @click="deleteDialogVisible = false" />
                    <Button type="button" label="Delete" severity="danger" :loading="form.processing" @click="deletePurity" />
                </div>
            </div>
        </Dialog>
    </AppLayout>
</template>
