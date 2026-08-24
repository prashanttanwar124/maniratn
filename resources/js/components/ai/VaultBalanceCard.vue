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
    <div class="border border-surface-200 bg-white shadow-xs rounded-none overflow-hidden my-2 font-sans" style="font-family: 'Poppins', sans-serif !important;">
        <!-- 🏛️ 1. Header Banner (Perfect Baseline Alignment) -->
        <div class="px-3.5 py-2.5 bg-[#1c3633] text-white flex items-center justify-between border-b border-[#c08f34]/40 rounded-none">
            <div class="space-y-0.5">
                <div class="flex items-center gap-1.5">
                    <Landmark class="w-4 h-4 text-[#c08f34]" />
                    <h4 class="font-bold text-xs !text-white tracking-wide uppercase leading-none">Showroom Vault Holdings</h4>
                </div>
                <p class="text-[10px] !text-[#c08f34] font-medium pl-5.5">Safe & Liquidity Balance</p>
            </div>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-emerald-500/20 border border-emerald-400/30 text-emerald-300 text-[10px] font-bold uppercase rounded-none">
                <ShieldCheck class="w-3 h-3" />
                Audited Live
            </span>
        </div>

        <!-- 💰 2. 3-Box High Contrast Metric Grid -->
        <div class="p-3 bg-surface-50 grid grid-cols-3 gap-2 text-center">
            <!-- Cash in Hand -->
            <div class="p-2.5 bg-white border border-surface-200 rounded-none shadow-2xs">
                <div class="flex items-center justify-center gap-1 text-[10px] font-bold text-emerald-800 uppercase tracking-wide">
                    <Banknote class="w-3 h-3 text-emerald-600" /> Cash
                </div>
                <p class="text-xs font-bold text-emerald-900 mt-1 font-mono">
                    {{ action.result.cash_in_hand ?? '₹0.00' }}
                </p>
                <span class="text-[9px] text-surface-500 font-medium">Counter Liquidity</span>
            </div>

            <!-- Gold Safe -->
            <div class="p-2.5 bg-white border border-surface-200 rounded-none shadow-2xs">
                <div class="flex items-center justify-center gap-1 text-[10px] font-bold text-amber-800 uppercase tracking-wide">
                    <Coins class="w-3 h-3 text-[#c08f34]" /> Gold Safe
                </div>
                <p class="text-xs font-bold text-[#c08f34] mt-1 font-mono">
                    {{ action.result.gold_in_vault ?? '0 g' }}
                </p>
                <span class="text-[9px] text-surface-500 font-medium">Safe Bullion</span>
            </div>

            <!-- Silver Safe -->
            <div class="p-2.5 bg-white border border-surface-200 rounded-none shadow-2xs">
                <div class="flex items-center justify-center gap-1 text-[10px] font-bold text-surface-700 uppercase tracking-wide">
                    <Gem class="w-3 h-3 text-surface-500" /> Silver Safe
                </div>
                <p class="text-xs font-bold text-surface-900 mt-1 font-mono">
                    {{ action.result.silver_in_vault ?? '0 g' }}
                </p>
                <span class="text-[9px] text-surface-500 font-medium">Safe Bullion</span>
            </div>
        </div>
    </div>
</template>
