<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import throttle from 'lodash/throttle';
import { computed, nextTick, ref, watch } from 'vue';
import { route } from 'ziggy-js';

import Avatar from 'primevue/avatar';
import Badge from 'primevue/badge';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
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
                detail: isEditing.value ? 'Task safaltapoorvak update ho gaya!' : 'Naya task create ho gaya!',
                life: 3000,
            });
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Kripya form ki details check karein.',
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
                    detail: `Task status "${newStatus}" me badal gaya!`,
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
                    detail: task.is_pinned ? 'Task unpin ho gaya.' : 'Task pin ho gaya!',
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
                detail: 'Task delete kar diya gaya.',
                life: 3000,
            });
        },
    });
};
</script>

<template>
    <AppLayout title="Showroom & Workshop Tasks">
        <Toast />

        <div class="space-y-6">
            <!-- 🌟 HERO HEADER & QUICK STATS -->
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-[#1c3633] to-[#142825] text-amber-400 shadow-md">
                            <i class="pi pi-check-square text-lg"></i>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold tracking-tight text-surface-900 dark:text-surface-0">Showroom & Workshop Tasks</h1>
                            <p class="text-xs text-surface-500 dark:text-surface-400">Customer follow-ups, karigar orders, stock count audits aur counter activities manage karein.</p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <!-- View Mode Toggle -->
                    <div class="inline-flex rounded-xl border border-surface-200 bg-surface-50 p-1 dark:border-surface-700 dark:bg-surface-800">
                        <button
                            type="button"
                            @click="viewMode = 'kanban'"
                            :class="[
                                'flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all',
                                viewMode === 'kanban' ? 'bg-white text-[#1c3633] shadow-xs dark:bg-surface-900 dark:text-amber-400' : 'text-surface-600 hover:text-surface-900 dark:text-surface-400',
                            ]"
                        >
                            <i class="pi pi-th-large text-xs"></i>
                            <span>Kanban</span>
                        </button>
                        <button
                            type="button"
                            @click="viewMode = 'table'"
                            :class="[
                                'flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all',
                                viewMode === 'table' ? 'bg-white text-[#1c3633] shadow-xs dark:bg-surface-900 dark:text-amber-400' : 'text-surface-600 hover:text-surface-900 dark:text-surface-400',
                            ]"
                        >
                            <i class="pi pi-list text-xs"></i>
                            <span>List View</span>
                        </button>
                    </div>

                    <!-- New Task Button -->
                    <Button
                        label="Naya Task Banayein"
                        icon="pi pi-plus"
                        @click="openCreateDialog"
                        class="!rounded-xl !bg-gradient-to-r !from-[#1c3633] !via-[#234541] !to-[#142825] !border-none !text-amber-300 !font-semibold !shadow-md hover:!brightness-110"
                    />
                </div>
            </div>

            <!-- 📊 TOP KPI METRICS STRIP -->
            <div class="grid grid-cols-2 gap-3.5 sm:grid-cols-3 lg:grid-cols-5">
                <!-- Total Tasks -->
                <div class="relative overflow-hidden rounded-2xl border border-surface-200/80 bg-white p-4 shadow-xs dark:border-surface-800 dark:bg-surface-900">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-surface-500 dark:text-surface-400">Total Tasks</span>
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-300">
                            <i class="pi pi-layers text-sm"></i>
                        </span>
                    </div>
                    <div class="mt-2 text-2xl font-bold text-surface-900 dark:text-surface-0">{{ metrics.total || 0 }}</div>
                    <div class="mt-1 text-[11px] text-surface-500">Active showroom tasks</div>
                </div>

                <!-- To Do -->
                <div class="relative overflow-hidden rounded-2xl border border-amber-200/80 bg-gradient-to-br from-amber-50/50 to-white p-4 shadow-xs dark:border-amber-900/40 dark:bg-surface-900">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-amber-800 dark:text-amber-300">To Do (Pending)</span>
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400">
                            <i class="pi pi-clock text-sm"></i>
                        </span>
                    </div>
                    <div class="mt-2 text-2xl font-bold text-amber-900 dark:text-amber-200">{{ metrics.todo || 0 }}</div>
                    <div class="mt-1 text-[11px] text-amber-700/80 dark:text-amber-400/80">Abhi shuru hona hai</div>
                </div>

                <!-- In Progress -->
                <div class="relative overflow-hidden rounded-2xl border border-blue-200/80 bg-gradient-to-br from-blue-50/50 to-white p-4 shadow-xs dark:border-blue-900/40 dark:bg-surface-900">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-blue-800 dark:text-blue-300">In Progress</span>
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400">
                            <i class="pi pi-bolt text-sm"></i>
                        </span>
                    </div>
                    <div class="mt-2 text-2xl font-bold text-blue-900 dark:text-blue-200">{{ metrics.in_progress || 0 }}</div>
                    <div class="mt-1 text-[11px] text-blue-700/80 dark:text-blue-400/80">Staff kaam kar raha hai</div>
                </div>

                <!-- Overdue (Alert) -->
                <div :class="['relative overflow-hidden rounded-2xl border p-4 shadow-xs transition-all', metrics.overdue > 0 ? 'border-rose-300 bg-rose-50/60 dark:border-rose-900/60 dark:bg-rose-950/20' : 'border-surface-200/80 bg-white dark:border-surface-800 dark:bg-surface-900']">
                    <div class="flex items-center justify-between">
                        <span :class="['text-xs font-medium', metrics.overdue > 0 ? 'text-rose-800 dark:text-rose-300 font-semibold' : 'text-surface-500 dark:text-surface-400']">Overdue Tasks</span>
                        <span :class="['flex h-8 w-8 items-center justify-center rounded-lg', metrics.overdue > 0 ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/60 dark:text-rose-300 animate-pulse' : 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300']">
                            <i class="pi pi-exclamation-circle text-sm"></i>
                        </span>
                    </div>
                    <div :class="['mt-2 text-2xl font-bold', metrics.overdue > 0 ? 'text-rose-900 dark:text-rose-200' : 'text-surface-900 dark:text-surface-0']">{{ metrics.overdue || 0 }}</div>
                    <div class="mt-1 text-[11px] text-rose-700/80 dark:text-rose-400/80">Deadline nikal chuki hai</div>
                </div>

                <!-- Completed Today -->
                <div class="relative overflow-hidden rounded-2xl border border-emerald-200/80 bg-gradient-to-br from-emerald-50/50 to-white p-4 shadow-xs dark:border-emerald-900/40 dark:bg-surface-900">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-emerald-800 dark:text-emerald-300">Completed Today</span>
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400">
                            <i class="pi pi-check-circle text-sm"></i>
                        </span>
                    </div>
                    <div class="mt-2 text-2xl font-bold text-emerald-900 dark:text-emerald-200">{{ metrics.completed_today || 0 }}</div>
                    <div class="mt-1 text-[11px] text-emerald-700/80 dark:text-emerald-400/80">Total {{ metrics.completed || 0 }} tasks done</div>
                </div>
            </div>

            <!-- 🔍 CONTROL BAR & FILTER TABS -->
            <div class="rounded-2xl border border-surface-200/80 bg-white p-4 shadow-xs dark:border-surface-800 dark:bg-surface-900">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <!-- Quick Tabs -->
                    <div class="flex flex-wrap items-center gap-1.5">
                        <button
                            type="button"
                            @click="setTab('all')"
                            :class="[
                                'rounded-xl px-3 py-1.5 text-xs font-semibold transition-all',
                                activeTab === 'all' ? 'bg-[#1c3633] text-amber-300 shadow-xs' : 'bg-surface-100 text-surface-700 hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-300',
                            ]"
                        >
                            Sabhi Tasks ({{ metrics.total || 0 }})
                        </button>
                        <button
                            type="button"
                            @click="setTab('my_tasks')"
                            :class="[
                                'flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-semibold transition-all',
                                activeTab === 'my_tasks' ? 'bg-[#1c3633] text-amber-300 shadow-xs' : 'bg-surface-100 text-surface-700 hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-300',
                            ]"
                        >
                            <i class="pi pi-user text-xs"></i>
                            <span>Mere Tasks</span>
                            <span v-if="metrics.my_pending > 0" class="rounded-full bg-amber-400 px-1.5 py-0.2 text-[10px] font-bold text-slate-950">{{ metrics.my_pending }}</span>
                        </button>
                        <button
                            type="button"
                            @click="setTab('due_today')"
                            :class="[
                                'flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-semibold transition-all',
                                activeTab === 'due_today' ? 'bg-[#1c3633] text-amber-300 shadow-xs' : 'bg-surface-100 text-surface-700 hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-300',
                            ]"
                        >
                            <i class="pi pi-calendar text-xs"></i>
                            <span>Aaj Ke ({{ metrics.due_today || 0 }})</span>
                        </button>
                        <button
                            type="button"
                            @click="setTab('urgent')"
                            :class="[
                                'flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-semibold transition-all',
                                activeTab === 'urgent' ? 'bg-[#1c3633] text-amber-300 shadow-xs' : 'bg-surface-100 text-surface-700 hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-300',
                            ]"
                        >
                            <span>High / Urgent 🔥</span>
                        </button>
                        <button
                            type="button"
                            @click="setTab('completed')"
                            :class="[
                                'flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-semibold transition-all',
                                activeTab === 'completed' ? 'bg-[#1c3633] text-amber-300 shadow-xs' : 'bg-surface-100 text-surface-700 hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-300',
                            ]"
                        >
                            <i class="pi pi-check text-xs"></i>
                            <span>Completed</span>
                        </button>
                    </div>

                    <!-- Search and Dropdown Filters -->
                    <div class="flex flex-wrap items-center gap-2.5">
                        <!-- Search -->
                        <div class="relative w-full sm:w-60">
                            <i class="pi pi-search absolute top-1/2 left-3 -translate-y-1/2 text-xs text-surface-400"></i>
                            <InputText
                                v-model="search"
                                placeholder="Search tasks..."
                                class="!w-full !rounded-xl !pl-8 !text-xs !bg-surface-50 dark:!bg-surface-800"
                            />
                            <button v-if="search" @click="search = ''" class="absolute top-1/2 right-2.5 -translate-y-1/2 text-surface-400 hover:text-surface-600">
                                <i class="pi pi-times text-xs"></i>
                            </button>
                        </div>

                        <!-- Category Filter -->
                        <Select
                            v-model="categoryFilter"
                            :options="[{ label: 'All Categories', value: 'all' }, ...categories]"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Category"
                            class="!rounded-xl !text-xs !bg-surface-50 dark:!bg-surface-800 w-40"
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
                            class="!rounded-xl !text-xs !bg-surface-50 dark:!bg-surface-800 w-36"
                        />
                    </div>
                </div>
            </div>

            <!-- 📋 VIEW 1: KANBAN BOARD VIEW -->
            <div v-if="viewMode === 'kanban'" class="grid grid-cols-1 gap-5 lg:grid-cols-3">
                <!-- Column 1: TO DO (Pending) -->
                <div class="flex flex-col rounded-2xl border border-surface-200/90 bg-surface-50/70 p-3.5 dark:border-surface-800 dark:bg-surface-900/50">
                    <div class="mb-3 flex items-center justify-between border-b border-surface-200/80 pb-2.5 dark:border-surface-800">
                        <div class="flex items-center gap-2">
                            <span class="h-3 w-3 rounded-full bg-amber-500 shadow-xs"></span>
                            <h2 class="text-sm font-bold text-surface-900 dark:text-surface-0">To Do</h2>
                            <Badge :value="todoTasks.length" severity="warn" class="!text-[11px]" />
                        </div>
                        <Button
                            icon="pi pi-plus"
                            text
                            rounded
                            size="small"
                            @click="openCreateDialog"
                            v-tooltip.top="'Naya Task'"
                        />
                    </div>

                    <div class="flex flex-col gap-3 min-h-[400px]">
                        <div v-if="todoTasks.length === 0" class="flex flex-col items-center justify-center rounded-xl border border-dashed border-surface-200 py-12 text-center dark:border-surface-800">
                            <i class="pi pi-check-circle text-2xl text-surface-400 mb-2"></i>
                            <p class="text-xs text-surface-500 font-medium">Koi pending task nahi hai</p>
                        </div>

                        <!-- Task Cards -->
                        <div
                            v-for="task in todoTasks"
                            :key="task.id"
                            class="group relative rounded-xl border border-surface-200 bg-white p-3.5 shadow-xs transition-all hover:border-[#1c3633]/40 hover:shadow-md dark:border-surface-800 dark:bg-surface-900"
                        >
                            <!-- Card Header: Category & Priority & Pin -->
                            <div class="flex items-center justify-between gap-1.5">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <Tag
                                        :value="getCategoryMeta(task.category).label"
                                        severity="secondary"
                                        class="!text-[10px] !font-medium"
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
                                    :class="['text-xs transition-colors', task.is_pinned ? 'text-amber-500' : 'text-surface-300 hover:text-surface-600']"
                                    v-tooltip.top="task.is_pinned ? 'Unpin' : 'Pin'"
                                >
                                    <i :class="task.is_pinned ? 'pi pi-bookmark-fill' : 'pi pi-bookmark'"></i>
                                </button>
                            </div>

                            <!-- Title & Description -->
                            <h3
                                @click="openDetailDrawer(task)"
                                class="mt-2.5 cursor-pointer text-sm font-semibold text-surface-900 hover:text-[#1c3633] dark:text-surface-0 dark:hover:text-amber-300 line-clamp-2"
                            >
                                {{ task.title }}
                            </h3>
                            <p v-if="task.description" class="mt-1 text-xs text-surface-500 dark:text-surface-400 line-clamp-2">
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
                            <div class="mt-3.5 flex items-center justify-between border-t border-surface-100 pt-2.5 text-xs dark:border-surface-800">
                                <div class="flex items-center gap-1.5">
                                    <span
                                        :class="[
                                            'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-medium',
                                            task.is_overdue
                                                ? 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300'
                                                : 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300',
                                        ]"
                                    >
                                        <i class="pi pi-calendar text-[10px]"></i>
                                        <span>{{ task.due_date || 'No Date' }}</span>
                                    </span>
                                </div>

                                <div class="flex items-center gap-1.5">
                                    <Avatar
                                        :label="task.assigned_to?.name ? task.assigned_to.name.charAt(0) : '?'"
                                        shape="circle"
                                        size="small"
                                        class="!h-6 !w-6 !text-[11px] !bg-amber-100 !text-amber-800 font-bold"
                                        v-tooltip.top="task.assigned_to?.name || 'Unassigned'"
                                    />
                                </div>
                            </div>

                            <!-- Card Action Buttons -->
                            <div class="mt-3 flex items-center justify-between gap-1 border-t border-surface-100 pt-2 dark:border-surface-800">
                                <Button
                                    label="Kaam Shuru Karein ⚡"
                                    size="small"
                                    text
                                    class="!p-0 !text-xs !font-semibold !text-blue-600 hover:!underline dark:!text-blue-400"
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
                <div class="flex flex-col rounded-2xl border border-blue-200/90 bg-blue-50/20 p-3.5 dark:border-blue-900/30 dark:bg-surface-900/50">
                    <div class="mb-3 flex items-center justify-between border-b border-surface-200/80 pb-2.5 dark:border-surface-800">
                        <div class="flex items-center gap-2">
                            <span class="h-3 w-3 rounded-full bg-blue-500 shadow-xs"></span>
                            <h2 class="text-sm font-bold text-surface-900 dark:text-surface-0">In Progress</h2>
                            <Badge :value="inProgressTasks.length" severity="info" class="!text-[11px]" />
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 min-h-[400px]">
                        <div v-if="inProgressTasks.length === 0" class="flex flex-col items-center justify-center rounded-xl border border-dashed border-surface-200 py-12 text-center dark:border-surface-800">
                            <i class="pi pi-bolt text-2xl text-surface-400 mb-2"></i>
                            <p class="text-xs text-surface-500 font-medium">Koi task in-progress nahi hai</p>
                        </div>

                        <!-- Task Cards -->
                        <div
                            v-for="task in inProgressTasks"
                            :key="task.id"
                            class="group relative rounded-xl border border-blue-200/80 bg-white p-3.5 shadow-xs transition-all hover:border-blue-400 hover:shadow-md dark:border-surface-800 dark:bg-surface-900"
                        >
                            <div class="flex items-center justify-between gap-1.5">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <Tag
                                        :value="getCategoryMeta(task.category).label"
                                        severity="secondary"
                                        class="!text-[10px] !font-medium"
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
                                    :class="['text-xs transition-colors', task.is_pinned ? 'text-amber-500' : 'text-surface-300 hover:text-surface-600']"
                                    v-tooltip.top="task.is_pinned ? 'Unpin' : 'Pin'"
                                >
                                    <i :class="task.is_pinned ? 'pi pi-bookmark-fill' : 'pi pi-bookmark'"></i>
                                </button>
                            </div>

                            <h3
                                @click="openDetailDrawer(task)"
                                class="mt-2.5 cursor-pointer text-sm font-semibold text-surface-900 hover:text-blue-600 dark:text-surface-0 dark:hover:text-blue-400 line-clamp-2"
                            >
                                {{ task.title }}
                            </h3>
                            <p v-if="task.description" class="mt-1 text-xs text-surface-500 dark:text-surface-400 line-clamp-2">
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
                            <div class="mt-3.5 flex items-center justify-between border-t border-surface-100 pt-2.5 text-xs dark:border-surface-800">
                                <div class="flex items-center gap-1.5">
                                    <span
                                        :class="[
                                            'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-medium',
                                            task.is_overdue
                                                ? 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300'
                                                : 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300',
                                        ]"
                                    >
                                        <i class="pi pi-calendar text-[10px]"></i>
                                        <span>{{ task.due_date || 'No Date' }}</span>
                                    </span>
                                </div>

                                <div class="flex items-center gap-1.5">
                                    <Avatar
                                        :label="task.assigned_to?.name ? task.assigned_to.name.charAt(0) : '?'"
                                        shape="circle"
                                        size="small"
                                        class="!h-6 !w-6 !text-[11px] !bg-blue-100 !text-blue-800 font-bold"
                                        v-tooltip.top="task.assigned_to?.name || 'Unassigned'"
                                    />
                                </div>
                            </div>

                            <!-- Card Action Buttons -->
                            <div class="mt-3 flex items-center justify-between gap-1 border-t border-surface-100 pt-2 dark:border-surface-800">
                                <Button
                                    label="Mark Done ✅"
                                    size="small"
                                    text
                                    class="!p-0 !text-xs !font-semibold !text-emerald-600 hover:!underline dark:!text-emerald-400"
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
                <div class="flex flex-col rounded-2xl border border-emerald-200/90 bg-emerald-50/20 p-3.5 dark:border-emerald-900/30 dark:bg-surface-900/50">
                    <div class="mb-3 flex items-center justify-between border-b border-surface-200/80 pb-2.5 dark:border-surface-800">
                        <div class="flex items-center gap-2">
                            <span class="h-3 w-3 rounded-full bg-emerald-500 shadow-xs"></span>
                            <h2 class="text-sm font-bold text-surface-900 dark:text-surface-0">Completed</h2>
                            <Badge :value="completedTasks.length" severity="success" class="!text-[11px]" />
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 min-h-[400px]">
                        <div v-if="completedTasks.length === 0" class="flex flex-col items-center justify-center rounded-xl border border-dashed border-surface-200 py-12 text-center dark:border-surface-800">
                            <i class="pi pi-inbox text-2xl text-surface-400 mb-2"></i>
                            <p class="text-xs text-surface-500 font-medium">Completed tasks yahan aayenge</p>
                        </div>

                        <!-- Task Cards -->
                        <div
                            v-for="task in completedTasks"
                            :key="task.id"
                            class="group relative rounded-xl border border-emerald-100 bg-white/90 p-3.5 opacity-90 shadow-xs transition-all hover:opacity-100 hover:shadow-md dark:border-surface-800 dark:bg-surface-900"
                        >
                            <div class="flex items-center justify-between gap-1.5">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <Tag
                                        :value="getCategoryMeta(task.category).label"
                                        severity="secondary"
                                        class="!text-[10px] !font-medium"
                                    />
                                    <Tag
                                        value="DONE"
                                        severity="success"
                                        class="!text-[10px] !font-bold"
                                    />
                                </div>
                                <i class="pi pi-check-circle text-emerald-600 text-sm"></i>
                            </div>

                            <h3
                                @click="openDetailDrawer(task)"
                                class="mt-2.5 cursor-pointer text-sm font-medium text-surface-700 line-through hover:text-emerald-700 dark:text-surface-300 dark:hover:text-emerald-400 line-clamp-2"
                            >
                                {{ task.title }}
                            </h3>

                            <!-- Due Date & Assignee -->
                            <div class="mt-3 flex items-center justify-between border-t border-surface-100 pt-2.5 text-xs dark:border-surface-800">
                                <span class="text-[11px] text-surface-500">Done at: {{ task.completed_at ? new Date(task.completed_at).toLocaleDateString('en-IN') : 'N/A' }}</span>
                                <Avatar
                                    :label="task.assigned_to?.name ? task.assigned_to.name.charAt(0) : '?'"
                                    shape="circle"
                                    size="small"
                                    class="!h-6 !w-6 !text-[11px] !bg-emerald-100 !text-emerald-800 font-bold"
                                    v-tooltip.top="task.assigned_to?.name || 'Unassigned'"
                                />
                            </div>

                            <!-- Card Action Buttons -->
                            <div class="mt-3 flex items-center justify-between gap-1 border-t border-surface-100 pt-2 dark:border-surface-800">
                                <Button
                                    label="Reopen ↺"
                                    size="small"
                                    text
                                    class="!p-0 !text-xs !text-surface-500 hover:!text-surface-800 dark:hover:!text-surface-200"
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

            <!-- 📋 VIEW 2: LIST / TABLE VIEW -->
            <div v-else class="rounded-2xl border border-surface-200/80 bg-white p-4 shadow-xs dark:border-surface-800 dark:bg-surface-900">
                <DataTable
                    :value="tasks"
                    paginator
                    :rows="10"
                    :rowsPerPageOptions="[10, 20, 50]"
                    responsiveLayout="scroll"
                    class="p-datatable-sm"
                >
                    <template #empty>
                        <div class="py-8 text-center text-surface-500">
                            <i class="pi pi-search text-3xl mb-2 text-surface-400"></i>
                            <p>Koi matching task nahi mila.</p>
                        </div>
                    </template>

                    <!-- Pinned -->
                    <Column header="" style="width: 3rem">
                        <template #body="{ data }">
                            <button
                                type="button"
                                @click="togglePin(data)"
                                :class="['text-xs transition-colors', data.is_pinned ? 'text-amber-500' : 'text-surface-300 hover:text-surface-600']"
                            >
                                <i :class="data.is_pinned ? 'pi pi-bookmark-fill' : 'pi pi-bookmark'"></i>
                            </button>
                        </template>
                    </Column>

                    <!-- Title & Details -->
                    <Column header="Task Title" sortable field="title">
                        <template #body="{ data }">
                            <div class="cursor-pointer" @click="openDetailDrawer(data)">
                                <span :class="['font-semibold text-surface-900 dark:text-surface-0 hover:text-[#1c3633] dark:hover:text-amber-300', data.status === 'COMPLETED' ? 'line-through text-surface-500' : '']">
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

                    <!-- Checklist -->
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
                                    'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium',
                                    data.is_overdue
                                        ? 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 font-bold'
                                        : 'text-surface-700 dark:text-surface-300',
                                ]"
                            >
                                <i class="pi pi-calendar text-[10px]"></i>
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
                                    class="!h-6 !w-6 !text-[11px] !bg-amber-100 !text-amber-800 font-bold"
                                />
                                <span class="text-xs text-surface-700 dark:text-surface-300 font-medium">
                                    {{ data.assigned_to?.name || 'Unassigned' }}
                                </span>
                            </div>
                        </template>
                    </Column>

                    <!-- Status -->
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
                                class="!rounded-xl !text-xs w-32"
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

        <!-- 🌟 MODAL: CREATE / EDIT TASK -->
        <Dialog
            v-model:visible="taskDialog"
            modal
            :header="isEditing ? 'Task Edit Karein' : 'Naya Task Banayein'"
            :style="{ width: '560px' }"
            class="!rounded-2xl"
        >
            <form @submit.prevent="saveTask" class="space-y-4 pt-1">
                <!-- Title -->
                <div>
                    <label class="mb-1 block text-xs font-semibold text-surface-700 dark:text-surface-200">
                        Task Title <span class="text-rose-500">*</span>
                    </label>
                    <InputText
                        v-model="taskForm.title"
                        placeholder="e.g. Ramesh ji ko custom antique necklace delivery ke liye call karein"
                        class="!w-full !rounded-xl !text-sm"
                        required
                    />
                </div>

                <!-- Category & Priority -->
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-surface-700 dark:text-surface-200">
                            Category <span class="text-rose-500">*</span>
                        </label>
                        <Select
                            v-model="taskForm.category"
                            :options="categories"
                            optionLabel="label"
                            optionValue="value"
                            class="!w-full !rounded-xl !text-xs"
                            required
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-surface-700 dark:text-surface-200">
                            Priority <span class="text-rose-500">*</span>
                        </label>
                        <Select
                            v-model="taskForm.priority"
                            :options="priorities"
                            optionLabel="label"
                            optionValue="value"
                            class="!w-full !rounded-xl !text-xs"
                            required
                        />
                    </div>
                </div>

                <!-- Assigned To & Status -->
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-surface-700 dark:text-surface-200">
                            Assign Staff Member
                        </label>
                        <Select
                            v-model="taskForm.assigned_to"
                            :options="availableUsers"
                            optionLabel="name"
                            optionValue="id"
                            placeholder="Select Staff"
                            class="!w-full !rounded-xl !text-xs"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-surface-700 dark:text-surface-200">
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
                            class="!w-full !rounded-xl !text-xs"
                        />
                    </div>
                </div>

                <!-- Due Date & Time -->
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-surface-700 dark:text-surface-200">
                            Due Date
                        </label>
                        <InputText
                            type="date"
                            v-model="taskForm.due_date"
                            class="!w-full !rounded-xl !text-xs"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-surface-700 dark:text-surface-200">
                            Target Time
                        </label>
                        <InputText
                            type="time"
                            v-model="taskForm.due_time"
                            class="!w-full !rounded-xl !text-xs"
                        />
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label class="mb-1 block text-xs font-semibold text-surface-700 dark:text-surface-200">
                        Description / Notes
                    </label>
                    <Textarea
                        v-model="taskForm.description"
                        rows="2"
                        placeholder="Task ke sambandh me zaroori nirdesh ya details likhein..."
                        class="!w-full !rounded-xl !text-xs"
                    />
                </div>

                <!-- 📋 Checklist Builder -->
                <div class="rounded-xl border border-surface-200 bg-surface-50 p-3 dark:border-surface-800 dark:bg-surface-800/60">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-xs font-semibold text-surface-800 dark:text-surface-200">Subtask Checklist (Steps)</span>
                        <span class="text-[11px] text-surface-500">{{ taskForm.checklist.length }} items</span>
                    </div>

                    <!-- Existing Checklist Items -->
                    <div v-if="taskForm.checklist.length > 0" class="space-y-1.5 mb-2.5">
                        <div
                            v-for="(item, idx) in taskForm.checklist"
                            :key="idx"
                            class="flex items-center justify-between gap-2 rounded-lg bg-white p-2 text-xs shadow-2xs dark:bg-surface-900"
                        >
                            <div class="flex items-center gap-2 flex-1">
                                <Checkbox v-model="item.is_completed" :binary="true" />
                                <span :class="[item.is_completed ? 'line-through text-surface-400' : 'text-surface-800 dark:text-surface-100']">
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
                            class="!flex-1 !rounded-lg !text-xs !bg-white dark:!bg-surface-900"
                        />
                        <Button
                            type="button"
                            icon="pi pi-plus"
                            size="small"
                            @click="addChecklistItem"
                            class="!rounded-lg !bg-[#1c3633] !text-amber-300 !border-none"
                        />
                    </div>
                </div>

                <!-- Pin Option -->
                <div class="flex items-center gap-2 pt-1">
                    <Checkbox v-model="taskForm.is_pinned" :binary="true" inputId="is_pinned" />
                    <label for="is_pinned" class="cursor-pointer text-xs font-medium text-surface-700 dark:text-surface-300">
                        Is task ko top par Pin karein (High Visibility)
                    </label>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end gap-2 border-t border-surface-100 pt-3 dark:border-surface-800">
                    <Button label="Cancel" text @click="taskDialog = false" class="!rounded-xl !text-xs" />
                    <Button
                        type="submit"
                        :label="isEditing ? 'Update Task' : 'Create Task'"
                        icon="pi pi-check"
                        :loading="taskForm.processing"
                        class="!rounded-xl !bg-[#1c3633] !text-amber-300 !border-none !font-semibold"
                    />
                </div>
            </form>
        </Dialog>

        <!-- 🌟 MODAL: TASK DETAIL & RESOLUTION DRAWER -->
        <Dialog
            v-model:visible="detailDrawer"
            modal
            header="Task Details"
            :style="{ width: '540px' }"
            class="!rounded-2xl"
        >
            <div v-if="selectedTask" class="space-y-4">
                <!-- Header Card -->
                <div class="rounded-xl border border-surface-200 bg-surface-50 p-3.5 dark:border-surface-800 dark:bg-surface-800/60">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <Tag :value="getCategoryMeta(selectedTask.category).label" severity="secondary" />
                            <Tag :value="selectedTask.priority" :severity="getPrioritySeverity(selectedTask.priority)" />
                        </div>
                        <Tag :value="selectedTask.status" :severity="getStatusSeverity(selectedTask.status)" />
                    </div>

                    <h2 class="mt-2.5 text-base font-bold text-surface-900 dark:text-surface-0">
                        {{ selectedTask.title }}
                    </h2>
                    <p v-if="selectedTask.description" class="mt-1 text-xs text-surface-600 dark:text-surface-300 leading-relaxed">
                        {{ selectedTask.description }}
                    </p>
                </div>

                <!-- Metadata Grid -->
                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div class="rounded-xl border border-surface-200 p-3 dark:border-surface-800">
                        <span class="text-[11px] text-surface-400 block mb-1">Assigned Staff</span>
                        <div class="flex items-center gap-2 font-semibold text-surface-800 dark:text-surface-200">
                            <Avatar
                                :label="selectedTask.assigned_to?.name ? selectedTask.assigned_to.name.charAt(0) : '?'"
                                shape="circle"
                                size="small"
                                class="!h-5 !w-5 !text-[10px] !bg-amber-100 !text-amber-800 font-bold"
                            />
                            <span>{{ selectedTask.assigned_to?.name || 'Unassigned' }}</span>
                        </div>
                    </div>

                    <div class="rounded-xl border border-surface-200 p-3 dark:border-surface-800">
                        <span class="text-[11px] text-surface-400 block mb-1">Due Date</span>
                        <span :class="['font-semibold', selectedTask.is_overdue ? 'text-rose-600 font-bold' : 'text-surface-800 dark:text-surface-200']">
                            {{ selectedTask.due_date || 'No Date' }} {{ selectedTask.due_time ? '(' + selectedTask.due_time + ')' : '' }}
                        </span>
                    </div>
                </div>

                <!-- Live Interactive Checklist -->
                <div v-if="selectedTask.checklist && selectedTask.checklist.length > 0" class="rounded-xl border border-surface-200 bg-white p-3.5 dark:border-surface-800 dark:bg-surface-900">
                    <div class="mb-2 flex items-center justify-between text-xs font-semibold text-surface-900 dark:text-surface-0">
                        <span>Checklist Steps</span>
                        <span class="text-surface-500 font-normal">{{ selectedTask.completed_subtasks }}/{{ selectedTask.total_subtasks }} Done</span>
                    </div>

                    <div class="space-y-2">
                        <div
                            v-for="item in selectedTask.checklist"
                            :key="item.id"
                            @click="toggleChecklist(selectedTask, item.id)"
                            class="flex cursor-pointer items-center gap-2.5 rounded-lg p-2 transition-colors hover:bg-surface-100 dark:hover:bg-surface-800"
                        >
                            <Checkbox :modelValue="Boolean(item.is_completed)" :binary="true" />
                            <span :class="['text-xs', item.is_completed ? 'line-through text-surface-400' : 'text-surface-800 dark:text-surface-200 font-medium']">
                                {{ item.text }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Handover Notes -->
                <div v-if="selectedTask.handover_notes" class="rounded-xl border border-amber-200 bg-amber-50/60 p-3 text-xs dark:border-amber-900/40 dark:bg-surface-900">
                    <span class="font-semibold text-amber-900 dark:text-amber-300 block mb-1">Handover Notes:</span>
                    <p class="text-amber-800 dark:text-amber-400">{{ selectedTask.handover_notes }}</p>
                </div>

                <!-- Quick Status Advancement Buttons -->
                <div class="flex items-center justify-between border-t border-surface-100 pt-3 dark:border-surface-800">
                    <div class="flex items-center gap-2">
                        <Button
                            v-if="selectedTask.status === 'TODO'"
                            label="Start Work ⚡"
                            size="small"
                            @click="updateTaskStatus(selectedTask, 'IN_PROGRESS')"
                            class="!rounded-xl !bg-blue-600 !border-none !text-white !font-semibold !text-xs"
                        />
                        <Button
                            v-if="selectedTask.status === 'IN_PROGRESS'"
                            label="Mark Completed ✅"
                            size="small"
                            @click="updateTaskStatus(selectedTask, 'COMPLETED')"
                            class="!rounded-xl !bg-emerald-600 !border-none !text-white !font-semibold !text-xs"
                        />
                        <Button
                            v-if="selectedTask.status === 'COMPLETED'"
                            label="Reopen Task ↺"
                            size="small"
                            text
                            @click="updateTaskStatus(selectedTask, 'TODO')"
                            class="!rounded-xl !text-xs"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <Button
                            label="Edit"
                            icon="pi pi-pencil"
                            size="small"
                            text
                            @click="detailDrawer = false; openEditDialog(selectedTask)"
                            class="!rounded-xl !text-xs"
                        />
                        <Button
                            label="Delete"
                            icon="pi pi-trash"
                            size="small"
                            text
                            severity="danger"
                            @click="confirmDelete(selectedTask)"
                            class="!rounded-xl !text-xs"
                        />
                    </div>
                </div>
            </div>
        </Dialog>

        <!-- 🌟 DELETE CONFIRMATION DIALOG -->
        <Dialog v-model:visible="deleteDialog" modal header="Task Delete Karein?" :style="{ width: '400px' }" class="!rounded-2xl">
            <div class="flex items-center gap-3">
                <i class="pi pi-exclamation-triangle text-2xl text-rose-500"></i>
                <span class="text-sm text-surface-700 dark:text-surface-300">
                    Kya aap sach me <strong>"{{ taskToDelete?.title }}"</strong> task ko delete karna chahte hain?
                </span>
            </div>
            <template #footer>
                <Button label="Cancel" text @click="deleteDialog = false" class="!rounded-xl !text-xs" />
                <Button label="Haan, Delete Karein" severity="danger" @click="deleteTask" class="!rounded-xl !text-xs !font-semibold" />
            </template>
        </Dialog>
    </AppLayout>
</template>
