<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2, CheckSquare, Clock, Filter, Layers, Plus, Search, Tag as TagIcon, User, AlertCircle, Bookmark, Eye, Pencil, Trash2 } from 'lucide-vue-next';
import throttle from 'lodash/throttle';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';

import Avatar from 'primevue/avatar';
import Badge from 'primevue/badge';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import ProgressBar from 'primevue/progressbar';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    tasks: {
        type: Array,
        default: () => [],
    },
    metrics: {
        type: Object,
        default: () => ({}),
    },
    availableUsers: {
        type: Array,
        default: () => [],
    },
    customers: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    categories: {
        type: Array,
        default: () => [],
    },
    priorities: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const toast = useToast();
const currentUserId = computed(() => page.props.auth?.user?.id);
const isDayOpen = computed(() => Boolean(page.props.dayStatus?.is_open));

// Filters State
const search = ref(props.filters?.search || '');
const activeTab = ref(props.filters?.tab || 'all');
const statusFilter = ref(props.filters?.status || 'all');
const priorityFilter = ref(props.filters?.priority || 'all');
const categoryFilter = ref(props.filters?.category || 'all');
const assigneeFilter = ref(props.filters?.assigned_to || 'all');
const viewMode = ref(props.filters?.view || 'kanban'); // 'kanban' or 'table'

// Modals State
const taskDialog = ref(false);
const detailDrawer = ref(false);
const deleteDialog = ref(false);
const isEditing = ref(false);
const selectedTask = ref(null);
const taskToDelete = ref(null);

// Form
const taskForm = useForm({
    id: null,
    title: '',
    description: '',
    category: 'CUSTOMER_FOLLOWUP',
    priority: 'MEDIUM',
    status: 'TODO',
    due_date: '',
    due_time: '',
    assigned_to: null,
    checklist: [],
    is_pinned: false,
    handover_notes: '',
    related_type: '',
    related_id: null,
});

const newChecklistText = ref('');

// Computed filtered tasks by status for Kanban columns
const todoTasks = computed(() => props.tasks.filter((t) => t.status === 'TODO'));
const inProgressTasks = computed(() => props.tasks.filter((t) => t.status === 'IN_PROGRESS'));
const completedTasks = computed(() => props.tasks.filter((t) => t.status === 'COMPLETED'));

// Category helpers
const getCategoryMeta = (catKey) => {
    const found = props.categories.find((c) => c.value === catKey);
    return found || { label: catKey, icon: 'pi-tag', color: 'slate' };
};

const getPrioritySeverity = (priority) => {
    switch (priority) {
        case 'URGENT':
            return 'danger';
        case 'HIGH':
            return 'warn';
        case 'MEDIUM':
            return 'info';
        default:
            return 'secondary';
    }
};

const getStatusSeverity = (status) => {
    switch (status) {
        case 'COMPLETED':
            return 'success';
        case 'IN_PROGRESS':
            return 'info';
        case 'TODO':
            return 'warn';
        default:
            return 'secondary';
    }
};

// URL Query Sync
const applyFilters = throttle(() => {
    router.get(
        route('tasks.index'),
        {
            search: search.value,
            tab: activeTab.value,
            status: statusFilter.value,
            priority: priorityFilter.value,
            category: categoryFilter.value,
            assigned_to: assigneeFilter.value,
            view: viewMode.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}, 300);

watch([search, activeTab, statusFilter, priorityFilter, categoryFilter, assigneeFilter, viewMode], () => {
    applyFilters();
});

const setTab = (tab) => {
    activeTab.value = tab;
};

// Open Create Dialog
const openCreateDialog = () => {
    taskForm.reset();
    taskForm.clearErrors();
    taskForm.id = null;
    taskForm.category = 'CUSTOMER_FOLLOWUP';
    taskForm.priority = 'MEDIUM';
    taskForm.status = 'TODO';
    taskForm.assigned_to = currentUserId.value;
    taskForm.checklist = [];
    taskForm.is_pinned = false;
    newChecklistText.value = '';
    isEditing.value = false;
    taskDialog.value = true;
};

// Open Edit Dialog
const openEditDialog = (task) => {
    taskForm.reset();
    taskForm.clearErrors();
    taskForm.id = task.id;
    taskForm.title = task.title;
    taskForm.description = task.description || '';
    taskForm.category = task.category;
    taskForm.priority = task.priority;
    taskForm.status = task.status;
    taskForm.due_date = task.due_date || '';
    taskForm.due_time = task.due_time || '';
    taskForm.assigned_to = task.assigned_to;
    taskForm.checklist = Array.isArray(task.checklist) ? JSON.parse(JSON.stringify(task.checklist)) : [];
    taskForm.is_pinned = Boolean(task.is_pinned);
    taskForm.handover_notes = task.handover_notes || '';
    taskForm.related_type = task.related_type || '';
    taskForm.related_id = task.related_id;
    newChecklistText.value = '';
    isEditing.value = true;
    taskDialog.value = true;
};

// Open Details Drawer
const openDetailDrawer = (task) => {
    selectedTask.value = task;
    detailDrawer.value = true;
};

// Checklist Management in Modal
const addChecklistItem = () => {
    const text = newChecklistText.value.trim();
    if (!text) return;
    taskForm.checklist.push({
        id: 'cl_' + Date.now() + '_' + Math.random().toString(36).substr(2, 4),
        text,
        is_completed: false,
    });
    newChecklistText.value = '';
};

const removeChecklistItem = (index) => {
    taskForm.checklist.splice(index, 1);
};

// Save Task
const saveTask = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            taskDialog.value = false;
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: isEditing.value ? 'Task details updated.' : 'New task created successfully.',
                life: 3000,
            });
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Please check the task form.',
                life: 3500,
            });
        },
    };

    if (isEditing.value) {
        taskForm.put(route('tasks.update', taskForm.id), options);
    } else {
        taskForm.post(route('tasks.store'), options);
    }
};

// Quick Status Update
const updateTaskStatus = (task, newStatus) => {
    router.patch(
        route('tasks.update-status', task.id),
        { status: newStatus },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (selectedTask.value && selectedTask.value.id === task.id) {
                    selectedTask.value.status = newStatus;
                }
                toast.add({
                    severity: 'success',
                    summary: 'Status Updated',
                    detail: `Task status changed to ${newStatus}.`,
                    life: 2500,
                });
            },
        },
    );
};

// Quick Toggle Checklist Item
const toggleChecklist = (task, itemId) => {
    router.patch(
        route('tasks.toggle-checklist', { task: task.id, itemId: itemId }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (selectedTask.value && selectedTask.value.id === task.id) {
                    const item = (selectedTask.value.checklist || []).find((i) => i.id === itemId);
                    if (item) item.is_completed = !item.is_completed;
                }
            },
        },
    );
};

// Quick Pin Toggle
const togglePin = (task) => {
    router.patch(
        route('tasks.toggle-pin', task.id),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                toast.add({
                    severity: 'info',
                    summary: 'Pin Updated',
                    detail: task.is_pinned ? 'Task unpinned.' : 'Task pinned to top.',
                    life: 2000,
                });
            },
        },
    );
};

// Delete Task
const confirmDelete = (task) => {
    taskToDelete.value = task;
    deleteDialog.value = true;
};

const deleteTask = () => {
    if (!taskToDelete.value) return;
    router.delete(route('tasks.destroy', taskToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deleteDialog.value = false;
            if (detailDrawer.value && selectedTask.value?.id === taskToDelete.value?.id) {
                detailDrawer.value = false;
            }
            taskToDelete.value = null;
            toast.add({
                severity: 'success',
                summary: 'Deleted',
                detail: 'Task removed.',
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
            <!-- ========================================== -->
            <!-- 1. HEADER (Design Flow Match)              -->
            <!-- ========================================== -->
            <div class="border-b border-surface-200 bg-white px-5 py-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-2xl font-semibold tracking-tight text-surface-900">Showroom & Workshop Tasks</h1>
                            <Tag value="Task Board" severity="secondary" />
                            <Tag :value="isDayOpen ? 'Day Open' : 'Day Closed'" :severity="isDayOpen ? 'success' : 'danger'" />
                        </div>
                        <p class="mt-1 text-sm text-surface-500">Track customer follow-ups, karigar orders, daily stock audits, and counter duties.</p>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-2.5">
                        <!-- View Switcher -->
                        <div class="inline-flex border border-surface-200 bg-surface-50 p-0.5">
                            <button
                                type="button"
                                @click="viewMode = 'kanban'"
                                :class="[
                                    'flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold transition-all',
                                    viewMode === 'kanban' ? 'bg-white text-surface-900 shadow-xs' : 'text-surface-600 hover:text-surface-900',
                                ]"
                            >
                                <i class="pi pi-th-large text-xs"></i>
                                <span>Kanban</span>
                            </button>
                            <button
                                type="button"
                                @click="viewMode = 'table'"
                                :class="[
                                    'flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold transition-all',
                                    viewMode === 'table' ? 'bg-white text-surface-900 shadow-xs' : 'text-surface-600 hover:text-surface-900',
                                ]"
                            >
                                <i class="pi pi-list text-xs"></i>
                                <span>List View</span>
                            </button>
                        </div>

                        <!-- New Task Button -->
                        <Button
                            label="New Task"
                            icon="pi pi-plus"
                            @click="openCreateDialog"
                        />
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 2. STATS STRIP (Design Flow Match)         -->
            <!-- ========================================== -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <!-- Total Tasks -->
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm text-surface-500">Total Tasks</p>
                            <p class="mt-2 text-2xl font-semibold text-surface-900">{{ metrics.total || 0 }}</p>
                        </div>
                        <div class="flex h-10 w-10 items-center justify-center bg-surface-100 text-surface-700">
                            <Layers class="h-4 w-4" />
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-surface-500">All registered tasks</p>
                </div>

                <!-- To Do (Pending) -->
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm text-surface-500">To Do</p>
                            <p class="mt-2 text-2xl font-semibold text-surface-900">{{ metrics.todo || 0 }}</p>
                        </div>
                        <div class="flex h-10 w-10 items-center justify-center bg-amber-50 text-amber-600">
                            <Clock class="h-4 w-4" />
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-surface-500">Pending to start</p>
                </div>

                <!-- In Progress -->
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm text-surface-500">In Progress</p>
                            <p class="mt-2 text-2xl font-semibold text-surface-900">{{ metrics.in_progress || 0 }}</p>
                        </div>
                        <div class="flex h-10 w-10 items-center justify-center bg-blue-50 text-blue-600">
                            <CheckSquare class="h-4 w-4" />
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-surface-500">Staff working on it</p>
                </div>

                <!-- Overdue Tasks -->
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm text-surface-500">Overdue</p>
                            <p :class="['mt-2 text-2xl font-semibold', metrics.overdue > 0 ? 'text-rose-600' : 'text-surface-900']">
                                {{ metrics.overdue || 0 }}
                            </p>
                        </div>
                        <div :class="['flex h-10 w-10 items-center justify-center', metrics.overdue > 0 ? 'bg-rose-50 text-rose-600' : 'bg-surface-100 text-surface-600']">
                            <AlertCircle class="h-4 w-4" />
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-surface-500">{{ metrics.overdue > 0 ? 'Target deadline passed' : 'No overdue items' }}</p>
                </div>

                <!-- Completed Today -->
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm text-surface-500">Completed</p>
                            <p class="mt-2 text-2xl font-semibold text-surface-900">{{ metrics.completed_today || 0 }} today</p>
                        </div>
                        <div class="flex h-10 w-10 items-center justify-center bg-emerald-50 text-emerald-600">
                            <CheckCircle2 class="h-4 w-4" />
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-surface-500">{{ metrics.completed || 0 }} total done</p>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 3. FILTER CONTROLS (Design Flow Match)     -->
            <!-- ========================================== -->
            <div class="border border-surface-200 bg-white p-4">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <!-- Preset Tabs -->
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            @click="setTab('all')"
                            :class="[
                                'border px-3 py-1.5 text-xs font-semibold transition-all',
                                activeTab === 'all' ? 'border-surface-900 bg-surface-900 text-white' : 'border-surface-200 bg-surface-50 text-surface-700 hover:bg-surface-100',
                            ]"
                        >
                            All Tasks ({{ metrics.total || 0 }})
                        </button>
                        <button
                            type="button"
                            @click="setTab('my_tasks')"
                            :class="[
                                'flex items-center gap-1.5 border px-3 py-1.5 text-xs font-semibold transition-all',
                                activeTab === 'my_tasks' ? 'border-surface-900 bg-surface-900 text-white' : 'border-surface-200 bg-surface-50 text-surface-700 hover:bg-surface-100',
                            ]"
                        >
                            <User class="h-3.5 w-3.5" />
                            <span>My Tasks</span>
                            <span v-if="metrics.my_pending > 0" class="bg-amber-400 text-surface-900 px-1 text-[10px] font-bold">{{ metrics.my_pending }}</span>
                        </button>
                        <button
                            type="button"
                            @click="setTab('due_today')"
                            :class="[
                                'flex items-center gap-1.5 border px-3 py-1.5 text-xs font-semibold transition-all',
                                activeTab === 'due_today' ? 'border-surface-900 bg-surface-900 text-white' : 'border-surface-200 bg-surface-50 text-surface-700 hover:bg-surface-100',
                            ]"
                        >
                            <Clock class="h-3.5 w-3.5" />
                            <span>Due Today ({{ metrics.due_today || 0 }})</span>
                        </button>
                        <button
                            type="button"
                            @click="setTab('urgent')"
                            :class="[
                                'border px-3 py-1.5 text-xs font-semibold transition-all',
                                activeTab === 'urgent' ? 'border-surface-900 bg-surface-900 text-white' : 'border-surface-200 bg-surface-50 text-surface-700 hover:bg-surface-100',
                            ]"
                        >
                            High / Urgent
                        </button>
                        <button
                            type="button"
                            @click="setTab('completed')"
                            :class="[
                                'flex items-center gap-1.5 border px-3 py-1.5 text-xs font-semibold transition-all',
                                activeTab === 'completed' ? 'border-surface-900 bg-surface-900 text-white' : 'border-surface-200 bg-surface-50 text-surface-700 hover:bg-surface-100',
                            ]"
                        >
                            <CheckCircle2 class="h-3.5 w-3.5" />
                            <span>Completed</span>
                        </button>
                    </div>

                    <!-- Search & Dropdown Filters -->
                    <div class="flex flex-wrap items-center gap-2.5">
                        <!-- Search Box -->
                        <div class="relative w-full sm:w-60">
                            <Search class="absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-surface-400" />
                            <InputText
                                v-model="search"
                                placeholder="Search tasks..."
                                class="!w-full !pl-8 !text-xs !border-surface-200"
                            />
                        </div>

                        <!-- Category Filter -->
                        <Select
                            v-model="categoryFilter"
                            :options="[{ label: 'All Categories', value: 'all' }, ...categories]"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Category"
                            class="!text-xs w-40"
                        />

                        <!-- Staff Filter -->
                        <Select
                            v-model="assigneeFilter"
                            :options="[
                                { label: 'All Staff', value: 'all' },
                                { label: 'Unassigned', value: 'unassigned' },
                                ...availableUsers.map((u) => ({ label: u.name, value: u.id })),
                            ]"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Assignee"
                            class="!text-xs w-36"
                        />
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 4. KANBAN BOARD VIEW                       -->
            <!-- ========================================== -->
            <div v-if="viewMode === 'kanban'" class="grid grid-cols-1 gap-5 lg:grid-cols-3">
                <!-- Column 1: TO DO (Pending) -->
                <div class="border border-surface-200 bg-[#fbfbfa] p-4">
                    <div class="mb-4 flex items-center justify-between border-b border-surface-200 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                            <h2 class="text-sm font-semibold text-surface-900">To Do</h2>
                            <Tag :value="todoTasks.length" severity="warn" class="!text-[11px]" />
                        </div>
                        <Button
                            icon="pi pi-plus"
                            text
                            size="small"
                            @click="openCreateDialog"
                            v-tooltip.top="'Add Task'"
                            class="!p-1"
                        />
                    </div>

                    <div class="flex flex-col gap-3 min-h-[420px]">
                        <div v-if="todoTasks.length === 0" class="flex flex-col items-center justify-center border border-dashed border-surface-200 py-12 text-center">
                            <CheckSquare class="h-6 w-6 text-surface-400 mb-1" />
                            <p class="text-xs text-surface-500">No tasks in To Do</p>
                        </div>

                        <!-- Clean White Task Card -->
                        <div
                            v-for="task in todoTasks"
                            :key="task.id"
                            class="group relative border border-surface-200 bg-white p-4 shadow-2xs transition-all hover:border-surface-400 hover:shadow-xs"
                        >
                            <!-- Card Header: Category & Priority & Pin -->
                            <div class="flex items-center justify-between gap-1.5">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <Tag
                                        :value="getCategoryMeta(task.category).label"
                                        severity="secondary"
                                        class="!text-[10px]"
                                    />
                                    <Tag
                                        :value="task.priority"
                                        :severity="getPrioritySeverity(task.priority)"
                                        class="!text-[10px] !font-bold"
                                    />
                                </div>
                                <button
                                    type="button"
                                    @click="togglePin(task)"
                                    :class="['text-xs transition-colors', task.is_pinned ? 'text-amber-600' : 'text-surface-300 hover:text-surface-600']"
                                    v-tooltip.top="task.is_pinned ? 'Unpin' : 'Pin'"
                                >
                                    <i :class="task.is_pinned ? 'pi pi-bookmark-fill' : 'pi pi-bookmark'"></i>
                                </button>
                            </div>

                            <!-- Title & Description -->
                            <h3
                                @click="openDetailDrawer(task)"
                                class="mt-2.5 cursor-pointer text-sm font-semibold text-surface-900 hover:text-blue-600 line-clamp-2"
                            >
                                {{ task.title }}
                            </h3>
                            <p v-if="task.description" class="mt-1 text-xs text-surface-500 line-clamp-2">
                                {{ task.description }}
                            </p>

                            <!-- Subtasks checklist progress bar -->
                            <div v-if="task.total_subtasks > 0" class="mt-3">
                                <div class="flex items-center justify-between text-[11px] text-surface-500 mb-1">
                                    <span>Checklist</span>
                                    <span>{{ task.completed_subtasks }}/{{ task.total_subtasks }}</span>
                                </div>
                                <ProgressBar
                                    :value="task.checklist_progress"
                                    :showValue="false"
                                    class="!h-1.5"
                                />
                            </div>

                            <!-- Due Date & Assignee -->
                            <div class="mt-3.5 flex items-center justify-between border-t border-surface-100 pt-2.5 text-xs">
                                <div class="flex items-center gap-1.5">
                                    <span
                                        :class="[
                                            'inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-medium',
                                            task.is_overdue
                                                ? 'bg-rose-50 text-rose-700 border border-rose-200'
                                                : 'bg-surface-50 text-surface-600 border border-surface-200',
                                        ]"
                                    >
                                        <Clock class="h-3 w-3" />
                                        <span>{{ task.due_date || 'No Date' }}</span>
                                    </span>
                                </div>

                                <div class="flex items-center gap-1.5">
                                    <Avatar
                                        :label="task.assigned_to?.name ? task.assigned_to.name.charAt(0) : '?'"
                                        shape="circle"
                                        size="small"
                                        class="!h-6 !w-6 !text-[11px] !bg-surface-100 !text-surface-700 font-bold border border-surface-200"
                                        v-tooltip.top="task.assigned_to?.name || 'Unassigned'"
                                    />
                                    <span class="text-xs text-surface-600 max-w-[90px] truncate">
                                        {{ task.assigned_to?.name || 'Unassigned' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Card Action Buttons -->
                            <div class="mt-3 flex items-center justify-between gap-1 border-t border-surface-100 pt-2">
                                <Button
                                    label="Start Work ⚡"
                                    size="small"
                                    text
                                    class="!p-0 !text-xs !font-semibold !text-blue-600 hover:!underline"
                                    @click="updateTaskStatus(task, 'IN_PROGRESS')"
                                />
                                <div class="flex items-center gap-1">
                                    <Button icon="pi pi-eye" text rounded size="small" @click="openDetailDrawer(task)" v-tooltip.top="'View Details'" class="!p-1" />
                                    <Button icon="pi pi-pencil" text rounded size="small" @click="openEditDialog(task)" v-tooltip.top="'Edit'" class="!p-1" />
                                    <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="confirmDelete(task)" v-tooltip.top="'Delete'" class="!p-1" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Column 2: IN PROGRESS -->
                <div class="border border-surface-200 bg-[#fbfbfa] p-4">
                    <div class="mb-4 flex items-center justify-between border-b border-surface-200 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span>
                            <h2 class="text-sm font-semibold text-surface-900">In Progress</h2>
                            <Tag :value="inProgressTasks.length" severity="info" class="!text-[11px]" />
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 min-h-[420px]">
                        <div v-if="inProgressTasks.length === 0" class="flex flex-col items-center justify-center border border-dashed border-surface-200 py-12 text-center">
                            <CheckSquare class="h-6 w-6 text-surface-400 mb-1" />
                            <p class="text-xs text-surface-500">No tasks in progress</p>
                        </div>

                        <!-- Clean White Task Card -->
                        <div
                            v-for="task in inProgressTasks"
                            :key="task.id"
                            class="group relative border border-surface-200 bg-white p-4 shadow-2xs transition-all hover:border-surface-400 hover:shadow-xs"
                        >
                            <div class="flex items-center justify-between gap-1.5">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <Tag
                                        :value="getCategoryMeta(task.category).label"
                                        severity="secondary"
                                        class="!text-[10px]"
                                    />
                                    <Tag
                                        :value="task.priority"
                                        :severity="getPrioritySeverity(task.priority)"
                                        class="!text-[10px] !font-bold"
                                    />
                                </div>
                                <button
                                    type="button"
                                    @click="togglePin(task)"
                                    :class="['text-xs transition-colors', task.is_pinned ? 'text-amber-600' : 'text-surface-300 hover:text-surface-600']"
                                    v-tooltip.top="task.is_pinned ? 'Unpin' : 'Pin'"
                                >
                                    <i :class="task.is_pinned ? 'pi pi-bookmark-fill' : 'pi pi-bookmark'"></i>
                                </button>
                            </div>

                            <h3
                                @click="openDetailDrawer(task)"
                                class="mt-2.5 cursor-pointer text-sm font-semibold text-surface-900 hover:text-blue-600 line-clamp-2"
                            >
                                {{ task.title }}
                            </h3>
                            <p v-if="task.description" class="mt-1 text-xs text-surface-500 line-clamp-2">
                                {{ task.description }}
                            </p>

                            <!-- Subtasks checklist progress bar -->
                            <div v-if="task.total_subtasks > 0" class="mt-3">
                                <div class="flex items-center justify-between text-[11px] text-surface-500 mb-1">
                                    <span>Checklist</span>
                                    <span>{{ task.completed_subtasks }}/{{ task.total_subtasks }}</span>
                                </div>
                                <ProgressBar
                                    :value="task.checklist_progress"
                                    :showValue="false"
                                    class="!h-1.5"
                                />
                            </div>

                            <!-- Due Date & Assignee -->
                            <div class="mt-3.5 flex items-center justify-between border-t border-surface-100 pt-2.5 text-xs">
                                <div class="flex items-center gap-1.5">
                                    <span
                                        :class="[
                                            'inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-medium',
                                            task.is_overdue
                                                ? 'bg-rose-50 text-rose-700 border border-rose-200'
                                                : 'bg-surface-50 text-surface-600 border border-surface-200',
                                        ]"
                                    >
                                        <Clock class="h-3 w-3" />
                                        <span>{{ task.due_date || 'No Date' }}</span>
                                    </span>
                                </div>

                                <div class="flex items-center gap-1.5">
                                    <Avatar
                                        :label="task.assigned_to?.name ? task.assigned_to.name.charAt(0) : '?'"
                                        shape="circle"
                                        size="small"
                                        class="!h-6 !w-6 !text-[11px] !bg-blue-50 !text-blue-700 font-bold border border-blue-200"
                                        v-tooltip.top="task.assigned_to?.name || 'Unassigned'"
                                    />
                                    <span class="text-xs text-surface-600 max-w-[90px] truncate">
                                        {{ task.assigned_to?.name || 'Unassigned' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Card Action Buttons -->
                            <div class="mt-3 flex items-center justify-between gap-1 border-t border-surface-100 pt-2">
                                <Button
                                    label="Mark Done ✅"
                                    size="small"
                                    text
                                    class="!p-0 !text-xs !font-semibold !text-emerald-600 hover:!underline"
                                    @click="updateTaskStatus(task, 'COMPLETED')"
                                />
                                <div class="flex items-center gap-1">
                                    <Button icon="pi pi-eye" text rounded size="small" @click="openDetailDrawer(task)" v-tooltip.top="'View Details'" class="!p-1" />
                                    <Button icon="pi pi-pencil" text rounded size="small" @click="openEditDialog(task)" v-tooltip.top="'Edit'" class="!p-1" />
                                    <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="confirmDelete(task)" v-tooltip.top="'Delete'" class="!p-1" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Column 3: COMPLETED -->
                <div class="border border-surface-200 bg-[#fbfbfa] p-4">
                    <div class="mb-4 flex items-center justify-between border-b border-surface-200 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                            <h2 class="text-sm font-semibold text-surface-900">Completed</h2>
                            <Tag :value="completedTasks.length" severity="success" class="!text-[11px]" />
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 min-h-[420px]">
                        <div v-if="completedTasks.length === 0" class="flex flex-col items-center justify-center border border-dashed border-surface-200 py-12 text-center">
                            <CheckCircle2 class="h-6 w-6 text-surface-400 mb-1" />
                            <p class="text-xs text-surface-500">Completed tasks will appear here</p>
                        </div>

                        <!-- Clean White Task Card -->
                        <div
                            v-for="task in completedTasks"
                            :key="task.id"
                            class="group relative border border-surface-200 bg-white p-4 shadow-2xs opacity-90 transition-all hover:opacity-100 hover:border-surface-400"
                        >
                            <div class="flex items-center justify-between gap-1.5">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <Tag
                                        :value="getCategoryMeta(task.category).label"
                                        severity="secondary"
                                        class="!text-[10px]"
                                    />
                                    <Tag
                                        value="DONE"
                                        severity="success"
                                        class="!text-[10px] !font-bold"
                                    />
                                </div>
                                <CheckCircle2 class="h-4 w-4 text-emerald-600" />
                            </div>

                            <h3
                                @click="openDetailDrawer(task)"
                                class="mt-2.5 cursor-pointer text-sm font-medium text-surface-500 line-through hover:text-surface-900 line-clamp-2"
                            >
                                {{ task.title }}
                            </h3>

                            <!-- Due Date & Assignee -->
                            <div class="mt-3 flex items-center justify-between border-t border-surface-100 pt-2.5 text-xs">
                                <span class="text-[11px] text-surface-500">Completed</span>
                                <Avatar
                                    :label="task.assigned_to?.name ? task.assigned_to.name.charAt(0) : '?'"
                                    shape="circle"
                                    size="small"
                                    class="!h-6 !w-6 !text-[11px] !bg-emerald-50 !text-emerald-700 font-bold border border-emerald-200"
                                    v-tooltip.top="task.assigned_to?.name || 'Unassigned'"
                                />
                            </div>

                            <!-- Card Action Buttons -->
                            <div class="mt-3 flex items-center justify-between gap-1 border-t border-surface-100 pt-2">
                                <Button
                                    label="Reopen ↺"
                                    size="small"
                                    text
                                    class="!p-0 !text-xs !text-surface-500 hover:!text-surface-900"
                                    @click="updateTaskStatus(task, 'TODO')"
                                />
                                <div class="flex items-center gap-1">
                                    <Button icon="pi pi-eye" text rounded size="small" @click="openDetailDrawer(task)" v-tooltip.top="'View Details'" class="!p-1" />
                                    <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="confirmDelete(task)" v-tooltip.top="'Delete'" class="!p-1" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 5. LIST / TABLE VIEW                       -->
            <!-- ========================================== -->
            <div v-else class="border border-surface-200 bg-white">
                <DataTable
                    :value="tasks"
                    stripedRows
                    rowHover
                    paginator
                    :rows="10"
                    :rowsPerPageOptions="[10, 20, 50]"
                    responsiveLayout="scroll"
                    class="p-datatable-sm"
                >
                    <template #empty>
                        <div class="py-8 text-center text-surface-500">
                            <Search class="mx-auto h-8 w-8 text-surface-400 mb-2" />
                            <p>No matching tasks found.</p>
                        </div>
                    </template>

                    <!-- Pinned -->
                    <Column header="" style="width: 3rem">
                        <template #body="{ data }">
                            <button
                                type="button"
                                @click="togglePin(data)"
                                :class="['text-xs transition-colors', data.is_pinned ? 'text-amber-600' : 'text-surface-300 hover:text-surface-600']"
                            >
                                <i :class="data.is_pinned ? 'pi pi-bookmark-fill' : 'pi pi-bookmark'"></i>
                            </button>
                        </template>
                    </Column>

                    <!-- Title & Details -->
                    <Column header="Task Title" sortable field="title">
                        <template #body="{ data }">
                            <div class="cursor-pointer" @click="openDetailDrawer(data)">
                                <span :class="['font-semibold text-surface-900 hover:text-blue-600', data.status === 'COMPLETED' ? 'line-through text-surface-400' : '']">
                                    {{ data.title }}
                                </span>
                                <p v-if="data.description" class="text-xs text-surface-500 line-clamp-1 mt-0.5">
                                    {{ data.description }}
                                </p>
                            </div>
                        </template>
                    </Column>

                    <!-- Category -->
                    <Column header="Category" sortable field="category">
                        <template #body="{ data }">
                            <Tag
                                :value="getCategoryMeta(data.category).label"
                                severity="secondary"
                                class="!text-[11px]"
                            />
                        </template>
                    </Column>

                    <!-- Priority -->
                    <Column header="Priority" sortable field="priority">
                        <template #body="{ data }">
                            <Tag
                                :value="data.priority"
                                :severity="getPrioritySeverity(data.priority)"
                                class="!text-[11px] !font-bold"
                            />
                        </template>
                    </Column>

                    <!-- Checklist Progress -->
                    <Column header="Checklist">
                        <template #body="{ data }">
                            <div v-if="data.total_subtasks > 0" class="w-28">
                                <div class="flex justify-between text-[10px] text-surface-500 mb-0.5">
                                    <span>{{ data.completed_subtasks }}/{{ data.total_subtasks }}</span>
                                    <span>{{ data.checklist_progress }}%</span>
                                </div>
                                <ProgressBar :value="data.checklist_progress" :showValue="false" class="!h-1.5" />
                            </div>
                            <span v-else class="text-xs text-surface-400">—</span>
                        </template>
                    </Column>

                    <!-- Due Date -->
                    <Column header="Due Date" sortable field="due_date">
                        <template #body="{ data }">
                            <span
                                :class="[
                                    'inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium',
                                    data.is_overdue
                                        ? 'bg-rose-50 text-rose-700 border border-rose-200 font-bold'
                                        : 'text-surface-700',
                                ]"
                            >
                                <Clock class="h-3 w-3" />
                                {{ data.due_date || 'N/A' }}
                            </span>
                        </template>
                    </Column>

                    <!-- Assignee -->
                    <Column header="Assigned Staff" sortable field="assigned_to.name">
                        <template #body="{ data }">
                            <div class="flex items-center gap-2">
                                <Avatar
                                    :label="data.assigned_to?.name ? data.assigned_to.name.charAt(0) : '?'"
                                    shape="circle"
                                    size="small"
                                    class="!h-6 !w-6 !text-[11px] !bg-surface-100 !text-surface-700 font-bold border border-surface-200"
                                />
                                <span class="text-xs text-surface-700 font-medium">
                                    {{ data.assigned_to?.name || 'Unassigned' }}
                                </span>
                            </div>
                        </template>
                    </Column>

                    <!-- Status Dropdown -->
                    <Column header="Status" sortable field="status">
                        <template #body="{ data }">
                            <Select
                                :modelValue="data.status"
                                @update:modelValue="(val) => updateTaskStatus(data, val)"
                                :options="[
                                    { label: 'To Do', value: 'TODO' },
                                    { label: 'In Progress', value: 'IN_PROGRESS' },
                                    { label: 'Completed', value: 'COMPLETED' },
                                ]"
                                optionLabel="label"
                                optionValue="value"
                                class="!text-xs w-32"
                            />
                        </template>
                    </Column>

                    <!-- Actions -->
                    <Column header="Actions" style="width: 7rem">
                        <template #body="{ data }">
                            <div class="flex items-center gap-1">
                                <Button icon="pi pi-eye" text rounded size="small" @click="openDetailDrawer(data)" v-tooltip.top="'View'" />
                                <Button icon="pi pi-pencil" text rounded size="small" @click="openEditDialog(data)" v-tooltip.top="'Edit'" />
                                <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="confirmDelete(data)" v-tooltip.top="'Delete'" />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- 6. MODAL: CREATE / EDIT TASK               -->
        <!-- ========================================== -->
        <Dialog
            v-model:visible="taskDialog"
            modal
            :header="isEditing ? 'Edit Task' : 'Create New Task'"
            :style="{ width: '560px' }"
        >
            <form @submit.prevent="saveTask" class="space-y-4 pt-1">
                <!-- Title -->
                <div>
                    <label class="mb-1 block text-xs font-semibold text-surface-700">
                        Task Title <span class="text-rose-500">*</span>
                    </label>
                    <InputText
                        v-model="taskForm.title"
                        placeholder="e.g. Call Ramesh ji for custom bridal necklace delivery"
                        class="!w-full !text-sm"
                        required
                    />
                </div>

                <!-- Category & Priority -->
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-surface-700">
                            Category <span class="text-rose-500">*</span>
                        </label>
                        <Select
                            v-model="taskForm.category"
                            :options="categories"
                            optionLabel="label"
                            optionValue="value"
                            class="!w-full !text-xs"
                            required
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-surface-700">
                            Priority <span class="text-rose-500">*</span>
                        </label>
                        <Select
                            v-model="taskForm.priority"
                            :options="priorities"
                            optionLabel="label"
                            optionValue="value"
                            class="!w-full !text-xs"
                            required
                        />
                    </div>
                </div>

                <!-- Assigned To & Status -->
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-surface-700">
                            Assign Staff Member
                        </label>
                        <Select
                            v-model="taskForm.assigned_to"
                            :options="availableUsers"
                            optionLabel="name"
                            optionValue="id"
                            placeholder="Select Staff"
                            class="!w-full !text-xs"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-surface-700">
                            Status
                        </label>
                        <Select
                            v-model="taskForm.status"
                            :options="[
                                { label: 'To Do (Pending)', value: 'TODO' },
                                { label: 'In Progress (Working)', value: 'IN_PROGRESS' },
                                { label: 'Completed (Done)', value: 'COMPLETED' },
                            ]"
                            optionLabel="label"
                            optionValue="value"
                            class="!w-full !text-xs"
                        />
                    </div>
                </div>

                <!-- Due Date & Time -->
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-surface-700">
                            Due Date
                        </label>
                        <InputText
                            type="date"
                            v-model="taskForm.due_date"
                            class="!w-full !text-xs"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-surface-700">
                            Target Time
                        </label>
                        <InputText
                            type="time"
                            v-model="taskForm.due_time"
                            class="!w-full !text-xs"
                        />
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label class="mb-1 block text-xs font-semibold text-surface-700">
                        Description / Notes
                    </label>
                    <Textarea
                        v-model="taskForm.description"
                        rows="2"
                        placeholder="Add task notes or instructions..."
                        class="!w-full !text-xs"
                    />
                </div>

                <!-- Checklist Builder -->
                <div class="border border-surface-200 bg-surface-50 p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-xs font-semibold text-surface-800">Subtask Checklist (Steps)</span>
                        <span class="text-[11px] text-surface-500">{{ taskForm.checklist.length }} items</span>
                    </div>

                    <!-- Existing Checklist Items -->
                    <div v-if="taskForm.checklist.length > 0" class="space-y-1.5 mb-2.5">
                        <div
                            v-for="(item, idx) in taskForm.checklist"
                            :key="idx"
                            class="flex items-center justify-between gap-2 border border-surface-200 bg-white p-2 text-xs"
                        >
                            <div class="flex items-center gap-2 flex-1">
                                <Checkbox v-model="item.is_completed" :binary="true" />
                                <span :class="[item.is_completed ? 'line-through text-surface-400' : 'text-surface-800']">
                                    {{ item.text }}
                                </span>
                            </div>
                            <button type="button" @click="removeChecklistItem(idx)" class="text-surface-400 hover:text-rose-500">
                                <i class="pi pi-trash text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Add New Item Input -->
                    <div class="flex items-center gap-2">
                        <InputText
                            v-model="newChecklistText"
                            @keydown.enter.prevent="addChecklistItem"
                            placeholder="Add subtask step (Press Enter)..."
                            class="!flex-1 !text-xs !bg-white"
                        />
                        <Button
                            type="button"
                            icon="pi pi-plus"
                            size="small"
                            @click="addChecklistItem"
                        />
                    </div>
                </div>

                <!-- Pin Option -->
                <div class="flex items-center gap-2 pt-1">
                    <Checkbox v-model="taskForm.is_pinned" :binary="true" inputId="is_pinned" />
                    <label for="is_pinned" class="cursor-pointer text-xs font-medium text-surface-700">
                        Pin this task to top (High Priority)
                    </label>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end gap-2 border-t border-surface-100 pt-3">
                    <Button label="Cancel" text @click="taskDialog = false" class="!text-xs" />
                    <Button
                        type="submit"
                        :label="isEditing ? 'Update Task' : 'Create Task'"
                        icon="pi pi-check"
                        :loading="taskForm.processing"
                    />
                </div>
            </form>
        </Dialog>

        <!-- ========================================== -->
        <!-- 7. MODAL: TASK DETAILS DRAWER              -->
        <!-- ========================================== -->
        <Dialog
            v-model:visible="detailDrawer"
            modal
            header="Task Details"
            :style="{ width: '540px' }"
        >
            <div v-if="selectedTask" class="space-y-4">
                <!-- Header Card -->
                <div class="border border-surface-200 bg-surface-50 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <Tag :value="getCategoryMeta(selectedTask.category).label" severity="secondary" />
                            <Tag :value="selectedTask.priority" :severity="getPrioritySeverity(selectedTask.priority)" />
                        </div>
                        <Tag :value="selectedTask.status" :severity="getStatusSeverity(selectedTask.status)" />
                    </div>

                    <h2 class="mt-2.5 text-base font-semibold text-surface-900">
                        {{ selectedTask.title }}
                    </h2>
                    <p v-if="selectedTask.description" class="mt-1 text-xs text-surface-600 leading-relaxed">
                        {{ selectedTask.description }}
                    </p>
                </div>

                <!-- Metadata Grid -->
                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div class="border border-surface-200 bg-white p-3">
                        <span class="text-[11px] text-surface-400 block mb-1">Assigned Staff</span>
                        <div class="flex items-center gap-2 font-semibold text-surface-800">
                            <Avatar
                                :label="selectedTask.assigned_to?.name ? selectedTask.assigned_to.name.charAt(0) : '?'"
                                shape="circle"
                                size="small"
                                class="!h-5 !w-5 !text-[10px] !bg-surface-100 !text-surface-700 font-bold border border-surface-200"
                            />
                            <span>{{ selectedTask.assigned_to?.name || 'Unassigned' }}</span>
                        </div>
                    </div>

                    <div class="border border-surface-200 bg-white p-3">
                        <span class="text-[11px] text-surface-400 block mb-1">Due Date</span>
                        <span :class="['font-semibold', selectedTask.is_overdue ? 'text-rose-600 font-bold' : 'text-surface-800']">
                            {{ selectedTask.due_date || 'No Date' }} {{ selectedTask.due_time ? '(' + selectedTask.due_time + ')' : '' }}
                        </span>
                    </div>
                </div>

                <!-- Live Interactive Checklist -->
                <div v-if="selectedTask.checklist && selectedTask.checklist.length > 0" class="border border-surface-200 bg-white p-4">
                    <div class="mb-2 flex items-center justify-between text-xs font-semibold text-surface-900">
                        <span>Checklist Steps</span>
                        <span class="text-surface-500 font-normal">{{ selectedTask.completed_subtasks }}/{{ selectedTask.total_subtasks }} Done</span>
                    </div>

                    <div class="space-y-2">
                        <div
                            v-for="item in selectedTask.checklist"
                            :key="item.id"
                            @click="toggleChecklist(selectedTask, item.id)"
                            class="flex cursor-pointer items-center gap-2.5 border border-surface-100 p-2 transition-colors hover:bg-surface-50"
                        >
                            <Checkbox :modelValue="Boolean(item.is_completed)" :binary="true" />
                            <span :class="['text-xs', item.is_completed ? 'line-through text-surface-400' : 'text-surface-800 font-medium']">
                                {{ item.text }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Handover Notes -->
                <div v-if="selectedTask.handover_notes" class="border border-amber-200 bg-amber-50/60 p-3 text-xs">
                    <span class="font-semibold text-amber-900 block mb-1">Handover Notes:</span>
                    <p class="text-amber-800">{{ selectedTask.handover_notes }}</p>
                </div>

                <!-- Quick Status Advancement Buttons -->
                <div class="flex items-center justify-between border-t border-surface-100 pt-3">
                    <div class="flex items-center gap-2">
                        <Button
                            v-if="selectedTask.status === 'TODO'"
                            label="Start Work ⚡"
                            size="small"
                            @click="updateTaskStatus(selectedTask, 'IN_PROGRESS')"
                        />
                        <Button
                            v-if="selectedTask.status === 'IN_PROGRESS'"
                            label="Mark Completed ✅"
                            size="small"
                            severity="success"
                            @click="updateTaskStatus(selectedTask, 'COMPLETED')"
                        />
                        <Button
                            v-if="selectedTask.status === 'COMPLETED'"
                            label="Reopen Task ↺"
                            size="small"
                            text
                            @click="updateTaskStatus(selectedTask, 'TODO')"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <Button
                            label="Edit"
                            icon="pi pi-pencil"
                            size="small"
                            text
                            @click="detailDrawer = false; openEditDialog(selectedTask)"
                        />
                        <Button
                            label="Delete"
                            icon="pi pi-trash"
                            size="small"
                            text
                            severity="danger"
                            @click="confirmDelete(selectedTask)"
                        />
                    </div>
                </div>
            </div>
        </Dialog>

        <!-- ========================================== -->
        <!-- 8. DELETE CONFIRMATION DIALOG              -->
        <!-- ========================================== -->
        <Dialog v-model:visible="deleteDialog" modal header="Delete Task" :style="{ width: '400px' }">
            <div class="flex items-center gap-3">
                <i class="pi pi-exclamation-triangle text-2xl text-rose-500"></i>
                <span class="text-sm text-surface-700">
                    Are you sure you want to delete <strong>"{{ taskToDelete?.title }}"</strong>?
                </span>
            </div>
            <template #footer>
                <Button label="Cancel" text @click="deleteDialog = false" class="!text-xs" />
                <Button label="Delete" severity="danger" @click="deleteTask" class="!text-xs font-semibold" />
            </template>
        </Dialog>
    </AppLayout>
</template>
