<script setup lang="ts">
import { computed, ref } from 'vue';
import InputText from 'primevue/inputtext';
import {
    FileText,
    Printer,
    Receipt,
    ExternalLink,
    ShieldCheck,
    Check,
    Tag,
    X,
} from 'lucide-vue-next';

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
    (e: 'recalculate', draft: any): void;
}>();

const showBarcodeField = ref(!!props.action.result?.barcode);

const isConfirmed = computed(() => {
    return Boolean(
        props.action.result?.invoice_number &&
        (props.action.result?.status === 'INVOICE_GENERATED_REAL_DB' || props.action.result?.is_preview === false)
    );
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
    <div class="border border-slate-300 bg-white shadow-xs rounded-none overflow-hidden my-2 font-sans" style="font-family: 'Poppins', sans-serif !important;">
        <!-- 📝 1. DRAFT PREVIEW STATE (Before DB Insert) -->
        <div v-if="isDraft" class="p-3 bg-white space-y-3">
            <!-- Header Banner (Sharp rectangular) -->
            <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                <div class="flex items-center gap-2">
                    <div class="w-6 h-6 bg-[#1c3633] text-[#c08f34] flex items-center justify-center rounded-none">
                        <Receipt class="w-3.5 h-3.5" />
                    </div>
                    <div>
                        <h4 class="font-bold text-xs text-slate-900 tracking-wide uppercase">Invoice Draft Preview</h4>
                        <p class="text-[10px] text-slate-500 font-medium">Verify & edit details before creating in database</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-100 border border-amber-300 text-amber-900 text-[10px] font-bold uppercase rounded-none">
                    <ShieldCheck class="w-3 h-3" />
                    Review Required
                </span>
            </div>

            <!-- 2-Column Compact Input Grid -->
            <div class="grid grid-cols-2 gap-2.5 text-xs">
                <!-- Customer Name -->
                <div>
                    <label class="text-[10.5px] font-bold text-slate-700 block uppercase tracking-wider">Customer Name *</label>
                    <InputText
                        v-model="action.result.customer_name"
                        size="small"
                        placeholder="Customer Name (or Walk-in)"
                        class="w-full mt-1 font-semibold text-slate-900 rounded-none !font-sans"
                    />
                </div>

                <!-- Mobile Number -->
                <div>
                    <label class="text-[10.5px] font-bold text-slate-700 block uppercase tracking-wider">Mobile Number</label>
                    <InputText
                        v-model="action.result.customer_phone"
                        size="small"
                        placeholder="10-digit mobile (optional)"
                        class="w-full mt-1 font-mono font-medium text-slate-900 rounded-none !font-sans"
                    />
                </div>

                <!-- Item Description & Barcode Toggle -->
                <div :class="[showBarcodeField ? 'col-span-1' : 'col-span-2']">
                    <div class="flex items-center justify-between">
                        <label class="text-[10.5px] font-bold text-slate-700 block uppercase tracking-wider">Item Description *</label>
                        <button
                            v-if="!showBarcodeField"
                            type="button"
                            @click="showBarcodeField = true"
                            class="text-[10px] font-bold text-[#c08f34] hover:underline cursor-pointer flex items-center gap-0.5"
                        >
                            <Tag class="w-2.5 h-2.5" />
                            <span>+ Attach Barcode</span>
                        </button>
                    </div>
                    <InputText
                        v-model="action.result.item_name"
                        size="small"
                        placeholder="e.g. 22K Gold Chain"
                        class="w-full mt-1 font-semibold text-slate-900 rounded-none !font-sans"
                    />
                </div>

                <!-- Optional Barcode Field (Only if present or clicked) -->
                <div v-if="showBarcodeField" class="col-span-1">
                    <div class="flex items-center justify-between">
                        <label class="text-[10.5px] font-bold text-slate-700 block uppercase tracking-wider">Stock Barcode</label>
                        <button
                            type="button"
                            @click="showBarcodeField = false; action.result.barcode = ''"
                            class="text-slate-400 hover:text-red-600 cursor-pointer"
                            title="Remove Barcode"
                        >
                            <X class="w-3 h-3" />
                        </button>
                    </div>
                    <InputText
                        v-model="action.result.barcode"
                        size="small"
                        placeholder="e.g. G00026"
                        class="w-full mt-1 font-mono font-bold uppercase text-slate-900 rounded-none !font-sans"
                    />
                </div>

                <!-- Weight & Live Rate -->
                <div>
                    <label class="text-[10.5px] font-bold text-slate-700 block uppercase tracking-wider">Net Weight (g) *</label>
                    <div class="relative mt-1">
                        <InputText
                            v-model.number="action.result.weight"
                            type="number"
                            step="0.001"
                            size="small"
                            @input="emit('recalculate', action.result)"
                            class="w-full pl-2.5 pr-6 font-bold text-slate-900 rounded-none !font-sans"
                        />
                        <span class="absolute right-2.5 top-2 text-[10px] font-bold text-slate-400">g</span>
                    </div>
                </div>
                <div>
                    <label class="text-[10.5px] font-bold text-slate-700 block uppercase tracking-wider">Live Rate (₹/g) *</label>
                    <div class="relative mt-1">
                        <span class="absolute left-2.5 top-2 text-xs font-bold text-slate-500 z-1">₹</span>
                        <InputText
                            v-model.number="action.result.rate_per_gm"
                            type="number"
                            step="1"
                            size="small"
                            @input="emit('recalculate', action.result)"
                            class="w-full pl-6 pr-2 font-bold text-slate-900 rounded-none !font-sans"
                        />
                    </div>
                </div>

                <!-- Purity Chips (Sharp rectangular) -->
                <div class="col-span-2">
                    <label class="text-[10.5px] font-bold text-slate-700 block uppercase tracking-wider mb-1">Purity</label>
                    <div class="grid grid-cols-5 gap-1.5">
                        <button
                            v-for="p in ['22K', '18K', '24K', '14K', 'Silver']"
                            :key="p"
                            type="button"
                            @click="setPurity(action.result, p)"
                            :class="[
                                'py-1 text-[11px] font-bold border transition-all text-center rounded-none cursor-pointer',
                                action.result.purity === p
                                    ? 'bg-[#1c3633] text-[#c08f34] border-[#1c3633]'
                                    : 'bg-slate-50 text-slate-700 border-slate-300 hover:bg-slate-100'
                            ]"
                        >
                            {{ p }}
                        </button>
                    </div>
                </div>

                <!-- Making Charge (Sharp rectangular tabs) -->
                <div class="col-span-2 space-y-1.5 pt-1 border-t border-slate-100">
                    <div class="flex items-center justify-between">
                        <label class="text-[10.5px] font-bold text-slate-700 uppercase tracking-wider">Making Charge</label>
                        <span class="text-[11px] font-bold text-slate-800 font-mono">
                            = ₹{{ formatMoney(action.result.making_charges) }}
                        </span>
                    </div>
                    <div class="grid grid-cols-3 gap-1">
                        <button
                            type="button"
                            @click="setMakingType(action.result, 'percentage')"
                            :class="[
                                'py-1 text-[10.5px] font-bold border transition-all text-center rounded-none cursor-pointer',
                                action.result.making_type === 'percentage'
                                    ? 'bg-[#1c3633] text-white border-[#1c3633]'
                                    : 'bg-slate-50 text-slate-700 border-slate-300 hover:bg-slate-100'
                            ]"
                        >
                            % Percent
                        </button>
                        <button
                            type="button"
                            @click="setMakingType(action.result, 'per_gram')"
                            :class="[
                                'py-1 text-[10.5px] font-bold border transition-all text-center rounded-none cursor-pointer',
                                action.result.making_type === 'per_gram'
                                    ? 'bg-[#1c3633] text-white border-[#1c3633]'
                                    : 'bg-slate-50 text-slate-700 border-slate-300 hover:bg-slate-100'
                            ]"
                        >
                            ₹/g Per Gram
                        </button>
                        <button
                            type="button"
                            @click="setMakingType(action.result, 'flat')"
                            :class="[
                                'py-1 text-[10.5px] font-bold border transition-all text-center rounded-none cursor-pointer',
                                action.result.making_type === 'flat'
                                    ? 'bg-[#1c3633] text-white border-[#1c3633]'
                                    : 'bg-slate-50 text-slate-700 border-slate-300 hover:bg-slate-100'
                            ]"
                        >
                            ₹ Flat
                        </button>
                    </div>

                    <div class="grid grid-cols-2 gap-2 pt-1">
                        <div>
                            <label class="text-[10px] text-slate-600 block">
                                Value {{ action.result.making_type === 'percentage' ? '(%)' : '(₹)' }}
                            </label>
                            <InputText
                                v-model.number="action.result.making_value"
                                type="number"
                                step="0.1"
                                size="small"
                                @input="emit('recalculate', action.result)"
                                class="w-full mt-0.5 font-bold text-slate-900 rounded-none !font-sans"
                            />
                        </div>
                        <div>
                            <label class="text-[10px] text-slate-600 block">Discount (₹)</label>
                            <InputText
                                v-model.number="action.result.discount_amount"
                                type="number"
                                step="1"
                                size="small"
                                @input="emit('recalculate', action.result)"
                                class="w-full mt-0.5 font-bold text-emerald-800 rounded-none !font-sans"
                            />
                        </div>
                    </div>
                </div>

                <!-- Payment Mode (Sharp rectangular) -->
                <div class="col-span-2 pt-1 border-t border-slate-100">
                    <label class="text-[10.5px] font-bold text-slate-700 block uppercase tracking-wider mb-1">Payment Mode</label>
                    <div class="grid grid-cols-5 gap-1">
                        <button
                            v-for="mode in ['Cash', 'UPI', 'Card', 'Bank', 'Credit']"
                            :key="mode"
                            type="button"
                            @click="setPaymentMode(action.result, mode)"
                            :class="[
                                'py-1 text-[10.5px] font-bold border transition-all text-center rounded-none cursor-pointer',
                                action.result.payment_mode === mode
                                    ? 'bg-[#1c3633] text-white border-[#1c3633]'
                                    : 'bg-slate-50 text-slate-700 border-slate-300 hover:bg-slate-100'
                            ]"
                        >
                            {{ mode }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Live Bill Summary Box (Sharp rectangular) -->
            <div class="bg-slate-50 border border-slate-200 p-2.5 space-y-1.5 text-xs rounded-none">
                <div class="flex justify-between text-slate-700">
                    <span>Metal Value ({{ formatWeight(action.result.weight) }} @ ₹{{ formatMoney(action.result.rate_per_gm) }})</span>
                    <span class="font-bold text-slate-900 font-mono">₹{{ formatMoney(action.result.metal_value) }}</span>
                </div>
                <div class="flex justify-between text-slate-700">
                    <span>Making Charges</span>
                    <span class="font-bold text-slate-900 font-mono">+ ₹{{ formatMoney(action.result.making_charges) }}</span>
                </div>
                <div v-if="action.result.discount_amount > 0" class="flex justify-between text-emerald-700">
                    <span>Discount</span>
                    <span class="font-bold font-mono">- ₹{{ formatMoney(action.result.discount_amount) }}</span>
                </div>
                <div class="flex justify-between text-slate-700">
                    <span>3% GST</span>
                    <span class="font-bold text-slate-900 font-mono">+ ₹{{ formatMoney(action.result.gst_3_percent) }}</span>
                </div>
                <div class="flex justify-between items-center pt-2 border-t border-slate-200">
                    <span class="font-bold text-slate-900 uppercase tracking-wide text-xs">GRAND TOTAL</span>
                    <span class="text-base font-bold text-emerald-800 font-mono">
                        ₹{{ formatMoney(action.result.grand_total) }}
                    </span>
                </div>
            </div>

            <!-- Confirmation & Discard Buttons (Sharp rectangular) -->
            <div class="flex items-center gap-2 pt-1">
                <button
                    type="button"
                    :disabled="isConfirming"
                    @click="emit('confirm', action, msgId)"
                    class="flex-1 py-2.5 bg-[#1c3633] hover:bg-[#254642] text-white text-xs font-bold flex items-center justify-center gap-1.5 transition-all disabled:opacity-50 rounded-none cursor-pointer border border-[#1c3633]"
                >
                    <Check class="w-4 h-4 text-[#c08f34]" />
                    <span>{{ isConfirming ? 'Creating Invoice in Database...' : 'Confirm & Create Invoice in Database' }}</span>
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

        <!-- 📄 2. CONFIRMED FINAL INVOICE VOUCHER (Post-Confirmation) -->
        <div v-else-if="isConfirmed" class="p-3 bg-white space-y-2.5">
            <!-- Invoice Header Banner (Sharp rectangular) -->
            <div class="p-2.5 bg-emerald-700 text-white flex items-center justify-between rounded-none">
                <div class="flex items-center gap-2">
                    <div class="w-6 h-6 bg-white/20 flex items-center justify-center font-bold text-white rounded-none">
                        <Receipt class="w-3.5 h-3.5" />
                    </div>
                    <div>
                        <div class="font-bold text-xs font-mono tracking-wide">{{ action.result.invoice_number }}</div>
                        <div class="text-[10.5px] text-emerald-100 font-medium">
                            Customer: <strong>{{ action.result.customer_name || 'Walk-in Customer' }}</strong> {{ action.result.customer_phone ? '(' + action.result.customer_phone + ')' : '' }}
                        </div>
                    </div>
                </div>
                <span class="px-2 py-0.5 bg-white text-emerald-800 text-[10px] font-bold uppercase rounded-none shadow-xs">
                    Bill Created
                </span>
            </div>

            <!-- Breakdown Grid (Sharp rectangular) -->
            <div class="bg-slate-50 border border-slate-200 p-2.5 space-y-1.5 text-xs rounded-none">
                <div class="grid grid-cols-2 gap-2 text-[11px]">
                    <div>
                        <span class="text-slate-500 text-[10px] block">Item & Purity</span>
                        <span class="font-bold text-slate-900">{{ action.result.item_name }} ({{ action.result.purity || '22K' }})</span>
                    </div>
                    <div>
                        <span class="text-slate-500 text-[10px] block">Weight & Rate</span>
                        <span class="font-bold text-slate-900">{{ formatWeight(action.result.weight) }} @ ₹{{ formatMoney(action.result.rate_per_gm) }}/g</span>
                    </div>
                    <div>
                        <span class="text-slate-500 text-[10px] block">Metal Value</span>
                        <span class="font-bold text-slate-900">₹{{ formatMoney(action.result.metal_value) }}</span>
                    </div>
                    <div>
                        <span class="text-slate-500 text-[10px] block">Making Charges</span>
                        <span class="font-bold text-slate-900">₹{{ formatMoney(action.result.making_charges) }}</span>
                    </div>
                </div>

                <div class="flex justify-between items-center pt-2 border-t border-slate-200">
                    <div>
                        <span class="text-[10px] text-slate-500 uppercase tracking-wider block">Grand Total (Inc. 3% GST)</span>
                        <span class="text-base font-bold font-mono text-emerald-800">
                            ₹{{ formatMoney(action.result.grand_total) }}
                        </span>
                    </div>
                    <span class="px-2 py-0.5 bg-emerald-50 border border-emerald-300 text-emerald-800 text-[10px] font-bold font-mono rounded-none">
                        Paid via {{ action.result.payment_mode || 'Cash' }}
                    </span>
                </div>
            </div>

            <!-- Action Buttons: View Invoice & Print Bill PDF (Sharp rectangular) -->
            <div class="flex items-center gap-2 pt-0.5">
                <a
                    :href="action.result.view_url"
                    target="_blank"
                    class="flex-1 py-2 bg-white border border-slate-300 hover:border-slate-800 text-slate-800 text-xs font-bold flex items-center justify-center gap-1.5 shadow-xs transition-all text-center rounded-none cursor-pointer"
                >
                    <FileText class="w-3.5 h-3.5 text-amber-600" />
                    <span>View Invoice</span>
                    <ExternalLink class="w-3 h-3 text-slate-400" />
                </a>
                <a
                    :href="action.result.print_url"
                    target="_blank"
                    class="flex-1 py-2 bg-[#1c3633] hover:bg-[#254642] text-white text-xs font-bold flex items-center justify-center gap-1.5 shadow-xs transition-all text-center rounded-none cursor-pointer"
                >
                    <Printer class="w-3.5 h-3.5 text-amber-400" />
                    <span>Print PDF</span>
                </a>
            </div>
        </div>

        <!-- 🚫 3. DISCARDED STATE -->
        <div v-else class="p-2 bg-slate-100 text-slate-500 text-xs italic text-center rounded-none">
            Invoice draft was discarded.
        </div>
    </div>
</template>
