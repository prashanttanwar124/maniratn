<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import throttle from 'lodash/throttle';
import { useToast } from 'primevue/usetoast';
import { ref, watch } from 'vue';
import { route } from 'ziggy-js';
// PrimeVue Components
import { Plus, Search } from 'lucide-vue-next';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import Paginator from 'primevue/paginator';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
const props = defineProps({
    suppliers: Object,
    filters: Object,
});

const toast = useToast();
const supplierDialog = ref(false);
const deleteDialog = ref(false);
const supplier = ref({});
const isEditing = ref(false);

// Options for the "Type" dropdown
const supplierTypes = ref([
    { label: 'Gold', value: 'GOLD' },
    { label: 'Silver', value: 'SILVER' },
    { label: 'Diamond', value: 'DIAMOND' },
    { label: 'Packaging', value: 'PACKAGING' },
]);

// Initialize Form with EXACT database column names
const form = useForm({
    id: null,
    company_name: '',
    contact_person: '',
    mobile: '',
    type: 'GOLD', // Default as per schema
    gst_number: '',
    pan_no: '', // Matches database column 'pan_no'
    bank_name: '',
    account_no: '',
    ifsc_code: '',
});

// --- ACTIONS ---

const search = ref(props.filters?.search || ''); // You need to accept filters prop

// Watch Search and reload
watch(
    search,
    throttle((value) => {
        router.get(
            route('suppliers.index'),
            { search: value }, // Send search term to Laravel
            {
                preserveState: true,
                preserveScroll: true,
                replace: true, // Replaces history so "Back" button works better
            },
        );
    }, 300),
);

const onPageChange = (event) => {
    const newPage = event.page + 1;
    router.get(route('suppliers.index'), { page: newPage }, { preserveScroll: true, preserveState: true });
};

const openNew = () => {
    supplier.value = {};
    isEditing.value = false;
    form.reset();
    form.clearErrors();

    // Set default type
    form.type = 'GOLD';

    supplierDialog.value = true;
};

const editSupplier = (sup) => {
    supplier.value = { ...sup };
    isEditing.value = true;

    form.clearErrors();

    // Map existing data to form
    form.id = sup.id;
    form.company_name = sup.company_name;
    form.contact_person = sup.contact_person;
    form.mobile = sup.mobile;
    form.type = sup.type;
    form.gst_number = sup.gst_number;
    form.pan_no = sup.pan_no;
    form.bank_name = sup.bank_name;
    form.account_no = sup.account_no;
    form.ifsc_code = sup.ifsc_code;

    supplierDialog.value = true;
};

const saveSupplier = () => {
    const options = {
        onSuccess: () => {
            supplierDialog.value = false;
            toast.add({ severity: 'success', summary: 'Successful', detail: 'Supplier Saved', life: 3000 });
            form.reset();
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Please check the form', life: 3000 });
        },
    };

    if (isEditing.value) {
        form.put(route('suppliers.update', form.id), options);
    } else {
        form.post(route('suppliers.store'), options);
    }
};

const confirmDeleteSupplier = (sup) => {
    supplier.value = sup;
    deleteDialog.value = true;
};

const deleteSupplier = () => {
    router.delete(route('suppliers.destroy', supplier.value.id), {
        onSuccess: () => {
            deleteDialog.value = false;
            supplier.value = {};
            toast.add({ severity: 'success', summary: 'Successful', detail: 'Supplier Deleted', life: 3000 });
        },
    });
};

const getTypeSeverity = (type) => {
    switch (type) {
        case 'GOLD':
            return 'warning'; // Yellowish
        case 'SILVER':
            return 'secondary'; // Gray
        case 'DIAMOND':
            return 'info'; // Blue
        case 'PACKAGING':
            return 'success'; // Green
        default:
            return 'contrast';
    }
};
</script>

<template>
    <AppLayout>
        <div class="card p-4">
            <Toast />

            <div class="mb-4 flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-xl font-bold">Supplier Master</h2>

                <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                    <div class="relative w-full sm:w-64">
                        <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <InputText v-model="search" placeholder="Search..." class="w-full !pl-10" />
                    </div>

                    <div class="flex gap-2">
                        <Button @click="openNew" class="flex shrink-0 items-center gap-2 whitespace-nowrap">
                            <Plus class="h-4 w-4" />
                            <span>New Supplier</span>
                        </Button>
                    </div>
                </div>
            </div>
            <DataTable :value="suppliers.data" stripedRows tableStyle="min-width: 60rem" dataKey="id">
                <Column field="company_name" header="Firm Name" sortable>
                    <template #body="slotProps">
                        <div class="font-bold">{{ slotProps.data.company_name }}</div>
                        <div class="text-xs text-gray-500">GST: {{ slotProps.data.gst_number || 'N/A' }}</div>
                    </template>
                </Column>

                <Column field="contact_person" header="Contact Person">
                    <template #body="slotProps">
                        <div class="flex flex-col">
                            <span><i class="pi pi-user mr-1 text-xs text-gray-400"></i>{{ slotProps.data.contact_person }}</span>
                            <span class="text-sm text-blue-600"><i class="pi pi-phone mr-1 text-xs"></i>{{ slotProps.data.mobile }}</span>
                        </div>
                    </template>
                </Column>

                <Column field="type" header="Type">
                    <template #body="slotProps">
                        <Tag :value="slotProps.data.type" :severity="getTypeSeverity(slotProps.data.type)" />
                    </template>
                </Column>

                <Column header="Banking Info">
                    <template #body="slotProps">
                        <div v-if="slotProps.data.bank_name" class="text-xs">
                            <div class="font-semibold">{{ slotProps.data.bank_name }}</div>
                            <div class="text-gray-500">{{ slotProps.data.account_no }}</div>
                        </div>
                        <span v-else class="text-xs text-gray-400">Not Added</span>
                    </template>
                </Column>

                <Column header="Action" style="min-width: 8rem">
                    <template #body="slotProps">
                        <div class="flex gap-2">
                            <Button icon="pi pi-pencil" size="small" outlined severity="success" @click="editSupplier(slotProps.data)" />
                            <Button icon="pi pi-trash" size="small" outlined severity="danger" @click="confirmDeleteSupplier(slotProps.data)" />
                        </div>
                    </template>
                </Column>
            </DataTable>

            <Paginator
                :rows="suppliers.per_page"
                :totalRecords="suppliers.total"
                :first="(suppliers.current_page - 1) * suppliers.per_page"
                @page="onPageChange"
                class="mt-2 border-t border-gray-100"
            ></Paginator>

            <Dialog v-model:visible="supplierDialog" :header="isEditing ? 'Edit Supplier' : 'Add New Supplier'" :modal="true" :style="{ width: '600px' }">
                <form @submit.prevent="saveSupplier" class="mt-2 flex flex-col gap-5">
                    <div class="flex flex-col gap-3">
                        <div class="flex flex-col gap-1">
                            <label class="text-sm font-bold text-gray-700">Company Name</label>
                            <InputText v-model="form.company_name" placeholder="e.g. Raj Gold House" required autofocus :class="{ 'p-invalid': form.errors.company_name }" />
                            <small class="text-xs text-red-500" v-if="form.errors.company_name">{{ form.errors.company_name }}</small>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="flex flex-col gap-1">
                                <label class="text-sm font-bold text-gray-700">Contact Person</label>
                                <InputText v-model="form.contact_person" placeholder="e.g. Rajesh Bhai" required :class="{ 'p-invalid': form.errors.contact_person }" />
                                <small class="text-xs text-red-500" v-if="form.errors.contact_person">{{ form.errors.contact_person }}</small>
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-sm font-bold text-gray-700">Mobile</label>
                                <InputText v-model="form.mobile" placeholder="98XXXXXXXX" required :class="{ 'p-invalid': form.errors.mobile }" />
                                <small class="text-xs text-red-500" v-if="form.errors.mobile">{{ form.errors.mobile }}</small>
                            </div>
                        </div>

                        <div class="flex flex-col gap-1">
                            <label class="text-sm font-bold text-gray-700">Supplier Type</label>
                            <Dropdown v-model="form.type" :options="supplierTypes" optionLabel="label" optionValue="value" placeholder="Select Type" class="w-full" />
                            <small class="text-xs text-red-500" v-if="form.errors.type">{{ form.errors.type }}</small>
                        </div>
                    </div>

                    <div class="rounded border border-gray-100 bg-gray-50 p-3">
                        <h3 class="mb-3 text-xs font-bold tracking-wider text-gray-400 uppercase">Taxation (For Input Tax Credit)</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="flex flex-col gap-1">
                                <label class="text-sm font-bold text-gray-700">GST Number</label>
                                <InputText v-model="form.gst_number" placeholder="27XXXX..." :class="{ 'p-invalid': form.errors.gst_number }" />
                                <small class="text-xs text-red-500" v-if="form.errors.gst_number">{{ form.errors.gst_number }}</small>
                                <small class="text-xs text-gray-400" v-else>Mandatory for B2B</small>
                            </div>

                            <div class="flex flex-col gap-1">
                                <label class="text-sm font-bold text-gray-700">PAN No</label>
                                <InputText v-model="form.pan_no" placeholder="ABCDE1234F" :class="{ 'p-invalid': form.errors.pan_no }" />
                                <small class="text-xs text-red-500" v-if="form.errors.pan_no">{{ form.errors.pan_no }}</small>
                            </div>
                        </div>
                    </div>

                    <div class="rounded border border-gray-100 bg-gray-50 p-3">
                        <h3 class="mb-3 text-xs font-bold tracking-wider text-gray-400 uppercase">Bank Details (Payouts)</h3>
                        <div class="flex flex-col gap-3">
                            <div class="flex flex-col gap-1">
                                <label class="text-sm font-bold text-gray-700">Bank Name</label>
                                <InputText v-model="form.bank_name" placeholder="e.g. HDFC Bank" :class="{ 'p-invalid': form.errors.bank_name }" />
                                <small class="text-xs text-red-500" v-if="form.errors.bank_name">{{ form.errors.bank_name }}</small>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="flex flex-col gap-1">
                                    <label class="text-sm font-bold text-gray-700">Account No</label>
                                    <InputText v-model="form.account_no" placeholder="0000..." :class="{ 'p-invalid': form.errors.account_no }" />
                                    <small class="text-xs text-red-500" v-if="form.errors.account_no">{{ form.errors.account_no }}</small>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <label class="text-sm font-bold text-gray-700">IFSC Code</label>
                                    <InputText v-model="form.ifsc_code" placeholder="HDFC000..." :class="{ 'p-invalid': form.errors.ifsc_code }" />
                                    <small class="text-xs text-red-500" v-if="form.errors.ifsc_code">{{ form.errors.ifsc_code }}</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-2 flex justify-end gap-2 border-t pt-4">
                        <Button label="Cancel" text severity="secondary" @click="supplierDialog = false" />
                        <Button :label="isEditing ? 'Update Supplier' : 'Save Supplier'" type="submit" :loading="form.processing" />
                    </div>
                </form>
            </Dialog>

            <Dialog v-model:visible="deleteDialog" :style="{ width: '450px' }" header="Confirm Delete" :modal="true">
                <div class="flex items-center gap-4">
                    <i class="pi pi-exclamation-triangle text-3xl text-red-500" />
                    <span v-if="supplier">
                        Are you sure you want to delete <b>{{ supplier.company_name }}</b
                        >?
                    </span>
                </div>
                <template #footer>
                    <Button label="No" text severity="secondary" @click="deleteDialog = false" />
                    <Button label="Yes, Delete" severity="danger" @click="deleteSupplier" />
                </template>
            </Dialog>
        </div>
    </AppLayout>
</template>
