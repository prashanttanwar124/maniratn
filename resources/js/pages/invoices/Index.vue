<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import ConfirmDialog from 'primevue/confirmdialog';
import DataTable from 'primevue/datatable';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { route } from 'ziggy-js';

const props = defineProps({ invoices: Array });

const confirm = useConfirm();
const toast = useToast();

// Logic to Cancel Bill
const cancelBill = (id) => {
    confirm.require({
        message: 'Are you sure you want to cancel this bill? Stock will be restored.',
        header: 'Confirm Cancellation',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: () => {
            router.post(
                route('invoices.cancel', id),
                {},
                {
                    onSuccess: () => toast.add({ severity: 'success', summary: 'Cancelled', detail: 'Bill cancelled successfully', life: 3000 }),
                },
            );
        },
    });
};

// Helper to format Date
const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-IN', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
};
</script>

<template>
    <AppLayout>
        <ConfirmDialog />
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <div class="overflow-hidden bg-white p-6 shadow-xl sm:rounded-lg">
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-2xl font-bold text-gray-800">Invoice History</h2>
                    <Button label="New Bill" icon="pi pi-plus" @click="router.visit(route('invoices.create'))" />
                </div>

                <DataTable :value="invoices" paginator :rows="10" stripedRows tableStyle="min-width: 50rem">
                    <Column field="invoice_number" header="Bill No" sortable>
                        <template #body="slotProps">
                            <span class="font-mono font-bold text-indigo-600">{{ slotProps.data.invoice_number }}</span>
                        </template>
                    </Column>

                    <Column field="customer.name" header="Customer" sortable></Column>

                    <Column field="date" header="Date" sortable>
                        <template #body="slotProps">
                            {{ formatDate(slotProps.data.date) }}
                        </template>
                    </Column>

                    <Column field="total_amount" header="Total Amount" sortable>
                        <template #body="slotProps">
                            <span class="font-bold">
                                {{ new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(slotProps.data.total_amount) }}
                            </span>
                        </template>
                    </Column>

                    <Column field="status" header="Status" sortable>
                        <template #body="slotProps">
                            <Tag :severity="slotProps.data.status === 'CANCELLED' ? 'danger' : 'success'" :value="slotProps.data.status" />
                        </template>
                    </Column>

                    <Column header="Actions">
                        <template #body="slotProps">
                            <div class="flex gap-2">
                                <Button
                                    v-if="slotProps.data.status !== 'CANCELLED'"
                                    icon="pi pi-times"
                                    severity="danger"
                                    text
                                    rounded
                                    aria-label="Cancel"
                                    @click="cancelBill(slotProps.data.id)"
                                    v-tooltip="'Cancel Bill'"
                                />

                                <Button icon="pi pi-eye" severity="secondary" text rounded aria-label="View" />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>
