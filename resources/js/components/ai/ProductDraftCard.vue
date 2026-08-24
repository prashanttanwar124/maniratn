<script setup lang="ts">
import { Check, PackagePlus, ShieldCheck } from 'lucide-vue-next';
import InputText from 'primevue/inputtext';
import { computed } from 'vue';

interface ActionItem {
    tool: string;
    args: Record<string, any>;
    result: Record<string, any>;
}

const props = defineProps<{
    action: ActionItem;
    msgId: string;
    isConfirming: boolean;
}>();

const emit = defineEmits<{
    (e: 'confirm', action: ActionItem, msgId: string): void;
    (e: 'discard', action: ActionItem, msgId: string): void;
}>();

const isConfirmed = computed(() => {
    return Boolean(props.action.result?.barcode && (props.action.result?.status === 'IN_STOCK_REAL_DB' || props.action.result?.is_preview === false));
});

const isDraft = computed(() => {
    return !isConfirmed.value && !props.action.result?.is_discarded;
});

const formatMoney = (val: any) => {
    if (val === null || val === undefined || val === '') return '0.00';
    if (typeof val === 'number') {
        return isNaN(val) ? '0.00' : val.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    const clean = String(val).replace(/[^0-9.-]/g, '');
    const num = parseFloat(clean);
    return isNaN(num) ? '0.00' : num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatWeight = (val: any) => {
    if (val === null || val === undefined || val === '') return '0 g';
    const clean = String(val).replace(/[^0-9.]/g, '');
    const num = parseFloat(clean);
    return isNaN(num) ? '0 g' : `${num} g`;
};
</script>

<template>
    <section
        class="my-2 overflow-hidden rounded-none border border-t-2 border-surface-200 border-t-[#c08f34] bg-white font-sans shadow-xs"
        style="font-family: 'Poppins', sans-serif !important"
        aria-label="Product stock draft"
    >
        <!-- 📝 1. PRODUCT ADD DRAFT PREVIEW -->
        <div v-if="isDraft" class="space-y-3 bg-white p-3">
            <!-- Header Banner (Sharp rectangular, perfectly aligned) -->
            <div class="flex flex-col gap-2 border-b border-surface-200 pb-2.5 min-[430px]:flex-row min-[430px]:items-center min-[430px]:justify-between">
                <div class="space-y-0.5">
                    <div class="flex items-center gap-1.5">
                        <PackagePlus class="h-4 w-4 text-[#c08f34]" />
                        <h4 class="text-xs leading-none font-semibold tracking-wide text-surface-900">Add ornament to stock</h4>
                    </div>
                    <p class="pl-5.5 text-[10.5px] font-normal text-surface-500">Details verify karein, phir stock mein save karein</p>
                </div>
                <span class="inline-flex items-center gap-1 rounded-none border border-amber-300 bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-900 uppercase">
                    <ShieldCheck class="h-3 w-3 text-amber-700" />
                    Review required
                </span>
            </div>

            <!-- Input Grid (Sharp rectangular) -->
            <div class="grid grid-cols-1 gap-3 text-xs min-[430px]:grid-cols-2">
                <div class="min-[430px]:col-span-2">
                    <label class="block text-[11px] font-medium text-surface-700">Ornament name <span class="text-red-600">*</span></label>
                    <InputText v-model="action.result.name" size="small" placeholder="e.g. 22K Gold Chain" class="mt-1 w-full rounded-none !font-sans font-semibold text-slate-900" />
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-surface-700">Net weight (g) <span class="text-red-600">*</span></label>
                    <div class="relative mt-1">
                        <InputText v-model.number="action.result.weight" type="number" step="0.001" size="small" class="w-full rounded-none pr-7 pl-2.5 !font-sans font-bold text-slate-900" />
                        <span class="absolute top-2 right-2.5 text-[10.5px] font-bold text-slate-400">g</span>
                    </div>
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-surface-700">Purity</label>
                    <InputText v-model="action.result.purity" placeholder="22K, 18K, 24K" size="small" class="mt-1 w-full rounded-none !font-sans font-semibold text-slate-900" />
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-surface-700">Category</label>
                    <InputText v-model="action.result.category" placeholder="Chain, Ring, Bangle" size="small" class="mt-1 w-full rounded-none !font-sans text-slate-900" />
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-surface-700">Making charge (₹/g)</label>
                    <InputText v-model.number="action.result.making_charge_per_gm" type="number" size="small" class="mt-1 w-full rounded-none !font-sans font-semibold text-slate-900" />
                </div>
            </div>

            <!-- Action Buttons (Sharp rectangular) -->
            <div class="flex flex-col-reverse gap-2 border-t border-surface-100 pt-3 min-[430px]:flex-row min-[430px]:items-center">
                <button
                    type="button"
                    :disabled="isConfirming"
                    @click="emit('confirm', action, msgId)"
                    class="flex flex-1 items-center justify-center gap-1.5 rounded-none border border-[#1c3633] bg-[#1c3633] py-2.5 text-xs font-semibold text-white transition-colors hover:bg-[#254642] disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <Check class="h-4 w-4 text-[#c08f34]" />
                    <span>{{ isConfirming ? 'Stock mein save ho raha hai...' : 'Confirm and save' }}</span>
                </button>
                <button
                    type="button"
                    @click="emit('discard', action, msgId)"
                    class="rounded-none border border-surface-300 bg-white px-4 py-2.5 text-xs font-medium text-surface-600 transition-colors hover:border-red-300 hover:bg-red-50 hover:text-red-700"
                >
                    Discard
                </button>
            </div>
        </div>

        <!-- 📦 2. CONFIRMED SAVED PRODUCT STATE -->
        <div v-else-if="isConfirmed" class="space-y-3 bg-white p-3">
            <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                <div class="flex items-center gap-2">
                    <div class="flex h-6 w-6 items-center justify-center rounded-none bg-emerald-700 text-white">
                        <PackagePlus class="h-3.5 w-3.5" />
                    </div>
                    <div>
                        <span class="block text-[10px] font-medium tracking-wide text-emerald-700 uppercase">Saved to stock</span>
                        <span class="text-xs font-semibold text-slate-900">{{ action.result.name }}</span>
                    </div>
                </div>
                <span class="rounded-none bg-[#1c3633] px-2 py-0.5 font-mono text-xs font-bold tracking-widest text-[#c08f34]">
                    {{ action.result.barcode }}
                </span>
            </div>
            <div class="grid grid-cols-1 border border-surface-200 bg-surface-50 text-[11px] text-slate-600 min-[380px]:grid-cols-3 min-[380px]:divide-x min-[380px]:divide-surface-200">
                <div class="p-2">
                    Weight<br /><strong class="font-mono text-slate-900">{{ formatWeight(action.result.weight) }}</strong>
                </div>
                <div class="border-t border-surface-200 p-2 min-[380px]:border-t-0">
                    Purity<br /><strong class="text-slate-900">{{ action.result.purity }}</strong>
                </div>
                <div class="border-t border-surface-200 p-2 min-[380px]:border-t-0">
                    Making<br /><strong class="font-mono text-slate-900">₹{{ formatMoney(action.result.making_charge_per_gm) }}/g</strong>
                </div>
            </div>
        </div>

        <!-- 🚫 3. DISCARDED STATE -->
        <div v-else class="rounded-none bg-slate-100 p-2 text-center text-xs text-slate-500 italic">Product draft was discarded.</div>
    </section>
</template>
