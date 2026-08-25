<script setup lang="ts">
import { AlertCircle, Calendar, CheckCircle2, CheckSquare, Clock, ExternalLink, Plus } from 'lucide-vue-next';

interface ActionItem {
    tool: string;
    args: Record<string, any>;
    result: Record<string, any>;
}

defineProps<{
    action: ActionItem;
}>();
</script>

<template>
    <section
        class="my-3 overflow-hidden rounded-xl border border-l-[3px] border-surface-200 border-l-amber-500 bg-white font-sans shadow-[0_4px_14px_rgba(15,23,42,0.06)]"
        style="font-family: 'Poppins', sans-serif !important"
        aria-label="Tasks list"
    >
        <!-- 📋 Header -->
        <div class="flex items-center justify-between gap-3 border-b border-surface-200 bg-[#f8f6f0] px-3.5 py-2.5">
            <div class="flex items-center gap-2.5">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-[#1c3633] text-amber-300 shadow-xs">
                    <CheckSquare class="h-3.5 w-3.5" />
                </span>
                <div class="flex flex-col justify-center">
                    <p class="!m-0 !p-0 !text-xs font-semibold tracking-wide text-surface-900 !leading-tight">
                        {{ action.result.created ? 'Naya Task Created' : 'Showroom Tasks' }}
                    </p>
                    <p class="!m-0 !p-0 !text-[10px] font-normal text-surface-500 !leading-tight">
                        {{ action.result.created ? 'Task board par add ho gaya' : (action.result.count ?? 0) + ' active tasks found' }}
                    </p>
                </div>
            </div>
            <a
                href="/tasks"
                class="inline-flex items-center gap-1 rounded-md border border-amber-300 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-800 transition-colors hover:bg-amber-100 uppercase"
            >
                <ExternalLink class="h-2.5 w-2.5" />
                Open Board
            </a>
        </div>

        <!-- 🌟 Single Created Task View -->
        <div v-if="action.result.created && action.result.task" class="p-3.5">
            <div class="flex items-start justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="rounded-md bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 uppercase">
                        {{ action.result.task.priority || 'MEDIUM' }}
                    </span>
                    <span class="rounded-md bg-surface-100 px-2 py-0.5 text-[10px] font-medium text-surface-700">
                        {{ action.result.task.category }}
                    </span>
                </div>
                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700">
                    <CheckCircle2 class="h-3 w-3" /> Ready
                </span>
            </div>
            <h4 class="mt-2 text-xs font-bold text-surface-900">
                #{{ action.result.task.id }} - {{ action.result.task.title }}
            </h4>
            <div class="mt-2 flex items-center justify-between border-t border-surface-100 pt-2 text-[10px] text-surface-500">
                <span>Due: {{ action.result.task.due_date || 'Today' }}</span>
                <span>Staff: {{ action.result.task.assigned_to_name || 'Unassigned' }}</span>
            </div>
        </div>

        <!-- 📋 List of Tasks View -->
        <div v-else-if="action.result.tasks && action.result.tasks.length > 0" class="divide-y divide-surface-100">
            <div
                v-for="task in action.result.tasks"
                :key="task.id"
                class="flex items-center justify-between gap-3 p-3 transition-colors hover:bg-surface-50/80"
            >
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5 mb-1">
                        <span
                            :class="[
                                'rounded px-1.5 py-0.2 text-[9px] font-bold uppercase',
                                task.priority === 'URGENT'
                                    ? 'bg-rose-100 text-rose-800'
                                    : task.priority === 'HIGH'
                                      ? 'bg-amber-100 text-amber-800'
                                      : 'bg-surface-100 text-surface-700',
                            ]"
                        >
                            {{ task.priority }}
                        </span>
                        <span class="text-[10px] text-surface-400 font-mono">#{{ task.id }}</span>
                    </div>
                    <p class="truncate text-xs font-semibold text-surface-900">
                        {{ task.title }}
                    </p>
                    <div class="mt-1 flex items-center gap-3 text-[10px] text-surface-500">
                        <span class="inline-flex items-center gap-1">
                            <Clock class="h-2.5 w-2.5" />
                            {{ task.due_date || 'No Date' }}
                        </span>
                        <span>👤 {{ task.assigned_to_name }}</span>
                    </div>
                </div>

                <div class="shrink-0">
                    <span
                        :class="[
                            'rounded-full px-2 py-0.5 text-[10px] font-semibold',
                            task.status === 'COMPLETED'
                                ? 'bg-emerald-100 text-emerald-800'
                                : task.status === 'IN_PROGRESS'
                                  ? 'bg-blue-100 text-blue-800'
                                  : 'bg-amber-100 text-amber-800',
                        ]"
                    >
                        {{ task.status === 'IN_PROGRESS' ? 'Working' : task.status }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="py-6 text-center text-surface-500">
            <CheckSquare class="mx-auto h-6 w-6 text-surface-400 mb-1" />
            <p class="text-xs font-medium">Koi pending task nahi mila.</p>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-between border-t border-surface-200 bg-surface-50/60 px-3.5 py-2 text-xs">
            <span class="text-[11px] text-surface-500">Showroom Operations Board</span>
            <a href="/tasks" class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#1c3633] hover:underline">
                View Kanban Board →
            </a>
        </div>
    </section>
</template>
