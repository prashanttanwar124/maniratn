<script setup lang="ts">
import { ref } from 'vue';
import {
    Boxes,
    Check,
    Coins,
    Copy,
    Gem,
    Search,
} from 'lucide-vue-next';

interface ActionItem {
    tool: string;
    args: Record<string, any>;
    result: Record<string, any>;
}

const props = defineProps<{
    action: ActionItem;
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
    <div class="border border-surface-200 bg-white shadow-xs rounded-none overflow-hidden my-2 font-sans" style="font-family: 'Poppins', sans-serif !important;">
        <!-- 🏛️ 1. Header (Clean Solid Dark Green with Crisp White Text) -->
        <div class="px-3.5 py-2.5 bg-[#1c3633] text-white flex items-center justify-between border-b border-[#c08f34]/40 rounded-none">
            <div class="flex items-center gap-2">
                <div class="w-6 h-6 bg-white/10 text-[#c08f34] flex items-center justify-center rounded-none">
                    <Boxes class="w-3.5 h-3.5" />
                </div>
                <div>
                    <h4 class="font-bold text-xs !text-white tracking-wide uppercase">Showroom Stock Inventory</h4>
                    <p class="text-[10px] !text-[#c08f34] font-medium">Live Showcase Balance</p>
                </div>
            </div>
            <span class="px-2 py-0.5 bg-emerald-500/20 border border-emerald-400/30 text-emerald-300 text-[10px] font-mono font-bold uppercase rounded-none">
                {{ action.result.total_items ?? 0 }} Items
            </span>
        </div>

        <!-- 📊 2. Clean Stats Bar (Gold & Silver Breakdown) -->
        <div class="grid grid-cols-2 bg-surface-50 border-b border-surface-200 text-xs">
            <!-- Gold Stock -->
            <div class="p-2.5 border-r border-surface-200 flex items-center gap-2">
                <div class="w-7 h-7 bg-amber-100 text-amber-800 flex items-center justify-center shrink-0">
                    <Coins class="w-3.5 h-3.5 text-[#c08f34]" />
                </div>
                <div>
                    <div class="text-[10px] uppercase font-bold text-surface-500 tracking-wider">Gold Stock</div>
                    <div class="font-bold text-surface-900 text-xs font-mono">
                        {{ action.result.gold_count ?? 0 }} pcs <span class="text-surface-500 font-normal">({{ action.result.gold_weight ?? '0 g' }})</span>
                    </div>
                </div>
            </div>

            <!-- Silver Stock -->
            <div class="p-2.5 flex items-center gap-2">
                <div class="w-7 h-7 bg-slate-100 text-slate-700 flex items-center justify-center shrink-0">
                    <Gem class="w-3.5 h-3.5 text-slate-600" />
                </div>
                <div>
                    <div class="text-[10px] uppercase font-bold text-surface-500 tracking-wider">Silver Stock</div>
                    <div class="font-bold text-surface-900 text-xs font-mono">
                        {{ action.result.silver_count ?? 0 }} pcs <span class="text-surface-500 font-normal">({{ action.result.silver_weight ?? '0 g' }})</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 📦 3. Matching Inventory Items List (Clean ERP Table Style) -->
        <div v-if="action.result.items && action.result.items.length > 0" class="divide-y divide-surface-100 max-h-64 overflow-y-auto">
            <div
                v-for="(item, i) in action.result.items"
                :key="i"
                class="p-2.5 hover:bg-surface-50 flex items-center justify-between gap-3 text-xs transition-colors"
            >
                <!-- Left: Name, Purity & Category -->
                <div class="min-w-0 flex-1 space-y-0.5">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="font-semibold text-surface-900 text-xs">{{ item.name }}</span>
                        <span class="px-1.5 py-0.2 text-[9.5px] font-bold bg-surface-100 border border-surface-300 text-surface-800 uppercase font-mono rounded-none">
                            {{ item.purity || '22K' }}
                        </span>
                    </div>
                    <div class="text-[10.5px] text-surface-500 flex items-center gap-2">
                        <span>{{ item.category || 'Jewellery' }}</span>
                        <span class="text-surface-300">•</span>
                        <span>Making: <strong class="text-surface-700">{{ item.making }}</strong></span>
                    </div>
                </div>

                <!-- Right: Weight & Barcode Copy Tag -->
                <div class="text-right shrink-0 flex flex-col items-end gap-1">
                    <span class="font-mono font-bold text-xs text-surface-900">{{ item.weight }}</span>
                    <button
                        type="button"
                        @click="copyBarcode(item.barcode)"
                        :title="copiedBarcode === item.barcode ? 'Barcode Copied!' : 'Click to copy barcode'"
                        class="inline-flex items-center gap-1 px-2 py-0.5 bg-surface-50 hover:bg-[#1c3633] hover:text-white text-surface-700 text-[10px] font-mono font-semibold border border-surface-300 transition-colors rounded-none cursor-pointer"
                    >
                        <Check v-if="copiedBarcode === item.barcode" class="w-2.5 h-2.5 text-emerald-500" />
                        <Copy v-else class="w-2.5 h-2.5 text-surface-400" />
                        <span>{{ item.barcode }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 🚫 Empty State -->
        <div v-else class="p-4 text-center text-xs text-surface-500 italic bg-surface-50 space-y-1">
            <Search class="w-4 h-4 text-surface-400 mx-auto" />
            <p>Showcase me is query ka koi item nahi mila.</p>
        </div>
    </div>
</template>
