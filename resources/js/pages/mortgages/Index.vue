<script setup>
import CustomerSelector from '@/components/CustomerSelector.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import Image from 'primevue/image';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Paginator from 'primevue/paginator';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';
// Props from Laravel Controller
const props = defineProps({
    mortgages: Object,
    customers: Array, // Pass simple list {id, name, mobile} for dropdown
});

// --- STATE MANAGEMENT ---
const showCreateModal = ref(false);
const showViewModal = ref(false);
const selectedMortgage = ref(null);

// --- FORMS ---
const form = useForm({
    customer_id: null,
    item_name: '',
    gross_weight: null,
    loan_amount: null,
    interest_rate: 2.0,
    start_date: new Date(),
    item_image: null,
    notes: '',
    type: '',
});

// --- ACTIONS ---

import FileUpload from 'primevue/fileupload';

// ... inside script setup ...

// Create a variable to hold the preview URL
const previewImage = ref(null);

// Update your file selection function
const onFileSelect = (event) => {
    // 1. PrimeVue sends the file in 'event.files' (different from native input)
    const file = event.files[0];
    form.item_image = file;

    // 2. Create a fake URL to show the image instantly
    previewImage.value = URL.createObjectURL(file);
};

const paymentForm = useForm({
    amount: null,
    type: 'INTEREST',
    date: new Date().toISOString().substr(0, 10),
    note: '',
});

const submitPayment = () => {
    paymentForm.post(route('mortgages.payment', selectedMortgage.value.id), {
        onSuccess: () => {
            paymentForm.reset();
            // Close modal or keep it open to see the new row?
            // Better to keep it open, but we need to refresh data.
            // Inertia auto-refreshes props, so just re-select the mortgage from the new list
            // NOTE: You might need to handle re-selecting the updated mortgage item here.
            showViewModal.value = false;
        },
    });
};

// 1. Open Create Modal
const openCreate = () => {
    form.reset();
    showCreateModal.value = true;
};

// 2. Submit New Loan
const submitCreate = () => {
    form.post(route('mortgages.store'), {
        forceFormData: true, // Critical for Image Upload
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        },
    });
};

// 4. View Details / Release Loan
const openView = (mortgage) => {
    selectedMortgage.value = mortgage;
    showViewModal.value = true;
};

// 5. Release (Close) Loan
const releaseLoan = () => {
    if (!confirm('Are you sure the customer paid back the money?')) return;

    router.post(
        route('mortgages.update', selectedMortgage.value.id),
        {
            action: 'RELEASE',
            _method: 'PUT',
        },
        {
            onSuccess: () => (showViewModal.value = false),
        },
    );
};

// Helper: Format Money

const paymentTypes = [
    { label: 'Interest Only', value: 'INTEREST' },
    { label: 'Principal Cut', value: 'PRINCIPAL' },
];

const onPageChange = (event) => {
    const newPage = event.page + 1;

    // Reload page with new data
    router.get(
        route('mortgages.index'),
        { page: newPage },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const tableData = computed(() => props.customers.data);
</script>

<template>
    <AppLayout>
        <div class="card p-4">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-xl font-bold">Active Mortgages (Girvi)</h2>
                <Button label="New Mortgage" icon="pi pi-plus" @click="openCreate" />
            </div>

            <DataTable :value="mortgages.data" stripedRows tableStyle="min-width: 50rem">
                <Column header="Customer">
                    <template #body="slotProps">
                        <div class="font-bold">{{ slotProps.data.customer.name }}</div>
                        <div class="text-xs text-gray-500">{{ slotProps.data.customer.mobile }}</div>
                    </template>
                </Column>

                <Column field="item_name" header="Item" />

                <Column header="Weight">
                    <template #body="slotProps"> {{ slotProps.data.gross_weight }}g </template>
                </Column>

                <Column header="Loan Amount">
                    <template #body="slotProps">
                        <span class="font-bold text-red-600">{{ $formatMoney(slotProps.data.loan_amount) }}</span>
                    </template>
                </Column>

                <Column header="Date">
                    <template #body="slotProps">
                        {{ new Date(slotProps.data.start_date).toLocaleDateString('en-IN') }}
                    </template>
                </Column>

                <Column header="Action">
                    <template #body="slotProps">
                        <Button icon="pi pi-eye" size="small" outlined @click="openView(slotProps.data)" />
                    </template>
                </Column>
            </DataTable>

            <Paginator
                :rows="mortgages.per_page"
                :totalRecords="mortgages.total"
                :first="(mortgages.current_page - 1) * mortgages.per_page"
                @page="onPageChange"
                class="border-t border-gray-100"
            ></Paginator>

            <Dialog v-model:visible="showCreateModal" header="Add New Girvi" :modal="true" :style="{ width: '500px' }">
                <form @submit.prevent="submitCreate" class="mt-2 flex flex-col gap-4">
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-bold">Select Customer</label>

                        <CustomerSelector v-model="form.customer_id" :errorMessage="form.errors.customer_id" />
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-bold">Item Name</label>
                        <InputText v-model="form.item_name" placeholder="e.g. Gold Chain" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1">
                            <label class="text-sm font-bold">Gross Weight (g)</label>
                            <InputNumber v-model="form.gross_weight" :minFractionDigits="3" suffix=" g" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-sm font-bold">Loan Amount (₹)</label>
                            <InputNumber v-model="form.loan_amount" mode="currency" currency="INR" locale="en-IN" />
                        </div>
                    </div>

                    <div class="flex flex-col gap-1">
                        <div class="flex items-center gap-4">
                            <div class="flex flex-col gap-2">
                                <label class="text-sm font-bold text-gray-600">Item Photo</label>
                                <FileUpload
                                    mode="basic"
                                    name="demo[]"
                                    accept="image/*"
                                    :maxFileSize="2000000"
                                    @select="onFileSelect"
                                    :auto="false"
                                    chooseLabel="Choose Photo"
                                    class="p-button-outlined"
                                />
                                <small class="text-xs text-gray-400">Max size: 2MB</small>
                            </div>

                            <div v-if="previewImage" class="relative">
                                <img :src="previewImage" class="h-16 w-16 rounded border border-gray-300 object-cover shadow-sm" />
                            </div>

                            <div v-else class="flex h-16 w-16 items-center justify-center rounded border border-dashed border-gray-300 bg-gray-50 text-xs text-gray-400">No Img</div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-bold">Notes / Bag No</label>
                        <Textarea v-model="form.notes" rows="2" />
                    </div>

                    <div class="mt-4 flex justify-end gap-2">
                        <Button label="Cancel" text severity="secondary" @click="showCreateModal = false" />
                        <Button label="Save Mortgage" type="submit" :loading="form.processing" />
                    </div>
                </form>
            </Dialog>

            <Dialog v-model:visible="showViewModal" header="Loan Details" :modal="true" :style="{ width: '600px' }">
                <div v-if="selectedMortgage" class="flex flex-col gap-6">
                    <div class="flex gap-4 border-b pb-4">
                        <div v-if="selectedMortgage.image_url">
                            <Image :src="selectedMortgage.image_url" alt="Item" width="120" preview />
                        </div>

                        <div class="grid flex-1 grid-cols-2 gap-y-1 text-sm">
                            <span class="text-gray-500">Item:</span>
                            <span class="font-bold">{{ selectedMortgage.item_name }}</span>

                            <span class="text-gray-500">Original Loan:</span>
                            <span class="font-bold">{{ $formatMoney(selectedMortgage.loan_amount) }}</span>

                            <span class="text-gray-500">Current Balance:</span>
                            <span class="text-lg font-bold text-red-600">
                                {{ $formatMoney(selectedMortgage.pending_amount) }}
                            </span>
                        </div>
                    </div>

                    <div>
                        <h3 class="mb-2 text-sm font-bold text-gray-700">Payment History</h3>
                        <div v-if="selectedMortgage.payments.length === 0" class="text-xs text-gray-400 italic">No payments made yet.</div>
                        <table v-else class="w-full border text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="p-2 text-left">Date</th>
                                    <th class="p-2 text-left">Type</th>
                                    <th class="p-2 text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="pay in selectedMortgage.payments" :key="pay.id" class="border-t">
                                    <td class="p-2">{{ new Date(pay.date).toLocaleDateString('en-IN') }}</td>
                                    <td class="p-2">
                                        <Tag :severity="pay.type === 'INTEREST' ? 'warning' : 'success'" :value="pay.type" />
                                    </td>
                                    <td class="p-2 text-right font-mono">{{ $formatMoney(pay.amount) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-5">
                        <h3 class="mb-3 flex items-center gap-2 text-sm font-bold text-slate-800">
                            <i class="pi pi-wallet text-indigo-600"></i>
                            Receive Payment
                        </h3>

                        <div class="grid grid-cols-12 items-end gap-3">
                            <div class="col-span-5">
                                <label class="mb-1 ml-1 block text-xs font-semibold text-slate-600">Amount</label>
                                <InputNumber v-model="paymentForm.amount" mode="currency" currency="INR" locale="en-IN" placeholder="₹ 0.00" class="w-full" inputClass="w-full" />
                            </div>

                            <div class="col-span-4">
                                <label class="mb-1 ml-1 block text-xs font-semibold text-slate-600">Payment Type</label>
                                <Dropdown v-model="paymentForm.type" :options="paymentTypes" optionLabel="label" optionValue="value" placeholder="Select Type" class="w-full" />
                            </div>

                            <div class="col-span-3">
                                <Button label="Add" icon="pi pi-check" class="w-full" @click="submitPayment" :disabled="!paymentForm.amount" />
                            </div>
                        </div>
                    </div>
                </div>
            </Dialog>
        </div>
    </AppLayout>
</template>
