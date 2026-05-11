<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

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
import Paginator from 'primevue/paginator';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { formatIndianDate } from '@/utils/indiaTime';

const props = defineProps({
    expenses: Object, // Paginated List
});

const page = usePage();
const confirm = useConfirm();
const toast = useToast();
const showDialog = ref(false);
const isDayOpen = computed(() => Boolean(page.props.dayStatus?.is_open));
const expensesList = computed(() => props.expenses?.data || []);

const form = useForm({
    title: '',
    category: 'Food',
    amount: null,
    payment_method: 'CASH',
    date: new Date(),
});

const categories = ['Food', 'Travel', 'Utility', 'Salary', 'Repair', 'Other'];
const methods = ['CASH', 'UPI', 'BANK'];

const totalRecords = computed(() => props.expenses?.total || 0);
const pageSpend = computed(() => expensesList.value.reduce((sum, row) => sum + Number(row.amount || 0), 0));
const cashCount = computed(() => expensesList.value.filter((row) => row.payment_method === 'CASH').length);
const digitalCount = computed(() => expensesList.value.filter((row) => row.payment_method !== 'CASH').length);

const openExpenseDialog = () => {
    if (!isDayOpen.value) {
        toast.add({ severity: 'warn', summary: 'Day Closed', detail: 'Open the shop day to record expenses.', life: 3000 });
        return;
    }

    form.reset();
    form.clearErrors();
    form.date = new Date();
    showDialog.value = true;
};

const saveExpense = () => {
    if (!isDayOpen.value) {
        toast.add({ severity: 'warn', summary: 'Day Closed', detail: 'Open the shop day to record expenses.', life: 3000 });
        return;
    }

    form.post(route('expenses.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showDialog.value = false;
            form.reset();
            form.date = new Date();
            toast.add({ severity: 'success', summary: 'Saved', detail: 'Expense recorded successfully.', life: 2500 });
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Validation Error', detail: 'Please correct the highlighted fields.', life: 3000 });
        },
    });
};

const deleteExpense = (event, id) => {
    if (!isDayOpen.value) {
        toast.add({ severity: 'warn', summary: 'Day Closed', detail: 'Open the shop day to delete expenses.', life: 3000 });
        return;
    }

    confirm.require({
        target: event.currentTarget,
        message: 'Delete this expense? Money will be refunded to the vault.',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: () => {
            router.delete(route('expenses.destroy', id), {
                preserveScroll: true,
                onSuccess: () => {
                    toast.add({ severity: 'success', summary: 'Deleted', detail: 'Expense deleted and refunded.', life: 2500 });
                },
            });
        },
    });
};

const formatCurrency = (val) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(val);
const formatDate = (date) => formatIndianDate(date);

const onPageChange = (event: { page: number }) => {
    router.get(route('expenses.index'), { page: event.page + 1 }, { preserveScroll: true, preserveState: true });
};
</script>

<template>
    <AppLayout>
        <Toast />
        <div class="space-y-6">
            <section class="border border-surface-200 bg-white px-5 py-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl">
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-2xl font-semibold tracking-tight text-surface-900">Expense Desk</h1>
                            <Tag value="Daily Operations" severity="secondary" />
                        </div>
                        <p class="mt-2 text-sm leading-6 text-surface-600">
                            Record daily cash and bank expenses with clear payment tagging and quick cleanup controls.
                        </p>
                    </div>

                    <div class="flex justify-start lg:justify-end">
                        <Button label="New Expense" icon="pi pi-plus" @click="openExpenseDialog" :disabled="!isDayOpen" />
                    </div>
                </div>
            </section>

            <section v-if="!isDayOpen" class="border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Day is currently closed. Open the day from dashboard to create or delete expenses.
            </section>

            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Total Entries</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ totalRecords }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">This Page Spend</p>
                    <p class="mt-2 text-2xl font-semibold text-red-600">{{ formatCurrency(pageSpend) }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Cash Payments</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ cashCount }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Bank/UPI Payments</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ digitalCount }}</p>
                </div>
            </section>

            <section class="overflow-hidden border border-surface-200 bg-white">
                <div class="border-b border-surface-200 px-5 py-4">
                    <h2 class="text-lg font-semibold text-surface-900">Expense Register</h2>
                    <p class="mt-1 text-sm text-surface-500">Recent expenses with payment method and deduction source.</p>
                </div>

                <div class="p-4">
                    <DataTable :value="expensesList" stripedRows rowHover tableStyle="min-width: 60rem">
                        <template #empty>
                            <div class="py-12 text-center text-surface-500">No expenses recorded yet.</div>
                        </template>

                        <Column field="created_at" header="Date">
                            <template #body="slotProps">
                                <span class="text-surface-700">{{ formatDate(slotProps.data.created_at) }}</span>
                            </template>
                        </Column>
                        <Column field="title" header="Description">
                            <template #body="slotProps">
                                <span class="font-semibold text-surface-900">{{ slotProps.data.title }}</span>
                                <div class="mt-1 text-xs text-surface-500">{{ slotProps.data.category }}</div>
                            </template>
                        </Column>
                        <Column field="payment_method" header="Paid Via">
                            <template #body="slotProps">
                                <Tag :value="slotProps.data.payment_method" :severity="slotProps.data.payment_method === 'CASH' ? 'success' : 'info'" />
                            </template>
                        </Column>
                        <Column header="Recorded By">
                            <template #body="slotProps">
                                <span class="text-sm text-surface-700">{{ slotProps.data.user?.name || 'System' }}</span>
                            </template>
                        </Column>
                        <Column field="amount" header="Amount">
                            <template #body="slotProps">
                                <span class="font-mono font-bold text-red-600">-{{ formatCurrency(slotProps.data.amount) }}</span>
                            </template>
                        </Column>
                        <Column header="Action">
                            <template #body="slotProps">
                                <Button icon="pi pi-trash" text rounded severity="danger" @click="deleteExpense($event, slotProps.data.id)" :disabled="!isDayOpen" />
                            </template>
                        </Column>
                    </DataTable>

                    <Paginator
                        v-if="expenses.total > 0"
                        :rows="expenses.per_page"
                        :totalRecords="expenses.total"
                        :first="(expenses.current_page - 1) * expenses.per_page"
                        @page="onPageChange"
                        class="mt-4 border-t border-surface-200"
                    />
                </div>
            </section>
        </div>

        <Dialog v-model:visible="showDialog" modal header="Add Expense Entry" class="w-full max-w-lg">
            <div class="space-y-4 pt-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Date</label>
                    <Calendar v-model="form.date" dateFormat="dd/mm/yy" showIcon class="w-full" inputClass="w-full" />
                    <small class="text-red-500" v-if="form.errors.date">{{ form.errors.date }}</small>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Description</label>
                    <InputText v-model="form.title" placeholder="e.g. Evening Snacks" class="w-full" :invalid="!!form.errors.title" />
                    <small class="mt-1 block font-bold text-red-500" v-if="form.errors.title">
                        {{ form.errors.title }}
                    </small>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Category</label>
                        <Dropdown v-model="form.category" :options="categories" class="w-full" :invalid="!!form.errors.category" />
                        <small class="mt-1 block font-bold text-red-500" v-if="form.errors.category">
                            {{ form.errors.category }}
                        </small>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-surface-700">Payment Method</label>
                        <Dropdown v-model="form.payment_method" :options="methods" class="w-full" :invalid="!!form.errors.payment_method" />
                        <small class="mt-1 block font-bold text-red-500" v-if="form.errors.payment_method">
                            {{ form.errors.payment_method }}
                        </small>
                    </div>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Amount (INR)</label>
                    <InputNumber v-model="form.amount" mode="currency" currency="INR" locale="en-IN" class="w-full" :invalid="!!form.errors.amount" />
                    <small class="mt-1 block font-bold text-red-500" v-if="form.errors.amount">
                        {{ form.errors.amount }}
                    </small>
                </div>
                <Button label="Save Expense" icon="pi pi-check" class="mt-2 w-full" @click="saveExpense" :loading="form.processing" />
            </div>
        </Dialog>

        <ConfirmPopup />
    </AppLayout>
</template>
