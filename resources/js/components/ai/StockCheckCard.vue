<script setup lang="ts">
import { ref } from 'vue';
import {
    Boxes,
    Copy,
    Check,
    Coins,
    Gem,
    Receipt,
    Search,
    Tag,
} from 'lucide-vue-next';

interface ActionItem {
    tool: string;
    args: Record<string, any>;
    result: Record<string, any>;
}

const props = defineProps<{
    action: ActionItem;
}>();

const emit = defineEmits<{
    (e: 'select-barcode', barcode: string): void;
}>();

const copiedBarcode = ref<string | null>(null);

const copyBarcode = (barcode: string) => {
    if (!barcode) return;
    navigator.clipboard.writeText(barcode);
    copiedBarcode.value = barcode;
    setTimeout(() => {
        copiedBarcode.value = null;
    }, 2000);
};
</script>

<template>
    <div class="border border-slate-300 bg-white shadow-xs rounded-none overflow-hidden my-2 font-sans" style="font-family: 'Poppins', sans-serif !important;">
        <!-- 🏛️ 1. Top Header Banner -->
        <div class="px-3.5 py-2.5 bg-gradient-to-r from-[#142926] via-[#1c3633] to-[#142926] text-white flex items-center justify-between border-b-2 border-b-[#c08f34] rounded-none">
            <div class="flex items-center gap-2">
                <div class="w-6 h-6 bg-[#c08f34]/20 border border-[#c08f34]/40 text-[#c08f34] flex items-center justify-center rounded-none">
                    <Boxes class="w-3.5 h-3.5" />
                </div>
                <div>
                    <h4 class="font-bold text-xs text-white uppercase tracking-wider">Live Inventory Stock</h4>
                    <p class="text-[10px] text-[#c08f34] font-medium">Real-time ERP Showcase Balance</p>
                </div>
            </div>
            <span class="px-2 py-0.5 bg-emerald-500/20 border border-emerald-400/30 text-emerald-300 text-[10px] font-mono font-bold uppercase rounded-none">
                {{ action.result.total_items ?? 0 }} Items
            </span>
        </div>

        <!-- 📊 2. Compact Stats Bar (Gold vs Silver split) -->
        <div class="grid grid-cols-3 gap-px bg-slate-200 border-b border-slate-200 text-center">
            <!-- Total Stock -->
            <div class="bg-white p-2 text-center">
                <span class="text-[9.5px] font-bold text-slate-500 uppercase tracking-wide block">Total Stock</span>
                <span class="text-xs font-bold text-slate-900 font-mono block mt-0.5">
                    {{ action.result.total_items ?? 0 }} pcs
                </span>
                <span class="text-[10px] text-slate-500 font-mono">{{ action.result.total_weight ?? '0 g' }}</span>
            </div>

            <!-- Gold Stock -->
            <div class="bg-[#fcfaf6] p-2 text-center border-x border-slate-200">
                <span class="text-[9.5px] font-bold text-amber-800 uppercase tracking-wide block flex items-center justify-center gap-1">
                    <Coins class="w-2.5 h-2.5 text-[#c08f34]" /> Gold
                </span>
                <span class="text-xs font-bold text-[#c08f34] font-mono block mt-0.5">
                    {{ action.result.gold_count ?? 0 }} pcs
                </span>
                <span class="text-[10px] text-amber-700 font-mono">{{ action.result.gold_weight ?? '0 g' }}</span>
            </div>

            <!-- Silver Stock -->
            <div class="bg-slate-50 p-2 text-center">
                <span class="text-[9.5px] font-bold text-slate-700 uppercase tracking-wide block flex items-center justify-center gap-1">
                    <Gem class="w-2.5 h-2.5 text-slate-500" /> Silver
                </span>
                <span class="text-xs font-bold text-slate-700 font-mono block mt-0.5">
                    {{ action.result.silver_count ?? 0 }} pcs
                </span>
                <span class="text-[10px] text-slate-500 font-mono">{{ action.result.silver_weight ?? '0 g' }}</span>
            </div>
        </div>

        <!-- 📦 3. Matching Inventory Items List -->
        <div v-if="action.result.items && action.result.items.length > 0" class="divide-y divide-slate-100 max-h-60 overflow-y-auto">
            <div
                v-for="(item, i) in action.result.items"
                :key="i"
                class="p-2.5 hover:bg-slate-50 flex items-center justify-between gap-3 text-xs transition-colors"
            >
                <!-- Left: Name & Category Specs -->
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="font-bold text-slate-900 text-xs truncate">{{ item.name }}</span>
                        <span
                            :class="[
                                'px-1.5 py-0.2 text-[9.5px] font-bold uppercase rounded-none font-mono',
                                item.metal === 'GOLD' || item.purity?.includes('22K') || item.purity?.includes('18K')
                                    ? 'bg-amber-100 text-amber-900 border border-amber-300'
                                    : 'bg-slate-100 text-slate-700 border border-slate-300'
                            ]"
                        >
                            {{ item.purity || '22K' }}
                        </span>
                    </div>
                    <div class="text-[10.5px] text-slate-500 flex items-center gap-2 mt-0.5">
                        <span>{{ item.category || 'Jewellery' }}</span>
                        <span class="text-slate-300">•</span>
                        <span>Making: <strong>{{ item.making }}</strong></span>
                    </div>
                </div>

                <!-- Right: Weight, Barcode & Action -->
                <div class="text-right shrink-0 flex flex-col items-end gap-1">
                    <span class="font-mono font-bold text-xs text-[#1c3633]">{{ item.weight }}</span>
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            @click="copyBarcode(item.barcode)"
                            :title="copiedBarcode === item.barcode ? 'Barcode Copied!' : 'Click to copy barcode'"
                            class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-slate-100 hover:bg-[#1c3633] hover:text-[#c08f34] text-slate-700 text-[10px] font-mono font-bold border border-slate-300 transition-all rounded-none cursor-pointer"
                        >
                            <Check v-if="copiedBarcode === item.barcode" class="w-2.5 h-2.5 text-emerald-600" />
                            <Copy v-else class="w-2.5 h-2.5 text-slate-400" />
                            <span>{{ item.barcode }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 🚫 Empty State -->
        <div v-else class="p-4 text-center text-xs text-slate-500 italic bg-slate-50 space-y-1">
            <Search class="w-4 h-4 text-slate-400 mx-auto" />
            <p>Showcase me is naam ka koi active item nahi mila.</p>
        </div>
    </div>
</template>
