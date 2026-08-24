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
    <div class="border border-slate-300 bg-white shadow-xs rounded-none overflow-hidden my-2 font-sans" style="font-family: 'Poppins', sans-serif !important;">
        <!-- 🏛️ 1. Header Banner -->
        <div class="px-3.5 py-2.5 bg-gradient-to-r from-[#142926] via-[#1c3633] to-[#142926] text-white flex items-center justify-between border-b-2 border-b-[#c08f34] rounded-none">
            <div class="flex items-center gap-2">
                <div class="w-6 h-6 bg-[#c08f34]/20 border border-[#c08f34]/40 text-[#c08f34] flex items-center justify-center rounded-none">
                    <Landmark class="w-3.5 h-3.5" />
                </div>
                <div>
                    <h4 class="font-bold text-xs text-white uppercase tracking-wider">Showroom Vault Holdings</h4>
                    <p class="text-[10px] text-[#c08f34] font-medium">Safe & Liquidity Balance</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-emerald-500/20 border border-emerald-400/30 text-emerald-300 text-[10px] font-bold uppercase rounded-none">
                <ShieldCheck class="w-3 h-3" />
                Audited Live
            </span>
        </div>

        <!-- 💰 2. 3-Box High Contrast Metric Grid -->
        <div class="p-3 bg-[#fcfaf6] grid grid-cols-3 gap-2 text-center">
            <!-- Cash in Hand -->
            <div class="p-2.5 bg-white border border-slate-200 hover:border-emerald-500 transition-colors rounded-none shadow-2xs">
                <div class="flex items-center justify-center gap-1 text-[10px] font-bold text-emerald-800 uppercase tracking-wide">
                    <Banknote class="w-3 h-3 text-emerald-600" /> Cash
                </div>
                <p class="text-xs font-bold text-emerald-900 mt-1 font-mono">
                    {{ action.result.cash_in_hand ?? '₹0.00' }}
                </p>
                <span class="text-[9px] text-slate-400 font-medium">Counter Liquidity</span>
            </div>

            <!-- Gold Safe -->
            <div class="p-2.5 bg-white border border-slate-200 hover:border-[#c08f34] transition-colors rounded-none shadow-2xs">
                <div class="flex items-center justify-center gap-1 text-[10px] font-bold text-amber-800 uppercase tracking-wide">
                    <Coins class="w-3 h-3 text-[#c08f34]" /> Gold Safe
                </div>
                <p class="text-xs font-bold text-[#c08f34] mt-1 font-mono">
                    {{ action.result.gold_in_vault ?? '0 g' }}
                </p>
                <span class="text-[9px] text-slate-400 font-medium">Safe Bullion</span>
            </div>

            <!-- Silver Safe -->
            <div class="p-2.5 bg-white border border-slate-200 hover:border-slate-500 transition-colors rounded-none shadow-2xs">
                <div class="flex items-center justify-center gap-1 text-[10px] font-bold text-slate-700 uppercase tracking-wide">
                    <Gem class="w-3 h-3 text-slate-500" /> Silver Safe
                </div>
                <p class="text-xs font-bold text-slate-800 mt-1 font-mono">
                    {{ action.result.silver_in_vault ?? '0 g' }}
                </p>
                <span class="text-[9px] text-slate-400 font-medium">Safe Bullion</span>
            </div>
        </div>
    </div>
</template>
