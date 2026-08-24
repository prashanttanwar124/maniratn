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
    <div class="border border-surface-200 bg-white shadow-xs rounded-none overflow-hidden my-2 font-sans border-t-2 border-t-[#c08f34]" style="font-family: 'Poppins', sans-serif !important;">
        <!-- 🏛️ 1. Sleek Compact Header -->
        <div class="px-3 py-2 bg-surface-50/90 border-b border-surface-200 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <Landmark class="w-4 h-4 text-[#c08f34]" />
                <div>
                    <h4 class="font-semibold text-xs text-surface-900 tracking-wide leading-none">Showroom Vault Holdings</h4>
                    <span class="text-[10px] text-surface-500 font-normal">Safe & Liquidity Balance</span>
                </div>
            </div>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-emerald-50 border border-emerald-200 text-emerald-800 text-[10px] font-medium uppercase rounded-none">
                <ShieldCheck class="w-3 h-3 text-emerald-600" />
                Audited Live
            </span>
        </div>

        <!-- 💰 2. 3-Box Compact Metric Grid -->
        <div class="p-2.5 bg-white grid grid-cols-3 gap-2 text-center">
            <!-- Cash in Hand -->
            <div class="p-2 bg-surface-50 border border-surface-200 rounded-none">
                <div class="flex items-center justify-center gap-1 text-[10px] font-semibold text-emerald-800 uppercase tracking-wide">
                    <Banknote class="w-3 h-3 text-emerald-600" /> Cash
                </div>
                <p class="text-xs font-bold text-emerald-900 mt-0.5 font-mono">
                    {{ action.result.cash_in_hand ?? '₹0.00' }}
                </p>
                <span class="text-[9px] text-surface-400 font-normal">Counter Cash</span>
            </div>

            <!-- Gold Safe -->
            <div class="p-2 bg-surface-50 border border-surface-200 rounded-none">
                <div class="flex items-center justify-center gap-1 text-[10px] font-semibold text-amber-800 uppercase tracking-wide">
                    <Coins class="w-3 h-3 text-[#c08f34]" /> Gold Safe
                </div>
                <p class="text-xs font-bold text-[#c08f34] mt-0.5 font-mono">
                    {{ action.result.gold_in_vault ?? '0 g' }}
                </p>
                <span class="text-[9px] text-surface-400 font-normal">Safe Bullion</span>
            </div>

            <!-- Silver Safe -->
            <div class="p-2 bg-surface-50 border border-surface-200 rounded-none">
                <div class="flex items-center justify-center gap-1 text-[10px] font-semibold text-slate-700 uppercase tracking-wide">
                    <Gem class="w-3 h-3 text-slate-500" /> Silver Safe
                </div>
                <p class="text-xs font-bold text-surface-900 mt-0.5 font-mono">
                    {{ action.result.silver_in_vault ?? '0 g' }}
                </p>
                <span class="text-[9px] text-surface-400 font-normal">Safe Bullion</span>
            </div>
        </div>
    </div>
</template>
