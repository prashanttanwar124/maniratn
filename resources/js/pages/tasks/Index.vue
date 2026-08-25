<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import throttle from 'lodash/throttle';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';

import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Drawer from 'primevue/drawer';
import IconField from 'primevue/iconfield';
import InputGroup from 'primevue/inputgroup';
import InputIcon from 'primevue/inputicon';
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

const statusOptions = [
    { label: 'All statuses', value: 'all' },
    { label: 'To do', value: 'TODO' },
    { label: 'In progress', value: 'IN_PROGRESS' },
    { label: 'Completed', value: 'COMPLETED' },
];

const kanbanColumns = [
    {
        status: 'TODO',
        title: 'To do',
        description: 'Waiting to be started',
        icon: 'pi pi-inbox',
        dotClass: 'bg-amber-500',
        badgeSeverity: 'warn',
        accentClass: 'border-l-amber-400',
        emptyTitle: 'Nothing waiting',
        emptyDescription: 'New tasks will appear here.',
    },
    {
        status: 'IN_PROGRESS',
        title: 'In progress',
        description: 'Currently being worked on',
        icon: 'pi pi-bolt',
        dotClass: 'bg-blue-500',
        badgeSeverity: 'info',
        accentClass: 'border-l-blue-400',
        emptyTitle: 'No active work',
        emptyDescription: 'Start a pending task to move it here.',
    },
    {
        status: 'COMPLETED',
        title: 'Completed',
        description: 'Finished work',
        icon: 'pi pi-check-circle',
        dotClass: 'bg-emerald-500',
        badgeSeverity: 'success',
        accentClass: 'border-l-emerald-400',
        emptyTitle: 'No completed tasks',
        emptyDescription: 'Finished work will be collected here.',
    },
];

const tasksForStatus = (status) => props.tasks.filter((task) => task.status === status);

const activeFilterCount = computed(() => [statusFilter.value, priorityFilter.value, categoryFilter.value, assigneeFilter.value].filter((value) => value !== 'all').length);
const completionRate = computed(() => (props.metrics.total ? Math.round(((props.metrics.completed || 0) / props.metrics.total) * 100) : 0));

const dialogStyle = { width: 'min(560px, calc(100vw - 2rem))' };
const deleteDialogStyle = { width: 'min(400px, calc(100vw - 2rem))' };

// Category helpers
const getCategoryMeta = (catKey) => {
    const found = props.categories.find((c) => c.value === catKey);
    return found || { label: catKey, icon: 'pi-tag', color: 'slate' };
};

const getCategoryTone = (catKey) => {
    const tones = {
        CUSTOMER_FOLLOWUP: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        KARIGAR_WORKSHOP: 'bg-amber-50 text-amber-800 ring-amber-200',
        INVENTORY_AUDIT: 'bg-blue-50 text-blue-700 ring-blue-200',
        BILLING_FINANCE: 'bg-violet-50 text-violet-700 ring-violet-200',
        MAINTENANCE: 'bg-teal-50 text-teal-700 ring-teal-200',
        GENERAL: 'bg-surface-100 text-surface-600 ring-surface-200',
    };

    return tones[catKey] || tones.GENERAL;
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

const getStatusLabel = (status) => statusOptions.find((option) => option.value === status)?.label || status;

const formatDate = (date) => {
    if (!date) return 'No due date';

    const parsedDate = new Date(`${date}T00:00:00`);
    if (Number.isNaN(parsedDate.getTime())) return date;

    return new Intl.DateTimeFormat('en-IN', {
        day: '2-digit',
        month: 'short',
        year: parsedDate.getFullYear() === new Date().getFullYear() ? undefined : 'numeric',
    }).format(parsedDate);
};

const formatTime = (time) => {
    if (!time) return '';

    const [hours = '0', minutes = '0'] = time.split(':');
    const parsedTime = new Date();
    parsedTime.setHours(Number(hours), Number(minutes), 0, 0);

    return new Intl.DateTimeFormat('en-IN', { hour: 'numeric', minute: '2-digit' }).format(parsedTime);
};

const getDueMeta = (task) => {
    if (!task.due_date) {
        return { label: 'No due date', detail: '', class: 'border-surface-200 bg-surface-50 text-surface-500' };
    }

    const dueDate = new Date(`${task.due_date}T00:00:00`);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const dayDifference = Math.round((dueDate.getTime() - today.getTime()) / 86400000);
    const detail = task.due_time ? ` · ${formatTime(task.due_time)}` : '';

    if (task.is_overdue || dayDifference < 0) {
        return { label: `Overdue · ${formatDate(task.due_date)}`, detail, class: 'border-rose-200 bg-rose-50 text-rose-700' };
    }

    if (dayDifference === 0) {
        return { label: 'Due today', detail, class: 'border-amber-200 bg-amber-50 text-amber-800' };
    }

    if (dayDifference === 1) {
        return { label: 'Due tomorrow', detail, class: 'border-blue-200 bg-blue-50 text-blue-700' };
    }

    return { label: formatDate(task.due_date), detail, class: 'border-surface-200 bg-surface-50 text-surface-600' };
};

const getNextAction = (status) => {
    if (status === 'TODO') return { label: 'Start work', icon: 'pi pi-play', nextStatus: 'IN_PROGRESS', severity: 'info' };
    if (status === 'IN_PROGRESS') return { label: 'Mark complete', icon: 'pi pi-check', nextStatus: 'COMPLETED', severity: 'success' };
    return { label: 'Reopen', icon: 'pi pi-refresh', nextStatus: 'TODO', severity: 'secondary' };
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

const setQueue = (tab, status = 'all') => {
    search.value = '';
    activeTab.value = tab;
    statusFilter.value = status;
    priorityFilter.value = 'all';
    categoryFilter.value = 'all';
    assigneeFilter.value = 'all';
};

const isQueueActive = (tab, status = 'all') => activeTab.value === tab && statusFilter.value === status;

const resetFilters = () => {
    search.value = '';
    activeTab.value = 'all';
    statusFilter.value = 'all';
    priorityFilter.value = 'all';
    categoryFilter.value = 'all';
    assigneeFilter.value = 'all';
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
    taskForm.assigned_to = task.assigned_to?.id ?? null;
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
                summary: 'Saved',
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
                    detail: `Task moved to ${getStatusLabel(newStatus)}.`,
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

        <div class="space-y-5">
            <section class="border-b border-surface-200 bg-white px-5 py-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-2xl font-semibold tracking-tight text-surface-900">Showroom & Workshop Tasks</h1>
                            <Tag value="Tasks" severity="secondary" />
                            <Tag :value="isDayOpen ? 'Day Open' : 'Day Closed'" :severity="isDayOpen ? 'success' : 'danger'" />
                        </div>
                        <p class="mt-1 text-sm text-surface-500">Track customer follow-ups, karigar orders, stock count audits and counter duties.</p>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        <div class="inline-flex rounded border border-surface-200 bg-surface-50 p-0.5" role="group" aria-label="Task view">
                            <button
                                type="button"
                                @click="viewMode = 'kanban'"
                                :class="[
                                    'inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold transition-colors',
                                    viewMode === 'kanban' ? 'bg-white text-surface-900 shadow-xs' : 'text-surface-600 hover:text-surface-900',
                                ]"
                            >
                                <i class="pi pi-th-large text-xs"></i>
                                <span>Board</span>
                            </button>
                            <button
                                type="button"
                                @click="viewMode = 'table'"
                                :class="[
                                    'inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold transition-colors',
                                    viewMode === 'table' ? 'bg-white text-surface-900 shadow-xs' : 'text-surface-600 hover:text-surface-900',
                                ]"
                            >
                                <i class="pi pi-list text-xs"></i>
                                <span>List</span>
                            </button>
                        </div>

                        <Button label="New Task" icon="pi pi-plus" @click="openCreateDialog" />
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <button
                    type="button"
                    :class="[
                        'col-span-2 border bg-white p-4 text-left transition-all hover:-translate-y-0.5 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 lg:col-span-1',
                        isQueueActive('all') ? 'border-primary-300 ring-1 ring-primary-100' : 'border-surface-200',
                    ]"
                    @click="setQueue('all')"
                >
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-surface-500 uppercase">All tasks</p>
                            <p class="mt-1 text-2xl font-bold text-surface-900">{{ metrics.total || 0 }}</p>
                        </div>
                        <span class="flex h-9 w-9 items-center justify-center rounded-md bg-surface-100 text-surface-600"><i class="pi pi-layers"></i></span>
                    </div>
                    <div class="mt-2.5 flex items-center gap-2">
                        <ProgressBar :value="completionRate" :showValue="false" class="!h-1.5 flex-1" />
                        <span class="text-[10px] font-semibold text-surface-500">{{ completionRate }}% done</span>
                    </div>
                </button>
                <button
                    type="button"
                    :class="[
                        'border bg-white p-4 text-left transition-all hover:-translate-y-0.5 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500',
                        isQueueActive('all', 'TODO') ? 'border-amber-300 ring-1 ring-amber-100' : 'border-surface-200',
                    ]"
                    @click="setQueue('all', 'TODO')"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-amber-700 uppercase">To do</p>
                            <p class="mt-1 text-2xl font-bold text-surface-900">{{ metrics.todo || 0 }}</p>
                        </div>
                        <span class="flex h-9 w-9 items-center justify-center rounded-md border border-amber-200 bg-amber-50 text-amber-600"><i class="pi pi-inbox"></i></span>
                    </div>
                    <p class="mt-2 text-xs text-surface-400">Waiting to start</p>
                </button>
                <button
                    type="button"
                    :class="[
                        'border bg-white p-4 text-left transition-all hover:-translate-y-0.5 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500',
                        isQueueActive('all', 'IN_PROGRESS') ? 'border-blue-300 ring-1 ring-blue-100' : 'border-surface-200',
                    ]"
                    @click="setQueue('all', 'IN_PROGRESS')"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-blue-700 uppercase">Active</p>
                            <p class="mt-1 text-2xl font-bold text-surface-900">{{ metrics.in_progress || 0 }}</p>
                        </div>
                        <span class="flex h-9 w-9 items-center justify-center rounded-md border border-blue-200 bg-blue-50 text-blue-600"><i class="pi pi-bolt"></i></span>
                    </div>
                    <p class="mt-2 text-xs text-surface-400">Work in progress</p>
                </button>
                <button
                    type="button"
                    :class="[
                        'border bg-white p-4 text-left transition-all hover:-translate-y-0.5 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500',
                        isQueueActive('overdue') ? 'border-rose-400 ring-1 ring-rose-100' : metrics.overdue > 0 ? 'border-rose-200' : 'border-surface-200',
                    ]"
                    @click="setQueue('overdue')"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p :class="['text-xs font-semibold tracking-wide uppercase', metrics.overdue > 0 ? 'text-rose-700' : 'text-surface-500']">Overdue</p>
                            <p :class="['mt-1 text-2xl font-bold', metrics.overdue > 0 ? 'text-rose-600' : 'text-surface-900']">{{ metrics.overdue || 0 }}</p>
                        </div>
                        <span
                            :class="[
                                'flex h-9 w-9 items-center justify-center rounded-md border',
                                metrics.overdue > 0 ? 'border-rose-200 bg-rose-50 text-rose-600' : 'border-surface-200 bg-surface-100 text-surface-600',
                            ]"
                            ><i class="pi pi-exclamation-circle"></i
                        ></span>
                    </div>
                    <p class="mt-2 text-xs text-surface-400">{{ metrics.overdue > 0 ? 'Needs attention' : 'Nothing delayed' }}</p>
                </button>
                <button
                    type="button"
                    :class="[
                        'border bg-white p-4 text-left transition-all hover:-translate-y-0.5 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500',
                        isQueueActive('completed_today') ? 'border-emerald-300 ring-1 ring-emerald-100' : 'border-surface-200',
                    ]"
                    @click="setQueue('completed_today')"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-emerald-700 uppercase">Done today</p>
                            <p class="mt-1 text-2xl font-bold text-surface-900">{{ metrics.completed_today || 0 }}</p>
                        </div>
                        <span class="flex h-9 w-9 items-center justify-center rounded-md border border-emerald-200 bg-emerald-50 text-emerald-600"><i class="pi pi-check-circle"></i></span>
                    </div>
                    <p class="mt-2 text-xs text-surface-400">{{ metrics.completed || 0 }} total completed</p>
                </button>
            </section>

            <section class="overflow-hidden border border-surface-200 bg-white">
                <div class="overflow-x-auto border-b border-surface-200 px-3 pt-3 sm:px-4">
                    <div class="flex min-w-max items-center gap-1" role="tablist" aria-label="Task shortcuts">
                        <button
                            type="button"
                            @click="setTab('all')"
                            role="tab"
                            :aria-selected="activeTab === 'all'"
                            :class="[
                                'inline-flex items-center gap-1.5 border-b-2 px-3 py-2.5 text-xs font-semibold transition-colors',
                                activeTab === 'all' ? 'border-primary-500 text-primary-700' : 'border-transparent text-surface-600 hover:text-surface-900',
                            ]"
                        >
                            <span>All tasks</span>
                            <span class="rounded-full bg-surface-100 px-1.5 py-0.5 text-[10px] text-surface-700">{{ metrics.total || 0 }}</span>
                        </button>
                        <button
                            type="button"
                            @click="setTab('my_tasks')"
                            role="tab"
                            :aria-selected="activeTab === 'my_tasks'"
                            :class="[
                                'inline-flex items-center gap-1.5 border-b-2 px-3 py-2.5 text-xs font-semibold transition-colors',
                                activeTab === 'my_tasks' ? 'border-primary-500 text-primary-700' : 'border-transparent text-surface-600 hover:text-surface-900',
                            ]"
                        >
                            <i class="pi pi-user text-xs"></i>
                            <span>My tasks</span>
                            <span v-if="metrics.my_pending > 0" class="rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800">{{ metrics.my_pending }}</span>
                        </button>
                        <button
                            type="button"
                            @click="setTab('due_today')"
                            role="tab"
                            :aria-selected="activeTab === 'due_today'"
                            :class="[
                                'inline-flex items-center gap-1.5 border-b-2 px-3 py-2.5 text-xs font-semibold transition-colors',
                                activeTab === 'due_today' ? 'border-primary-500 text-primary-700' : 'border-transparent text-surface-600 hover:text-surface-900',
                            ]"
                        >
                            <i class="pi pi-calendar text-xs"></i>
                            <span>Due today</span>
                            <span class="rounded-full bg-surface-100 px-1.5 py-0.5 text-[10px] text-surface-700">{{ metrics.due_today || 0 }}</span>
                        </button>
                        <button
                            type="button"
                            @click="setTab('urgent')"
                            role="tab"
                            :aria-selected="activeTab === 'urgent'"
                            :class="[
                                'inline-flex items-center gap-1.5 border-b-2 px-3 py-2.5 text-xs font-semibold transition-colors',
                                activeTab === 'urgent' ? 'border-primary-500 text-primary-700' : 'border-transparent text-surface-600 hover:text-surface-900',
                            ]"
                        >
                            <i class="pi pi-bolt text-xs"></i>
                            <span>High / urgent</span>
                        </button>
                        <button
                            type="button"
                            @click="setTab('overdue')"
                            role="tab"
                            :aria-selected="activeTab === 'overdue'"
                            :class="[
                                'inline-flex items-center gap-1.5 border-b-2 px-3 py-2.5 text-xs font-semibold transition-colors',
                                activeTab === 'overdue' ? 'border-rose-500 text-rose-700' : 'border-transparent text-surface-600 hover:text-surface-900',
                            ]"
                        >
                            <i class="pi pi-exclamation-circle text-xs"></i>
                            <span>Overdue</span>
                            <span v-if="metrics.overdue > 0" class="rounded-full bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-700">{{ metrics.overdue }}</span>
                        </button>
                        <button
                            type="button"
                            @click="setTab('completed')"
                            role="tab"
                            :aria-selected="activeTab === 'completed'"
                            :class="[
                                'inline-flex items-center gap-1.5 border-b-2 px-3 py-2.5 text-xs font-semibold transition-colors',
                                activeTab === 'completed' ? 'border-primary-500 text-primary-700' : 'border-transparent text-surface-600 hover:text-surface-900',
                            ]"
                        >
                            <i class="pi pi-check text-xs"></i>
                            <span>Completed</span>
                        </button>
                    </div>
                </div>

                <div class="p-3 sm:p-4">
                    <div class="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-[minmax(240px,1fr)_160px_160px_190px_170px_auto]">
                        <IconField class="w-full">
                            <InputIcon class="pi pi-search text-xs" />
                            <InputText v-model="search" placeholder="Search title, notes or staff..." class="w-full text-sm" aria-label="Search tasks" />
                        </IconField>
                        <Select v-model="statusFilter" :options="statusOptions" optionLabel="label" optionValue="value" class="w-full text-xs" aria-label="Filter by status" />
                        <Select
                            v-model="priorityFilter"
                            :options="[{ label: 'All priorities', value: 'all' }, ...priorities]"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full text-xs"
                            aria-label="Filter by priority"
                        />
                        <Select
                            v-model="categoryFilter"
                            :options="[{ label: 'All Categories', value: 'all' }, ...categories]"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full text-xs"
                            aria-label="Filter by category"
                        />
                        <Select
                            v-model="assigneeFilter"
                            :options="[{ label: 'All Staff', value: 'all' }, { label: 'Unassigned', value: 'unassigned' }, ...availableUsers.map((u) => ({ label: u.name, value: u.id }))]"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full text-xs"
                            aria-label="Filter by assignee"
                        />
                        <Button
                            label="Reset"
                            icon="pi pi-filter-slash"
                            severity="secondary"
                            text
                            class="w-full whitespace-nowrap md:w-auto"
                            :disabled="!search && activeTab === 'all' && activeFilterCount === 0"
                            @click="resetFilters"
                        />
                    </div>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-surface-100 pt-3 text-xs text-surface-500">
                        <span
                            ><strong class="font-semibold text-surface-800">{{ tasks.length }}</strong> {{ tasks.length === 1 ? 'task' : 'tasks' }} shown</span
                        >
                        <span v-if="activeFilterCount > 0" class="inline-flex items-center gap-1.5 rounded-full bg-primary-50 px-2 py-1 font-medium text-primary-700">
                            <i class="pi pi-filter text-[10px]"></i>
                            {{ activeFilterCount }} {{ activeFilterCount === 1 ? 'filter' : 'filters' }} active
                        </span>
                    </div>
                </div>
            </section>

            <!-- ========================================== -->
            <!-- 4. KANBAN BOARD VIEW                       -->
            <!-- ========================================== -->
            <div
                v-if="viewMode === 'kanban'"
                class="grid snap-x snap-mandatory auto-cols-[minmax(300px,calc(100vw_-_3rem))] grid-flow-col gap-4 overflow-x-auto pb-3 sm:auto-cols-[minmax(320px,1fr)] xl:grid-flow-row xl:grid-cols-3 xl:overflow-visible"
            >
                <section v-for="column in kanbanColumns" :key="column.status" class="min-h-[460px] snap-start border border-surface-200 bg-surface-50/70 p-3 sm:p-4">
                    <header class="mb-3 flex items-center justify-between border-b border-surface-200 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="column.dotClass"></span>
                            <h2 class="text-sm font-semibold text-surface-900">{{ column.title }}</h2>
                            <Tag :value="tasksForStatus(column.status).length" :severity="column.badgeSeverity" class="!text-[10px]" />
                        </div>
                        <Button
                            v-if="column.status === 'TODO'"
                            icon="pi pi-plus"
                            text
                            rounded
                            size="small"
                            aria-label="Add task"
                            v-tooltip.top="'Add task'"
                            class="!h-7 !w-7 !p-0 text-surface-500 hover:text-surface-900"
                            @click="openCreateDialog"
                        />
                    </header>

                    <div class="flex flex-col gap-3">
                        <div
                            v-if="tasksForStatus(column.status).length === 0"
                            class="flex min-h-40 flex-col items-center justify-center border border-dashed border-surface-300 bg-white/60 px-5 text-center"
                        >
                            <span class="mb-2 flex h-9 w-9 items-center justify-center rounded-full bg-surface-100 text-surface-400"><i :class="column.icon"></i></span>
                            <p class="text-xs font-semibold text-surface-700">{{ column.emptyTitle }}</p>
                            <p class="mt-1 text-[11px] leading-5 text-surface-400">{{ column.emptyDescription }}</p>
                        </div>

                        <article
                            v-for="task in tasksForStatus(column.status)"
                            :key="task.id"
                            :class="[
                                'group relative border border-l-4 border-surface-200 bg-white p-3.5 shadow-sm transition-all hover:border-surface-300 hover:shadow-md',
                                task.is_overdue ? '!border-l-rose-500' : column.accentClass,
                                task.status === 'COMPLETED' ? 'opacity-90' : '',
                            ]"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex min-w-0 flex-wrap items-center gap-1.5">
                                    <span :class="['inline-flex max-w-full items-center gap-1 rounded px-2 py-1 text-[10px] font-semibold ring-1 ring-inset', getCategoryTone(task.category)]">
                                        <i :class="['pi text-[9px]', getCategoryMeta(task.category).icon]"></i>
                                        <span class="truncate">{{ getCategoryMeta(task.category).label }}</span>
                                    </span>
                                    <Tag :value="task.priority" :severity="getPrioritySeverity(task.priority)" class="!text-[10px] !font-bold" />
                                </div>
                                <button
                                    type="button"
                                    :aria-label="task.is_pinned ? 'Unpin task' : 'Pin task'"
                                    :class="['-mt-1 -mr-1 rounded p-2 text-xs transition-colors', task.is_pinned ? 'text-amber-600' : 'text-surface-300 hover:bg-surface-100 hover:text-surface-600']"
                                    v-tooltip.top="task.is_pinned ? 'Unpin task' : 'Pin task'"
                                    @click="togglePin(task)"
                                >
                                    <i :class="task.is_pinned ? 'pi pi-bookmark-fill' : 'pi pi-bookmark'"></i>
                                </button>
                            </div>

                            <button type="button" class="mt-2.5 block w-full text-left" @click="openDetailDrawer(task)">
                                <span
                                    :class="[
                                        'line-clamp-2 block text-sm leading-5 font-semibold text-surface-900 transition-colors hover:text-primary-700',
                                        task.status === 'COMPLETED' ? 'text-surface-600' : '',
                                    ]"
                                >
                                    {{ task.title }}
                                </span>
                                <span v-if="task.description" class="mt-1 line-clamp-2 block text-xs leading-5 text-surface-500">{{ task.description }}</span>
                            </button>

                            <div v-if="task.total_subtasks > 0" class="mt-3 rounded-md bg-surface-50 px-2.5 py-2">
                                <div class="mb-1.5 flex items-center justify-between text-[11px] text-surface-500">
                                    <span class="inline-flex items-center gap-1"><i class="pi pi-check-square text-[10px]"></i> Checklist</span>
                                    <span class="font-semibold text-surface-700">{{ task.completed_subtasks }}/{{ task.total_subtasks }}</span>
                                </div>
                                <ProgressBar :value="task.checklist_progress" :showValue="false" class="!h-1.5" />
                            </div>

                            <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                                <span :class="['inline-flex items-center gap-1 rounded-md border px-2 py-1 text-[10px] font-semibold', getDueMeta(task).class]">
                                    <i class="pi pi-calendar text-[9px]"></i>
                                    {{ task.status === 'COMPLETED' ? 'Completed' : getDueMeta(task).label }}{{ task.status === 'COMPLETED' ? '' : getDueMeta(task).detail }}
                                </span>
                                <div class="flex min-w-0 items-center gap-1.5" v-tooltip.top="task.assigned_to?.name || 'Unassigned'">
                                    <Avatar
                                        :label="task.assigned_to?.name ? task.assigned_to.name.charAt(0).toUpperCase() : '?'"
                                        shape="circle"
                                        class="!h-6 !w-6 shrink-0 border border-surface-200 !bg-surface-100 !text-[10px] font-bold !text-surface-700"
                                    />
                                    <span class="max-w-24 truncate text-[11px] font-medium text-surface-600">{{ task.assigned_to?.name || 'Unassigned' }}</span>
                                </div>
                            </div>

                            <footer class="mt-3 flex items-center justify-between gap-2 border-t border-surface-100 pt-2.5">
                                <Button
                                    :label="getNextAction(task.status).label"
                                    :icon="getNextAction(task.status).icon"
                                    :severity="getNextAction(task.status).severity"
                                    size="small"
                                    text
                                    class="!px-1 !py-1 !text-xs !font-semibold"
                                    @click="updateTaskStatus(task, getNextAction(task.status).nextStatus)"
                                />
                                <div class="flex items-center gap-0.5">
                                    <Button
                                        icon="pi pi-eye"
                                        text
                                        rounded
                                        size="small"
                                        aria-label="View details"
                                        v-tooltip.top="'View details'"
                                        class="!h-8 !w-8 !p-0"
                                        @click="openDetailDrawer(task)"
                                    />
                                    <Button icon="pi pi-pencil" text rounded size="small" aria-label="Edit task" v-tooltip.top="'Edit task'" class="!h-8 !w-8 !p-0" @click="openEditDialog(task)" />
                                </div>
                            </footer>
                        </article>
                    </div>
                </section>
            </div>

            <!-- ========================================== -->
            <!-- 5. LIST / TABLE VIEW                       -->
            <!-- ========================================== -->
            <div v-else>
                <div class="space-y-3 lg:hidden">
                    <div v-if="tasks.length === 0" class="border border-dashed border-surface-300 bg-white px-5 py-12 text-center">
                        <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-surface-100 text-surface-400"><i class="pi pi-search"></i></span>
                        <p class="mt-3 text-sm font-semibold text-surface-700">No matching tasks</p>
                        <p class="mt-1 text-xs text-surface-400">Try changing or resetting your filters.</p>
                    </div>

                    <article v-for="task in tasks" :key="task.id" class="border border-surface-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span :class="['inline-flex items-center gap-1 rounded px-2 py-1 text-[10px] font-semibold ring-1 ring-inset', getCategoryTone(task.category)]">
                                        <i :class="['pi text-[9px]', getCategoryMeta(task.category).icon]"></i>
                                        {{ getCategoryMeta(task.category).label }}
                                    </span>
                                    <Tag :value="task.priority" :severity="getPrioritySeverity(task.priority)" class="!text-[10px]" />
                                </div>
                                <button type="button" class="mt-2 block text-left text-sm leading-5 font-semibold text-surface-900" @click="openDetailDrawer(task)">{{ task.title }}</button>
                            </div>
                            <Button icon="pi pi-pencil" text rounded size="small" aria-label="Edit task" class="!-mt-2 !-mr-2 !h-9 !w-9 !p-0" @click="openEditDialog(task)" />
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-2 text-[11px]">
                            <div :class="['flex items-center gap-1.5 rounded-md border px-2 py-2 font-semibold', getDueMeta(task).class]">
                                <i class="pi pi-calendar text-[10px]"></i>
                                <span class="truncate">{{ task.status === 'COMPLETED' ? 'Completed' : getDueMeta(task).label }}</span>
                            </div>
                            <div class="flex min-w-0 items-center gap-1.5 rounded-md bg-surface-50 px-2 py-2 text-surface-600">
                                <Avatar
                                    :label="task.assigned_to?.name ? task.assigned_to.name.charAt(0).toUpperCase() : '?'"
                                    shape="circle"
                                    class="!h-5 !w-5 shrink-0 !bg-white !text-[9px] font-bold"
                                />
                                <span class="truncate font-medium">{{ task.assigned_to?.name || 'Unassigned' }}</span>
                            </div>
                        </div>

                        <div v-if="task.total_subtasks > 0" class="mt-3">
                            <div class="mb-1 flex justify-between text-[10px] text-surface-500">
                                <span>Checklist</span>
                                <span>{{ task.completed_subtasks }}/{{ task.total_subtasks }}</span>
                            </div>
                            <ProgressBar :value="task.checklist_progress" :showValue="false" class="!h-1.5" />
                        </div>

                        <div class="mt-3 flex items-center justify-between border-t border-surface-100 pt-2.5">
                            <Tag :value="getStatusLabel(task.status)" :severity="getStatusSeverity(task.status)" class="!text-[10px]" />
                            <Button
                                :label="getNextAction(task.status).label"
                                :icon="getNextAction(task.status).icon"
                                :severity="getNextAction(task.status).severity"
                                size="small"
                                text
                                class="!px-2 !py-1 !text-xs"
                                @click="updateTaskStatus(task, getNextAction(task.status).nextStatus)"
                            />
                        </div>
                    </article>
                </div>

                <div class="hidden border border-surface-200 bg-white lg:block">
                    <DataTable :value="tasks" dataKey="id" stripedRows rowHover paginator :rows="10" :rowsPerPageOptions="[10, 20, 50]" responsiveLayout="scroll" class="p-datatable-sm">
                        <template #empty>
                            <div class="py-8 text-center text-surface-500">
                                <i class="pi pi-search mb-2 text-3xl text-surface-400"></i>
                                <p class="text-xs">No matching tasks found.</p>
                            </div>
                        </template>

                        <!-- Pinned -->
                        <Column header="" style="width: 3rem">
                            <template #body="{ data }">
                                <button
                                    type="button"
                                    @click="togglePin(data)"
                                    :class="['p-1 text-xs transition-colors', data.is_pinned ? 'text-amber-600' : 'text-surface-300 hover:text-surface-600']"
                                >
                                    <i :class="data.is_pinned ? 'pi pi-bookmark-fill' : 'pi pi-bookmark'"></i>
                                </button>
                            </template>
                        </Column>

                        <!-- Title & Details -->
                        <Column header="Task Title" sortable field="title">
                            <template #body="{ data }">
                                <div class="cursor-pointer py-1" @click="openDetailDrawer(data)">
                                    <span :class="['text-sm font-semibold text-surface-900 hover:text-blue-600', data.status === 'COMPLETED' ? 'text-surface-400 line-through' : '']">
                                        {{ data.title }}
                                    </span>
                                    <p v-if="data.description" class="mt-0.5 line-clamp-1 text-xs text-surface-500">
                                        {{ data.description }}
                                    </p>
                                </div>
                            </template>
                        </Column>

                        <!-- Category -->
                        <Column header="Category" sortable field="category">
                            <template #body="{ data }">
                                <Tag :value="getCategoryMeta(data.category).label" severity="secondary" class="!text-[11px]" />
                            </template>
                        </Column>

                        <!-- Priority -->
                        <Column header="Priority" sortable field="priority">
                            <template #body="{ data }">
                                <Tag :value="data.priority" :severity="getPrioritySeverity(data.priority)" class="!text-[11px] !font-bold" />
                            </template>
                        </Column>

                        <!-- Checklist Progress -->
                        <Column header="Checklist">
                            <template #body="{ data }">
                                <div v-if="data.total_subtasks > 0" class="w-28">
                                    <div class="mb-0.5 flex justify-between text-[10px] text-surface-500">
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
                                        'inline-flex items-center gap-1 rounded border px-2 py-0.5 text-xs font-medium',
                                        data.is_overdue ? 'border-rose-200 bg-rose-50 font-bold text-rose-700' : 'border-surface-200 bg-surface-50 text-surface-700',
                                    ]"
                                >
                                    <i class="pi pi-calendar text-[10px]"></i>
                                    {{ formatDate(data.due_date) }}
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
                                        class="!h-6 !w-6 border border-surface-200 !bg-surface-100 !text-[11px] font-bold !text-surface-700"
                                    />
                                    <span class="text-xs font-medium text-surface-700">
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
                                    :options="statusOptions.slice(1)"
                                    optionLabel="label"
                                    optionValue="value"
                                    class="w-32 text-xs"
                                />
                            </template>
                        </Column>

                        <!-- Actions -->
                        <Column header="Actions" style="width: 7rem">
                            <template #body="{ data }">
                                <div class="flex items-center gap-1">
                                    <Button icon="pi pi-eye" text rounded size="small" @click="openDetailDrawer(data)" v-tooltip.top="'View'" class="!h-7 !w-7 !p-0" />
                                    <Button icon="pi pi-pencil" text rounded size="small" @click="openEditDialog(data)" v-tooltip.top="'Edit'" class="!h-7 !w-7 !p-0" />
                                    <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="confirmDelete(data)" v-tooltip.top="'Delete'" class="!h-7 !w-7 !p-0" />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- 6. MODAL: CREATE / EDIT TASK               -->
        <!-- ========================================== -->
        <Dialog v-model:visible="taskDialog" modal :style="dialogStyle">
            <template #header>
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-md bg-primary-50 text-primary-700"><i :class="isEditing ? 'pi pi-pencil' : 'pi pi-plus'"></i></span>
                    <div>
                        <p class="text-base font-semibold text-surface-900">{{ isEditing ? 'Edit task' : 'Create new task' }}</p>
                        <p class="mt-0.5 text-xs font-normal text-surface-500">{{ isEditing ? 'Update ownership, timing or instructions.' : 'Add clear ownership and a realistic due time.' }}</p>
                    </div>
                </div>
            </template>
            <form @submit.prevent="saveTask" class="space-y-4 pt-2">
                <!-- Title -->
                <div>
                    <label for="task-title" class="mb-1.5 block text-sm font-medium text-surface-700"> Task title <span class="text-rose-500">*</span> </label>
                    <InputText
                        id="task-title"
                        v-model="taskForm.title"
                        placeholder="e.g. Call Ramesh ji for custom bridal necklace delivery"
                        class="w-full text-sm"
                        :invalid="Boolean(taskForm.errors.title)"
                        autofocus
                        required
                    />
                    <small v-if="taskForm.errors.title" class="mt-1 block text-xs text-rose-600">{{ taskForm.errors.title }}</small>
                </div>

                <!-- Category & Priority -->
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-surface-700"> Category <span class="text-rose-500">*</span> </label>
                        <Select
                            v-model="taskForm.category"
                            :options="categories"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full text-xs"
                            :invalid="Boolean(taskForm.errors.category)"
                            required
                        />
                        <small v-if="taskForm.errors.category" class="mt-1 block text-xs text-rose-600">{{ taskForm.errors.category }}</small>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-surface-700"> Priority <span class="text-rose-500">*</span> </label>
                        <Select
                            v-model="taskForm.priority"
                            :options="priorities"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full text-xs"
                            :invalid="Boolean(taskForm.errors.priority)"
                            required
                        />
                        <small v-if="taskForm.errors.priority" class="mt-1 block text-xs text-rose-600">{{ taskForm.errors.priority }}</small>
                    </div>
                </div>

                <!-- Assigned To & Status -->
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-surface-700"> Assign to </label>
                        <Select
                            v-model="taskForm.assigned_to"
                            :options="availableUsers"
                            optionLabel="name"
                            optionValue="id"
                            placeholder="Select Staff"
                            class="w-full text-xs"
                            :invalid="Boolean(taskForm.errors.assigned_to)"
                        />
                        <small v-if="taskForm.errors.assigned_to" class="mt-1 block text-xs text-rose-600">{{ taskForm.errors.assigned_to }}</small>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-surface-700"> Status </label>
                        <Select v-model="taskForm.status" :options="statusOptions.slice(1)" optionLabel="label" optionValue="value" class="w-full text-xs" />
                    </div>
                </div>

                <!-- Due Date & Time -->
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label for="task-due-date" class="mb-1.5 block text-sm font-medium text-surface-700"> Due date </label>
                        <InputText id="task-due-date" type="date" v-model="taskForm.due_date" class="w-full text-xs" :invalid="Boolean(taskForm.errors.due_date)" />
                        <small v-if="taskForm.errors.due_date" class="mt-1 block text-xs text-rose-600">{{ taskForm.errors.due_date }}</small>
                    </div>

                    <div>
                        <label for="task-due-time" class="mb-1.5 block text-sm font-medium text-surface-700"> Target time </label>
                        <InputText id="task-due-time" type="time" v-model="taskForm.due_time" class="w-full text-xs" />
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label for="task-description" class="mb-1.5 block text-sm font-medium text-surface-700"> Description / notes </label>
                    <Textarea
                        id="task-description"
                        v-model="taskForm.description"
                        rows="2"
                        placeholder="Add task notes or instructions..."
                        class="w-full text-xs"
                        :invalid="Boolean(taskForm.errors.description)"
                    />
                    <small v-if="taskForm.errors.description" class="mt-1 block text-xs text-rose-600">{{ taskForm.errors.description }}</small>
                </div>

                <div>
                    <label for="task-handover" class="mb-1.5 block text-sm font-medium text-surface-700">Handover notes</label>
                    <Textarea
                        id="task-handover"
                        v-model="taskForm.handover_notes"
                        rows="2"
                        placeholder="Anything the next shift should know..."
                        class="w-full text-xs"
                        :invalid="Boolean(taskForm.errors.handover_notes)"
                    />
                    <small v-if="taskForm.errors.handover_notes" class="mt-1 block text-xs text-rose-600">{{ taskForm.errors.handover_notes }}</small>
                </div>

                <!-- Checklist Builder -->
                <div class="border border-surface-200 bg-surface-50 p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-medium text-surface-800">Checklist steps</span>
                        <span class="text-[11px] text-surface-500">{{ taskForm.checklist.length }} items</span>
                    </div>

                    <!-- Existing Checklist Items -->
                    <div v-if="taskForm.checklist.length > 0" class="mb-2.5 space-y-1.5">
                        <div v-for="(item, idx) in taskForm.checklist" :key="idx" class="flex items-center justify-between gap-2 border border-surface-200 bg-white p-2 text-xs">
                            <div class="flex flex-1 items-center gap-2">
                                <Checkbox v-model="item.is_completed" :binary="true" />
                                <span :class="[item.is_completed ? 'text-surface-400 line-through' : 'text-surface-800']">
                                    {{ item.text }}
                                </span>
                            </div>
                            <button type="button" @click="removeChecklistItem(idx)" class="p-1 text-surface-400 hover:text-rose-500">
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
                            class="flex-1 !bg-white text-xs !h-9"
                        />
                        <Button
                            type="button"
                            label="Add"
                            icon="pi pi-plus"
                            size="small"
                            class="!h-9 !px-3.5 !text-xs !font-semibold shrink-0"
                            @click="addChecklistItem"
                        />
                    </div>
                </div>

                <!-- Pin Option -->
                <div class="flex items-center gap-2 pt-1">
                    <Checkbox v-model="taskForm.is_pinned" :binary="true" inputId="is_pinned" />
                    <label for="is_pinned" class="cursor-pointer text-xs font-medium text-surface-700 select-none"> Pin this task to the top </label>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end gap-2 border-t border-surface-100 pt-3">
                    <Button label="Cancel" text @click="taskDialog = false" class="!text-xs" />
                    <Button type="submit" :label="isEditing ? 'Update task' : 'Create task'" icon="pi pi-check" :loading="taskForm.processing" />
                </div>
            </form>
        </Dialog>

        <!-- ========================================== -->
        <!-- 7. TASK DETAILS DRAWER                     -->
        <!-- ========================================== -->
        <Drawer v-model:visible="detailDrawer" header="Task details" position="right" class="!w-full sm:!w-[560px]">
            <div v-if="selectedTask" class="space-y-4 pt-1">
                <!-- Header Card -->
                <div class="border border-surface-200 bg-surface-50 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <Tag :value="getCategoryMeta(selectedTask.category).label" severity="secondary" />
                            <Tag :value="selectedTask.priority" :severity="getPrioritySeverity(selectedTask.priority)" />
                        </div>
                        <Tag :value="getStatusLabel(selectedTask.status)" :severity="getStatusSeverity(selectedTask.status)" />
                    </div>

                    <h2 class="mt-2.5 text-base leading-snug font-semibold text-surface-900">
                        {{ selectedTask.title }}
                    </h2>
                    <p v-if="selectedTask.description" class="mt-1 text-xs leading-relaxed text-surface-600">
                        {{ selectedTask.description }}
                    </p>
                </div>

                <!-- Metadata Grid -->
                <div class="grid grid-cols-1 gap-3 text-xs sm:grid-cols-2">
                    <div class="border border-surface-200 bg-white p-3">
                        <span class="mb-1 block text-[11px] tracking-wide text-surface-400 uppercase">Assigned Staff</span>
                        <div class="flex items-center gap-2 font-semibold text-surface-800">
                            <Avatar
                                :label="selectedTask.assigned_to?.name ? selectedTask.assigned_to.name.charAt(0) : '?'"
                                shape="circle"
                                size="small"
                                class="!h-5 !w-5 border border-surface-200 !bg-surface-100 !text-[10px] font-bold !text-surface-700"
                            />
                            <span>{{ selectedTask.assigned_to?.name || 'Unassigned' }}</span>
                        </div>
                    </div>

                    <div class="border border-surface-200 bg-white p-3">
                        <span class="mb-1 block text-[11px] tracking-wide text-surface-400 uppercase">Due Date</span>
                        <span :class="['font-semibold', selectedTask.is_overdue ? 'font-bold text-rose-600' : 'text-surface-800']">
                            {{ getDueMeta(selectedTask).label }}{{ getDueMeta(selectedTask).detail }}
                        </span>
                    </div>
                </div>

                <!-- Live Interactive Checklist -->
                <div v-if="selectedTask.checklist && selectedTask.checklist.length > 0" class="border border-surface-200 bg-white p-4">
                    <div class="mb-2 flex items-center justify-between text-xs font-semibold text-surface-900">
                        <span class="inline-flex items-center gap-1.5">
                            <i class="pi pi-check-square text-xs text-surface-600"></i>
                            Checklist Steps
                        </span>
                        <span class="font-normal text-surface-500">{{ selectedTask.completed_subtasks }}/{{ selectedTask.total_subtasks }} Done</span>
                    </div>

                    <div class="space-y-2">
                        <button
                            v-for="item in selectedTask.checklist"
                            :key="item.id"
                            type="button"
                            @click="toggleChecklist(selectedTask, item.id)"
                            class="flex w-full cursor-pointer items-center gap-2.5 border border-surface-100 p-2 text-left transition-colors hover:bg-surface-50"
                        >
                            <Checkbox :modelValue="Boolean(item.is_completed)" :binary="true" class="pointer-events-none" />
                            <span :class="['text-xs', item.is_completed ? 'text-surface-400 line-through' : 'font-medium text-surface-800']">
                                {{ item.text }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Handover Notes -->
                <div v-if="selectedTask.handover_notes" class="border border-amber-200 bg-amber-50/60 p-3 text-xs">
                    <span class="mb-1 block font-semibold text-amber-900">Handover Notes:</span>
                    <p class="text-amber-800">{{ selectedTask.handover_notes }}</p>
                </div>

                <!-- Quick Status Advancement Buttons -->
                <div class="flex flex-col gap-3 border-t border-surface-100 pt-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2">
                        <Button v-if="selectedTask.status === 'TODO'" label="Start work" icon="pi pi-play" size="small" @click="updateTaskStatus(selectedTask, 'IN_PROGRESS')" />
                        <Button
                            v-if="selectedTask.status === 'IN_PROGRESS'"
                            label="Mark complete"
                            icon="pi pi-check"
                            size="small"
                            severity="success"
                            @click="updateTaskStatus(selectedTask, 'COMPLETED')"
                        />
                        <Button v-if="selectedTask.status === 'COMPLETED'" label="Reopen task" icon="pi pi-refresh" size="small" text @click="updateTaskStatus(selectedTask, 'TODO')" />
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        <Button
                            label="Edit"
                            icon="pi pi-pencil"
                            size="small"
                            text
                            @click="
                                detailDrawer = false;
                                openEditDialog(selectedTask);
                            "
                        />
                        <Button label="Delete" icon="pi pi-trash" size="small" text severity="danger" @click="confirmDelete(selectedTask)" />
                    </div>
                </div>
            </div>
        </Drawer>

        <!-- ========================================== -->
        <!-- 8. DELETE CONFIRMATION DIALOG              -->
        <!-- ========================================== -->
        <Dialog v-model:visible="deleteDialog" modal header="Delete task" :style="deleteDialogStyle">
            <div class="flex items-center gap-3 py-2">
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
