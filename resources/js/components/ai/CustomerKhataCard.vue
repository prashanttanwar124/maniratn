<script setup lang="ts">
import { BookOpen, CheckCircle, ExternalLink, Phone, Receipt, User } from 'lucide-vue-next';

defineProps<{
    action: {
        tool: string;
        args: Record<string, any>;
        result: Record<string, any>;
    };
}>();
</script>

<template>
    <div
        v-if="action.result?.found"
        class="my-3 overflow-hidden border border-l-[3px] border-surface-300 border-l-[#c08f34] bg-white font-sans shadow-[0_4px_14px_rgba(15,23,42,0.06)]"
    >
        <!-- Header -->
        <div class="flex items-center justify-between gap-3 border-b border-surface-200 bg-[#f8f6f0] px-3.5 py-2.5">
            <div class="flex items-center gap-2.5">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center bg-[#1c3633] text-[#e5c278]">
                    <BookOpen class="h-3.5 w-3.5" />
                </span>
                <div class="flex flex-col justify-center">
                    <p class="!m-0 !p-0 !text-xs font-semibold tracking-wide text-surface-900 !leading-tight">
                        {{ action.result.customer_name }}
                    </p>
                    <p class="!m-0 !p-0 !text-[10px] font-normal text-surface-500 !leading-tight flex items-center gap-1.5 mt-0.5">
                        <span>📱 {{ action.result.mobile }}</span>
                        <span v-if="action.result.city && action.result.city !== '—'">📍 {{ action.result.city }}</span>
                    </p>
                </div>
            </div>
            <span
                :class="[
                    'inline-flex items-center gap-1 border px-2 py-0.5 text-[9.5px] font-semibold tracking-wide uppercase',
                    action.result.status_type === 'DUE'
                        ? 'border-rose-300 bg-rose-50 text-rose-800'
                        : 'border-emerald-300 bg-emerald-50 text-emerald-800'
                ]"
            >
                {{ action.result.status_type === 'DUE' ? 'Pending Due' : 'Settled' }}
            </span>
        </div>

        <!-- 3-Column Metrics Grid -->
        <div class="grid grid-cols-3 divide-x divide-surface-200 bg-white text-left text-xs">
            <div class="p-2.5">
                <span class="block text-[10px] font-medium text-surface-400">Total Purchase</span>
                <span class="font-mono font-semibold text-surface-800">{{ action.result.total_purchases }}</span>
            </div>
            <div class="p-2.5">
                <span class="block text-[10px] font-medium text-surface-400">Total Paid</span>
                <span class="font-mono font-semibold text-emerald-700">{{ action.result.total_paid }}</span>
            </div>
            <div :class="['p-2.5', action.result.status_type === 'DUE' ? 'bg-rose-50/50' : 'bg-emerald-50/40']">
                <span class="block text-[10px] font-medium text-surface-500">Net Balance</span>
                <span :class="['font-mono text-xs font-bold', action.result.status_type === 'DUE' ? 'text-rose-700' : 'text-emerald-800']">
                    {{ action.result.pending_due }}
                </span>
            </div>
        </div>

        <!-- Recent Bills Timeline (if any) -->
        <div v-if="action.result.recent_bills?.length" class="border-t border-surface-100 bg-surface-50/70 p-2.5">
            <p class="text-[9.5px] font-bold text-surface-500 uppercase tracking-wider mb-1.5">Recent Purchase Bills</p>
            <div class="space-y-1">
                <div
                    v-for="bill in action.result.recent_bills"
                    :key="bill.id"
                    class="flex items-center justify-between bg-white px-2 py-1.5 border border-surface-200 text-xs text-surface-700"
                >
                    <div class="flex items-center gap-2">
                        <Receipt class="h-3 w-3 text-[#c08f34]" />
                        <span class="font-mono font-medium text-surface-900">{{ bill.invoice_number }}</span>
                        <span class="text-[10px] text-surface-400">({{ bill.date }})</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="font-mono font-bold text-surface-800">{{ bill.total }}</span>
                        <a
                            :href="`/invoices/${bill.id}/print`"
                            target="_blank"
                            class="text-surface-400 hover:text-[#1c3633]"
                            title="View/Print Bill"
                        >
                            <ExternalLink class="h-3 w-3" />
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div v-else class="my-2 border border-surface-200 bg-surface-50 p-3 text-xs text-surface-600">
        {{ action.result?.message || 'Customer record not found.' }}
    </div>
</template>
