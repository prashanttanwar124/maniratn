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
const viewMode = ref(props.filters?.view || 'kanban');

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

// Computed filtered tasks
const todoTasks = computed(() => props.tasks.filter((t) => t.status === 'TODO'));
const inProgressTasks = computed(() => props.tasks.filter((t) => t.status === 'IN_PROGRESS'));
const completedTasks = computed(() => props.tasks.filter((t) => t.status === 'COMPLETED'));

const getCategoryMeta = (catKey) => {
    const found = props.categories.find((c) => c.value === catKey);
    return found || { label: catKey, icon: 'pi-tag' };
};

const getPriorityClass = (priority) => {
    switch (priority) {
        case 'URGENT':
            return 'bg-rose-600 text-white font-bold';
        case 'HIGH':
            return 'bg-amber-600 text-white font-bold';
        case 'MEDIUM':
            return 'bg-sky-600 text-white font-semibold';
        default:
            return 'bg-slate-600 text-white';
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

// Checklist Management
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
                detail: isEditing.value ? 'Task details updated.' : 'Naya task ban gaya hai!',
                life: 3000,
            });
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Kripya form details check karein.',
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
                    detail: `Task status "${newStatus}" me update ho gaya.`,
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
    <AppLayout>
        <Toast />

        <div class="space-y-5">
            <!-- ========================================== -->
            <!-- 1. HERO HEADER                             -->
            <!-- ========================================== -->
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-[#1c3633] to-[#142825] text-amber-400 shadow-md border border-amber-500/20">
                            <i class="pi pi-check-square text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold tracking-tight text-surface-900">Showroom & Workshop Tasks</h1>
                            <p class="text-xs text-surface-500 mt-0.5">Customer follow-ups, karigar orders, stock count audits aur counter activities manage karein.</p>
                        </div>
                    </div>
                </div>

                <div class="flex shrink-0 flex-wrap items-center gap-3">
                    <!-- View Switcher -->
                    <div class="inline-flex rounded-xl border border-surface-200 bg-surface-100 p-1 shadow-2xs">
                        <button
                            type="button"
                            @click="viewMode = 'kanban'"
                            :class="[
                                'inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-bold transition-all',
                                viewMode === 'kanban' ? 'bg-[#1c3633] text-amber-300 shadow-sm' : 'text-surface-600 hover:text-surface-900',
                            ]"
                        >
                            <i class="pi pi-th-large text-xs"></i>
                            <span>Kanban</span>
                        </button>
                        <button
                            type="button"
                            @click="viewMode = 'table'"
                            :class="[
                                'inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-bold transition-all',
                                viewMode === 'table' ? 'bg-[#1c3633] text-amber-300 shadow-sm' : 'text-surface-600 hover:text-surface-900',
                            ]"
                        >
                            <i class="pi pi-list text-xs"></i>
                            <span>List View</span>
                        </button>
                    </div>

                    <!-- Luxury Naya Task Button -->
                    <button
                        type="button"
                        @click="openCreateDialog"
                        class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 px-4 py-2 text-xs font-bold text-slate-950 shadow-md transition-transform hover:scale-[1.02] active:scale-[0.98]"
                    >
                        <i class="pi pi-plus text-xs font-bold"></i>
                        <span>+ Naya Task Banayein</span>
                    </button>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 2. LUXURY STATS KPI STRIP                  -->
            <!-- ========================================== -->
            <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 xl:grid-cols-5">
                <!-- Total Tasks -->
                <div class="rounded-2xl border border-[#2a4d49] bg-[#1c3633] p-4 text-white shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <span class="text-xs font-medium text-emerald-200/80">Total Tasks</span>
                            <div class="mt-1.5 text-2xl font-black text-white leading-none">{{ metrics.total || 0 }}</div>
                        </div>
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/10 text-amber-300">
                            <i class="pi pi-layers text-sm"></i>
                        </div>
                    </div>
                    <p class="mt-2.5 text-[11px] text-emerald-200/60">Active showroom tasks</p>
                </div>

                <!-- To Do -->
                <div class="rounded-2xl border border-amber-800/40 bg-amber-950/20 p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <span class="text-xs font-semibold text-amber-700">To Do (Pending)</span>
                            <div class="mt-1.5 text-2xl font-black text-amber-600 leading-none">{{ metrics.todo || 0 }}</div>
                        </div>
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-500/20 text-amber-700">
                            <i class="pi pi-clock text-sm"></i>
                        </div>
                    </div>
                    <p class="mt-2.5 text-[11px] text-amber-700/80">Abhi shuru hona hai</p>
                </div>

                <!-- In Progress -->
                <div class="rounded-2xl border border-sky-800/40 bg-sky-950/20 p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <span class="text-xs font-semibold text-sky-700">In Progress</span>
                            <div class="mt-1.5 text-2xl font-black text-sky-600 leading-none">{{ metrics.in_progress || 0 }}</div>
                        </div>
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-sky-500/20 text-sky-700">
                            <i class="pi pi-bolt text-sm"></i>
                        </div>
                    </div>
                    <p class="mt-2.5 text-[11px] text-sky-700/80">Staff kaam kar raha hai</p>
                </div>

                <!-- Overdue -->
                <div class="rounded-2xl border border-rose-800/40 bg-rose-950/20 p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <span class="text-xs font-semibold text-rose-700">Overdue Tasks</span>
                            <div class="mt-1.5 text-2xl font-black text-rose-600 leading-none">{{ metrics.overdue || 0 }}</div>
                        </div>
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-rose-500/20 text-rose-700">
                            <i class="pi pi-exclamation-circle text-sm"></i>
                        </div>
                    </div>
                    <p class="mt-2.5 text-[11px] text-rose-700/80">Deadline nikal chuki hai</p>
                </div>

                <!-- Completed Today -->
                <div class="rounded-2xl border border-emerald-800/40 bg-emerald-950/20 p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <span class="text-xs font-semibold text-emerald-700">Completed Today</span>
                            <div class="mt-1.5 text-2xl font-black text-emerald-600 leading-none">{{ metrics.completed_today || 0 }}</div>
                        </div>
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-500/20 text-emerald-700">
                            <i class="pi pi-check-circle text-sm"></i>
                        </div>
                    </div>
                    <p class="mt-2.5 text-[11px] text-emerald-700/80">Total {{ metrics.completed || 0 }} tasks done</p>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 3. SINGLE-ROW LUXURY FILTER & SEARCH BAR   -->
            <!-- ========================================== -->
            <div class="rounded-2xl border border-[#2a4d49] bg-[#1c3633] p-3 shadow-md">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <!-- Left: Horizontal Non-Wrapping Tabs -->
                    <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar py-0.5">
                        <button
                            type="button"
                            @click="setTab('all')"
                            :class="[
                                'inline-flex shrink-0 items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold transition-all whitespace-nowrap',
                                activeTab === 'all'
                                    ? 'bg-amber-400 text-slate-950 shadow-sm'
                                    : 'text-emerald-100/70 hover:bg-white/10 hover:text-white',
                            ]"
                        >
                            <span>Sabhi Tasks ({{ metrics.total || 0 }})</span>
                        </button>

                        <button
                            type="button"
                            @click="setTab('my_tasks')"
                            :class="[
                                'inline-flex shrink-0 items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold transition-all whitespace-nowrap',
                                activeTab === 'my_tasks'
                                    ? 'bg-amber-400 text-slate-950 shadow-sm'
                                    : 'text-emerald-100/70 hover:bg-white/10 hover:text-white',
                            ]"
                        >
                            <i class="pi pi-user text-xs"></i>
                            <span>Mere Tasks</span>
                            <span v-if="metrics.my_pending > 0" class="rounded-full bg-amber-500 text-slate-950 px-1.5 py-0.2 text-[10px] font-black">
                                {{ metrics.my_pending }}
                            </span>
                        </button>

                        <button
                            type="button"
                            @click="setTab('due_today')"
                            :class="[
                                'inline-flex shrink-0 items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold transition-all whitespace-nowrap',
                                activeTab === 'due_today'
                                    ? 'bg-amber-400 text-slate-950 shadow-sm'
                                    : 'text-emerald-100/70 hover:bg-white/10 hover:text-white',
                            ]"
                        >
                            <i class="pi pi-calendar text-xs"></i>
                            <span>Aaj Ke ({{ metrics.due_today || 0 }})</span>
                        </button>

                        <button
                            type="button"
                            @click="setTab('urgent')"
                            :class="[
                                'inline-flex shrink-0 items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold transition-all whitespace-nowrap',
                                activeTab === 'urgent'
                                    ? 'bg-amber-400 text-slate-950 shadow-sm'
                                    : 'text-emerald-100/70 hover:bg-white/10 hover:text-white',
                            ]"
                        >
                            <span>High / Urgent 🔥</span>
                        </button>

                        <button
                            type="button"
                            @click="setTab('completed')"
                            :class="[
                                'inline-flex shrink-0 items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold transition-all whitespace-nowrap',
                                activeTab === 'completed'
                                    ? 'bg-amber-400 text-slate-950 shadow-sm'
                                    : 'text-emerald-100/70 hover:bg-white/10 hover:text-white',
                            ]"
                        >
                            <i class="pi pi-check text-xs"></i>
                            <span>Completed</span>
                        </button>
                    </div>

                    <!-- Right: Search Input + Category + Staff Select in 1 Row -->
                    <div class="flex shrink-0 flex-wrap items-center gap-2 lg:flex-nowrap">
                        <!-- Search Box -->
                        <div class="relative min-w-[200px] flex-1 lg:w-56">
                            <i class="pi pi-search absolute top-1/2 left-3 -translate-y-1/2 text-xs text-emerald-200/50"></i>
                            <input
                                type="text"
                                v-model="search"
                                placeholder="Search tasks..."
                                class="w-full rounded-xl border border-[#2e5752] bg-[#142825] py-1.5 pr-3 pl-8 text-xs text-white placeholder:text-emerald-200/40 focus:border-amber-400 focus:outline-none"
                            />
                        </div>

                        <!-- Category Select -->
                        <Select
                            v-model="categoryFilter"
                            :options="[{ label: 'All Categories', value: 'all' }, ...categories]"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Category"
                            class="!rounded-xl !bg-[#142825] !border-[#2e5752] !text-white text-xs w-36"
                        />

                        <!-- Staff Select -->
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
                            class="!rounded-xl !bg-[#142825] !border-[#2e5752] !text-white text-xs w-32"
                        />
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 4. LUXURY KANBAN BOARD VIEW                -->
            <!-- ========================================== -->
            <div v-if="viewMode === 'kanban'" class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <!-- Column 1: TO DO -->
                <div class="rounded-2xl border border-surface-200 bg-[#e8ecea] p-4 shadow-sm">
                    <!-- Column Header -->
                    <div class="mb-3.5 border-b border-surface-300/80 pb-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="h-3 w-3 shrink-0 rounded-full bg-amber-500 shadow-sm"></span>
                                <h2 class="text-base font-bold text-surface-900 leading-none tracking-tight">To Do</h2>
                                <span class="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-amber-500 px-1.5 text-[11px] font-black text-slate-950 leading-none">
                                    {{ todoTasks.length }}
                                </span>
                            </div>
                            <Button
                                icon="pi pi-plus"
                                text
                                rounded
                                size="small"
                                @click="openCreateDialog"
                                v-tooltip.top="'Add Task'"
                                class="!h-7 !w-7 !p-0 text-surface-600 hover:text-surface-900"
                            />
                        </div>
                    </div>

                    <!-- Column Cards List -->
                    <div class="flex flex-col gap-3 min-h-[420px]">
                        <div v-if="todoTasks.length === 0" class="flex flex-col items-center justify-center border-2 border-dashed border-surface-300 rounded-2xl py-12 text-center">
                            <i class="pi pi-check-square text-2xl text-surface-400 mb-1"></i>
                            <p class="text-xs text-surface-600 font-medium">No tasks in To Do</p>
                        </div>

                        <!-- Card -->
                        <div
                            v-for="task in todoTasks"
                            :key="task.id"
                            class="group relative rounded-xl border border-[#2a4d49] bg-[#1c3633] p-4 shadow-md transition-all hover:border-amber-400/60 hover:shadow-lg"
                        >
                            <!-- Card Header -->
                            <div class="flex items-center justify-between gap-1.5">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="rounded-md bg-white/10 px-2 py-0.5 text-[10px] font-medium text-emerald-100">
                                        {{ getCategoryMeta(task.category).label }}
                                    </span>
                                    <span :class="['rounded-md px-2 py-0.5 text-[10px]', getPriorityClass(task.priority)]">
                                        {{ task.priority }}
                                    </span>
                                </div>
                                <button
                                    type="button"
                                    @click="togglePin(task)"
                                    :class="['text-xs p-1 transition-colors', task.is_pinned ? 'text-amber-400' : 'text-emerald-100/30 hover:text-emerald-100']"
                                >
                                    <i :class="task.is_pinned ? 'pi pi-bookmark-fill text-amber-400' : 'pi pi-bookmark'"></i>
                                </button>
                            </div>

                            <!-- Card Title & Description with Crisp Legible Font -->
                            <h3
                                @click="openDetailDrawer(task)"
                                class="mt-2.5 cursor-pointer text-sm font-bold text-white leading-snug tracking-tight hover:text-amber-300 line-clamp-2"
                            >
                                {{ task.title }}
                            </h3>
                            <p v-if="task.description" class="mt-1 text-xs text-emerald-100/70 line-clamp-2 leading-relaxed">
                                {{ task.description }}
                            </p>

                            <!-- Subtasks Checklist Progress Bar -->
                            <div v-if="task.total_subtasks > 0" class="mt-3">
                                <div class="flex items-center justify-between text-[11px] text-emerald-200/70 mb-1">
                                    <span class="inline-flex items-center gap-1">
                                        <i class="pi pi-check-square text-[10px]"></i>
                                        Checklist
                                    </span>
                                    <span>{{ task.completed_subtasks }}/{{ task.total_subtasks }}</span>
                                </div>
                                <ProgressBar
                                    :value="task.checklist_progress"
                                    :showValue="false"
                                    class="!h-1.5 !bg-black/30"
                                />
                            </div>

                            <!-- Due Date & Assignee -->
                            <div class="mt-3.5 flex items-center justify-between border-t border-white/10 pt-2.5 text-xs">
                                <div class="flex items-center gap-1.5">
                                    <span
                                        :class="[
                                            'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-medium border',
                                            task.is_overdue
                                                ? 'bg-rose-500/20 text-rose-300 border-rose-500/40'
                                                : 'bg-black/30 text-emerald-200 border-white/10',
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
                                        class="!h-6 !w-6 !text-[11px] !bg-amber-400 !text-slate-950 font-bold"
                                        v-tooltip.top="task.assigned_to?.name || 'Unassigned'"
                                    />
                                    <span class="text-xs text-emerald-100/90 max-w-[90px] truncate font-medium">
                                        {{ task.assigned_to?.name || 'Unassigned' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Card Action Footer -->
                            <div class="mt-3 flex items-center justify-between gap-1 border-t border-white/10 pt-2">
                                <button
                                    type="button"
                                    @click="updateTaskStatus(task, 'IN_PROGRESS')"
                                    class="text-xs font-bold text-sky-400 hover:text-sky-300 hover:underline"
                                >
                                    Kaam Shuru Karein ⚡
                                </button>
                                <div class="flex items-center gap-1">
                                    <Button icon="pi pi-eye" text rounded size="small" @click="openDetailDrawer(task)" class="!h-7 !w-7 !p-0 text-emerald-200/70 hover:text-white" />
                                    <Button icon="pi pi-pencil" text rounded size="small" @click="openEditDialog(task)" class="!h-7 !w-7 !p-0 text-emerald-200/70 hover:text-white" />
                                    <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="confirmDelete(task)" class="!h-7 !w-7 !p-0 text-rose-400 hover:text-rose-300" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Column 2: IN PROGRESS -->
                <div class="rounded-2xl border border-surface-200 bg-[#e8ecea] p-4 shadow-sm">
                    <div class="mb-3.5 border-b border-surface-300/80 pb-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="h-3 w-3 shrink-0 rounded-full bg-blue-500 shadow-sm"></span>
                                <h2 class="text-base font-bold text-surface-900 leading-none tracking-tight">In Progress</h2>
                                <span class="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-blue-500 px-1.5 text-[11px] font-black text-white leading-none">
                                    {{ inProgressTasks.length }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 min-h-[420px]">
                        <div v-if="inProgressTasks.length === 0" class="flex flex-col items-center justify-center border-2 border-dashed border-surface-300 rounded-2xl py-12 text-center">
                            <i class="pi pi-bolt text-2xl text-surface-400 mb-1"></i>
                            <p class="text-xs text-surface-600 font-medium">No tasks in progress</p>
                        </div>

                        <!-- Card -->
                        <div
                            v-for="task in inProgressTasks"
                            :key="task.id"
                            class="group relative rounded-xl border border-[#2a4d49] bg-[#1c3633] p-4 shadow-md transition-all hover:border-amber-400/60 hover:shadow-lg"
                        >
                            <div class="flex items-center justify-between gap-1.5">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="rounded-md bg-white/10 px-2 py-0.5 text-[10px] font-medium text-emerald-100">
                                        {{ getCategoryMeta(task.category).label }}
                                    </span>
                                    <span :class="['rounded-md px-2 py-0.5 text-[10px]', getPriorityClass(task.priority)]">
                                        {{ task.priority }}
                                    </span>
                                </div>
                                <button
                                    type="button"
                                    @click="togglePin(task)"
                                    :class="['text-xs p-1 transition-colors', task.is_pinned ? 'text-amber-400' : 'text-emerald-100/30 hover:text-emerald-100']"
                                >
                                    <i :class="task.is_pinned ? 'pi pi-bookmark-fill text-amber-400' : 'pi pi-bookmark'"></i>
                                </button>
                            </div>

                            <h3
                                @click="openDetailDrawer(task)"
                                class="mt-2.5 cursor-pointer text-sm font-bold text-white leading-snug tracking-tight hover:text-amber-300 line-clamp-2"
                            >
                                {{ task.title }}
                            </h3>
                            <p v-if="task.description" class="mt-1 text-xs text-emerald-100/70 line-clamp-2 leading-relaxed">
                                {{ task.description }}
                            </p>

                            <!-- Subtasks Checklist Progress Bar -->
                            <div v-if="task.total_subtasks > 0" class="mt-3">
                                <div class="flex items-center justify-between text-[11px] text-emerald-200/70 mb-1">
                                    <span class="inline-flex items-center gap-1">
                                        <i class="pi pi-check-square text-[10px]"></i>
                                        Checklist
                                    </span>
                                    <span>{{ task.completed_subtasks }}/{{ task.total_subtasks }}</span>
                                </div>
                                <ProgressBar
                                    :value="task.checklist_progress"
                                    :showValue="false"
                                    class="!h-1.5 !bg-black/30"
                                />
                            </div>

                            <!-- Due Date & Assignee -->
                            <div class="mt-3.5 flex items-center justify-between border-t border-white/10 pt-2.5 text-xs">
                                <div class="flex items-center gap-1.5">
                                    <span
                                        :class="[
                                            'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-medium border',
                                            task.is_overdue
                                                ? 'bg-rose-500/20 text-rose-300 border-rose-500/40'
                                                : 'bg-black/30 text-emerald-200 border-white/10',
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
                                        class="!h-6 !w-6 !text-[11px] !bg-blue-400 !text-slate-950 font-bold"
                                        v-tooltip.top="task.assigned_to?.name || 'Unassigned'"
                                    />
                                    <span class="text-xs text-emerald-100/90 max-w-[90px] truncate font-medium">
                                        {{ task.assigned_to?.name || 'Unassigned' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Card Action Footer -->
                            <div class="mt-3 flex items-center justify-between gap-1 border-t border-white/10 pt-2">
                                <button
                                    type="button"
                                    @click="updateTaskStatus(task, 'COMPLETED')"
                                    class="text-xs font-bold text-emerald-400 hover:text-emerald-300 hover:underline"
                                >
                                    Mark Done ✅
                                </button>
                                <div class="flex items-center gap-1">
                                    <Button icon="pi pi-eye" text rounded size="small" @click="openDetailDrawer(task)" class="!h-7 !w-7 !p-0 text-emerald-200/70 hover:text-white" />
                                    <Button icon="pi pi-pencil" text rounded size="small" @click="openEditDialog(task)" class="!h-7 !w-7 !p-0 text-emerald-200/70 hover:text-white" />
                                    <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="confirmDelete(task)" class="!h-7 !w-7 !p-0 text-rose-400 hover:text-rose-300" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Column 3: COMPLETED -->
                <div class="rounded-2xl border border-surface-200 bg-[#e8ecea] p-4 shadow-sm">
                    <div class="mb-3.5 border-b border-surface-300/80 pb-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="h-3 w-3 shrink-0 rounded-full bg-emerald-500 shadow-sm"></span>
                                <h2 class="text-base font-bold text-surface-900 leading-none tracking-tight">Completed</h2>
                                <span class="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-emerald-500 px-1.5 text-[11px] font-black text-white leading-none">
                                    {{ completedTasks.length }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 min-h-[420px]">
                        <div v-if="completedTasks.length === 0" class="flex flex-col items-center justify-center border-2 border-dashed border-surface-300 rounded-2xl py-12 text-center">
                            <i class="pi pi-check-circle text-2xl text-surface-400 mb-1"></i>
                            <p class="text-xs text-surface-600 font-medium">Completed tasks will appear here</p>
                        </div>

                        <!-- Card -->
                        <div
                            v-for="task in completedTasks"
                            :key="task.id"
                            class="group relative rounded-xl border border-[#2a4d49] bg-[#1c3633]/80 p-4 shadow-md opacity-90 transition-all hover:opacity-100 hover:border-emerald-400/60"
                        >
                            <div class="flex items-center justify-between gap-1.5">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="rounded-md bg-white/10 px-2 py-0.5 text-[10px] font-medium text-emerald-100">
                                        {{ getCategoryMeta(task.category).label }}
                                    </span>
                                    <span class="rounded-md bg-emerald-600 px-2 py-0.5 text-[10px] font-bold text-white">
                                        DONE
                                    </span>
                                </div>
                                <i class="pi pi-check-circle text-emerald-400 text-sm"></i>
                            </div>

                            <h3
                                @click="openDetailDrawer(task)"
                                class="mt-2.5 cursor-pointer text-sm font-medium text-emerald-200/80 line-through leading-snug tracking-tight hover:text-white line-clamp-2"
                            >
                                {{ task.title }}
                            </h3>

                            <!-- Due Date & Assignee -->
                            <div class="mt-3 flex items-center justify-between border-t border-white/10 pt-2.5 text-xs">
                                <span class="text-[11px] text-emerald-200/60">Completed</span>
                                <Avatar
                                    :label="task.assigned_to?.name ? task.assigned_to.name.charAt(0) : '?'"
                                    shape="circle"
                                    size="small"
                                    class="!h-6 !w-6 !text-[11px] !bg-emerald-500 !text-slate-950 font-bold"
                                    v-tooltip.top="task.assigned_to?.name || 'Unassigned'"
                                />
                            </div>

                            <!-- Card Action Footer -->
                            <div class="mt-3 flex items-center justify-between gap-1 border-t border-white/10 pt-2">
                                <button
                                    type="button"
                                    @click="updateTaskStatus(task, 'TODO')"
                                    class="text-xs font-medium text-emerald-200/70 hover:text-white hover:underline"
                                >
                                    Reopen ↺
                                </button>
                                <div class="flex items-center gap-1">
                                    <Button icon="pi pi-eye" text rounded size="small" @click="openDetailDrawer(task)" class="!h-7 !w-7 !p-0 text-emerald-200/70 hover:text-white" />
                                    <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="confirmDelete(task)" class="!h-7 !w-7 !p-0 text-rose-400 hover:text-rose-300" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 5. LIST / TABLE VIEW                       -->
            <!-- ========================================== -->
            <div v-else class="rounded-2xl border border-surface-200 bg-white overflow-hidden shadow-sm">
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
                        <div class="py-12 text-center text-surface-500">
                            <i class="pi pi-search text-3xl text-surface-400 mb-2"></i>
                            <p class="text-xs">No matching tasks found.</p>
                        </div>
                    </template>

                    <!-- Pinned -->
                    <Column header="" style="width: 3rem">
                        <template #body="{ data }">
                            <button
                                type="button"
                                @click="togglePin(data)"
                                :class="['text-xs transition-colors p-1', data.is_pinned ? 'text-amber-600' : 'text-surface-300 hover:text-surface-600']"
                            >
                                <i :class="data.is_pinned ? 'pi pi-bookmark-fill text-amber-500' : 'pi pi-bookmark'"></i>
                            </button>
                        </template>
                    </Column>

                    <!-- Title & Details -->
                    <Column header="Task Title" sortable field="title">
                        <template #body="{ data }">
                            <div class="cursor-pointer py-1" @click="openDetailDrawer(data)">
                                <span :class="['font-bold text-surface-900 hover:text-amber-600 text-sm', data.status === 'COMPLETED' ? 'line-through text-surface-400' : '']">
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
                            <span :class="['rounded-md px-2 py-0.5 text-[11px]', getPriorityClass(data.priority)]">
                                {{ data.priority }}
                            </span>
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
                                    'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium border',
                                    data.is_overdue
                                        ? 'bg-rose-50 text-rose-700 border-rose-200 font-bold'
                                        : 'bg-surface-50 text-surface-700 border-surface-200',
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
                                    class="!h-6 !w-6 !text-[11px] !bg-amber-400 !text-slate-950 font-bold"
                                />
                                <span class="text-xs text-surface-800 font-medium">
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
                                class="!rounded-xl text-xs w-32"
                            />
                        </template>
                    </Column>

                    <!-- Actions -->
                    <Column header="Actions" style="width: 7rem">
                        <template #body="{ data }">
                            <div class="flex items-center gap-1">
                                <Button icon="pi pi-eye" text rounded size="small" @click="openDetailDrawer(data)" class="!h-7 !w-7 !p-0" />
                                <Button icon="pi pi-pencil" text rounded size="small" @click="openEditDialog(data)" class="!h-7 !w-7 !p-0" />
                                <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="confirmDelete(data)" class="!h-7 !w-7 !p-0" />
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
            :header="isEditing ? 'Edit Task' : 'Naya Task Banayein'"
            :style="{ width: '560px' }"
            class="!rounded-2xl"
        >
            <form @submit.prevent="saveTask" class="space-y-4 pt-2">
                <!-- Title -->
                <div>
                    <label class="mb-1 block text-xs font-semibold text-surface-700">
                        Task Title <span class="text-rose-500">*</span>
                    </label>
                    <InputText
                        v-model="taskForm.title"
                        placeholder="e.g. Ramesh Sharma ji ko bridal necklace delivery follow-up karein"
                        class="w-full text-sm !rounded-xl"
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
                            class="w-full text-xs !rounded-xl"
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
                            class="w-full text-xs !rounded-xl"
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
                            class="w-full text-xs !rounded-xl"
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
                            class="w-full text-xs !rounded-xl"
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
                            class="w-full text-xs !rounded-xl"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-surface-700">
                            Target Time
                        </label>
                        <InputText
                            type="time"
                            v-model="taskForm.due_time"
                            class="w-full text-xs !rounded-xl"
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
                        placeholder="Task ki details ya instructions likhein..."
                        class="w-full text-xs !rounded-xl"
                    />
                </div>

                <!-- Seamless Checklist Builder -->
                <div class="rounded-xl border border-surface-200 bg-surface-50 p-3.5">
                    <div class="mb-2.5 flex items-center justify-between">
                        <span class="text-xs font-bold text-surface-800">Checklist steps</span>
                        <span class="text-xs font-medium text-surface-500">{{ taskForm.checklist.length }} items</span>
                    </div>

                    <!-- Existing Checklist Items -->
                    <div v-if="taskForm.checklist.length > 0" class="space-y-1.5 mb-3">
                        <div
                            v-for="(item, idx) in taskForm.checklist"
                            :key="idx"
                            class="flex items-center justify-between gap-2 rounded-lg border border-surface-200 bg-white px-3 py-2 text-xs shadow-2xs"
                        >
                            <div class="flex items-center gap-2.5 flex-1">
                                <Checkbox v-model="item.is_completed" :binary="true" />
                                <span :class="[item.is_completed ? 'line-through text-surface-400' : 'text-surface-800 font-medium']">
                                    {{ item.text }}
                                </span>
                            </div>
                            <Button
                                icon="pi pi-trash"
                                text
                                rounded
                                size="small"
                                severity="danger"
                                @click="removeChecklistItem(idx)"
                                class="!h-6 !w-6 !p-0 text-surface-400 hover:text-rose-600"
                            />
                        </div>
                    </div>

                    <!-- InputGroup -->
                    <InputGroup class="w-full">
                        <InputText
                            v-model="newChecklistText"
                            @keydown.enter.prevent="addChecklistItem"
                            placeholder="Add subtask step (Press Enter)..."
                            class="text-xs !bg-white"
                        />
                        <Button
                            type="button"
                            label="Add"
                            icon="pi pi-plus"
                            size="small"
                            @click="addChecklistItem"
                        />
                    </InputGroup>
                </div>

                <!-- Pin Option -->
                <div class="flex items-center gap-2 pt-1">
                    <Checkbox v-model="taskForm.is_pinned" :binary="true" inputId="is_pinned" />
                    <label for="is_pinned" class="cursor-pointer text-xs font-medium text-surface-700 select-none">
                        Pin this task to top (High Priority)
                    </label>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end gap-2 border-t border-surface-100 pt-3">
                    <Button label="Cancel" text @click="taskDialog = false" class="!rounded-xl !text-xs" />
                    <Button
                        type="submit"
                        :label="isEditing ? 'Update Task' : 'Create Task'"
                        icon="pi pi-check"
                        :loading="taskForm.processing"
                        class="!rounded-xl"
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
            class="!rounded-2xl"
        >
            <div v-if="selectedTask" class="space-y-4 pt-1">
                <!-- Header Card -->
                <div class="rounded-xl border border-surface-200 bg-surface-50 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <Tag :value="getCategoryMeta(selectedTask.category).label" severity="secondary" />
                            <span :class="['rounded-md px-2 py-0.5 text-xs', getPriorityClass(selectedTask.priority)]">
                                {{ selectedTask.priority }}
                            </span>
                        </div>
                        <Tag :value="selectedTask.status" :severity="selectedTask.status === 'COMPLETED' ? 'success' : selectedTask.status === 'IN_PROGRESS' ? 'info' : 'warn'" />
                    </div>

                    <h2 class="mt-2.5 text-base font-bold text-surface-900 leading-snug">
                        {{ selectedTask.title }}
                    </h2>
                    <p v-if="selectedTask.description" class="mt-1 text-xs text-surface-600 leading-relaxed">
                        {{ selectedTask.description }}
                    </p>
                </div>

                <!-- Metadata Grid -->
                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div class="rounded-xl border border-surface-200 bg-white p-3">
                        <span class="text-[11px] text-surface-400 block mb-1 uppercase tracking-wide">Assigned Staff</span>
                        <div class="flex items-center gap-2 font-semibold text-surface-800">
                            <Avatar
                                :label="selectedTask.assigned_to?.name ? selectedTask.assigned_to.name.charAt(0) : '?'"
                                shape="circle"
                                size="small"
                                class="!h-5 !w-5 !text-[10px] !bg-amber-400 !text-slate-950 font-bold"
                            />
                            <span>{{ selectedTask.assigned_to?.name || 'Unassigned' }}</span>
                        </div>
                    </div>

                    <div class="rounded-xl border border-surface-200 bg-white p-3">
                        <span class="text-[11px] text-surface-400 block mb-1 uppercase tracking-wide">Due Date</span>
                        <span :class="['font-semibold', selectedTask.is_overdue ? 'text-rose-600 font-bold' : 'text-surface-800']">
                            {{ selectedTask.due_date || 'No Date' }} {{ selectedTask.due_time ? '(' + selectedTask.due_time + ')' : '' }}
                        </span>
                    </div>
                </div>

                <!-- Live Interactive Checklist -->
                <div v-if="selectedTask.checklist && selectedTask.checklist.length > 0" class="rounded-xl border border-surface-200 bg-white p-4">
                    <div class="mb-2 flex items-center justify-between text-xs font-semibold text-surface-900">
                        <span class="inline-flex items-center gap-1.5">
                            <i class="pi pi-check-square text-xs text-surface-600"></i>
                            Checklist Steps
                        </span>
                        <span class="text-surface-500 font-normal">{{ selectedTask.completed_subtasks }}/{{ selectedTask.total_subtasks }} Done</span>
                    </div>

                    <div class="space-y-2">
                        <div
                            v-for="item in selectedTask.checklist"
                            :key="item.id"
                            @click="toggleChecklist(selectedTask, item.id)"
                            class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-surface-100 p-2 transition-colors hover:bg-surface-50"
                        >
                            <Checkbox :modelValue="Boolean(item.is_completed)" :binary="true" />
                            <span :class="['text-xs', item.is_completed ? 'line-through text-surface-400' : 'text-surface-800 font-medium']">
                                {{ item.text }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Handover Notes -->
                <div v-if="selectedTask.handover_notes" class="rounded-xl border border-amber-200 bg-amber-50/60 p-3 text-xs">
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
                            class="!rounded-xl"
                        />
                        <Button
                            v-if="selectedTask.status === 'IN_PROGRESS'"
                            label="Mark Completed ✅"
                            size="small"
                            severity="success"
                            @click="updateTaskStatus(selectedTask, 'COMPLETED')"
                            class="!rounded-xl"
                        />
                        <Button
                            v-if="selectedTask.status === 'COMPLETED'"
                            label="Reopen Task ↺"
                            size="small"
                            text
                            @click="updateTaskStatus(selectedTask, 'TODO')"
                            class="!rounded-xl"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <Button
                            label="Edit"
                            icon="pi pi-pencil"
                            size="small"
                            text
                            @click="detailDrawer = false; openEditDialog(selectedTask)"
                            class="!rounded-xl"
                        />
                        <Button
                            label="Delete"
                            icon="pi pi-trash"
                            size="small"
                            text
                            severity="danger"
                            @click="confirmDelete(selectedTask)"
                            class="!rounded-xl"
                        />
                    </div>
                </div>
            </div>
        </Dialog>

        <!-- ========================================== -->
        <!-- 8. DELETE CONFIRMATION DIALOG              -->
        <!-- ========================================== -->
        <Dialog v-model:visible="deleteDialog" modal header="Delete Task" :style="{ width: '400px' }" class="!rounded-2xl">
            <div class="flex items-center gap-3 py-2">
                <i class="pi pi-exclamation-triangle text-2xl text-rose-500"></i>
                <span class="text-sm text-surface-700">
                    Kya aap <strong>"{{ taskToDelete?.title }}"</strong> task ko delete karna chahte hain?
                </span>
            </div>
            <template #footer>
                <Button label="Cancel" text @click="deleteDialog = false" class="!rounded-xl !text-xs" />
                <Button label="Delete" severity="danger" @click="deleteTask" class="!rounded-xl !text-xs font-semibold" />
            </template>
        </Dialog>
    </AppLayout>
</template>
