<script setup lang="ts">
import { Banknote, CreditCard, ExternalLink, Landmark, QrCode, TrendingUp } from 'lucide-vue-next';

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
        class="erp-ai-card my-3 overflow-hidden border border-l-[3px] border-surface-300 border-l-[#c08f34] bg-white font-sans shadow-[0_4px_14px_rgba(15,23,42,0.06)]"
        style="font-family: 'Poppins', sans-serif !important"
    >
        <!-- 🏛️ 1. Header (Light Luxury ERP Style) -->
        <div class="flex items-center justify-between gap-3 border-b border-surface-200 bg-[#f8f6f0] px-3.5 py-2.5">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-[#1c3633] text-[#e5c278]">
                    <TrendingUp class="h-3.5 w-3.5" />
                </span>
                <div class="flex flex-col justify-center min-w-0">
                    <p class="!m-0 !p-0 !text-xs font-semibold tracking-wide text-surface-900 !leading-tight truncate">
                        Showroom Sales & Counter Report
                    </p>
                    <p class="!m-0 !p-0 !text-[10.5px] font-normal text-surface-500 !leading-tight mt-0.5">
                        {{ action.result.period_label }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span class="ai-status-pill border border-amber-300 bg-amber-50 text-amber-900">
                    {{ action.result.total_bills }} Bills
                </span>
                <a
                    href="/reports"
                    class="ai-action-link"
                    title="Open Full Sales Reports"
                >
                    <ExternalLink class="h-3 w-3 text-[#b07b24]" />
                    Reports
                </a>
            </div>
        </div>

        <!-- 📊 2. 4-Column Metrics Grid -->
        <div class="grid grid-cols-2 divide-x divide-y divide-surface-200 bg-white text-left text-xs min-[440px]:grid-cols-4 min-[440px]:divide-y-0">
            <div class="p-2.5 bg-amber-50/30">
                <span class="block text-[9.5px] font-semibold uppercase tracking-wider text-amber-800">Total Sales</span>
                <span class="mt-0.5 block font-mono text-sm font-bold text-[#9b6f1e]">{{ action.result.total_sales }}</span>
            </div>
            <div class="p-2.5">
                <span class="block text-[9.5px] font-semibold uppercase tracking-wider text-surface-400">Total Collected</span>
                <span class="mt-0.5 block font-mono text-xs font-bold text-emerald-700">{{ action.result.total_collected }}</span>
            </div>
            <div class="p-2.5">
                <span class="block text-[9.5px] font-semibold uppercase tracking-wider text-surface-400">Gold Sold</span>
                <span class="mt-0.5 block font-mono text-xs font-semibold text-surface-800">{{ action.result.gold_weight_sold }}</span>
            </div>
            <div class="p-2.5">
                <span class="block text-[9.5px] font-semibold uppercase tracking-wider text-surface-400">Silver Sold</span>
                <span class="mt-0.5 block font-mono text-xs font-semibold text-surface-800">{{ action.result.silver_weight_sold }}</span>
            </div>
        </div>

        <!-- 💳 3. Payment Breakdown Grid (Clean Lucide Icons) -->
        <div class="border-t border-surface-200 bg-surface-50/70 p-2.5 text-xs">
            <p class="text-[9.5px] font-bold text-surface-500 uppercase tracking-wider mb-1.5">Collection Breakdown</p>
            <div class="grid grid-cols-2 gap-1.5 min-[420px]:grid-cols-4 text-[10.5px]">
                <div class="rounded-md bg-white p-2 border border-surface-200 flex flex-col justify-between">
                    <div class="flex items-center gap-1 text-[9.5px] font-semibold uppercase tracking-wider text-surface-500">
                        <Banknote class="h-3 w-3 text-emerald-600" />
                        <span>Cash</span>
                    </div>
                    <span class="mt-1 font-mono font-bold text-surface-900 text-xs">{{ action.result.cash_collected }}</span>
                </div>
                <div class="rounded-md bg-white p-2 border border-surface-200 flex flex-col justify-between">
                    <div class="flex items-center gap-1 text-[9.5px] font-semibold uppercase tracking-wider text-surface-500">
                        <QrCode class="h-3 w-3 text-sky-600" />
                        <span>UPI</span>
                    </div>
                    <span class="mt-1 font-mono font-bold text-surface-900 text-xs">{{ action.result.upi_collected }}</span>
                </div>
                <div class="rounded-md bg-white p-2 border border-surface-200 flex flex-col justify-between">
                    <div class="flex items-center gap-1 text-[9.5px] font-semibold uppercase tracking-wider text-surface-500">
                        <CreditCard class="h-3 w-3 text-indigo-600" />
                        <span>Card</span>
                    </div>
                    <span class="mt-1 font-mono font-bold text-surface-900 text-xs">{{ action.result.card_collected }}</span>
                </div>
                <div class="rounded-md bg-white p-2 border border-surface-200 flex flex-col justify-between">
                    <div class="flex items-center gap-1 text-[9.5px] font-semibold uppercase tracking-wider text-surface-500">
                        <Landmark class="h-3 w-3 text-amber-600" />
                        <span>Bank</span>
                    </div>
                    <span class="mt-1 font-mono font-bold text-surface-900 text-xs">{{ action.result.bank_collected }}</span>
                </div>
            </div>
        </div>
    </div>
    <div v-else class="my-2 border border-surface-200 bg-surface-50 p-3 text-xs text-surface-600">
        {{ action.result?.message || 'No sales records found for this period.' }}
    </div>
</template>
