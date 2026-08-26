<script setup lang="ts">
import { ArrowLeftRight, Coins, ExternalLink } from 'lucide-vue-next';

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
                    <Coins class="h-3.5 w-3.5" />
                </span>
                <div class="flex flex-col justify-center min-w-0">
                    <p class="!m-0 !p-0 !text-xs font-semibold tracking-wide text-surface-900 !leading-tight truncate">
                        {{ action.result.item_name || 'Old Gold Valuation / Exchange' }}
                    </p>
                    <p class="!m-0 !p-0 !text-[10.5px] font-normal text-surface-500 !leading-tight mt-0.5">
                        {{ action.result.purity }} · {{ action.result.weight }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span class="ai-status-pill border border-amber-300 bg-amber-50 text-amber-900">
                    Old Gold Buyback
                </span>
                <a
                    href="/invoices/create"
                    class="ai-action-link"
                    title="Start Billing / Exchange"
                >
                    <ExternalLink class="h-3 w-3 text-[#b07b24]" />
                    Bill
                </a>
            </div>
        </div>

        <!-- 📊 2. 4-Column Metrics Grid -->
        <div class="grid grid-cols-2 divide-x divide-y divide-surface-200 bg-white text-left text-xs min-[460px]:grid-cols-4 min-[460px]:divide-y-0">
            <div class="p-2.5">
                <span class="block text-[9.5px] font-semibold uppercase tracking-wider text-surface-400">Rate / g</span>
                <span class="mt-0.5 block font-mono text-xs font-semibold text-surface-800">{{ action.result.rate_per_gm }}</span>
            </div>
            <div class="p-2.5">
                <span class="block text-[9.5px] font-semibold uppercase tracking-wider text-surface-400">Fine 24K Gold</span>
                <span class="mt-0.5 block font-mono text-xs font-semibold text-surface-800">{{ action.result.fine_gold_weight }}</span>
            </div>
            <div class="p-2.5">
                <span class="block text-[9.5px] font-semibold uppercase tracking-wider text-surface-400">Making / GST</span>
                <span class="mt-0.5 block font-mono text-xs font-semibold text-emerald-700">₹0 (None)</span>
            </div>
            <div class="bg-amber-50/40 p-2.5">
                <span class="block text-[9.5px] font-semibold uppercase tracking-wider text-amber-800">Net Valuation</span>
                <span class="mt-0.5 block font-mono text-sm font-bold text-[#9b6f1e]">{{ action.result.total_estimate }}</span>
            </div>
        </div>

        <!-- 🏷️ 3. Footnote -->
        <div class="flex items-center justify-between border-t border-surface-100 bg-surface-50/70 px-3 py-1.5 text-[10.5px] text-surface-500">
            <span class="flex items-center gap-1">
                <ArrowLeftRight class="h-3 w-3 text-[#c08f34]" />
                Customer Buyback / Exchange Value
            </span>
            <span class="font-medium text-surface-600">Base 24K Buy: {{ action.result.base_24k_rate }}</span>
        </div>
    </div>
    <div v-else class="my-2 rounded-lg border border-surface-200 bg-surface-50 p-3 text-xs text-surface-600">
        {{ action.result?.message || 'Old Gold valuation failed.' }}
    </div>
</template>
