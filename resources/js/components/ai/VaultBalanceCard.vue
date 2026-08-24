<script setup lang="ts">
import { Banknote, Coins, Gem, Landmark, ShieldCheck } from 'lucide-vue-next';

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
        class="my-2 overflow-hidden rounded-none border border-t-2 border-surface-200 border-t-[#c08f34] bg-white font-sans shadow-xs"
        style="font-family: 'Poppins', sans-serif !important"
        aria-label="Vault balance"
    >
        <!-- 🏛️ 1. Sleek Compact Header -->
        <div class="flex flex-col gap-2 border-b border-surface-200 bg-surface-50/90 px-3 py-2.5 min-[410px]:flex-row min-[410px]:items-center min-[410px]:justify-between">
            <div class="flex items-center gap-2">
                <Landmark class="h-4 w-4 text-[#c08f34]" />
                <div>
                    <h4 class="text-xs leading-none font-semibold tracking-wide text-surface-900">Showroom vault balance</h4>
                    <span class="text-[10px] font-normal text-surface-500">Cash aur bullion holdings</span>
                </div>
            </div>
            <span class="inline-flex items-center gap-1 rounded-none border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-medium text-emerald-800 uppercase">
                <ShieldCheck class="h-3 w-3 text-emerald-600" />
                Live balance
            </span>
        </div>

        <!-- 💰 2. 3-Box Compact Metric Grid -->
        <div class="grid grid-cols-1 divide-y divide-surface-200 bg-white min-[390px]:grid-cols-3 min-[390px]:divide-x min-[390px]:divide-y-0">
            <!-- Cash in Hand -->
            <div class="rounded-none bg-surface-50 p-3 text-left min-[390px]:text-center">
                <div class="flex items-center gap-1 text-[10px] font-semibold tracking-wide text-emerald-800 uppercase min-[390px]:justify-center">
                    <Banknote class="h-3 w-3 text-emerald-600" /> Cash
                </div>
                <p class="mt-1 font-mono text-sm font-bold text-emerald-900">
                    {{ action.result.cash_in_hand ?? '₹0.00' }}
                </p>
                <span class="text-[9px] font-normal text-surface-400">Counter Cash</span>
            </div>

            <!-- Gold Safe -->
            <div class="rounded-none bg-surface-50 p-3 text-left min-[390px]:text-center">
                <div class="flex items-center gap-1 text-[10px] font-semibold tracking-wide text-amber-800 uppercase min-[390px]:justify-center">
                    <Coins class="h-3 w-3 text-[#c08f34]" /> Gold Safe
                </div>
                <p class="mt-1 font-mono text-sm font-bold text-[#b07b24]">
                    {{ action.result.gold_in_vault ?? '0 g' }}
                </p>
                <span class="text-[9px] font-normal text-surface-400">Safe Bullion</span>
            </div>

            <!-- Silver Safe -->
            <div class="rounded-none bg-surface-50 p-3 text-left min-[390px]:text-center">
                <div class="flex items-center gap-1 text-[10px] font-semibold tracking-wide text-slate-700 uppercase min-[390px]:justify-center">
                    <Gem class="h-3 w-3 text-slate-500" /> Silver Safe
                </div>
                <p class="mt-1 font-mono text-sm font-bold text-surface-900">
                    {{ action.result.silver_in_vault ?? '0 g' }}
                </p>
                <span class="text-[9px] font-normal text-surface-400">Safe Bullion</span>
            </div>
        </div>
    </section>
</template>
