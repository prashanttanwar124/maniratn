<script setup lang="ts">
import InputText from 'primevue/inputtext';
import { PackagePlus, ShieldCheck, Check } from 'lucide-vue-next';

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
    <div class="border border-slate-300 bg-white shadow-xs rounded-none overflow-hidden my-2">
        <!-- 📝 1. PRODUCT ADD DRAFT PREVIEW -->
        <div v-if="action.result.is_preview" class="p-3 bg-white space-y-3">
            <!-- Header Banner (Sharp rectangular) -->
            <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                <div class="flex items-center gap-2">
                    <div class="w-6 h-6 bg-[#1c3633] text-[#c08f34] flex items-center justify-center rounded-none">
                        <PackagePlus class="w-3.5 h-3.5" />
                    </div>
                    <div>
                        <h4 class="font-bold text-xs text-slate-900 font-serif tracking-wide uppercase">Add Ornament to Stock</h4>
                        <p class="text-[10px] text-slate-500 font-medium">Review specs before saving barcode in ERP</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-100 border border-amber-300 text-amber-900 text-[10px] font-bold uppercase rounded-none">
                    <ShieldCheck class="w-3 h-3" />
                    Review Required
                </span>
            </div>

            <!-- Input Grid (Sharp rectangular) -->
            <div class="grid grid-cols-2 gap-2.5 text-xs">
                <div class="col-span-2">
                    <label class="text-[10.5px] font-bold text-slate-700 block uppercase tracking-wider">Ornament Name *</label>
                    <InputText
                        v-model="action.result.name"
                        size="small"
                        placeholder="e.g. 22K Gold Chain"
                        class="w-full mt-1 font-semibold text-slate-900 rounded-none"
                    />
                </div>
                <div>
                    <label class="text-[10.5px] font-bold text-slate-700 block uppercase tracking-wider">Net Weight (g) *</label>
                    <div class="relative mt-1">
                        <InputText
                            v-model.number="action.result.weight"
                            type="number"
                            step="0.001"
                            size="small"
                            class="w-full pl-2.5 pr-7 font-bold text-slate-900 rounded-none"
                        />
                        <span class="absolute right-2.5 top-2 text-[10.5px] font-bold text-slate-400">g</span>
                    </div>
                </div>
                <div>
                    <label class="text-[10.5px] font-bold text-slate-700 block uppercase tracking-wider">Purity</label>
                    <InputText
                        v-model="action.result.purity"
                        placeholder="22K, 18K, 24K"
                        size="small"
                        class="w-full mt-1 font-semibold text-slate-900 rounded-none"
                    />
                </div>
                <div>
                    <label class="text-[10.5px] font-bold text-slate-700 block uppercase tracking-wider">Category</label>
                    <InputText
                        v-model="action.result.category"
                        placeholder="Chain, Ring, Bangle"
                        size="small"
                        class="w-full mt-1 text-slate-900 rounded-none"
                    />
                </div>
                <div>
                    <label class="text-[10.5px] font-bold text-slate-700 block uppercase tracking-wider">Making Charge (₹/g)</label>
                    <InputText
                        v-model.number="action.result.making_charge_per_gm"
                        type="number"
                        size="small"
                        class="w-full mt-1 font-semibold text-slate-900 rounded-none"
                    />
                </div>
            </div>

            <!-- Action Buttons (Sharp rectangular) -->
            <div class="flex items-center gap-2 pt-1">
                <button
                    type="button"
                    :disabled="isConfirming"
                    @click="emit('confirm', action, msgId)"
                    class="flex-1 py-2.5 bg-[#1c3633] hover:bg-[#254642] text-white text-xs font-bold flex items-center justify-center gap-1.5 transition-all disabled:opacity-50 rounded-none cursor-pointer border border-[#1c3633]"
                >
                    <Check class="w-4 h-4 text-[#c08f34]" />
                    <span>{{ isConfirming ? 'Saving to Stock...' : 'Confirm & Save to Stock' }}</span>
                </button>
                <button
                    type="button"
                    @click="emit('discard', action, msgId)"
                    class="px-4 py-2.5 bg-white border border-slate-300 text-slate-700 hover:text-red-700 hover:border-red-300 text-xs font-semibold transition-all rounded-none cursor-pointer"
                >
                    Discard
                </button>
            </div>
        </div>

        <!-- 📦 2. CONFIRMED SAVED PRODUCT STATE -->
        <div v-else-if="!action.result.is_discarded" class="p-3 bg-white space-y-2">
            <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                <div class="flex items-center gap-2">
                    <div class="w-6 h-6 bg-emerald-700 text-white flex items-center justify-center rounded-none">
                        <PackagePlus class="w-3.5 h-3.5" />
                    </div>
                    <span class="font-bold text-xs text-slate-900">{{ action.result.name }}</span>
                </div>
                <span class="font-mono text-xs px-2 py-0.5 bg-[#1c3633] text-[#c08f34] font-bold tracking-widest rounded-none">
                    {{ action.result.barcode }}
                </span>
            </div>
            <div class="grid grid-cols-3 gap-2 text-[11px] text-slate-600 pt-1">
                <div>Weight: <strong class="text-slate-900">{{ formatWeight(action.result.weight) }}</strong></div>
                <div>Purity: <strong class="text-slate-900">{{ action.result.purity }}</strong></div>
                <div>Making: <strong class="text-slate-900">₹{{ formatMoney(action.result.making_charge_per_gm) }}/g</strong></div>
            </div>
        </div>

        <!-- 🚫 3. DISCARDED STATE -->
        <div v-else class="p-2 bg-slate-100 text-slate-500 text-xs italic text-center rounded-none">
            Product draft was discarded.
        </div>
    </div>
</template>
