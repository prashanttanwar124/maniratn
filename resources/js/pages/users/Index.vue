<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    users: Array,
    roles: Array,
    roleOptions: Array,
    permissions: Array,
});

const toast = useToast();
const showUserDialog = ref(false);
const showRoleDialog = ref(false);
const showPermissionDialog = ref(false);
const editingUser = ref(null);
const editingRole = ref(null);
const editingPermission = ref(null);
const cardReadLoading = ref(false);

const userForm = useForm({
    name: '',
    email: '',
    password: '',
    role: 'staff',
    attendance_enabled: false,
    attendance_passcode: '',
    attendance_card_uid: '',
    permissions: [],
});

const roleForm = useForm({
    id: null,
    name: '',
    permissions: [],
});

const permissionForm = useForm({
    id: null,
    name: '',
});

const selectedRole = computed(() => {
    return props.roles.find((role) => role.name === userForm.role) || null;
});

const inheritedPermissionSet = computed(() => {
    return new Set(selectedRole.value?.permissions || []);
});

const extraPermissionCount = computed(() => {
    return userForm.permissions.filter((permission) => !inheritedPermissionSet.value.has(permission)).length;
});

const openUserDialog = () => {
    userForm.reset();
    userForm.clearErrors();
    userForm.id = null;
    userForm.role = 'staff';
    userForm.attendance_enabled = false;
    userForm.attendance_passcode = '';
    userForm.attendance_card_uid = '';
    userForm.permissions = [];
    editingUser.value = null;
    showUserDialog.value = true;
};

const saveUser = () => {
    const isEditing = Boolean(editingUser.value);
    const endpoint = isEditing ? route('users.update', editingUser.value.id) : route('users.store');

    userForm
        .transform((data) => ({
            ...data,
            _method: isEditing ? 'patch' : 'post',
        }))
        .post(endpoint, {
        preserveScroll: true,
        onSuccess: () => {
            showUserDialog.value = false;
            editingUser.value = null;
            toast.add({
                severity: 'success',
                summary: 'Saved',
                detail: isEditing ? 'User updated successfully' : 'User created successfully',
                life: 3000,
            });
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Please check the form fields',
                life: 3000,
            });
        },
    });
};

const editUser = (user) => {
    userForm.reset();
    userForm.clearErrors();
    userForm.id = user.id;
    userForm.name = user.name;
    userForm.email = user.email;
    userForm.password = '';
    userForm.role = user.roles[0] || 'basic';
    userForm.attendance_enabled = Boolean(user.attendance_enabled);
    userForm.attendance_passcode = '';
    userForm.attendance_card_uid = user.attendance_card_uid || '';
    userForm.permissions = [...user.permissions];
    editingUser.value = user;
    showUserDialog.value = true;
};

const deleteUser = (user) => {
    if (!window.confirm(`Delete user "${user.name}"?`)) return;

    router.delete(route('users.destroy', user.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Deleted',
                detail: 'User deleted successfully',
                life: 3000,
            });
        },
        onError: (errors) => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: errors.user || 'Unable to delete user',
                life: 3000,
            });
        },
    });
};

const openRoleDialog = () => {
    roleForm.reset();
    roleForm.clearErrors();
    roleForm.id = null;
    roleForm.permissions = [];
    editingRole.value = null;
    showRoleDialog.value = true;
};

const saveRole = () => {
    const isEditing = Boolean(editingRole.value);
    const endpoint = isEditing ? route('roles.update', editingRole.value.id) : route('roles.store');

    roleForm
        .transform((data) => ({
            ...data,
            _method: isEditing ? 'patch' : 'post',
        }))
        .post(endpoint, {
        preserveScroll: true,
        onSuccess: () => {
            showRoleDialog.value = false;
            editingRole.value = null;
            toast.add({
                severity: 'success',
                summary: 'Saved',
                detail: isEditing ? 'Role updated successfully' : 'Role created successfully',
                life: 3000,
            });
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Please check the role form',
                life: 3000,
            });
        },
    });
};

const editRole = (role) => {
    roleForm.reset();
    roleForm.clearErrors();
    roleForm.id = role.id;
    roleForm.name = role.name;
    roleForm.permissions = [...role.permissions];
    editingRole.value = role;
    showRoleDialog.value = true;
};

const deleteRole = (role) => {
    if (!window.confirm(`Delete role "${role.label}"?`)) return;

    router.delete(route('roles.destroy', role.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Deleted',
                detail: 'Role deleted successfully',
                life: 3000,
            });
        },
        onError: (errors) => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: errors.role || 'Unable to delete role',
                life: 3000,
            });
        },
    });
};

const openPermissionDialog = () => {
    permissionForm.reset();
    permissionForm.clearErrors();
    permissionForm.id = null;
    editingPermission.value = null;
    showPermissionDialog.value = true;
};

const savePermission = () => {
    const isEditing = Boolean(editingPermission.value);
    const endpoint = isEditing ? route('permissions.update', editingPermission.value.id) : route('permissions.store');

    permissionForm
        .transform((data) => ({
            ...data,
            _method: isEditing ? 'patch' : 'post',
        }))
        .post(endpoint, {
        preserveScroll: true,
        onSuccess: () => {
            showPermissionDialog.value = false;
            editingPermission.value = null;
            toast.add({
                severity: 'success',
                summary: 'Saved',
                detail: isEditing ? 'Permission updated successfully' : 'Permission created successfully',
                life: 3000,
            });
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Please check the permission name',
                life: 3000,
            });
        },
    });
};

const editPermission = (permission) => {
    permissionForm.reset();
    permissionForm.clearErrors();
    permissionForm.id = permission.id;
    permissionForm.name = permission.value;
    editingPermission.value = permission;
    showPermissionDialog.value = true;
};

const deletePermission = (permission) => {
    if (!window.confirm(`Delete permission "${permission.value}"?`)) return;

    router.delete(route('permissions.destroy', permission.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Deleted',
                detail: 'Permission deleted successfully',
                life: 3000,
            });
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Unable to delete permission',
                life: 3000,
            });
        },
    });
};

const isInheritedPermission = (permission) => inheritedPermissionSet.value.has(permission.value);

const helperBaseUrl = 'http://127.0.0.1:8090';

const parseHelperError = async (response) => {
    const fallback = `Local NFC helper request failed (${response.status} ${response.statusText || 'Error'}).`;

    try {
        const contentType = response.headers.get('content-type') || '';

        if (contentType.includes('application/json')) {
            const data = await response.json();
            const message = data?.message || data?.error || data?.detail;

            return message ? `${fallback} ${message}` : fallback;
        }

        const text = (await response.text()).trim();
        return text ? `${fallback} ${text}` : fallback;
    } catch {
        return fallback;
    }
};

const readAttendanceCardIntoForm = async () => {
    cardReadLoading.value = true;

    try {
        const response = await fetch(`${helperBaseUrl}/nfc/read`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: JSON.stringify({
                context: 'attendance-register',
            }),
        });

        if (!response.ok) {
            throw new Error(await parseHelperError(response));
        }

        const payload = await response.json();
        const cardUid = payload.card_uid || payload.uid || payload.nfc_uid;

        if (!cardUid) {
            throw new Error('The NFC helper did not return a card UID.');
        }

        userForm.attendance_card_uid = cardUid;
        toast.add({ severity: 'success', summary: 'Card Read', detail: 'Attendance card UID captured successfully', life: 3000 });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Reader Error', detail: error.message || 'Unable to read attendance card', life: 4000 });
    } finally {
        cardReadLoading.value = false;
    }
};

const assignAttendanceCard = async (user) => {
    cardReadLoading.value = true;

    try {
        const helperResponse = await fetch(`${helperBaseUrl}/nfc/read`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: JSON.stringify({
                context: 'attendance-register',
            }),
        });

        if (!helperResponse.ok) {
            throw new Error(await parseHelperError(helperResponse));
        }

        const payload = await helperResponse.json();
        const cardUid = payload.card_uid || payload.uid || payload.nfc_uid;

        if (!cardUid) {
            throw new Error('The NFC helper did not return a card UID.');
        }

        await axios.post(route('users.attendance-card.assign', user.id), {
            card_uid: cardUid,
        });

        toast.add({ severity: 'success', summary: 'Card Assigned', detail: `Attendance card assigned to ${user.name}`, life: 3000 });
        router.reload({ only: ['users'] });
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Assign Failed',
            detail: error.response?.data?.message || error.message || 'Unable to assign attendance card',
            life: 4000,
        });
    } finally {
        cardReadLoading.value = false;
    }
};

const clearAttendanceCard = (user) => {
    if (!window.confirm(`Remove attendance card from "${user.name}"?`)) return;

    router.delete(route('users.attendance-card.clear', user.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Card Removed', detail: 'Attendance card removed successfully', life: 3000 });
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Unable to remove attendance card', life: 3000 });
        },
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Users" />
        <Toast />

        <div class="space-y-6">
            <div class="border-b border-surface-200 bg-white px-5 py-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-2xl font-semibold tracking-tight text-surface-900">Users</h1>
                            <Tag value="Admin Only" severity="secondary" />
                        </div>

                        <p class="mt-1 text-sm text-surface-500">Create shop users with roles and attach direct permissions whenever a user needs extra access.</p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <Button label="New Permission" icon="pi pi-key" outlined class="!w-auto shrink-0 whitespace-nowrap" @click="openPermissionDialog" />
                        <Button label="New Role" icon="pi pi-shield" outlined class="!w-auto shrink-0 whitespace-nowrap" @click="openRoleDialog" />
                        <Button label="New User" icon="pi pi-plus" class="!w-auto shrink-0 whitespace-nowrap" @click="openUserDialog" />
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div class="card overflow-hidden !p-0 xl:col-span-2">
                    <div class="border-b border-surface-200 bg-white px-5 py-4">
                        <h3 class="text-base font-semibold text-surface-900">User Accounts</h3>
                        <p class="mt-1 text-sm text-surface-500">Manage who can access the ERP and what they can do.</p>
                    </div>

                    <div class="bg-white p-4">
                        <DataTable :value="users" stripedRows rowHover tableStyle="min-width: 64rem">
                            <template #empty>
                                <div class="py-12 text-center text-surface-500">No users found</div>
                            </template>

                            <Column field="name" header="Name" />
                            <Column field="email" header="Email" />
                            <Column header="Role" style="width: 180px">
                                <template #body="{ data }">
                                    <div class="flex flex-wrap gap-2">
                                        <Tag v-for="role in data.roles" :key="role" :value="role" />
                                    </div>
                                </template>
                            </Column>
                            <Column header="Direct Permissions">
                                <template #body="{ data }">
                                    <div v-if="data.permissions.length" class="flex flex-wrap gap-2">
                                        <Tag v-for="permission in data.permissions" :key="permission" :value="permission" severity="secondary" />
                                    </div>
                                    <span v-else class="text-sm text-surface-500">No direct permissions</span>
                                </template>
                            </Column>
                            <Column header="Attendance" style="width: 180px">
                                <template #body="{ data }">
                                    <div class="flex flex-wrap gap-2">
                                        <Tag :value="data.attendance_enabled ? 'Enabled' : 'Disabled'" :severity="data.attendance_enabled ? 'success' : 'secondary'" />
                                        <Tag v-if="data.has_attendance_passcode" value="Passcode Set" severity="contrast" />
                                        <Tag v-if="data.has_attendance_card" value="Card Linked" severity="info" />
                                    </div>
                                </template>
                            </Column>
                            <Column field="created_at" header="Created" style="width: 180px" />
                            <Column header="" style="width: 160px">
                                <template #body="{ data }">
                                    <div class="flex justify-end gap-1">
                                        <Button icon="pi pi-id-card" text size="small" :disabled="cardReadLoading" @click="assignAttendanceCard(data)" />
                                        <Button v-if="data.has_attendance_card" icon="pi pi-times" text severity="warn" size="small" :disabled="cardReadLoading" @click="clearAttendanceCard(data)" />
                                        <Button icon="pi pi-pencil" text size="small" @click="editUser(data)" />
                                        <Button icon="pi pi-trash" text severity="danger" size="small" @click="deleteUser(data)" />
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>

                <div class="card overflow-hidden !p-0">
                    <div class="border-b border-surface-200 bg-white px-5 py-4">
                        <h3 class="text-base font-semibold text-surface-900">Project Roles</h3>
                        <p class="mt-1 text-sm text-surface-500">Roles stored in the database with their assigned permissions.</p>
                    </div>

                    <div class="space-y-4 bg-white p-4">
                        <div v-for="role in roles" :key="role.id" class="rounded border border-surface-200 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="font-medium text-surface-900">{{ role.label }}</div>
                                    <div class="mt-1 text-xs text-surface-500">{{ role.users_count }} users assigned</div>
                                </div>
                                <Tag :value="role.name" severity="secondary" />
                            </div>

                            <div class="mt-3 flex flex-wrap gap-2">
                                <Tag v-for="permission in role.permissions" :key="permission" :value="permission" />
                                <span v-if="!role.permissions.length" class="text-sm text-surface-500">No permissions assigned</span>
                            </div>

                            <div class="mt-4 flex justify-end gap-2">
                                <Button label="Edit" icon="pi pi-pencil" text size="small" @click="editRole(role)" />
                                <Button label="Delete" icon="pi pi-trash" text severity="danger" size="small" @click="deleteRole(role)" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card overflow-hidden !p-0">
                <div class="border-b border-surface-200 bg-white px-5 py-4">
                    <h3 class="text-base font-semibold text-surface-900">Available Permissions</h3>
                    <p class="mt-1 text-sm text-surface-500">Permissions used across dashboard, inventory, ledger, finance, and administration.</p>
                </div>

                <div class="bg-white p-4">
                    <DataTable :value="permissions" stripedRows rowHover tableStyle="min-width: 42rem">
                        <template #empty>
                            <div class="py-12 text-center text-surface-500">No permissions found</div>
                        </template>

                        <Column field="label" header="Permission" />
                        <Column field="value" header="Key" />
                        <Column header="Usage" style="width: 170px">
                            <template #body="{ data }">
                                <span class="text-sm text-surface-600">{{ data.roles_count }} roles • {{ data.users_count }} users</span>
                            </template>
                        </Column>
                        <Column header="" style="width: 150px">
                            <template #body="{ data }">
                                <div class="flex justify-end gap-1">
                                    <Button icon="pi pi-pencil" text size="small" @click="editPermission(data)" />
                                    <Button icon="pi pi-trash" text severity="danger" size="small" @click="deletePermission(data)" />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
        </div>

        <Dialog v-model:visible="showUserDialog" :header="editingUser ? 'Edit User' : 'Create User'" modal class="w-full max-w-2xl">
            <div class="grid gap-5 pt-2 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Name</label>
                    <InputText v-model="userForm.name" class="w-full" placeholder="Enter full name" />
                    <small v-if="userForm.errors.name" class="mt-1 block text-xs text-red-500">{{ userForm.errors.name }}</small>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Email</label>
                    <InputText v-model="userForm.email" type="email" class="w-full" placeholder="Enter email address" />
                    <small v-if="userForm.errors.email" class="mt-1 block text-xs text-red-500">{{ userForm.errors.email }}</small>
                </div>

                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-surface-700">Password</label>
                    <Password v-model="userForm.password" toggleMask fluid :feedback="false" placeholder="Set a secure password" />
                    <small v-if="userForm.errors.password" class="mt-1 block text-xs text-red-500">{{ userForm.errors.password }}</small>
                    <small v-if="editingUser" class="mt-1 block text-xs text-surface-500">Leave blank to keep the current password.</small>
                </div>

                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-surface-700">Access Role</label>
                    <Select v-model="userForm.role" :options="roleOptions" optionLabel="label" optionValue="value" class="w-full" />
                    <small v-if="userForm.errors.role" class="mt-1 block text-xs text-red-500">{{ userForm.errors.role }}</small>
                    <small v-else-if="selectedRole" class="mt-1 block text-xs text-surface-500">
                        This role already includes {{ selectedRole.permissions.length }} permission{{ selectedRole.permissions.length === 1 ? '' : 's' }}.
                    </small>
                </div>

                <div class="md:col-span-2 rounded border border-surface-200 bg-surface-50 p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-surface-900">Attendance Terminal Access</p>
                            <p class="mt-1 text-xs text-surface-500">Enable this user for the separate attendance terminal and set a private passcode.</p>
                        </div>
                        <Checkbox v-model="userForm.attendance_enabled" binary inputId="attendance_enabled" />
                    </div>

                    <div class="mt-4">
                        <label class="mb-2 block text-sm font-medium text-surface-700">Attendance Passcode</label>
                        <Password v-model="userForm.attendance_passcode" toggleMask fluid :feedback="false" placeholder="Enter passcode for attendance terminal" />
                        <small class="mt-1 block text-xs text-surface-500">
                            {{ editingUser ? 'Leave blank to keep the existing passcode.' : 'This passcode will be used on the attendance terminal.' }}
                        </small>
                        <small v-if="userForm.errors.attendance_passcode" class="mt-1 block text-xs text-red-500">{{ userForm.errors.attendance_passcode }}</small>
                    </div>

                    <div class="mt-4">
                        <div class="flex flex-col gap-3 md:flex-row md:items-end">
                            <div class="flex-1">
                                <label class="mb-2 block text-sm font-medium text-surface-700">Attendance Card UID</label>
                                <InputText v-model="userForm.attendance_card_uid" class="w-full" placeholder="Tap a card with the local NFC reader or enter UID manually" />
                                <small class="mt-1 block text-xs text-surface-500">This UID is used for tap-based attendance on the terminal.</small>
                                <small v-if="userForm.errors.attendance_card_uid" class="mt-1 block text-xs text-red-500">{{ userForm.errors.attendance_card_uid }}</small>
                            </div>
                            <div class="flex gap-2">
                                <Button label="Read Card" icon="pi pi-id-card" outlined :loading="cardReadLoading" :disabled="cardReadLoading" @click="readAttendanceCardIntoForm" />
                                <Button v-if="userForm.attendance_card_uid" label="Clear" severity="danger" text :disabled="cardReadLoading" @click="userForm.attendance_card_uid = ''" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <div class="mb-2 block text-sm font-medium text-surface-700">Direct Permissions</div>
                    <div v-if="selectedRole?.permissions?.length" class="mb-3 rounded border border-amber-200 bg-amber-50 px-4 py-3">
                        <p class="text-sm font-medium text-amber-900">Inherited From Role</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <Tag v-for="permission in selectedRole.permissions" :key="permission + '-inherited'" :value="permission" severity="warn" />
                        </div>
                    </div>
                    <div class="grid gap-3 rounded border border-surface-200 bg-surface-50 p-4 md:grid-cols-2">
                        <label
                            v-for="permission in permissions"
                            :key="permission.value"
                            class="flex items-center gap-3 rounded px-2 py-1 text-sm"
                            :class="isInheritedPermission(permission) ? 'bg-amber-50 text-surface-500' : 'text-surface-700'"
                        >
                            <Checkbox
                                v-model="userForm.permissions"
                                :inputId="permission.value"
                                :value="permission.value"
                                :disabled="isInheritedPermission(permission)"
                            />
                            <div class="flex min-w-0 flex-1 items-center justify-between gap-2">
                                <span>{{ permission.label }}</span>
                                <Tag v-if="isInheritedPermission(permission)" value="From Role" severity="warn" />
                            </div>
                        </label>
                    </div>
                    <small class="mt-1 block text-xs text-surface-500">
                        Select only extra permissions here. Inherited role permissions are shown above and cannot be selected again.
                    </small>
                    <small class="mt-1 block text-xs text-surface-500">
                        Extra direct permissions selected: {{ extraPermissionCount }}
                    </small>
                    <small v-if="userForm.errors.permissions" class="mt-1 block text-xs text-red-500">{{ userForm.errors.permissions }}</small>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button label="Cancel" text severity="secondary" @click="showUserDialog = false" />
                    <Button :label="editingUser ? 'Update User' : 'Create User'" :loading="userForm.processing" :disabled="userForm.processing" @click="saveUser" />
                </div>
            </template>
        </Dialog>

        <Dialog v-model:visible="showRoleDialog" :header="editingRole ? 'Edit Role' : 'Create Role'" modal class="w-full max-w-2xl">
            <div class="space-y-5 pt-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Role Name</label>
                    <InputText v-model="roleForm.name" class="w-full" placeholder="e.g. inventory_manager" />
                    <small v-if="roleForm.errors.name" class="mt-1 block text-xs text-red-500">{{ roleForm.errors.name }}</small>
                </div>

                <div>
                    <div class="mb-2 block text-sm font-medium text-surface-700">Assign Permissions</div>
                    <div class="grid gap-3 rounded border border-surface-200 bg-surface-50 p-4 md:grid-cols-2">
                        <label v-for="permission in permissions" :key="permission.value + '-role'" class="flex items-center gap-3 text-sm text-surface-700">
                            <Checkbox v-model="roleForm.permissions" :inputId="permission.value + '-role'" :value="permission.value" />
                            <span>{{ permission.label }}</span>
                        </label>
                    </div>
                    <small v-if="roleForm.errors.permissions" class="mt-1 block text-xs text-red-500">{{ roleForm.errors.permissions }}</small>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button label="Cancel" text severity="secondary" @click="showRoleDialog = false" />
                    <Button :label="editingRole ? 'Update Role' : 'Create Role'" :loading="roleForm.processing" :disabled="roleForm.processing" @click="saveRole" />
                </div>
            </template>
        </Dialog>

        <Dialog v-model:visible="showPermissionDialog" :header="editingPermission ? 'Edit Permission' : 'Create Permission'" modal class="w-full max-w-lg">
            <div class="space-y-5 pt-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Permission Name</label>
                    <InputText v-model="permissionForm.name" class="w-full" placeholder="e.g. manage_ledgers" />
                    <small v-if="permissionForm.errors.name" class="mt-1 block text-xs text-red-500">{{ permissionForm.errors.name }}</small>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button label="Cancel" text severity="secondary" @click="showPermissionDialog = false" />
                    <Button :label="editingPermission ? 'Update Permission' : 'Create Permission'" :loading="permissionForm.processing" :disabled="permissionForm.processing" @click="savePermission" />
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>
