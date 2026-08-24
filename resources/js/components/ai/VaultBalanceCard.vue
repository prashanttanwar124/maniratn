<script setup lang="ts">
import { Wallet } from 'lucide-vue-next';

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
    <div class="border border-slate-300 bg-white shadow-xs rounded-none overflow-hidden my-2">
        <div class="px-3 py-2 bg-[#1c3633] text-white flex items-center justify-between border-b-2 border-b-[#c08f34] rounded-none">
            <div class="flex items-center gap-1.5">
                <Wallet class="w-3.5 h-3.5 text-[#c08f34]" />
                <span class="font-serif text-xs font-bold tracking-wide uppercase">Showroom Vault Holdings</span>
            </div>
            <span class="text-[10px] text-[#c08f34] uppercase font-mono">Live Safe Balance</span>
        </div>
        <div class="p-3 bg-slate-50 grid grid-cols-3 gap-2 text-center">
            <div class="p-2.5 bg-white border border-emerald-200 rounded-none shadow-2xs">
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Cash In Hand</p>
                <p class="text-xs font-bold text-emerald-800 mt-1 font-mono">{{ action.result.cash_in_hand }}</p>
            </div>
            <div class="p-2.5 bg-white border border-amber-200 rounded-none shadow-2xs">
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Gold Safe</p>
                <p class="text-xs font-bold text-[#c08f34] mt-1 font-mono">{{ action.result.gold_in_vault }}</p>
            </div>
            <div class="p-2.5 bg-white border border-slate-200 rounded-none shadow-2xs">
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Silver Safe</p>
                <p class="text-xs font-bold text-slate-700 mt-1 font-mono">{{ action.result.silver_in_vault }}</p>
            </div>
        </div>
    </div>
</template>
