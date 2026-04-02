<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

// PrimeVue Components
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Column from 'primevue/column';
import ConfirmPopup from 'primevue/confirmpopup';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import { formatIndianDate } from '@/utils/indiaTime';

const props = defineProps({
    expenses: Object, // Paginated List
});

const page = usePage();
const confirm = useConfirm();
const showDialog = ref(false);
const isDayOpen = computed(() => Boolean(page.props.dayStatus?.is_open));

const form = useForm({
    title: '',
    category: 'Food',
    amount: null,
    payment_method: 'CASH',
    date: new Date(),
});

const categories = ['Food', 'Travel', 'Utility', 'Salary', 'Repair', 'Other'];
const methods = ['CASH', 'UPI', 'BANK'];

const saveExpense = () => {
    if (!isDayOpen.value) {
        return;
    }

    form.post(route('expenses.store'), {
        onSuccess: () => {
            showDialog.value = false;
            form.reset();
        },
    });
};

const deleteExpense = (event, id) => {
    if (!isDayOpen.value) {
        return;
    }

    confirm.require({
        target: event.currentTarget,
        message: 'Delete this expense? Money will be refunded to the vault.',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: () => {
            router.delete(route('expenses.destroy', id));
        },
    });
};

const formatCurrency = (val) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(val);
const formatDate = (date) => formatIndianDate(date);
</script>

<template>
    <AppLayout>
        <div class="min-h-screen space-y-6 bg-slate-50 p-6">
            <div class="flex flex-col items-center justify-between rounded-xl border border-gray-100 bg-white p-4 shadow-sm md:flex-row">
                <div>
                    <h1 class="text-xl font-bold text-gray-800">Daily Expenses</h1>
                    <p class="text-sm text-gray-500">Track shop spending (Tea, Petrol, Bills)</p>
                </div>
                <Button label="New Expense" icon="pi pi-plus" class="p-button-danger" @click="showDialog = true" :disabled="!isDayOpen" />
            </div>

            <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                <DataTable :value="expenses.data" stripedRows size="small">
                    <Column field="created_at" header="Date">
                        <template #body="slotProps">
                            <span class="text-gray-600">{{ formatDate(slotProps.data.created_at) }}</span>
                        </template>
                    </Column>
                    <Column field="title" header="Description">
                        <template #body="slotProps">
                            <span class="font-bold text-gray-800">{{ slotProps.data.title }}</span>
                            <div class="text-xs text-gray-400">{{ slotProps.data.category }}</div>
                        </template>
                    </Column>
                    <Column field="payment_method" header="Paid Via">
                        <template #body="slotProps">
                            <Tag :value="slotProps.data.payment_method" :severity="slotProps.data.payment_method === 'CASH' ? 'success' : 'info'" />
                        </template>
                    </Column>
                    <Column field="amount" header="Amount">
                        <template #body="slotProps">
                            <span class="font-mono font-bold text-red-600">-{{ formatCurrency(slotProps.data.amount) }}</span>
                        </template>
                    </Column>
                    <Column header="Action">
                        <template #body="slotProps">
                            <Button icon="pi pi-trash" text rounded severity="danger" @click="deleteExpense($event, slotProps.data.id)" />
                        </template>
                    </Column>
                </DataTable>

                <div class="mt-4 flex justify-center gap-2" v-if="expenses.links">
                    <template v-for="link in expenses.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="rounded border px-3 py-1 text-sm"
                            :class="link.active ? 'bg-gray-900 text-white' : 'bg-white text-gray-700'"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>

        <Dialog v-model:visible="showDialog" modal header="📉 Add New Expense" class="w-full max-w-sm">
            <div class="space-y-4 pt-2">
                <div>
                    <label class="mb-1 block text-xs font-bold text-gray-700">Date</label>
                    <Calendar v-model="form.date" dateFormat="dd/mm/yy" showIcon class="w-full" inputClass="w-full" />
                    <small class="text-red-500" v-if="form.errors.date">{{ form.errors.date }}</small>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-bold text-gray-700">Description</label>
                    <InputText v-model="form.title" placeholder="e.g. Evening Snacks" class="w-full" :invalid="!!form.errors.title" />
                    <small class="mt-1 block font-bold text-red-500" v-if="form.errors.amount">
                        {{ form.errors.title }}
                    </small>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-bold text-gray-700">Category</label>
                        <Dropdown v-model="form.category" :options="categories" class="w-full" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-bold text-gray-700">Payment Method</label>
                        <Dropdown v-model="form.payment_method" :options="methods" class="w-full" />
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-bold text-gray-700">Amount (₹)</label>
                    <InputNumber v-model="form.amount" mode="currency" currency="INR" locale="en-IN" class="w-full" :invalid="!!form.errors.amount" />
                    <small class="mt-1 block font-bold text-red-500" v-if="form.errors.amount">
                        {{ form.errors.amount }}
                    </small>
                </div>
                <Button label="Save Expense" icon="pi pi-check" class="p-button-danger mt-2 w-full" @click="saveExpense" :loading="form.processing" />
            </div>
        </Dialog>

        <ConfirmPopup />
    </AppLayout>
</template>
