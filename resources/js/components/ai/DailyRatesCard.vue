<script setup lang="ts">
import { Check, CheckCircle2, Edit3, ShieldCheck } from 'lucide-vue-next';
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
    return Boolean(props.action.result?.status === 'UPDATED_IN_DATABASE' || props.action.result?.is_preview === false);
});

const isDraft = computed(() => {
    return !isConfirmed.value && !props.action.result?.is_discarded;
});

const canConfirm = computed(() => {
    return Number(props.action.result?.gold_24k_sell) > 0 && Number(props.action.result?.silver_sell) > 0;
});
</script>

<template>
    <section
        class="my-3 overflow-hidden rounded-none border border-l-[3px] border-surface-300 border-l-[#c08f34] bg-white font-sans shadow-[0_4px_14px_rgba(15,23,42,0.06)]"
        style="font-family: 'Poppins', sans-serif !important"
        aria-label="Daily rates update draft"
    >
        <!-- 📝 1. DAILY RATES DRAFT PREVIEW -->
        <div v-if="isDraft" class="space-y-4 bg-white p-4">
            <div class="-mx-4 -mt-4 flex items-center justify-between gap-3 border-b border-surface-200 bg-[#f8f6f0] px-3.5 py-2.5">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center bg-[#1c3633] text-[#e5c278]">
                        <Edit3 class="h-3.5 w-3.5" />
                    </span>
                    <div class="flex flex-col justify-center">
                        <p class="!m-0 !p-0 !text-xs font-semibold tracking-wide text-surface-900 !leading-tight">Daily rates update</p>
                        <p class="!m-0 !p-0 !text-[10px] font-normal text-surface-500 !leading-tight">Aaj ke rates verify karke live karein</p>
                    </div>
                </div>
                <span class="inline-flex w-fit items-center gap-1 border border-amber-300 bg-amber-50 px-2 py-0.5 text-[9.5px] font-semibold tracking-wide text-amber-900 uppercase">
                    <ShieldCheck class="h-3 w-3 text-amber-700" />
                    Review
                </span>
            </div>

            <div class="grid grid-cols-1 gap-2.5 min-[400px]:grid-cols-2">
                <div class="rounded-none border border-amber-200 bg-amber-50/40 p-3">
                    <label class="block text-[11px] font-medium text-surface-700">Gold 24K sell rate <span class="text-surface-400">(₹/g)</span></label>
                    <div class="relative mt-1">
                        <span class="absolute top-2 left-2.5 z-1 text-xs font-bold text-[#c08f34]">₹</span>
                        <InputText
                            v-model.number="action.result.gold_24k_sell"
                            type="number"
                            size="small"
                            class="w-full rounded-none pr-2 pl-6 !font-sans font-mono text-base font-bold text-[#9b6f1e]"
                        />
                    </div>
                </div>
                <div class="rounded-none border border-surface-200 bg-surface-50 p-3">
                    <label class="block text-[11px] font-medium text-surface-700">Silver sell rate <span class="text-surface-400">(₹/g)</span></label>
                    <div class="relative mt-1">
                        <span class="absolute top-2 left-2.5 z-1 text-xs font-bold text-slate-500">₹</span>
                        <InputText
                            v-model.number="action.result.silver_sell"
                            type="number"
                            size="small"
                            class="w-full rounded-none pr-2 pl-6 !font-sans font-mono text-base font-bold text-surface-800"
                        />
                    </div>
                </div>
            </div>

            <p class="border-l-2 border-amber-400 bg-amber-50/60 px-2.5 py-2 text-[10.5px] leading-4 text-amber-900">Ye rates billing aur estimates mein turant use honge.</p>

            <p v-if="!canConfirm" class="border-l-2 border-red-500 bg-red-50 px-2.5 py-2 text-[10.5px] text-red-700">Gold aur silver dono rates required hain.</p>

            <div class="-mx-4 -mb-4 flex flex-col-reverse gap-2 border-t border-surface-200 bg-surface-50 px-4 py-3 min-[430px]:flex-row min-[430px]:items-center">
                <button
                    type="button"
                    :disabled="isConfirming || !canConfirm"
                    @click="emit('confirm', action, msgId)"
                    class="flex flex-1 items-center justify-center gap-1.5 rounded-none border border-[#1c3633] bg-[#1c3633] py-2.5 text-xs font-semibold text-white transition-colors hover:bg-[#254642] disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <Check class="h-4 w-4 text-[#c08f34]" />
                    <span>{{ isConfirming ? 'Rates update ho rahe hain...' : canConfirm ? 'Confirm and update rates' : 'Enter both rates' }}</span>
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

        <!-- 📈 2. CONFIRMED RATES STATE -->
        <div v-else-if="isConfirmed" class="flex flex-col gap-3 rounded-none bg-emerald-50 p-4 min-[430px]:flex-row min-[430px]:items-center min-[430px]:justify-between">
            <div class="flex items-start gap-2">
                <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-emerald-700" />
                <div>
                    <span class="block text-xs font-semibold text-emerald-900">Rates updated successfully</span>
                    <span class="text-[10px] text-emerald-700">New rates ab billing mein active hain</span>
                </div>
            </div>
            <div class="flex shrink-0 divide-x divide-emerald-200 border border-emerald-200 bg-white text-[10.5px]">
                <span class="px-2 py-1.5 text-emerald-900"
                    >Gold <strong class="font-mono">₹{{ Number(action.result.gold_24k_sell).toLocaleString('en-IN') }}/g</strong></span
                >
                <span class="px-2 py-1.5 text-surface-700"
                    >Silver <strong class="font-mono">₹{{ Number(action.result.silver_sell || 0).toLocaleString('en-IN') }}/g</strong></span
                >
            </div>
        </div>

        <!-- 🚫 3. DISCARDED STATE -->
        <div v-else class="rounded-none bg-slate-100 p-2 text-center text-xs text-slate-500 italic">Rate update draft was discarded.</div>
    </section>
</template>
