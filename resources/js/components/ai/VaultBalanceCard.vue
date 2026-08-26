<script setup lang="ts">
import { Banknote, Coins, ExternalLink, Gem, Landmark, ShieldCheck } from 'lucide-vue-next';

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
        class="erp-ai-card my-3 overflow-hidden border border-l-[3px] border-surface-300 border-l-[#c08f34] bg-white font-sans shadow-[0_4px_14px_rgba(15,23,42,0.06)]"
        style="font-family: 'Poppins', sans-serif !important"
        aria-label="Vault balance"
    >
        <!-- 🏛️ 1. Sleek Compact Header -->
        <div class="flex items-center justify-between gap-3 border-b border-surface-200 bg-[#f8f6f0] px-3.5 py-2.5">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center bg-[#1c3633] text-[#e5c278]">
                    <Landmark class="h-3.5 w-3.5" />
                </span>
                <div class="flex flex-col justify-center min-w-0">
                    <p class="!m-0 !p-0 !text-xs font-semibold tracking-wide text-surface-900 !leading-tight truncate">
                        Showroom Vault Balance
                    </p>
                    <p class="!m-0 !p-0 !text-[10.5px] font-normal text-surface-500 !leading-tight mt-0.5">Cash & bullion holdings</p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span class="ai-status-pill border border-emerald-300 bg-emerald-50 text-emerald-800">
                    <ShieldCheck class="h-3 w-3 text-emerald-600" />
                    Live
                </span>
                <a
                    href="/reports"
                    class="ai-action-link"
                    title="Open Reports"
                >
                    <ExternalLink class="h-3 w-3 text-[#b07b24]" />
                    Reports
                </a>
            </div>
        </div>

        <!-- 💰 2. 3-Box Compact Metric Grid -->
        <div class="grid grid-cols-1 divide-y divide-surface-200 bg-white min-[390px]:grid-cols-3 min-[390px]:divide-x min-[390px]:divide-y-0">
            <!-- Cash in Hand -->
            <div class="bg-surface-50 p-3 text-left min-[390px]:text-center">
                <div class="flex items-center gap-1 text-[9.5px] font-semibold tracking-wider text-emerald-800 uppercase min-[390px]:justify-center">
                    <Banknote class="h-3 w-3 text-emerald-600" /> Cash
                </div>
                <p class="mt-1 font-mono text-sm font-bold text-emerald-900">
                    {{ action.result.cash_in_hand ?? '₹0.00' }}
                </p>
                <span class="text-[9.5px] font-normal text-surface-400">Counter Cash</span>
            </div>

            <!-- Gold Safe -->
            <div class="bg-surface-50 p-3 text-left min-[390px]:text-center">
                <div class="flex items-center gap-1 text-[9.5px] font-semibold tracking-wider text-amber-800 uppercase min-[390px]:justify-center">
                    <Coins class="h-3 w-3 text-[#c08f34]" /> Gold Safe
                </div>
                <p class="mt-1 font-mono text-sm font-bold text-[#b07b24]">
                    {{ action.result.gold_in_vault ?? '0 g' }}
                </p>
                <span class="text-[9.5px] font-normal text-surface-400">Safe Bullion</span>
            </div>

            <!-- Silver Safe -->
            <div class="bg-surface-50 p-3 text-left min-[390px]:text-center">
                <div class="flex items-center gap-1 text-[9.5px] font-semibold tracking-wider text-slate-700 uppercase min-[390px]:justify-center">
                    <Gem class="h-3 w-3 text-slate-500" /> Silver Safe
                </div>
                <p class="mt-1 font-mono text-sm font-bold text-surface-900">
                    {{ action.result.silver_in_vault ?? '0 g' }}
                </p>
                <span class="text-[9.5px] font-normal text-surface-400">Safe Bullion</span>
            </div>
        </div>
    </section>
</template>
