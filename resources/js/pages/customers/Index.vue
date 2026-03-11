<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Search, UserRoundPlus, Users, Wallet } from 'lucide-vue-next';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Paginator from 'primevue/paginator';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    customers: Object,
    topSpenders: Array,
    topDebtors: Array,
    totalCount: Number,
    newThisWeek: Number,
});

const page = usePage();
const search = ref('');
const toast = useToast();
const isDayOpen = computed(() => Boolean(page.props.dayStatus?.is_open));
const customerDialog = ref(false);
const editingCustomer = ref(null);

const form = useForm({
    id: null,
    name: '',
    mobile: '',
    email: '',
    address: '',
    city: '',
    pan_no: '',
    aadhaar_no: '',
    dob: '',
    anniversary_date: '',
    membership_id: '',
});

const breadcrumbs = [
    {
        title: 'Customers',
        href: '/customers',
    },
];

const filteredCustomers = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (!term) {
        return props.customers.data;
    }

    return props.customers.data.filter((customer) => {
        const haystack = [customer.name, customer.city, customer.mobile, customer.pan_no].filter(Boolean).join(' ').toLowerCase();

        return haystack.includes(term);
    });
});

const totalOutstanding = computed(() => props.customers.data.reduce((sum, customer) => sum + Math.max(Number(customer.balance || 0), 0), 0));

const averageSpend = computed(() => {
    if (!props.customers.data.length) {
        return 0;
    }

    const total = props.customers.data.reduce((sum, customer) => sum + Number(customer.total_spend || 0), 0);
    return total / props.customers.data.length;
});

const onPageChange = (event) => {
    router.get(
        route('customers.index'),
        { page: event.page + 1 },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const formatMoney = (value) =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 0,
    }).format(Number(value || 0));

const balanceSeverity = (balance) => (Number(balance || 0) > 0 ? 'danger' : 'success');

const openCreateCustomer = () => {
    if (!isDayOpen.value) {
        toast.add({ severity: 'warn', summary: 'Day Closed', detail: 'Open the shop day first from the dashboard.', life: 3000 });
        return;
    }
    editingCustomer.value = null;
    form.reset();
    form.clearErrors();
    customerDialog.value = true;
};

const editCustomer = (customer) => {
    editingCustomer.value = customer;
    form.clearErrors();
    form.id = customer.id;
    form.name = customer.name || '';
    form.mobile = customer.mobile || '';
    form.email = customer.email || '';
    form.address = customer.address || '';
    form.city = customer.city || '';
    form.pan_no = customer.pan_no || '';
    form.aadhaar_no = customer.aadhaar_no || '';
    form.dob = customer.dob || '';
    form.anniversary_date = customer.anniversary_date || '';
    form.membership_id = customer.membership_id || '';
    customerDialog.value = true;
};

const saveCustomer = () => {
    const isEditing = Boolean(editingCustomer.value);
    const endpoint = isEditing ? route('customers.update', editingCustomer.value.id) : route('customers.store');

    form.transform((data) => ({
        ...data,
        _method: isEditing ? 'put' : 'post',
    })).post(endpoint, {
        preserveScroll: true,
        onSuccess: () => {
            customerDialog.value = false;
            editingCustomer.value = null;
            toast.add({
                severity: 'success',
                summary: 'Saved',
                detail: isEditing ? 'Customer updated successfully' : 'Customer created successfully',
                life: 3000,
            });
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Please check the customer form',
                life: 3000,
            });
        },
    });
};

const deleteCustomer = (customer) => {
    if (!window.confirm(`Delete customer "${customer.name}"?`)) return;

    router.delete(route('customers.destroy', customer.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Deleted',
                detail: 'Customer deleted successfully',
                life: 3000,
            });
        },
        onError: (errors) => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: errors.customer || 'Unable to delete customer',
                life: 3000,
            });
        },
    });
};
</script>

<template>
    <Head title="Customers" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <Toast />
        <div class="space-y-6">
            <section class="relative overflow-hidden border border-surface-200 bg-white">
                <div class="absolute inset-y-0 right-0 hidden w-80 bg-[radial-gradient(circle_at_top_right,_rgba(217,119,6,0.14),_transparent_62%)] lg:block" />
                <div class="relative flex flex-col gap-6 px-5 py-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                                <Users class="h-5 w-5" />
                            </span>
                            <div>
                                <p class="text-xs font-semibold tracking-[0.22em] text-amber-700 uppercase">Customer Accounts</p>
                                <h1 class="text-2xl font-semibold tracking-tight text-surface-900">Relationship and recovery desk</h1>
                            </div>
                        </div>

                        <p class="mt-4 max-w-2xl text-sm leading-6 text-surface-600">Monitor dues, identify premium buyers, and move straight into an account ledger without jumping across screens.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="min-w-40 border border-surface-200 bg-surface-0 px-4 py-3">
                            <p class="text-xs font-medium tracking-wide text-surface-500 uppercase">Customers</p>
                            <p class="mt-2 text-2xl font-semibold text-surface-900">{{ totalCount }}</p>
                        </div>
                        <div class="min-w-40 border border-emerald-200 bg-emerald-50 px-4 py-3">
                            <p class="text-xs font-medium tracking-wide text-emerald-700 uppercase">New This Week</p>
                            <p class="mt-2 text-2xl font-semibold text-emerald-700">{{ newThisWeek }}</p>
                        </div>
                        <div class="min-w-40 border border-amber-200 bg-amber-50 px-4 py-3">
                            <p class="text-xs font-medium tracking-wide text-amber-700 uppercase">Open Due</p>
                            <p class="mt-2 text-2xl font-semibold text-amber-700">{{ formatMoney(totalOutstanding) }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 xl:grid-cols-4">
                <div class="border border-surface-200 bg-white p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm text-surface-500">Average spend on this page</p>
                            <p class="mt-2 text-2xl font-semibold text-surface-900">{{ formatMoney(averageSpend) }}</p>
                        </div>
                        <span class="rounded-full bg-surface-100 p-2 text-surface-600">
                            <Wallet class="h-4 w-4" />
                        </span>
                    </div>
                </div>

                <div class="border border-surface-200 bg-white p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm text-surface-500">Visible records</p>
                            <p class="mt-2 text-2xl font-semibold text-surface-900">{{ filteredCustomers.length }}</p>
                        </div>
                        <span class="rounded-full bg-surface-100 p-2 text-surface-600">
                            <Users class="h-4 w-4" />
                        </span>
                    </div>
                </div>

                <div class="border border-surface-200 bg-white px-5 py-4 xl:col-span-2">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="text-sm font-medium text-surface-900">Quick search</p>
                            <p class="mt-1 text-sm text-surface-500">Filter the current page by name, city, phone, or PAN.</p>
                        </div>

                        <div class="relative w-full lg:w-96">
                            <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-surface-400" />
                            <InputText v-model="search" placeholder="Search customers on this page..." class="w-full !pl-10" />
                        </div>

                        <Button label="New Customer" icon="pi pi-plus" class="!w-auto shrink-0 whitespace-nowrap" @click="openCreateCustomer" :disabled="!isDayOpen" />
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div class="card overflow-hidden !p-0 xl:col-span-2">
                    <div class="border-b border-surface-200 bg-white px-5 py-4">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h3 class="text-base font-semibold text-surface-900">Customer list</h3>
                                <p class="mt-1 text-sm text-surface-500">Open profile or ledger directly from the table.</p>
                            </div>

                            <Tag :value="`Page ${customers.current_page} of ${customers.last_page}`" severity="secondary" />
                        </div>
                    </div>

                    <div class="bg-white p-4">
                        <DataTable :value="filteredCustomers" stripedRows rowHover tableStyle="min-width: 62rem">
                            <template #empty>
                                <div class="py-12 text-center text-surface-500">No customers match the current search.</div>
                            </template>

                            <Column field="name" header="Customer" sortable>
                                <template #body="{ data }">
                                    <div class="flex items-center gap-3">
                                        <Avatar :label="data.name.charAt(0)" shape="circle" class="bg-amber-100 text-amber-700" />
                                        <div class="min-w-0">
                                            <Link :href="route('customers.show', data.id)" class="truncate font-medium text-surface-900 hover:text-amber-700">
                                                {{ data.name }}
                                            </Link>
                                            <p class="mt-1 truncate text-xs text-surface-500">{{ data.city || 'City not set' }}</p>
                                        </div>
                                    </div>
                                </template>
                            </Column>

                            <Column header="Contact" field="mobile">
                                <template #body="{ data }">
                                    <div class="text-sm">
                                        <p class="font-medium text-surface-800">{{ data.mobile || 'No mobile' }}</p>
                                        <p class="mt-1 text-xs text-surface-500">{{ data.pan_no || 'PAN not available' }}</p>
                                    </div>
                                </template>
                            </Column>

                            <Column field="total_spend" header="Total Spend" sortable>
                                <template #body="{ data }">
                                    <span class="font-semibold text-surface-900">{{ formatMoney(data.total_spend) }}</span>
                                </template>
                            </Column>

                            <Column field="balance" header="Outstanding" sortable>
                                <template #body="{ data }">
                                    <div class="flex items-center gap-2">
                                        <Tag :severity="balanceSeverity(data.balance)" :value="Number(data.balance || 0) > 0 ? 'Due' : 'Settled'" />
                                        <span :class="Number(data.balance || 0) > 0 ? 'font-semibold text-red-600' : 'font-semibold text-emerald-600'">
                                            {{ formatMoney(data.balance) }}
                                        </span>
                                    </div>
                                </template>
                            </Column>

                            <Column header="" style="width: 280px">
                                <template #body="{ data }">
                                    <div class="flex justify-end gap-2">
                                        <Link :href="route('customers.show', data.id)">
                                            <Button label="Profile" icon="pi pi-user" text size="small" />
                                        </Link>
                                        <Link :href="route('ledger.show', { type: 'customers', id: data.id })">
                                            <Button label="Ledger" icon="pi pi-book" text size="small" />
                                        </Link>
                                        <Button icon="pi pi-pencil" text size="small" @click="editCustomer(data)" />
                                        <Button icon="pi pi-trash" text severity="danger" size="small" @click="deleteCustomer(data)" />
                                    </div>
                                </template>
                            </Column>
                        </DataTable>

                        <Paginator
                            v-if="customers.total > 0"
                            :rows="customers.per_page"
                            :totalRecords="customers.total"
                            :first="(customers.current_page - 1) * customers.per_page"
                            @page="onPageChange"
                            class="mt-4 border-t border-surface-200"
                        />
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="overflow-hidden border border-surface-200 bg-white">
                        <div class="border-b border-surface-200 px-5 py-4">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-amber-100 text-amber-700">1</span>
                                <div>
                                    <h3 class="text-base font-semibold text-surface-900">Highest spenders</h3>
                                    <p class="mt-1 text-sm text-surface-500">Best value customers this cycle.</p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4 p-5">
                            <div v-for="(vip, index) in topSpenders" :key="vip.id" class="flex items-center justify-between gap-3 border-b border-surface-100 pb-4 last:border-b-0 last:pb-0">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="w-5 text-xs font-medium text-surface-400">{{ index + 1 }}</span>
                                    <Avatar :label="vip.name[0]" shape="circle" size="small" class="bg-surface-100 text-surface-700" />
                                    <div class="min-w-0">
                                        <Link :href="route('customers.show', vip.id)" class="truncate text-sm font-medium text-surface-900 hover:text-amber-700">
                                            {{ vip.name }}
                                        </Link>
                                    </div>
                                </div>
                                <span class="text-sm font-semibold text-amber-700">{{ formatMoney(vip.total_spend) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-hidden border border-surface-200 bg-white">
                        <div class="border-b border-surface-200 px-5 py-4">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-red-100 text-red-700">
                                    <UserRoundPlus class="h-4 w-4" />
                                </span>
                                <div>
                                    <h3 class="text-base font-semibold text-surface-900">Priority recovery</h3>
                                    <p class="mt-1 text-sm text-surface-500">Customers with the highest due amount.</p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4 p-5">
                            <div v-for="(debtor, index) in topDebtors" :key="debtor.id" class="flex items-center justify-between gap-3 border-b border-surface-100 pb-4 last:border-b-0 last:pb-0">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="w-5 text-xs font-medium text-surface-400">{{ index + 1 }}</span>
                                    <div class="min-w-0">
                                        <Link :href="route('ledger.show', { type: 'customers', id: debtor.id })" class="truncate text-sm font-medium text-surface-900 hover:text-red-600">
                                            {{ debtor.name }}
                                        </Link>
                                        <p class="mt-1 text-xs text-surface-500">Open ledger for follow-up</p>
                                    </div>
                                </div>
                                <span class="text-sm font-semibold text-red-600">{{ formatMoney(debtor.balance) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <Dialog v-model:visible="customerDialog" :header="editingCustomer ? 'Edit Customer' : 'New Customer'" modal class="w-full max-w-3xl">
            <div class="grid gap-5 pt-2 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Name</label>
                    <InputText v-model="form.name" class="w-full" placeholder="Customer full name" />
                    <small v-if="form.errors.name" class="mt-1 block text-xs text-red-500">{{ form.errors.name }}</small>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Mobile</label>
                    <InputText v-model="form.mobile" class="w-full" placeholder="10 digit mobile number" />
                    <small v-if="form.errors.mobile" class="mt-1 block text-xs text-red-500">{{ form.errors.mobile }}</small>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Email</label>
                    <InputText v-model="form.email" type="email" class="w-full" placeholder="Optional email address" />
                    <small v-if="form.errors.email" class="mt-1 block text-xs text-red-500">{{ form.errors.email }}</small>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">City</label>
                    <InputText v-model="form.city" class="w-full" placeholder="City / area" />
                    <small v-if="form.errors.city" class="mt-1 block text-xs text-red-500">{{ form.errors.city }}</small>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">PAN</label>
                    <InputText v-model="form.pan_no" class="w-full" placeholder="Optional PAN number" />
                    <small v-if="form.errors.pan_no" class="mt-1 block text-xs text-red-500">{{ form.errors.pan_no }}</small>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Aadhaar</label>
                    <InputText v-model="form.aadhaar_no" class="w-full" placeholder="Optional Aadhaar number" />
                    <small v-if="form.errors.aadhaar_no" class="mt-1 block text-xs text-red-500">{{ form.errors.aadhaar_no }}</small>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Date of Birth</label>
                    <InputText v-model="form.dob" type="date" class="w-full" />
                    <small v-if="form.errors.dob" class="mt-1 block text-xs text-red-500">{{ form.errors.dob }}</small>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Anniversary Date</label>
                    <InputText v-model="form.anniversary_date" type="date" class="w-full" />
                    <small v-if="form.errors.anniversary_date" class="mt-1 block text-xs text-red-500">{{ form.errors.anniversary_date }}</small>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Membership ID</label>
                    <InputText v-model="form.membership_id" class="w-full" placeholder="Optional scheme/member ID" />
                    <small v-if="form.errors.membership_id" class="mt-1 block text-xs text-red-500">{{ form.errors.membership_id }}</small>
                </div>

                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-surface-700">Address</label>
                    <Textarea v-model="form.address" rows="3" class="w-full" placeholder="Optional address" />
                    <small v-if="form.errors.address" class="mt-1 block text-xs text-red-500">{{ form.errors.address }}</small>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button label="Cancel" text severity="secondary" @click="customerDialog = false" />
                    <Button :label="editingCustomer ? 'Update Customer' : 'Create Customer'" :loading="form.processing" :disabled="form.processing" @click="saveCustomer" />
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>
