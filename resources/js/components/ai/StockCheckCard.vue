<script setup lang="ts">
import { Boxes, Check, Copy, ExternalLink, Info, Search } from 'lucide-vue-next';
import { ref } from 'vue';

interface ActionItem {
    tool: string;
    args: Record<string, any>;
    result: Record<string, any>;
}

defineProps<{
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
    <section
        class="erp-ai-card my-3 overflow-hidden border border-l-[3px] border-surface-300 border-l-[#c08f34] bg-white font-sans shadow-[0_4px_14px_rgba(15,23,42,0.06)]"
        style="font-family: 'Poppins', sans-serif !important"
        aria-label="Stock availability results"
    >
        <!-- 🏛️ 1. Sleek Compact Header (Light Luxury ERP Style) -->
        <div class="flex items-center justify-between gap-3 border-b border-surface-200 bg-[#f8f6f0] px-3.5 py-2.5">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center bg-[#1c3633] text-[#e5c278]">
                    <Boxes class="h-3.5 w-3.5" />
                </span>
                <div class="flex flex-col justify-center min-w-0">
                    <p class="!m-0 !p-0 !text-xs font-semibold tracking-wide text-surface-900 !leading-tight truncate">
                        {{ action.result.query || 'Showroom Inventory' }}
                    </p>
                    <p class="!m-0 !p-0 !text-[10.5px] font-normal text-surface-500 !leading-tight mt-0.5">Live showroom stock</p>
                </div>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
                <span class="border border-surface-300 bg-white px-2 py-0.5 text-[10px] font-mono font-medium text-surface-700">
                    {{ action.result.total_items ?? 0 }} items
                </span>
                <a
                    href="/products"
                    class="inline-flex items-center gap-1 border border-surface-300 bg-white px-2 py-0.5 text-[10px] font-medium text-surface-700 hover:border-[#c08f34] hover:text-[#1c3633] transition-colors"
                    title="Open Inventory Catalog"
                >
                    <ExternalLink class="h-2.5 w-2.5 text-[#b07b24]" />
                    Catalog
                </a>
            </div>
        </div>

        <!-- 💡 2. Smart Proximity Info (If requested weight is unavailable) -->
        <div
            v-if="action.result.target_weight && action.result.exact_weight_found === false && action.result.total_items > 0"
            class="flex items-start gap-1.5 border-b border-amber-200/60 bg-amber-50/70 px-3 py-2 text-[11px] leading-4 text-amber-900"
        >
            <Info class="h-3.5 w-3.5 shrink-0 text-amber-700" />
            <span>
                <strong>{{ action.result.target_weight }}g</strong> me koi item nahi hai. Sabse kam weight wale items neeche hain:
            </span>
        </div>

        <!-- 📊 3. Elegant Inline Stats Strip -->
        <div class="grid grid-cols-1 divide-y divide-surface-100 border-b border-surface-100 bg-white text-[11px] text-surface-600 min-[390px]:grid-cols-2 min-[390px]:divide-x min-[390px]:divide-y-0">
            <!-- Gold Stat -->
            <div class="flex items-center gap-1.5 px-3 py-2">
                <span class="inline-block h-2 w-2 bg-[#c08f34]"></span>
                <span class="font-medium text-surface-700">Gold:</span>
                <span class="font-mono font-semibold text-surface-900">{{ action.result.gold_count ?? 0 }} pcs</span>
                <span class="font-mono text-[10px] text-surface-400">({{ action.result.gold_weight ?? '0 g' }})</span>
            </div>

            <!-- Silver Stat -->
            <div class="flex items-center gap-1.5 px-3 py-2">
                <span class="inline-block h-2 w-2 bg-slate-400"></span>
                <span class="font-medium text-surface-700">Silver:</span>
                <span class="font-mono font-semibold text-surface-900">{{ action.result.silver_count ?? 0 }} pcs</span>
                <span class="font-mono text-[10px] text-surface-400">({{ action.result.silver_weight ?? '0 g' }})</span>
            </div>
        </div>

        <!-- 📦 4. Matching Inventory Items List (Compact & Clean) -->
        <div v-if="action.result.items && action.result.items.length > 0" class="max-h-64 divide-y divide-surface-100 overflow-y-auto">
            <div v-for="(item, i) in action.result.items" :key="i" class="flex items-center justify-between gap-3 px-3 py-2.5 text-xs transition-colors hover:bg-amber-50/30">
                <!-- Left: Name, Purity & Category -->
                <div class="min-w-0 flex-1 space-y-0.5">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="text-xs font-medium text-surface-900">{{ item.name }}</span>
                        <span class="border border-amber-200 bg-amber-50 px-1.5 py-0.2 font-mono text-[9px] font-bold text-amber-900 uppercase">
                            {{ item.purity || '22K' }}
                        </span>
                    </div>
                    <div class="flex items-center gap-1.5 text-[10.5px] text-surface-400">
                        <span>{{ item.category || 'Jewellery' }}</span>
                        <span>•</span>
                        <span>Making: <strong class="text-surface-600">{{ item.making }}</strong></span>
                    </div>
                </div>

                <!-- Right: Weight & Barcode Copy Tag -->
                <div class="flex shrink-0 flex-col items-end gap-1 text-right">
                    <span class="font-mono text-xs font-semibold text-surface-900">{{ item.weight }}</span>
                    <button
                        type="button"
                        @click="copyBarcode(item.barcode)"
                        :title="copiedBarcode === item.barcode ? 'Copied!' : 'Copy Barcode'"
                        class="inline-flex min-h-7 items-center gap-1 border border-surface-200 bg-surface-50 px-2 py-1 text-[9.5px] font-medium text-surface-600 transition-colors hover:border-[#1c3633] hover:bg-[#1c3633] hover:text-white"
                        :aria-label="`Copy barcode ${item.barcode}`"
                    >
                        <Check v-if="copiedBarcode === item.barcode" class="h-2.5 w-2.5 text-emerald-500" />
                        <Copy v-else class="h-2.5 w-2.5 text-surface-400" />
                        <span>{{ item.barcode }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 🚫 Empty State -->
        <div v-else class="space-y-1 bg-surface-50/50 p-5 text-center text-xs text-surface-500">
            <Search class="mx-auto h-5 w-5 text-surface-400 opacity-70" />
            <p class="text-[11px] font-medium text-surface-600">Matching stock nahi mila</p>
            <p class="text-[10px] text-surface-400">Weight, purity ya category change karke dobara poochiye.</p>
        </div>
    </section>
</template>
