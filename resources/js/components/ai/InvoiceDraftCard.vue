<script setup lang="ts">
import { AlertCircle, Check, Clock, ExternalLink, FileText, Pencil, Printer, Receipt, ShieldCheck, Tag, X } from 'lucide-vue-next';
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
    (e: 'recalculate', draft: any): void;
}>();

const showBarcodeField = ref(!!props.action.result?.barcode);
const isEditing = ref(!props.action.result?.item_name || !props.action.result?.weight || !props.action.result?.rate_per_gm);

const isExpired = computed(() => {
    return Boolean(props.isSuperseded || props.action.result?.is_superseded || props.action.result?.status === 'SUPERSEDED');
});

const isConfirmed = computed(() => {
    return Boolean(props.action.result?.invoice_number && (props.action.result?.status === 'INVOICE_GENERATED_REAL_DB' || props.action.result?.is_preview === false));
});

const isUnavailable = computed(() => {
    return Boolean(
        props.action.result?.found === false ||
        props.action.result?.status === 'PRODUCT_ALREADY_SOLD' ||
        props.action.result?.status === 'BARCODE_NOT_FOUND' ||
        props.action.result?.status === 'BARCODE_REQUIRED' ||
        props.action.result?.status === 'CUSTOMER_DETAILS_REQUIRED' ||
        !props.action.result?.barcode
    );
});

const isDraft = computed(() => {
    return !isConfirmed.value && !isUnavailable.value && !props.action.result?.is_discarded;
});

const canConfirm = computed(() => {
    return Boolean(
        !isExpired.value &&
        props.action.result?.barcode &&
        props.action.result?.customer_name &&
        props.action.result?.customer_name.trim() !== '' &&
        props.action.result?.customer_phone &&
        props.action.result?.customer_phone.trim() !== '' &&
        props.action.result?.item_name &&
        Number(props.action.result?.weight) > 0 &&
        Number(props.action.result?.rate_per_gm) > 0
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

const setMakingType = (draft: any, type: string) => {
    draft.making_type = type;
    emit('recalculate', draft);
};

const setPaymentMode = (draft: any, mode: string) => {
    draft.payment_mode = mode;
};

const setPurity = (draft: any, purity: string) => {
    draft.purity = purity;
};
</script>

<template>
    <section
class="erp-ai-card my-3 overflow-hidden border border-l-[3px] border-surface-300 border-l-[#c08f34] bg-white font-sans shadow-[0_4px_14px_rgba(15,23,42,0.06)]"
        style="font-family: 'Poppins', sans-serif !important"
        aria-label="Invoice draft"
    >
        <!-- 📝 1. DRAFT PREVIEW STATE (Before DB Insert) -->
        <div v-if="isDraft" class="space-y-4 bg-white p-4">
            <!-- Header Banner (Sharp rectangular, perfectly aligned) -->
            <div class="-mx-4 -mt-4 flex items-center justify-between gap-3 border-b border-surface-200 bg-[#f8f6f0] px-3.5 py-2.5">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center bg-[#1c3633] text-[#e5c278]">
                        <Receipt class="h-3.5 w-3.5" />
                    </span>
                    <div class="flex flex-col justify-center">
                        <p class="!m-0 !p-0 !text-xs font-semibold tracking-wide text-surface-900 !leading-tight">Invoice draft</p>
                        <p class="!m-0 !p-0 !text-[10px] font-normal text-surface-500 !leading-tight">Details verify karein, phir invoice create karein</p>
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
                    <span class="text-[10px] font-semibold tracking-wide text-surface-500 uppercase">Review summary</span>
                    <div class="flex items-center gap-1.5">
                        <a
                            href="/invoices/create"
                            class="flex items-center gap-1 border border-surface-300 bg-white px-2 py-1 text-[10px] font-medium text-surface-700 transition-colors hover:border-[#c08f34] hover:text-[#1c3633]"
                            title="Open Full Billing Screen"
                        >
                            <ExternalLink class="h-3 w-3 text-[#b07b24]" />
                            Full Form
                        </a>
                        <button
                            type="button"
                            class="flex items-center gap-1 border border-surface-300 bg-white px-2 py-1 text-[10px] font-medium text-surface-700 transition-colors hover:border-[#c08f34] hover:text-[#1c3633]"
                            :aria-expanded="isEditing"
                            @click="isEditing = !isEditing"
                        >
                            <Pencil class="h-3 w-3 text-[#b07b24]" />
                            {{ isEditing ? 'Hide details' : 'Edit details' }}
                        </button>
                    </div>
                </div>
                <dl class="grid grid-cols-2 divide-x divide-y divide-surface-200 text-[10.5px] min-[460px]:grid-cols-4 min-[460px]:divide-y-0">
                    <div class="p-2.5">
                        <dt class="text-surface-400">Customer</dt>
                        <dd class="mt-0.5 truncate font-semibold text-surface-800">{{ action.result.customer_name || 'Walk-in' }}</dd>
                    </div>
                    <div class="p-2.5">
                        <dt class="text-surface-400">Item</dt>
                        <dd class="mt-0.5 truncate font-semibold text-surface-800">{{ action.result.item_name || 'Not added' }}</dd>
                    </div>
                    <div class="p-2.5">
                        <dt class="text-surface-400">Weight / purity</dt>
                        <dd class="mt-0.5 font-mono font-semibold text-surface-800">{{ formatWeight(action.result.weight) }} · {{ action.result.purity || '22K' }}</dd>
                    </div>
                    <div class="p-2.5">
                        <dt class="text-surface-400">Payment</dt>
                        <dd class="mt-0.5 font-semibold text-surface-800">{{ action.result.payment_mode || 'Cash' }}</dd>
                    </div>
                </dl>
            </div>

            <div v-if="isEditing" class="grid grid-cols-1 gap-3 rounded-lg border border-[#c08f34]/30 bg-surface-50/50 p-3 text-xs min-[430px]:grid-cols-2">
                <!-- Customer Name -->
                <div>
                    <label class="block text-[11px] font-medium text-surface-700">Customer name <span class="text-red-600">*</span></label>
                    <InputText v-model="action.result.customer_name" size="small" placeholder="Customer Full Name" class="mt-1 w-full !font-sans font-semibold text-slate-900" />
                </div>

                <!-- Mobile Number -->
                <div>
                    <label class="block text-[11px] font-medium text-surface-700">Mobile number <span class="text-red-600">*</span></label>
                    <InputText
                        v-model="action.result.customer_phone"
                        size="small"
                        placeholder="10-digit mobile number"
                        class="mt-1 w-full !font-sans font-mono font-medium text-slate-900"
                    />
                </div>

                <!-- Item Description & Barcode Toggle -->
                <div :class="[showBarcodeField ? '' : 'min-[430px]:col-span-2']">
                    <div class="flex items-center justify-between">
                        <label class="block text-[11px] font-medium text-surface-700">Item description <span class="text-red-600">*</span></label>
                        <button v-if="!showBarcodeField" type="button" @click="showBarcodeField = true" class="flex items-center gap-0.5 text-[10px] font-medium text-[#a8741f] hover:underline">
                            <Tag class="h-2.5 w-2.5" />
                            <span>Add barcode</span>
                        </button>
                    </div>
                    <InputText v-model="action.result.item_name" size="small" placeholder="e.g. 22K Gold Chain" class="mt-1 w-full !font-sans font-semibold text-slate-900" />
                </div>

                <!-- Optional Barcode Field (Only if present or clicked) -->
                <div v-if="showBarcodeField">
                    <div class="flex items-center justify-between">
                        <label class="block text-[11px] font-medium text-surface-700">Stock barcode</label>
                        <button
                            type="button"
                            @click="
                                showBarcodeField = false;
                                action.result.barcode = '';
                            "
                            class="flex h-5 w-5 items-center justify-center rounded-md border border-transparent text-slate-400 hover:border-red-200 hover:text-red-600"
                            title="Remove Barcode"
                        >
                            <X class="h-3 w-3" />
                        </button>
                    </div>
                    <InputText v-model="action.result.barcode" size="small" placeholder="e.g. G00026" class="mt-1 w-full !font-sans font-mono font-bold text-slate-900 uppercase" />
                </div>

                <!-- Weight & Live Rate -->
                <div>
                    <label class="block text-[11px] font-medium text-surface-700">Net weight (g) <span class="text-red-600">*</span></label>
                    <div class="relative mt-1">
                        <InputText
                            v-model.number="action.result.weight"
                            type="number"
                            step="0.001"
                            size="small"
                            @input="emit('recalculate', action.result)"
                            class="w-full pr-6 pl-2.5 !font-sans font-bold text-slate-900"
                        />
                        <span class="absolute top-2 right-2.5 text-[10px] font-bold text-slate-400">g</span>
                    </div>
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-surface-700">Live rate (₹/g) <span class="text-red-600">*</span></label>
                    <div class="relative mt-1">
                        <span class="absolute top-2 left-2.5 z-1 text-xs font-bold text-slate-500">₹</span>
                        <InputText
                            v-model.number="action.result.rate_per_gm"
                            type="number"
                            step="1"
                            size="small"
                            @input="emit('recalculate', action.result)"
                            class="w-full pr-2 pl-6 !font-sans font-bold text-slate-900"
                        />
                    </div>
                </div>

                <!-- Purity Chips -->
                <div class="min-[430px]:col-span-2">
                    <label class="mb-1 block text-[11px] font-medium text-surface-700">Purity</label>
                    <div class="grid grid-cols-3 gap-1.5 min-[480px]:grid-cols-5">
                        <button
                            v-for="p in ['22K', '18K', '24K', '14K', 'Silver']"
                            :key="p"
                            type="button"
                            @click="setPurity(action.result, p)"
                            :class="[
                                'min-h-8 rounded-md border py-1 text-center text-[11px] font-semibold transition-colors',
                                action.result.purity === p ? 'border-[#1c3633] bg-[#1c3633] text-[#c08f34]' : 'border-slate-300 bg-slate-50 text-slate-700 hover:border-slate-400 hover:bg-slate-100',
                            ]"
                        >
                            {{ p }}
                        </button>
                    </div>
                </div>

                <!-- Making Charge -->
                <div class="space-y-2 border-t border-slate-100 pt-3 min-[430px]:col-span-2">
                    <div class="flex items-center justify-between">
                        <label class="text-[11px] font-medium text-surface-700">Making charge</label>
                        <span class="font-mono text-[11px] font-bold text-slate-800"> = ₹{{ formatMoney(action.result.making_charges) }} </span>
                    </div>
                    <div class="grid grid-cols-3 gap-1">
                        <button
                            type="button"
                            @click="setMakingType(action.result, 'percentage')"
                            :class="[
                                'min-h-8 rounded-md border py-1 text-center text-[10.5px] font-semibold transition-colors',
                                action.result.making_type === 'percentage' ? 'border-[#1c3633] bg-[#1c3633] text-white' : 'border-slate-300 bg-slate-50 text-slate-700 hover:bg-slate-100',
                            ]"
                        >
                            % Percent
                        </button>
                        <button
                            type="button"
                            @click="setMakingType(action.result, 'per_gram')"
                            :class="[
                                'min-h-8 rounded-md border py-1 text-center text-[10.5px] font-semibold transition-colors',
                                action.result.making_type === 'per_gram' ? 'border-[#1c3633] bg-[#1c3633] text-white' : 'border-slate-300 bg-slate-50 text-slate-700 hover:bg-slate-100',
                            ]"
                        >
                            ₹/g Per Gram
                        </button>
                        <button
                            type="button"
                            @click="setMakingType(action.result, 'flat')"
                            :class="[
                                'min-h-8 rounded-md border py-1 text-center text-[10.5px] font-semibold transition-colors',
                                action.result.making_type === 'flat' ? 'border-[#1c3633] bg-[#1c3633] text-white' : 'border-slate-300 bg-slate-50 text-slate-700 hover:bg-slate-100',
                            ]"
                        >
                            ₹ Flat
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-2 pt-1 min-[400px]:grid-cols-2">
                        <div>
                            <label class="block text-[10.5px] font-medium text-surface-600"> Value {{ action.result.making_type === 'percentage' ? '(%)' : '(₹)' }} </label>
                            <InputText
                                v-model.number="action.result.making_value"
                                type="number"
                                step="0.1"
                                size="small"
                                @input="emit('recalculate', action.result)"
                                class="mt-0.5 w-full !font-sans font-bold text-slate-900"
                            />
                        </div>
                        <div>
                            <label class="block text-[10.5px] font-medium text-surface-600">Discount (₹)</label>
                            <InputText
                                v-model.number="action.result.discount_amount"
                                type="number"
                                step="1"
                                size="small"
                                @input="emit('recalculate', action.result)"
                                class="mt-0.5 w-full !font-sans font-bold text-emerald-800"
                            />
                        </div>
                    </div>
                </div>

                <!-- Payment Mode -->
                <div class="border-t border-slate-100 pt-3 min-[430px]:col-span-2">
                    <label class="mb-1 block text-[11px] font-medium text-surface-700">Payment mode</label>
                    <div class="grid grid-cols-3 gap-1 min-[480px]:grid-cols-5">
                        <button
                            v-for="mode in ['Cash', 'UPI', 'Card', 'Bank', 'Credit']"
                            :key="mode"
                            type="button"
                            @click="setPaymentMode(action.result, mode)"
                            :class="[
                                'min-h-8 rounded-md border py-1 text-center text-[10.5px] font-semibold transition-colors',
                                action.result.payment_mode === mode ? 'border-[#1c3633] bg-[#1c3633] text-white' : 'border-slate-300 bg-slate-50 text-slate-700 hover:bg-slate-100',
                            ]"
                        >
                            {{ mode }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Live Bill Summary Box -->
            <div class="space-y-2 rounded-lg border border-slate-200 bg-[#fafafa] p-3.5 text-xs">
                <div class="mb-1 flex items-center justify-between border-b border-surface-200 pb-2">
                    <span class="text-[10px] font-semibold tracking-wide text-surface-500 uppercase">Bill summary</span>
                    <span class="text-[10px] text-surface-400">Live calculation</span>
                </div>
                <div class="flex items-start justify-between gap-3 text-slate-700">
                    <span class="leading-4"
                        >Metal value <span class="block text-[9.5px] text-surface-400">{{ formatWeight(action.result.weight) }} × ₹{{ formatMoney(action.result.rate_per_gm) }}/g</span></span
                    >
                    <span class="shrink-0 font-mono font-semibold text-slate-900">₹{{ formatMoney(action.result.metal_value) }}</span>
                </div>
                <div class="flex justify-between text-slate-700">
                    <span>Making Charges</span>
                    <span class="font-mono font-semibold text-slate-900">+ ₹{{ formatMoney(action.result.making_charges) }}</span>
                </div>
                <div v-if="action.result.discount_amount > 0" class="flex justify-between text-emerald-700">
                    <span>Discount</span>
                    <span class="font-mono font-semibold">- ₹{{ formatMoney(action.result.discount_amount) }}</span>
                </div>
                <div class="flex justify-between text-slate-700">
                    <span>3% GST</span>
                    <span class="font-mono font-semibold text-slate-900">+ ₹{{ formatMoney(action.result.gst_3_percent) }}</span>
                </div>
                <div class="-mx-3.5 mt-2 -mb-3.5 flex items-center justify-between rounded-b-lg border-t border-slate-200 bg-[#f3f7f5] px-3.5 py-3">
                    <span class="text-xs font-semibold tracking-wide text-slate-900 uppercase">Grand total</span>
                    <span class="font-mono text-base font-bold text-emerald-800"> ₹{{ formatMoney(action.result.grand_total) }} </span>
                </div>
            </div>

            <!-- Error Alert Banner -->
            <div
                v-if="action.result.error_message"
                class="flex items-start gap-2.5 rounded-lg border-l-4 border-l-rose-600 border border-rose-200 bg-rose-50 p-3 text-xs text-rose-900 shadow-xs"
            >
                <AlertCircle class="h-4 w-4 shrink-0 text-rose-600 mt-0.5" />
                <div class="flex-1 min-w-0">
                    <p class="font-bold text-rose-950">Action Blocked / Error:</p>
                    <p class="mt-0.5 leading-snug text-rose-800">{{ action.result.error_message }}</p>
                </div>
                <button
                    type="button"
                    @click="action.result.error_message = null"
                    class="text-rose-500 hover:text-rose-800 transition-colors p-0.5 -mr-1 -mt-1 rounded-md"
                >
                    <X class="h-3.5 w-3.5" />
                </button>
            </div>

            <!-- Confirmation & Discard Buttons -->
            <div v-if="isExpired" class="-mx-4 -mb-4 flex items-center justify-between border-t border-surface-200 bg-slate-50 px-4 py-2.5 text-xs text-slate-500">
                <div class="flex items-center gap-1.5">
                    <Clock class="h-3.5 w-3.5 text-slate-400" />
                    <span class="text-[11px] font-medium text-slate-600">Pichli chat aage badh gayi — Draft expired</span>
                </div>
                <span class="text-[10px] font-medium text-slate-400 italic">Inactive</span>
            </div>

            <div v-else class="-mx-4 -mb-4 flex flex-col gap-2 border-t border-surface-200 bg-surface-50 px-4 py-3">
                <p v-if="!canConfirm" class="rounded-md border-l-2 border-red-500 bg-red-50 px-2.5 py-2 text-[10.5px] text-red-700">Customer Name, Mobile Number, Valid Barcode aur live rate complete karna zaroori hai.</p>
                <div class="flex flex-col-reverse gap-2 min-[430px]:flex-row min-[430px]:items-center">
                    <button
                        type="button"
                        :disabled="isConfirming || !canConfirm"
                        @click="emit('confirm', action, msgId)"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-[#1c3633] bg-[#1c3633] py-2.5 text-xs font-semibold text-white transition-colors hover:bg-[#254642] disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <Check class="h-4 w-4 text-[#c08f34]" />
                        <span>{{ isConfirming ? 'Invoice create ho raha hai...' : canConfirm ? 'Confirm and create invoice' : 'Complete required details' }}</span>
                    </button>
                    <button
                        type="button"
                        @click="emit('discard', action, msgId)"
                        class="rounded-lg border border-surface-300 bg-white px-4 py-2.5 text-xs font-medium text-surface-600 transition-colors hover:border-red-300 hover:bg-red-50 hover:text-red-700"
                    >
                        Discard
                    </button>
                </div>
            </div>
        </div>

        <!-- 📄 2. CONFIRMED FINAL INVOICE VOUCHER (Post-Confirmation) -->
        <div v-else-if="isConfirmed" class="space-y-3 bg-white p-4">
            <!-- Invoice Header Banner -->
            <div class="-mx-4 -mt-4 flex flex-col gap-2 bg-emerald-700 px-4 py-3 text-white min-[420px]:flex-row min-[420px]:items-center min-[420px]:justify-between">
                <div class="flex items-center gap-2">
                    <div class="flex h-7 w-7 items-center justify-center rounded-md bg-white/20 font-bold text-white">
                        <Receipt class="h-4 w-4" />
                    </div>
                    <div>
                        <div class="font-mono text-xs font-bold tracking-wide">{{ action.result.invoice_number }}</div>
                        <div class="text-[10.5px] font-medium text-emerald-100">
                            Customer: <strong>{{ action.result.customer_name || 'Walk-in Customer' }}</strong> {{ action.result.customer_phone ? '(' + action.result.customer_phone + ')' : '' }}
                        </div>
                    </div>
                </div>
                <span class="ai-status-pill rounded-full bg-white px-2 py-0.5 text-[10px] font-bold text-emerald-800 uppercase shadow-xs"> Bill created </span>
            </div>

            <!-- Breakdown Grid -->
            <div class="space-y-1.5 rounded-lg border border-slate-200 bg-slate-50 p-2.5 text-xs">
                <div class="grid grid-cols-1 gap-3 text-[11px] min-[400px]:grid-cols-2">
                    <div>
                        <span class="block text-[10px] text-slate-500">Item & Purity</span>
                        <span class="font-bold text-slate-900">{{ action.result.item_name }} ({{ action.result.purity || '22K' }})</span>
                    </div>
                    <div>
                        <span class="block text-[10px] text-slate-500">Weight & Rate</span>
                        <span class="font-bold text-slate-900">{{ formatWeight(action.result.weight) }} @ ₹{{ formatMoney(action.result.rate_per_gm) }}/g</span>
                    </div>
                    <div>
                        <span class="block text-[10px] text-slate-500">Metal Value</span>
                        <span class="font-bold text-slate-900">₹{{ formatMoney(action.result.metal_value) }}</span>
                    </div>
                    <div>
                        <span class="block text-[10px] text-slate-500">Making Charges</span>
                        <span class="font-bold text-slate-900">₹{{ formatMoney(action.result.making_charges) }}</span>
                    </div>
                </div>

                <div class="flex flex-col gap-2 border-t border-slate-200 pt-2 min-[390px]:flex-row min-[390px]:items-center min-[390px]:justify-between">
                    <div>
                        <span class="block text-[10px] tracking-wider text-slate-500 uppercase">Grand Total (Inc. 3% GST)</span>
                        <span class="font-mono text-base font-bold text-emerald-800"> ₹{{ formatMoney(action.result.grand_total) }} </span>
                    </div>
                    <span class="ai-status-pill border border-emerald-300 bg-emerald-50 font-mono text-[10px] font-bold text-emerald-800">
                        Paid via {{ action.result.payment_mode || 'Cash' }}
                    </span>
                </div>
            </div>

            <!-- Action Buttons: View Invoice & Print Bill PDF -->
            <div class="-mx-4 -mb-4 grid grid-cols-1 gap-2 border-t border-surface-200 bg-surface-50 px-4 py-3 min-[390px]:grid-cols-2">
                <a
                    :href="action.result.view_url"
                    target="_blank"
                    class="erp-action-button flex items-center justify-center gap-1.5 rounded-lg border border-slate-300 bg-white py-2 text-center text-xs font-semibold text-slate-800 shadow-xs transition-colors hover:border-slate-800"
                >
                    <FileText class="h-3.5 w-3.5 text-amber-600" />
                    <span>View Invoice</span>
                    <ExternalLink class="h-3 w-3 text-slate-400" />
                </a>
                <a
                    :href="action.result.print_url"
                    target="_blank"
                    class="erp-action-button flex items-center justify-center gap-1.5 rounded-lg border border-[#1c3633] bg-[#1c3633] py-2 text-center text-xs font-semibold text-white shadow-xs transition-colors hover:bg-[#254642]"
                >
                    <Printer class="h-3.5 w-3.5 text-amber-400" />
                    <span>Print PDF</span>
                </a>
            </div>
        </div>

        <!-- ⚠️ 3. PRODUCT UNAVAILABLE / ALREADY SOLD -->
        <div v-else-if="isUnavailable" class="space-y-3 bg-white p-4">
            <div class="-mx-4 -mt-4 flex items-center justify-between border-b border-rose-800 bg-rose-900 px-4 py-3 text-white">
                <div class="flex items-center gap-2">
                    <div class="flex h-7 w-7 items-center justify-center rounded-md bg-rose-700 font-bold text-white">
                        <AlertCircle class="h-4 w-4" />
                    </div>
                    <div>
                        <span class="block font-mono text-[10px] font-bold tracking-wide text-rose-200 uppercase">
                            {{ action.result.status === 'PRODUCT_ALREADY_SOLD' ? 'Item Already Sold' : 'Barcode Not Found' }}
                        </span>
                        <span class="text-xs font-semibold text-white">
                            {{ action.result.barcode ? 'Barcode: ' + action.result.barcode : 'Inventory Item' }}
                        </span>
                    </div>
                </div>
                <span class="rounded-full bg-rose-950 px-2 py-0.5 text-[10px] font-bold text-rose-300 uppercase">
                    Unavailable
                </span>
            </div>

            <div class="rounded-lg border-l-4 border-l-rose-600 bg-rose-50 p-3 text-xs text-rose-900 leading-relaxed font-medium">
                {{ action.result.message || 'Yeh product showroom me available nahi hai ya pehle se bik chuka hai.' }}
            </div>
        </div>

        <!-- 🚫 4. DISCARDED STATE -->
        <div v-else class="rounded-lg bg-slate-100 p-2 text-center text-xs text-slate-500 italic">Invoice draft was discarded.</div>
    </section>
</template>
