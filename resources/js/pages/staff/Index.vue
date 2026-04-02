<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import throttle from 'lodash/throttle';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';

import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    staff: Array,
    availableUsers: Array,
    metrics: Object,
    filters: Object,
});

const toast = useToast();
const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || 'all');
const staffDialog = ref(false);
const deleteDialog = ref(false);
const deleteTarget = ref(null);
const isEditing = ref(false);

const staffForm = useForm({
    id: null,
    name: '',
    mobile: '',
    address: '',
    designation: '',
    joining_date: '',
    is_active: true,
    salary_amount: null,
    user_id: null,
});

watch(
    search,
    throttle((value) => {
        router.get(route('staff.index'), { search: value, status: statusFilter.value }, { preserveState: true, preserveScroll: true, replace: true });
    }, 300),
);

watch(statusFilter, (value) => {
    router.get(route('staff.index'), { search: search.value, status: value }, { preserveState: true, preserveScroll: true, replace: true });
});

const formatCurrency = (value) =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 0,
    }).format(value || 0);

const linkedUserOptions = computed(() =>
    props.availableUsers.map((user) => ({
        label: user.staff_id ? `${user.name} (${user.email}) • Linked` : `${user.name} (${user.email})`,
        value: user.id,
        attendance_enabled: user.attendance_enabled,
        disabled: user.staff_id && user.staff_id !== staffForm.id,
    })),
);

const openStaffDialog = (staffMember = null) => {
    staffForm.reset();
    staffForm.clearErrors();
    isEditing.value = Boolean(staffMember);

    if (staffMember) {
        staffForm.id = staffMember.id;
        staffForm.name = staffMember.name;
        staffForm.mobile = staffMember.mobile;
        staffForm.address = staffMember.address || '';
        staffForm.designation = staffMember.designation;
        staffForm.joining_date = staffMember.joining_date;
        staffForm.is_active = Boolean(staffMember.is_active);
        staffForm.salary_amount = staffMember.salary_amount;
        staffForm.user_id = staffMember.user_id;
    }

    staffDialog.value = true;
};

const saveStaff = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            staffDialog.value = false;
            toast.add({
                severity: 'success',
                summary: 'Saved',
                detail: isEditing.value ? 'Staff profile updated successfully' : 'Staff profile created successfully',
                life: 3000,
            });
        },
    };

    if (isEditing.value) {
        staffForm.put(route('staff.update', staffForm.id), options);
        return;
    }

    staffForm.post(route('staff.store'), options);
};

const confirmDelete = (staffMember) => {
    deleteTarget.value = staffMember;
    deleteDialog.value = true;
};

const deleteStaff = () => {
    if (!deleteTarget.value) return;

    router.delete(route('staff.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deleteDialog.value = false;
            deleteTarget.value = null;
            toast.add({
                severity: 'success',
                summary: 'Deleted',
                detail: 'Staff profile deleted successfully',
                life: 3000,
            });
        },
    });
};
</script>

<template>
    <AppLayout>
        <Toast />
        <div class="space-y-6">
            <section class="border border-surface-200 bg-white px-5 py-6">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl">
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-2xl font-semibold tracking-tight text-surface-900">Staff Management</h1>
                            <Tag value="People Ops" severity="secondary" />
                        </div>
                        <p class="mt-2 text-sm leading-6 text-surface-600">
                            Manage employee profiles, designation, salary, joining date, and link each person to a POS user for attendance and access control.
                        </p>
                    </div>

                    <div class="flex justify-start lg:justify-end">
                        <Button label="New Staff" icon="pi pi-user-plus" @click="openStaffDialog()" />
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Staff Profiles</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ metrics?.staff_count || 0 }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Active Staff</p>
                    <p class="mt-2 text-2xl font-semibold text-emerald-700">{{ metrics?.active_staff || 0 }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Monthly Salary</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ formatCurrency(metrics?.monthly_salary) }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Linked Users</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ metrics?.linked_users || 0 }}</p>
                </div>
            </section>

            <section class="overflow-hidden border border-surface-200 bg-white">
                <div class="border-b border-surface-200 px-5 py-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-surface-900">Staff Register</h2>
                            <p class="mt-1 text-sm text-surface-500">All staff records with linked users and attendance readiness.</p>
                        </div>

                        <div class="w-full lg:max-w-sm">
                            <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_180px]">
                                <InputText v-model="search" placeholder="Search staff..." class="w-full" />
                                <Select
                                    v-model="statusFilter"
                                    :options="[
                                        { label: 'Active Only', value: 'active' },
                                        { label: 'Inactive Only', value: 'inactive' },
                                        { label: 'All Staff', value: 'all' },
                                    ]"
                                    optionLabel="label"
                                    optionValue="value"
                                    class="w-full"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-4">
                    <DataTable :value="staff" stripedRows rowHover tableStyle="min-width: 72rem">
                        <template #empty>
                            <div class="py-12 text-center text-surface-500">No staff records found.</div>
                        </template>

                        <Column field="name" header="Staff">
                            <template #body="{ data }">
                                <div>
                                    <p class="font-medium text-surface-900">{{ data.name }}</p>
                                    <p class="mt-1 text-xs text-surface-500">{{ data.designation }}</p>
                                </div>
                            </template>
                        </Column>

                        <Column field="mobile" header="Contact">
                            <template #body="{ data }">
                                <div>
                                    <p class="text-sm font-medium text-surface-800">{{ data.mobile }}</p>
                                    <p class="mt-1 text-xs text-surface-500">{{ data.joining_date }}</p>
                                </div>
                            </template>
                        </Column>

                        <Column field="salary_amount" header="Salary">
                            <template #body="{ data }">
                                <span class="font-semibold text-surface-900">{{ formatCurrency(data.salary_amount) }}</span>
                            </template>
                        </Column>

                        <Column header="Linked User">
                            <template #body="{ data }">
                                <div v-if="data.user" class="space-y-1">
                                    <p class="text-sm font-medium text-surface-900">{{ data.user.name }}</p>
                                    <p class="text-xs text-surface-500">{{ data.user.email }}</p>
                                    <Tag :value="data.user.attendance_enabled ? 'Attendance Ready' : 'Attendance Off'" :severity="data.user.attendance_enabled ? 'success' : 'secondary'" />
                                </div>
                                <span v-else class="text-sm text-surface-500">Not linked</span>
                            </template>
                        </Column>

                        <Column header="Status">
                            <template #body="{ data }">
                                <Tag :value="data.is_active ? 'Active' : 'Inactive'" :severity="data.is_active ? 'success' : 'danger'" />
                            </template>
                        </Column>

                        <Column header="" style="width: 160px">
                            <template #body="{ data }">
                                <div class="flex justify-end gap-1">
                                    <Button icon="pi pi-pencil" text size="small" @click="openStaffDialog(data)" />
                                    <Button icon="pi pi-trash" text severity="danger" size="small" @click="confirmDelete(data)" />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </section>
        </div>

        <Dialog v-model:visible="staffDialog" :header="isEditing ? 'Edit Staff' : 'Create Staff'" modal class="w-full max-w-3xl">
            <div class="grid gap-5 pt-2 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Name</label>
                    <InputText v-model="staffForm.name" class="w-full" placeholder="Enter full name" />
                    <small v-if="staffForm.errors.name" class="mt-1 block text-xs text-red-500">{{ staffForm.errors.name }}</small>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Mobile</label>
                    <InputText v-model="staffForm.mobile" class="w-full" placeholder="Enter mobile number" />
                    <small v-if="staffForm.errors.mobile" class="mt-1 block text-xs text-red-500">{{ staffForm.errors.mobile }}</small>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Designation</label>
                    <InputText v-model="staffForm.designation" class="w-full" placeholder="Manager, Sales, Accountant..." />
                    <small v-if="staffForm.errors.designation" class="mt-1 block text-xs text-red-500">{{ staffForm.errors.designation }}</small>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Joining Date</label>
                    <InputText v-model="staffForm.joining_date" type="date" class="w-full" />
                    <small v-if="staffForm.errors.joining_date" class="mt-1 block text-xs text-red-500">{{ staffForm.errors.joining_date }}</small>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Salary Amount</label>
                    <InputText v-model="staffForm.salary_amount" type="number" class="w-full" placeholder="Enter monthly salary" />
                    <small v-if="staffForm.errors.salary_amount" class="mt-1 block text-xs text-red-500">{{ staffForm.errors.salary_amount }}</small>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Link User</label>
                    <Select
                        v-model="staffForm.user_id"
                        :options="linkedUserOptions"
                        optionLabel="label"
                        optionValue="value"
                        optionDisabled="disabled"
                        class="w-full"
                        placeholder="Optional user link"
                        showClear
                    />
                    <small v-if="staffForm.errors.user_id" class="mt-1 block text-xs text-red-500">{{ staffForm.errors.user_id }}</small>
                </div>

                <div class="md:col-span-2">
                    <label class="mb-2 flex items-center gap-3 text-sm font-medium text-surface-700">
                        <Checkbox v-model="staffForm.is_active" binary />
                        <span>Staff is active</span>
                    </label>
                </div>

                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-surface-700">Address</label>
                    <Textarea v-model="staffForm.address" rows="3" class="w-full" placeholder="Optional address" />
                    <small v-if="staffForm.errors.address" class="mt-1 block text-xs text-red-500">{{ staffForm.errors.address }}</small>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button label="Cancel" text severity="secondary" @click="staffDialog = false" />
                    <Button :label="isEditing ? 'Update Staff' : 'Create Staff'" :loading="staffForm.processing" :disabled="staffForm.processing" @click="saveStaff" />
                </div>
            </template>
        </Dialog>

        <Dialog v-model:visible="deleteDialog" header="Delete Staff" modal class="w-full max-w-md">
            <p class="text-sm text-surface-600">
                Delete staff profile
                <span class="font-medium text-surface-900">{{ deleteTarget?.name }}</span
                >?
            </p>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button label="Cancel" text severity="secondary" @click="deleteDialog = false" />
                    <Button label="Delete" severity="danger" @click="deleteStaff" />
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>
