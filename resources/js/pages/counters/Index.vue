<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps<{
    counters: Array<{
        id: number;
        name: string;
        gold_items_count: number;
        silver_items_count: number;
        created_at: string | null;
    }>;
    summary: {
        total_counters: number;
        gold_items: number;
        silver_items: number;
    };
}>();

const page = usePage();
const toast = useToast();
const isDayOpen = computed(() => Boolean(page.props.dayStatus?.is_open));
const dialogVisible = ref(false);
const deleteDialogVisible = ref(false);
const searchTerm = ref('');
const selectedCounter = ref<(typeof props.counters)[number] | null>(null);

const form = useForm({
    id: null as number | null,
    name: '',
});

const filteredCounters = computed(() => {
    const term = searchTerm.value.trim().toLowerCase();
    return term ? props.counters.filter((counter) => counter.name.toLowerCase().includes(term)) : props.counters;
});

const openCreateDialog = () => {
    form.reset();
    form.clearErrors();
    dialogVisible.value = true;
};

const openEditDialog = (counter: (typeof props.counters)[number]) => {
    form.clearErrors();
    form.id = counter.id;
    form.name = counter.name;
    dialogVisible.value = true;
};

const saveCounter = () => {
    if (!isDayOpen.value) {
        toast.add({ severity: 'warn', summary: 'Day Closed', detail: 'Open the shop day first from the dashboard.', life: 3000 });
        return;
    }

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            dialogVisible.value = false;
            toast.add({ severity: 'success', summary: 'Saved', detail: 'Counter saved successfully.', life: 2200 });
        },
    };

    if (form.id) {
        form.transform((data) => ({ ...data, _method: 'patch' })).post(route('counters.update', form.id), options);
        return;
    }

    form.post(route('counters.store'), options);
};

const confirmDelete = (counter: (typeof props.counters)[number]) => {
    selectedCounter.value = counter;
    deleteDialogVisible.value = true;
};

const deleteCounter = () => {
    if (!selectedCounter.value) return;

    form.delete(route('counters.destroy', selectedCounter.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deleteDialogVisible.value = false;
            selectedCounter.value = null;
            toast.add({ severity: 'success', summary: 'Deleted', detail: 'Counter deleted successfully.', life: 2200 });
        },
    });
};
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <section class="border border-surface-200 bg-white px-5 py-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-2xl font-semibold tracking-tight text-surface-900">Counters</h1>
                            <Tag value="Gold & Silver" severity="secondary" />
                        </div>
                        <p class="mt-2 text-sm text-surface-600">Manage counter names used to locate gold and silver products in the shop.</p>
                    </div>
                    <Button label="New Counter" icon="pi pi-plus" severity="contrast" @click="openCreateDialog" />
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Total Counters</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ summary.total_counters }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Linked Gold Items</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-700">{{ summary.gold_items }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Linked Silver Items</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-700">{{ summary.silver_items }}</p>
                </div>
            </section>

            <section class="overflow-hidden border border-surface-200 bg-white">
                <div class="flex flex-col gap-3 border-b border-surface-200 px-5 py-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-surface-900">Counter Register</h2>
                        <p class="mt-1 text-sm text-surface-500">Linked counters cannot be deleted.</p>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Search</label>
                        <InputText v-model="searchTerm" placeholder="Search counter name" class="w-full sm:w-72" />
                    </div>
                </div>

                <div class="p-4">
                    <DataTable :value="filteredCounters" stripedRows rowHover tableStyle="min-width: 42rem">
                        <template #empty><div class="py-12 text-center text-surface-500">No counters found.</div></template>
                        <Column field="name" header="Counter Name" />
                        <Column field="gold_items_count" header="Gold Items" style="width: 130px" />
                        <Column field="silver_items_count" header="Silver Items" style="width: 130px" />
                        <Column field="created_at" header="Created" style="width: 140px" />
                        <Column header="Actions" style="width: 180px">
                            <template #body="{ data }">
                                <div class="flex justify-end gap-2">
                                    <Button label="Edit" size="small" text @click="openEditDialog(data)" />
                                    <Button label="Delete" size="small" text severity="danger" @click="confirmDelete(data)" />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </section>
        </div>

        <Dialog v-model:visible="dialogVisible" :header="form.id ? 'Edit Counter' : 'New Counter'" modal :style="{ width: '30rem' }">
            <form class="space-y-4 pt-2" @submit.prevent="saveCounter">
                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Counter Name</label>
                    <InputText v-model="form.name" class="w-full" placeholder="Main Counter, Bridal Counter" autofocus />
                    <small v-if="form.errors.name" class="mt-1 block text-xs text-red-500">{{ form.errors.name }}</small>
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" label="Cancel" text @click="dialogVisible = false" />
                    <Button type="submit" :label="form.id ? 'Update Counter' : 'Create Counter'" :loading="form.processing" />
                </div>
            </form>
        </Dialog>

        <Dialog v-model:visible="deleteDialogVisible" header="Delete Counter" modal :style="{ width: '26rem' }">
            <div class="space-y-4 pt-2">
                <p class="text-sm text-surface-600">Delete <strong>{{ selectedCounter?.name }}</strong>? This only works when no products are linked.</p>
                <small v-if="form.errors.counter" class="block text-xs text-red-500">{{ form.errors.counter }}</small>
                <div class="flex justify-end gap-2">
                    <Button label="Cancel" text @click="deleteDialogVisible = false" />
                    <Button label="Delete" severity="danger" :loading="form.processing" @click="deleteCounter" />
                </div>
            </div>
        </Dialog>
    </AppLayout>
</template>
