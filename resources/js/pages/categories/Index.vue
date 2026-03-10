<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import { ref } from 'vue';
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];
const products = ref([
    {
        id: 1,
        barcode: 'MJ-RNG-001',
        name: 'Gold Ring Design A',
        category: 'Ring',
        net_weight: 5.25,
        purity: '22K',
    },
    {
        id: 2,
        barcode: 'MJ-CHN-005',
        name: 'Men Chain Rope',
        category: 'Chain',
        net_weight: 22.1,
        purity: '916',
    },
    {
        id: 3,
        barcode: 'MJ-BNG-102',
        name: 'Antique Bangle',
        category: 'Bangle',
        net_weight: 45.5,
        purity: '18K',
    },
    {
        id: 4,
        barcode: 'MJ-EAR-089',
        name: 'Diamond Jhumka',
        category: 'Earring',
        net_weight: 12.4,
        purity: '18K',
    },
]);
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="overflow-x-auto">
                <DataTable :value="products" showGridlines stripedRows paginator :rows="10" tableStyle="min-width: 50rem">
                    <Column field="barcode" header="Barcode" sortable></Column>

                    <Column field="name" header="Item Name" sortable></Column>

                    <Column field="category" header="Category" sortable></Column>

                    <Column field="net_weight" header="Net Weight (g)" sortable class="text-right">
                        <template #body="slotProps">
                            {{ slotProps.data.net_weight.toFixed(3) }}
                        </template>
                    </Column>

                    <Column field="purity" header="Purity" sortable>
                        <template #body="slotProps">
                            <span class="rounded bg-yellow-100 px-2 py-1 text-xs font-bold text-yellow-800">
                                {{ slotProps.data.purity }}
                            </span>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>
