<script setup lang="ts">
import { ref } from 'vue';
import {
    Boxes,
    Check,
    Coins,
    Copy,
    Info,
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
    <div class="border border-surface-200 bg-white shadow-xs rounded-none overflow-hidden my-2 font-sans border-t-2 border-t-[#c08f34]" style="font-family: 'Poppins', sans-serif !important;">
        <!-- 🏛️ 1. Sleek Compact Header (Light Luxury ERP Style) -->
        <div class="px-3 py-2 bg-surface-50/90 border-b border-surface-200 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <Boxes class="w-4 h-4 text-[#c08f34]" />
                <div>
                    <h4 class="font-semibold text-xs text-surface-900 tracking-wide leading-none">
                        {{ action.result.query || 'Showroom Inventory' }}
                    </h4>
                    <span class="text-[10px] text-surface-500 font-normal">Live Showcase Stock</span>
                </div>
            </div>
            <span class="px-2 py-0.5 bg-white border border-surface-200 text-surface-700 text-[10.5px] font-mono font-medium rounded-none shadow-2xs">
                {{ action.result.total_items ?? 0 }} Items
            </span>
        </div>

        <!-- 💡 2. Smart Proximity Info (If requested weight is unavailable) -->
        <div
            v-if="action.result.target_weight && action.result.exact_weight_found === false && action.result.total_items > 0"
            class="px-3 py-1.5 bg-amber-50/70 border-b border-amber-200/60 flex items-center gap-1.5 text-[11px] text-amber-900"
        >
            <Info class="w-3.5 h-3.5 text-amber-700 shrink-0" />
            <span>
                <strong>{{ action.result.target_weight }}g</strong> me koi item nahi hai. Sabse kam weight wale items neeche hain:
            </span>
        </div>

        <!-- 📊 3. Elegant Inline Stats Strip -->
        <div class="px-3 py-1.5 bg-white border-b border-surface-100 flex items-center justify-between text-[11px] text-surface-600">
            <!-- Gold Stat -->
            <div class="flex items-center gap-1.5">
                <span class="w-2 h-2 bg-[#c08f34] inline-block"></span>
                <span class="font-medium text-surface-700">Gold:</span>
                <span class="font-semibold text-surface-900 font-mono">{{ action.result.gold_count ?? 0 }} pcs</span>
                <span class="text-surface-400 font-mono text-[10px]">({{ action.result.gold_weight ?? '0 g' }})</span>
            </div>

            <!-- Silver Stat -->
            <div class="flex items-center gap-1.5">
                <span class="w-2 h-2 bg-slate-400 inline-block"></span>
                <span class="font-medium text-surface-700">Silver:</span>
                <span class="font-semibold text-surface-900 font-mono">{{ action.result.silver_count ?? 0 }} pcs</span>
                <span class="text-surface-400 font-mono text-[10px]">({{ action.result.silver_weight ?? '0 g' }})</span>
            </div>
        </div>

        <!-- 📦 4. Matching Inventory Items List (Compact & Clean) -->
        <div v-if="action.result.items && action.result.items.length > 0" class="divide-y divide-surface-100 max-h-60 overflow-y-auto">
            <div
                v-for="(item, i) in action.result.items"
                :key="i"
                class="px-3 py-2 hover:bg-amber-50/30 flex items-center justify-between gap-3 text-xs transition-colors"
            >
                <!-- Left: Name, Purity & Category -->
                <div class="min-w-0 flex-1 space-y-0.5">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="font-medium text-surface-900 text-xs">{{ item.name }}</span>
                        <span class="px-1.5 py-0.2 text-[9px] font-bold bg-amber-50 border border-amber-200 text-amber-900 uppercase font-mono rounded-none">
                            {{ item.purity || '22K' }}
                        </span>
                    </div>
                    <div class="text-[10px] text-surface-400 flex items-center gap-1.5">
                        <span>{{ item.category || 'Jewellery' }}</span>
                        <span>•</span>
                        <span>Making: <strong class="text-surface-600">{{ item.making }}</strong></span>
                    </div>
                </div>

                <!-- Right: Weight & Barcode Copy Tag -->
                <div class="text-right shrink-0 flex flex-col items-end gap-1">
                    <span class="font-mono font-semibold text-xs text-surface-900">{{ item.weight }}</span>
                    <button
                        type="button"
                        @click="copyBarcode(item.barcode)"
                        :title="copiedBarcode === item.barcode ? 'Copied!' : 'Copy Barcode'"
                        class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-surface-50 hover:bg-[#1c3633] hover:text-white text-surface-600 text-[9.5px] font-mono font-medium border border-surface-200 transition-colors rounded-none cursor-pointer"
                    >
                        <Check v-if="copiedBarcode === item.barcode" class="w-2.5 h-2.5 text-emerald-500" />
                        <Copy v-else class="w-2.5 h-2.5 text-surface-400" />
                        <span>{{ item.barcode }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 🚫 Empty State -->
        <div v-else class="p-3.5 text-center text-xs text-surface-500 bg-surface-50/50 space-y-1">
            <Search class="w-4 h-4 text-surface-400 mx-auto opacity-70" />
            <p class="text-[11px]">Showcase me is query ka koi item nahi mila.</p>
        </div>
    </div>
</template>
