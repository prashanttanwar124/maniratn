<script setup lang="ts">
import { CreditCard, DollarSign, Layers, Receipt, Sparkles, TrendingUp } from 'lucide-vue-next';

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
                    <TrendingUp class="h-3.5 w-3.5" />
                </span>
                <div class="flex flex-col justify-center">
                    <p class="!m-0 !p-0 !text-xs font-semibold tracking-wide text-surface-900 !leading-tight">
                        Showroom Sales & Counter Report
                    </p>
                    <p class="!m-0 !p-0 !text-[10px] font-normal text-surface-500 !leading-tight">
                        {{ action.result.period_label }}
                    </p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1 border border-amber-300 bg-amber-50 px-2 py-0.5 text-[9.5px] font-semibold tracking-wide text-amber-900 uppercase">
                {{ action.result.total_bills }} Bills
            </span>
        </div>

        <!-- 4-Column Metrics Grid -->
        <div class="grid grid-cols-2 divide-x divide-y divide-surface-200 bg-white text-left text-xs min-[440px]:grid-cols-4 min-[440px]:divide-y-0">
            <div class="p-2.5 bg-amber-50/40">
                <span class="block text-[10px] font-medium text-amber-800">Total Sales</span>
                <span class="font-mono text-sm font-bold text-[#9b6f1e]">{{ action.result.total_sales }}</span>
            </div>
            <div class="p-2.5">
                <span class="block text-[10px] font-medium text-surface-400">Total Collected</span>
                <span class="font-mono font-semibold text-emerald-700">{{ action.result.total_collected }}</span>
            </div>
            <div class="p-2.5">
                <span class="block text-[10px] font-medium text-surface-400">Gold Sold</span>
                <span class="font-mono font-semibold text-surface-800">{{ action.result.gold_weight_sold }}</span>
            </div>
            <div class="p-2.5">
                <span class="block text-[10px] font-medium text-surface-400">Silver Sold</span>
                <span class="font-mono font-semibold text-surface-800">{{ action.result.silver_weight_sold }}</span>
            </div>
        </div>

        <!-- Payment Breakdown -->
        <div class="border-t border-surface-100 bg-surface-50 p-2.5 text-xs">
            <div class="grid grid-cols-4 gap-1.5 text-center text-[10.5px]">
                <div class="bg-white p-1.5 border border-surface-200">
                    <span class="block text-[9px] text-surface-400 font-medium">💵 Cash</span>
                    <span class="font-mono font-semibold text-surface-800">{{ action.result.cash_collected }}</span>
                </div>
                <div class="bg-white p-1.5 border border-surface-200">
                    <span class="block text-[9px] text-surface-400 font-medium">📱 UPI</span>
                    <span class="font-mono font-semibold text-surface-800">{{ action.result.upi_collected }}</span>
                </div>
                <div class="bg-white p-1.5 border border-surface-200">
                    <span class="block text-[9px] text-surface-400 font-medium">💳 Card</span>
                    <span class="font-mono font-semibold text-surface-800">{{ action.result.card_collected }}</span>
                </div>
                <div class="bg-white p-1.5 border border-surface-200">
                    <span class="block text-[9px] text-surface-400 font-medium">🏦 Bank</span>
                    <span class="font-mono font-semibold text-surface-800">{{ action.result.bank_collected }}</span>
                </div>
            </div>
        </div>
    </div>
    <div v-else class="my-2 border border-surface-200 bg-surface-50 p-3 text-xs text-surface-600">
        {{ action.result?.message || 'No sales records found for this period.' }}
    </div>
</template>
