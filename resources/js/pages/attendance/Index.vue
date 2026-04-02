<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import throttle from 'lodash/throttle';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';

import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
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
    records: Array,
    reasons: Array,
    filters: Object,
    metrics: Object,
});

const toast = useToast();
const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'all');
const attendanceDate = ref(props.filters?.date || new Date().toISOString().slice(0, 10));
const reopenDialog = ref(false);
const reopenTarget = ref(null);
const reasonDialog = ref(false);
const reasonDeleteDialog = ref(false);
const editingReason = ref(null);
const deleteReasonTarget = ref(null);

const reopenForm = useForm({
    note: '',
});

const reasonForm = useForm({
    id: null,
    label: '',
    is_active: true,
});

const statusOptions = [
    { label: 'All Status', value: 'all' },
    { label: 'Present', value: 'present' },
    { label: 'Absent', value: 'absent' },
    { label: 'Half Day', value: 'half_day' },
    { label: 'Leave', value: 'leave' },
];

const stateSeverity = {
    IN_STORE: 'success',
    OUT_OF_STORE: 'warn',
    CHECKED_OUT: 'danger',
};

const stateLabel = {
    IN_STORE: 'In Store',
    OUT_OF_STORE: 'Out Of Store',
    CHECKED_OUT: 'Checked Out',
};

const syncFilters = () => {
    router.get(
        route('attendance.index'),
        {
            search: search.value,
            status: status.value,
            date: attendanceDate.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

watch(
    search,
    throttle(() => {
        syncFilters();
    }, 300),
);

watch([status, attendanceDate], () => {
    syncFilters();
});

const formattedDate = computed({
    get: () => (attendanceDate.value ? new Date(attendanceDate.value + 'T00:00:00') : null),
    set: (value) => {
        attendanceDate.value = value ? value.toISOString().slice(0, 10) : '';
    },
});

const formatEventLabel = (event) => {
    if (!event) return 'No activity';

    if (event.type === 'OUT') {
        return event.reason ? `Out for ${String(event.reason).replaceAll('_', ' ').toLowerCase()}` : 'Out of store';
    }

    if (event.type === 'REOPEN') return 'Checkout reopened';

    return String(event.type).replaceAll('_', ' ');
};

const openReopenDialog = (record) => {
    reopenTarget.value = record;
    reopenForm.reset();
    reopenForm.clearErrors();
    reopenDialog.value = true;
};

const submitReopen = () => {
    if (!reopenTarget.value) return;

    reopenForm.post(route('attendance.reopen', reopenTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            reopenDialog.value = false;
            reopenTarget.value = null;
            toast.add({
                severity: 'success',
                summary: 'Updated',
                detail: 'Checkout reopened successfully',
                life: 3000,
            });
        },
    });
};

const openReasonDialog = (reason = null) => {
    reasonForm.reset();
    reasonForm.clearErrors();
    editingReason.value = reason;

    if (reason) {
        reasonForm.id = reason.id;
        reasonForm.label = reason.label;
        reasonForm.is_active = Boolean(reason.is_active);
    }

    reasonDialog.value = true;
};

const saveReason = () => {
    const isEditing = Boolean(editingReason.value);
    const endpoint = isEditing
        ? route('attendance.reasons.update', editingReason.value.id)
        : route('attendance.reasons.store');

    reasonForm
        .transform((data) => ({
            ...data,
            _method: isEditing ? 'patch' : 'post',
        }))
        .post(endpoint, {
            preserveScroll: true,
            onSuccess: () => {
                reasonDialog.value = false;
                editingReason.value = null;
                toast.add({
                    severity: 'success',
                    summary: 'Saved',
                    detail: isEditing ? 'Attendance reason updated successfully' : 'Attendance reason added successfully',
                    life: 3000,
                });
            },
        });
};

const confirmDeleteReason = (reason) => {
    deleteReasonTarget.value = reason;
    reasonDeleteDialog.value = true;
};

const deleteReason = () => {
    if (!deleteReasonTarget.value) return;

    router.delete(route('attendance.reasons.destroy', deleteReasonTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            reasonDeleteDialog.value = false;
            deleteReasonTarget.value = null;
            toast.add({
                severity: 'success',
                summary: 'Deleted',
                detail: 'Attendance reason deleted successfully',
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
                            <h1 class="text-2xl font-semibold tracking-tight text-surface-900">Attendance</h1>
                            <Tag value="Admin Review" severity="secondary" />
                        </div>
                        <p class="mt-2 text-sm leading-6 text-surface-600">
                            Review daily staff attendance, see who is in store or out, and correct mistaken checkouts with a proper note.
                        </p>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Records</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ metrics?.total || 0 }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">In Store</p>
                    <p class="mt-2 text-2xl font-semibold text-emerald-700">{{ metrics?.in_store || 0 }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Out Of Store</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-600">{{ metrics?.out_of_store || 0 }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Checked Out</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ metrics?.checked_out || 0 }}</p>
                </div>
            </section>

            <section class="overflow-hidden border border-surface-200 bg-white">
                <div class="border-b border-surface-200 px-5 py-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-surface-900">Attendance Reasons</h2>
                            <p class="mt-1 text-sm text-surface-500">Compact reason chips for the terminal. Edit or disable any reason without changing code.</p>
                        </div>

                        <div class="flex justify-start lg:justify-end">
                            <Button label="Add Reason" icon="pi pi-plus" size="small" @click="openReasonDialog()" />
                        </div>
                    </div>
                </div>

                <div class="space-y-4 p-4">
                    <div v-if="!reasons.length" class="py-4 text-center text-surface-500">
                        No attendance reasons configured.
                    </div>

                    <div v-else class="flex flex-wrap gap-2">
                        <div
                            v-for="reason in reasons"
                            :key="reason.id"
                            class="inline-flex items-center gap-2 border border-surface-200 bg-surface-50 px-3 py-2"
                        >
                            <span class="text-sm font-medium text-surface-900">{{ reason.label }}</span>
                            <Tag
                                :value="reason.is_active ? 'Active' : 'Inactive'"
                                :severity="reason.is_active ? 'success' : 'secondary'"
                            />
                            <Button icon="pi pi-pencil" text rounded="false" size="small" @click="openReasonDialog(reason)" />
                            <Button icon="pi pi-trash" text rounded="false" severity="danger" size="small" @click="confirmDeleteReason(reason)" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 pt-3 text-xs text-surface-500">
                        Active reasons appear on the attendance terminal. Inactive reasons stay saved but are hidden from staff.
                    </div>
                </div>
            </section>

            <section class="overflow-hidden border border-surface-200 bg-white">
                <div class="border-b border-surface-200 px-5 py-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-surface-900">Daily Attendance Register</h2>
                            <p class="mt-1 text-sm text-surface-500">Use filters to review a date and correct mistakes safely.</p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3 lg:w-[700px]">
                            <Calendar v-model="formattedDate" dateFormat="yy-mm-dd" showIcon inputClass="w-full" />
                            <InputText v-model="search" placeholder="Search staff..." class="w-full" />
                            <Select v-model="status" :options="statusOptions" optionLabel="label" optionValue="value" class="w-full" />
                        </div>
                    </div>
                </div>

                <div class="p-4">
                    <DataTable :value="records" stripedRows rowHover tableStyle="min-width: 78rem">
                        <template #empty>
                            <div class="py-12 text-center text-surface-500">No attendance records found for the selected date.</div>
                        </template>

                        <Column header="Staff">
                            <template #body="{ data }">
                                <div>
                                    <p class="font-medium text-surface-900">{{ data.staff.name }}</p>
                                    <p class="mt-1 text-xs text-surface-500">{{ data.staff.designation || 'No designation' }}</p>
                                    <p class="mt-1 text-xs text-surface-500">{{ data.staff.mobile || data.staff.email }}</p>
                                </div>
                            </template>
                        </Column>

                        <Column header="State">
                            <template #body="{ data }">
                                <Tag :value="stateLabel[data.state] || data.state" :severity="stateSeverity[data.state] || 'secondary'" />
                            </template>
                        </Column>

                        <Column header="Check In">
                            <template #body="{ data }">
                                <span class="font-medium text-surface-900">{{ data.check_in_at || '—' }}</span>
                            </template>
                        </Column>

                        <Column header="Check Out">
                            <template #body="{ data }">
                                <span class="font-medium text-surface-900">{{ data.check_out_at || '—' }}</span>
                            </template>
                        </Column>

                        <Column header="Latest Activity">
                            <template #body="{ data }">
                                <div>
                                    <p class="text-sm font-medium text-surface-900">{{ formatEventLabel(data.latest_event) }}</p>
                                    <p v-if="data.latest_event?.event_time" class="mt-1 text-xs text-surface-500">{{ data.latest_event.event_time }}</p>
                                    <p v-if="data.latest_event?.notes" class="mt-1 text-xs text-surface-500">{{ data.latest_event.notes }}</p>
                                </div>
                            </template>
                        </Column>

                        <Column header="Correction">
                            <template #body="{ data }">
                                <div class="space-y-2">
                                    <Button
                                        v-if="data.can_reopen"
                                        label="Reopen Checkout"
                                        icon="pi pi-refresh"
                                        text
                                        severity="warning"
                                        @click="openReopenDialog(data)"
                                    />
                                    <span v-else class="text-sm text-surface-500">No correction needed</span>
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </section>
        </div>

        <Dialog v-model:visible="reopenDialog" modal header="Reopen Checkout" :style="{ width: '34rem' }">
            <div class="space-y-4">
                <div class="border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900">
                    Use this only when checkout was marked by mistake. The original event stays in history, and a correction note is required.
                </div>

                <div v-if="reopenTarget" class="border border-surface-200 bg-surface-50 px-4 py-4">
                    <p class="font-medium text-surface-900">{{ reopenTarget.staff.name }}</p>
                    <p class="mt-1 text-sm text-surface-500">{{ reopenTarget.staff.designation || 'No designation' }}</p>
                    <p class="mt-2 text-sm text-surface-700">Checked out at {{ reopenTarget.check_out_at || '—' }}</p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Correction Note</label>
                    <Textarea v-model="reopenForm.note" rows="4" class="w-full" placeholder="Example: Checked out by mistake while going for lunch." />
                    <small v-if="reopenForm.errors.note" class="mt-1 block text-xs text-red-500">{{ reopenForm.errors.note }}</small>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button label="Cancel" text severity="secondary" @click="reopenDialog = false" />
                    <Button label="Reopen Checkout" severity="warning" :loading="reopenForm.processing" @click="submitReopen" />
                </div>
            </template>
        </Dialog>

        <Dialog v-model:visible="reasonDialog" modal :header="editingReason ? 'Edit Attendance Reason' : 'Add Attendance Reason'" :style="{ width: '32rem' }">
            <div class="space-y-4">
                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Reason Label</label>
                    <InputText v-model="reasonForm.label" class="w-full" placeholder="Example: Vendor Visit" />
                    <small v-if="reasonForm.errors.label" class="mt-1 block text-xs text-red-500">{{ reasonForm.errors.label }}</small>
                </div>

                <div class="flex items-center gap-2">
                    <input id="reason_active" v-model="reasonForm.is_active" type="checkbox" class="h-4 w-4" />
                    <label for="reason_active" class="text-sm text-surface-700">Active reason</label>
                </div>

                <div class="border border-surface-200 bg-surface-50 px-4 py-3 text-sm text-surface-600">
                    The internal code is generated automatically from the label. Existing events keep their original stored value.
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button label="Cancel" text severity="secondary" @click="reasonDialog = false" />
                    <Button :label="editingReason ? 'Update Reason' : 'Add Reason'" :loading="reasonForm.processing" @click="saveReason" />
                </div>
            </template>
        </Dialog>

        <Dialog v-model:visible="reasonDeleteDialog" modal header="Delete Attendance Reason" :style="{ width: '28rem' }">
            <div class="space-y-3 text-sm text-surface-700">
                <p>Delete this attendance reason?</p>
                <p v-if="deleteReasonTarget" class="font-medium text-surface-900">{{ deleteReasonTarget.label }}</p>
                <p class="text-surface-500">Old attendance history will stay unchanged, but this reason will no longer be available on the terminal.</p>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button label="Cancel" text severity="secondary" @click="reasonDeleteDialog = false" />
                    <Button label="Delete" severity="danger" :loading="false" @click="deleteReason" />
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>
