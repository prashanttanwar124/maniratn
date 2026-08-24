<script setup lang="ts">
import { computed } from 'vue';
import InputText from 'primevue/inputtext';
import { Edit3, ShieldCheck, Check, CheckCircle2 } from 'lucide-vue-next';

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
    return Boolean(
        props.action.result?.status === 'UPDATED_IN_DATABASE' ||
        props.action.result?.is_preview === false
    );
});

const isDraft = computed(() => {
    return !isConfirmed.value && !props.action.result?.is_discarded;
});
</script>

<template>
    <div class="border border-surface-200 bg-white shadow-xs rounded-none overflow-hidden my-2 font-sans border-t-2 border-t-[#c08f34]" style="font-family: 'Poppins', sans-serif !important;">
        <!-- 📝 1. DAILY RATES DRAFT PREVIEW -->
        <div v-if="isDraft" class="p-3 bg-white space-y-3">
            <div class="flex items-center justify-between border-b border-surface-200 pb-2">
                <div class="space-y-0.5">
                    <div class="flex items-center gap-1.5">
                        <Edit3 class="w-4 h-4 text-[#c08f34]" />
                        <h4 class="font-bold text-xs text-surface-900 tracking-wide uppercase leading-none">Daily Rates Update Preview</h4>
                    </div>
                    <p class="text-[10.5px] text-surface-500 font-medium pl-5.5">Review rates before updating database</p>
                </div>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-50 border border-amber-300 text-amber-900 text-[10px] font-bold uppercase rounded-none">
                    <ShieldCheck class="w-3 h-3 text-amber-700" />
                    Review Required
                </span>
            </div>

            <div class="grid grid-cols-2 gap-2.5">
                <div class="bg-slate-50 p-2.5 border border-slate-200 rounded-none">
                    <label class="text-[10px] font-bold text-slate-700 uppercase tracking-wide block">Gold 24K Sell (₹/g)</label>
                    <div class="relative mt-1">
                        <span class="absolute left-2.5 top-2 text-xs font-bold text-[#c08f34] z-1">₹</span>
                        <InputText
                            v-model.number="action.result.gold_24k_sell"
                            type="number"
                            size="small"
                            class="w-full pl-6 pr-2 font-bold rounded-none !font-sans"
                        />
                    </div>
                </div>
                <div class="bg-slate-50 p-2.5 border border-slate-200 rounded-none">
                    <label class="text-[10px] font-bold text-slate-700 uppercase tracking-wide block">Silver Sell (₹/g)</label>
                    <div class="relative mt-1">
                        <span class="absolute left-2.5 top-2 text-xs font-bold text-slate-500 z-1">₹</span>
                        <InputText
                            v-model.number="action.result.silver_sell"
                            type="number"
                            size="small"
                            class="w-full pl-6 pr-2 font-bold rounded-none !font-sans"
                        />
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2 pt-1">
                <button
                    type="button"
                    :disabled="isConfirming"
                    @click="emit('confirm', action, msgId)"
                    class="flex-1 py-2.5 bg-[#1c3633] hover:bg-[#254642] text-white text-xs font-bold flex items-center justify-center gap-1.5 transition-all disabled:opacity-50 rounded-none cursor-pointer border border-[#1c3633]"
                >
                    <Check class="w-4 h-4 text-[#c08f34]" />
                    <span>{{ isConfirming ? 'Updating Database...' : 'Confirm & Update Live Rates' }}</span>
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

        <!-- 📈 2. CONFIRMED RATES STATE -->
        <div v-else-if="isConfirmed" class="p-3 bg-emerald-50 border-t border-emerald-300 flex items-center justify-between rounded-none">
            <div class="flex items-center gap-2">
                <CheckCircle2 class="w-4 h-4 text-emerald-700" />
                <span class="text-xs font-bold text-emerald-900">Rates updated successfully in database!</span>
            </div>
            <span class="text-xs font-bold text-[#1c3633] font-mono">24K: ₹{{ Number(action.result.gold_24k_sell).toLocaleString('en-IN') }}/g</span>
        </div>

        <!-- 🚫 3. DISCARDED STATE -->
        <div v-else class="p-2 bg-slate-100 text-slate-500 text-xs italic text-center rounded-none">
            Rate update draft was discarded.
        </div>
    </div>
</template>
