<script setup lang="ts">
import { AlertCircle, Check, Clock, PackagePlus, Pencil, ShieldCheck, X } from 'lucide-vue-next';
import InputText from 'primevue/inputtext';
import { computed, ref } from 'vue';

interface ActionItem {
    tool: string;
    args: Record<string, any>;
    result: Record<string, any>;
}

const props = defineProps<{
    action: ActionItem;
    msgId: string;
    isConfirming: boolean;
    isSuperseded?: boolean;
}>();

const emit = defineEmits<{
    (e: 'confirm', action: ActionItem, msgId: string): void;
    (e: 'discard', action: ActionItem, msgId: string): void;
}>();

const isEditing = ref(!props.action.result?.name || !props.action.result?.weight);

const isExpired = computed(() => {
    return Boolean(props.isSuperseded || props.action.result?.is_superseded || props.action.result?.status === 'SUPERSEDED');
});

const isConfirmed = computed(() => {
    return Boolean(props.action.result?.barcode && (props.action.result?.status === 'IN_STOCK_REAL_DB' || props.action.result?.is_preview === false));
});

const isDraft = computed(() => {
    return !isConfirmed.value && !props.action.result?.is_discarded;
});

const canConfirm = computed(() => {
    return Boolean(
        !isExpired.value &&
        props.action.result?.name &&
        Number(props.action.result?.weight) > 0 &&
        Number(props.action.result?.quantity || 1) >= 1
    );
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
        class="my-3 overflow-hidden rounded-none border border-l-[3px] border-surface-300 border-l-[#c08f34] bg-white font-sans shadow-[0_4px_14px_rgba(15,23,42,0.06)]"
        style="font-family: 'Poppins', sans-serif !important"
        aria-label="Product stock draft"
    >
        <!-- 📝 1. PRODUCT ADD DRAFT PREVIEW -->
        <div v-if="isDraft" class="space-y-4 bg-white p-4">
            <!-- Header Banner (Sharp rectangular, perfectly aligned) -->
            <div class="-mx-4 -mt-4 flex items-center justify-between gap-3 border-b border-surface-200 bg-[#f8f6f0] px-3.5 py-2.5">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center bg-[#1c3633] text-[#e5c278]">
                        <PackagePlus class="h-3.5 w-3.5" />
                    </span>
                    <div class="flex flex-col justify-center">
                        <p class="!m-0 !p-0 !text-xs font-semibold tracking-wide text-surface-900 !leading-tight">
                            {{ Number(action.result.quantity) > 1 ? `Add ${action.result.quantity} items to stock` : 'Add ornament to stock' }}
                        </p>
                        <p class="!m-0 !p-0 !text-[10px] font-normal text-surface-500 !leading-tight">
                            {{ Number(action.result.quantity) > 1 ? `${action.result.quantity} unique barcodes generate honge` : 'Details verify karein, phir stock mein save karein' }}
                        </p>
                    </div>
                </div>
                <span v-if="isExpired" class="inline-flex w-fit items-center gap-1 border border-slate-300 bg-slate-100 px-2 py-0.5 text-[9.5px] font-semibold tracking-wide text-slate-600 uppercase">
                    <Clock class="h-3 w-3 text-slate-500" />
                    Expired
                </span>
                <span v-else class="inline-flex w-fit items-center gap-1 border border-amber-300 bg-amber-50 px-2 py-0.5 text-[9.5px] font-semibold tracking-wide text-amber-900 uppercase">
                    <ShieldCheck class="h-3 w-3 text-amber-700" />
                    Review
                </span>
            </div>

            <div class="border border-surface-200 bg-[#fafbfa]">
                <div class="flex items-center justify-between gap-3 border-b border-surface-200 px-3 py-2">
                    <div class="min-w-0">
                        <p class="truncate text-xs font-semibold text-surface-900">
                            {{ action.result.name || 'Product details required' }}
                            <span v-if="Number(action.result.quantity) > 1" class="ml-1 text-[#b07b24] font-bold">({{ action.result.quantity }} Pieces)</span>
                        </p>
                        <p class="mt-0.5 text-[10px] text-surface-400">AI se nikali hui stock details</p>
                    </div>
                    <button
                        type="button"
                        class="flex shrink-0 items-center gap-1 border border-surface-300 bg-white px-2 py-1 text-[10px] font-medium text-surface-700 transition-colors hover:border-[#c08f34] hover:text-[#1c3633]"
                        :aria-expanded="isEditing"
                        @click="isEditing = !isEditing"
                    >
                        <Pencil class="h-3 w-3 text-[#b07b24]" />
                        {{ isEditing ? 'Hide details' : 'Edit details' }}
                    </button>
                </div>
                <dl :class="['grid divide-x divide-surface-200 text-center text-[10.5px]', Number(action.result.quantity) > 1 ? 'grid-cols-4' : 'grid-cols-3']">
                    <div v-if="Number(action.result.quantity) > 1" class="p-2.5 bg-amber-50/50">
                        <dt class="text-amber-800 font-medium">Quantity</dt>
                        <dd class="mt-0.5 font-bold text-[#1c3633]">{{ action.result.quantity }} Pcs</dd>
                    </div>
                    <div class="p-2.5">
                        <dt class="text-surface-400">{{ Number(action.result.quantity) > 1 ? 'Weight (pc)' : 'Weight' }}</dt>
                        <dd class="mt-0.5 font-mono font-semibold text-surface-800">{{ formatWeight(action.result.weight) }}</dd>
                    </div>
                    <div v-if="Number(action.result.quantity) > 1" class="p-2.5">
                        <dt class="text-surface-400">Total weight</dt>
                        <dd class="mt-0.5 font-mono font-bold text-surface-900">{{ formatWeight(Number(action.result.weight) * Number(action.result.quantity)) }}</dd>
                    </div>
                    <div class="p-2.5">
                        <dt class="text-surface-400">Purity</dt>
                        <dd class="mt-0.5 font-semibold text-surface-800">{{ action.result.purity || '—' }}</dd>
                    </div>
                    <div v-if="Number(action.result.quantity) <= 1" class="p-2.5">
                        <dt class="text-surface-400">Category</dt>
                        <dd class="mt-0.5 truncate font-semibold text-surface-800">{{ action.result.category || '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div v-if="isEditing" class="grid grid-cols-1 gap-3 border-l-2 border-[#c08f34] bg-surface-50/50 p-3 text-xs min-[430px]:grid-cols-2">
                <div>
                    <label class="block text-[11px] font-medium text-surface-700">Ornament name <span class="text-red-600">*</span></label>
                    <InputText v-model="action.result.name" size="small" placeholder="e.g. 22K Gold Chain" class="mt-1 w-full rounded-none !font-sans font-semibold text-slate-900" />
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-surface-700">Quantity (Pieces) <span class="text-red-600">*</span></label>
                    <InputText v-model.number="action.result.quantity" type="number" :min="1" size="small" placeholder="1" class="mt-1 w-full rounded-none !font-sans font-bold text-slate-900" />
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-surface-700">Net weight per piece (g) <span class="text-red-600">*</span></label>
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

            <!-- Error Alert Banner -->
            <div
                v-if="action.result.error_message"
                class="mt-3 flex items-start gap-2.5 border-l-4 border-l-rose-600 border border-rose-200 bg-rose-50 p-3 text-xs text-rose-900 shadow-xs"
            >
                <AlertCircle class="h-4 w-4 shrink-0 text-rose-600 mt-0.5" />
                <div class="flex-1 min-w-0">
                    <p class="font-bold text-rose-950">Action Blocked / Error:</p>
                    <p class="mt-0.5 leading-snug text-rose-800">{{ action.result.error_message }}</p>
                </div>
                <button
                    type="button"
                    @click="action.result.error_message = null"
                    class="text-rose-500 hover:text-rose-800 transition-colors p-0.5 -mr-1 -mt-1"
                >
                    <X class="h-3.5 w-3.5" />
                </button>
            </div>

            <!-- Action Buttons (Sharp rectangular) -->
            <div v-if="isExpired" class="-mx-4 -mb-4 flex items-center justify-between border-t border-surface-200 bg-slate-50 px-4 py-2.5 text-xs text-slate-500">
                <div class="flex items-center gap-1.5">
                    <Clock class="h-3.5 w-3.5 text-slate-400" />
                    <span class="text-[11px] font-medium text-slate-600">Pichli chat aage badh gayi — Draft expired</span>
                </div>
                <span class="text-[10px] font-medium text-slate-400 italic">Inactive</span>
            </div>

            <div v-else class="-mx-4 -mb-4 flex flex-col gap-2 border-t border-surface-200 bg-surface-50 px-4 py-3">
                <p v-if="!canConfirm" class="border-l-2 border-red-500 bg-red-50 px-2.5 py-2 text-[10.5px] text-red-700">Ornament name, net weight aur valid quantity complete karna zaroori hai.</p>
                <div class="flex flex-col-reverse gap-2 min-[430px]:flex-row min-[430px]:items-center">
                    <button
                        type="button"
                        :disabled="isConfirming || !canConfirm"
                        @click="emit('confirm', action, msgId)"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-none border border-[#1c3633] bg-[#1c3633] py-2.5 text-xs font-semibold text-white transition-colors hover:bg-[#254642] disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <Check class="h-4 w-4 text-[#c08f34]" />
                        <span>{{ isConfirming ? 'Stock mein save ho raha hai...' : canConfirm ? (Number(action.result.quantity) > 1 ? `Confirm & Save ${action.result.quantity} Items` : 'Confirm and save') : 'Complete required details' }}</span>
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
        </div>

        <!-- 📦 2. CONFIRMED SAVED PRODUCT STATE -->
        <div v-else-if="isConfirmed" class="space-y-3 bg-white p-4">
            <div class="-mx-4 -mt-4 flex items-center justify-between border-b border-emerald-800 bg-emerald-700 px-4 py-3 text-white">
                <div class="flex items-center gap-2">
                    <div class="flex h-7 w-7 items-center justify-center rounded-none bg-white/15 text-white">
                        <PackagePlus class="h-3.5 w-3.5" />
                    </div>
                    <div>
                        <span class="block text-[10px] font-medium tracking-wide text-emerald-100 uppercase">
                            {{ Number(action.result.quantity) > 1 ? `${action.result.quantity} Items Saved to stock` : 'Saved to stock' }}
                        </span>
                        <span class="text-xs font-semibold text-white">{{ action.result.name }}</span>
                    </div>
                </div>
                <span class="rounded-none bg-[#1c3633] px-2 py-0.5 font-mono text-xs font-bold tracking-widest text-[#c08f34]">
                    {{ action.result.barcode }}
                </span>
            </div>
            <div class="grid grid-cols-1 border border-surface-200 bg-surface-50 text-[11px] text-slate-600 min-[380px]:grid-cols-4 min-[380px]:divide-x min-[380px]:divide-surface-200">
                <div v-if="Number(action.result.quantity) > 1" class="p-2">
                    Quantity<br /><strong class="text-slate-900">{{ action.result.quantity }} Pieces</strong>
                </div>
                <div class="p-2">
                    {{ Number(action.result.quantity) > 1 ? 'Weight (pc)' : 'Weight' }}<br /><strong class="font-mono text-slate-900">{{ formatWeight(action.result.weight) }}</strong>
                </div>
                <div v-if="Number(action.result.quantity) > 1" class="p-2">
                    Total weight<br /><strong class="font-mono text-slate-900">{{ formatWeight(action.result.total_weight || Number(action.result.weight) * Number(action.result.quantity)) }}</strong>
                </div>
                <div class="border-t border-surface-200 p-2 min-[380px]:border-t-0">
                    Purity<br /><strong class="text-slate-900">{{ action.result.purity }}</strong>
                </div>
                <div v-if="Number(action.result.quantity) <= 1" class="border-t border-surface-200 p-2 min-[380px]:border-t-0">
                    Making<br /><strong class="font-mono text-slate-900">₹{{ formatMoney(action.result.making_charge_per_gm) }}/g</strong>
                </div>
            </div>
        </div>

        <!-- 🚫 3. DISCARDED STATE -->
        <div v-else class="rounded-none bg-slate-100 p-2 text-center text-xs text-slate-500 italic">Product draft was discarded.</div>
    </section>
</template>
