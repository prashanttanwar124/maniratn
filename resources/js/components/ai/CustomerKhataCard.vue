<script setup lang="ts">
import { BookOpen, Check, Copy, ExternalLink, MapPin, Phone, Receipt, ShieldCheck } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    action: {
        tool: string;
        args: Record<string, any>;
        result: Record<string, any>;
    };
}>();

const copiedVault = ref(false);

const copyVaultLink = (url: string) => {
    if (!url) return;
    navigator.clipboard.writeText(url);
    copiedVault.value = true;
    setTimeout(() => {
        copiedVault.value = false;
    }, 2000);
};
</script>

<template>
    <div
        v-if="action.result?.found"
        class="erp-ai-card my-3 overflow-hidden border border-l-[3px] border-surface-300 border-l-[#c08f34] bg-white font-sans shadow-[0_4px_14px_rgba(15,23,42,0.06)]"
        style="font-family: 'Poppins', sans-serif !important"
    >
        <!-- 🏛️ 1. Header (Light Luxury ERP Style) -->
        <div class="flex items-center justify-between gap-3 border-b border-surface-200 bg-[#f8f6f0] px-3.5 py-2.5">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center bg-[#1c3633] text-[#e5c278]">
                    <BookOpen class="h-3.5 w-3.5" />
                </span>
                <div class="flex flex-col justify-center min-w-0">
                    <p class="!m-0 !p-0 !text-xs font-semibold tracking-wide text-surface-900 !leading-tight truncate">
                        {{ action.result.customer_name }}
                    </p>
                    <div class="!m-0 !p-0 !text-[10.5px] font-normal text-surface-500 !leading-tight flex items-center gap-2 mt-0.5">
                        <span v-if="action.result.mobile && action.result.mobile !== '—'" class="inline-flex items-center gap-1">
                            <Phone class="h-2.5 w-2.5 text-surface-400" />
                            {{ action.result.mobile }}
                        </span>
                        <span v-if="action.result.city && action.result.city !== '—'" class="inline-flex items-center gap-1">
                            <MapPin class="h-2.5 w-2.5 text-surface-400" />
                            {{ action.result.city }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
                <span
                    :class="[
                        'inline-flex items-center gap-1 border px-2 py-0.5 text-[9.5px] font-semibold tracking-wide uppercase',
                        action.result.status_type === 'DUE'
                            ? 'border-rose-300 bg-rose-50 text-rose-800'
                            : 'border-emerald-300 bg-emerald-50 text-emerald-800'
                    ]"
                >
                    {{ action.result.status_type === 'DUE' ? 'Pending Due' : 'Settled' }}
                </span>
                <a
                    v-if="action.result.customer_id"
                    :href="`/customers/${action.result.customer_id}`"
                    target="_blank"
                    class="inline-flex items-center gap-1 border border-surface-300 bg-white px-2 py-0.5 text-[10px] font-medium text-surface-700 hover:border-[#c08f34] hover:text-[#1c3633] transition-colors"
                    title="Open Full Customer Ledger"
                >
                    <ExternalLink class="h-2.5 w-2.5 text-[#b07b24]" />
                    Ledger
                </a>
            </div>
        </div>

        <!-- 📊 2. 3-Column Metrics Grid -->
        <div class="grid grid-cols-3 divide-x divide-surface-200 bg-white text-left text-xs">
            <div class="p-2.5">
                <span class="block text-[9.5px] font-semibold uppercase tracking-wider text-surface-400">Total Purchase</span>
                <span class="mt-0.5 block font-mono text-xs font-semibold text-surface-800">{{ action.result.total_purchases }}</span>
            </div>
            <div class="p-2.5">
                <span class="block text-[9.5px] font-semibold uppercase tracking-wider text-surface-400">Total Paid</span>
                <span class="mt-0.5 block font-mono text-xs font-semibold text-emerald-700">{{ action.result.total_paid }}</span>
            </div>
            <div :class="['p-2.5', action.result.status_type === 'DUE' ? 'bg-rose-50/50' : 'bg-emerald-50/40']">
                <span class="block text-[9.5px] font-semibold uppercase tracking-wider text-surface-500">Net Balance</span>
                <span :class="['mt-0.5 block font-mono text-xs font-bold', action.result.status_type === 'DUE' ? 'text-rose-700' : 'text-emerald-800']">
                    {{ action.result.pending_due }}
                </span>
            </div>
        </div>

        <!-- 🔐 3. Digital Vault Link Box (Matching Luxury ERP Scheme) -->
        <div v-if="action.result.vault_url" class="border-t border-surface-200 bg-surface-50/70 p-3">
            <p class="text-[9.5px] font-semibold uppercase tracking-wider text-surface-600">Public Customer Vault URL</p>
            <div class="mt-1.5 flex items-center gap-2">
                <input
                    type="text"
                    :value="action.result.vault_url"
                    readonly
                    class="w-full border border-surface-200 bg-white px-2.5 py-1.5 text-xs font-mono text-surface-800 focus:outline-none focus:border-[#c08f34]"
                />
                <button
                    type="button"
                    @click="copyVaultLink(action.result.vault_url)"
                    class="inline-flex shrink-0 items-center gap-1 border border-surface-300 bg-white px-2.5 py-1.5 text-xs font-medium text-surface-700 hover:border-[#1c3633] hover:bg-[#1c3633] hover:text-white transition-colors"
                >
                    <Check v-if="copiedVault" class="h-3 w-3 text-emerald-500" />
                    <Copy v-else class="h-3 w-3 text-surface-400" />
                    <span>{{ copiedVault ? 'Copied' : 'Copy' }}</span>
                </button>
            </div>
            <p class="mt-1.5 text-[10.5px] text-surface-500 leading-snug">
                This link is permanently assigned to this customer. New purchases automatically reflect in their vault.
            </p>
        </div>

        <!-- 🧾 4. Recent Bills Timeline -->
        <div v-if="action.result.recent_bills?.length" class="border-t border-surface-100 bg-surface-50/50 p-2.5">
            <p class="text-[9.5px] font-bold text-surface-500 uppercase tracking-wider mb-1.5">Recent Purchase Bills</p>
            <div class="space-y-1">
                <div
                    v-for="bill in action.result.recent_bills"
                    :key="bill.id"
                    class="flex items-center justify-between bg-white px-2.5 py-2 border border-surface-200 text-xs text-surface-700 transition-colors hover:border-surface-300"
                >
                    <div class="flex items-center gap-2">
                        <Receipt class="h-3.5 w-3.5 text-[#c08f34]" />
                        <span class="font-mono font-bold text-surface-900 text-[11.5px]">{{ bill.invoice_number }}</span>
                        <span class="text-[10px] text-surface-400">({{ bill.date }})</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="font-mono font-bold text-surface-900 text-xs">{{ bill.total }}</span>
                        <a
                            :href="`/invoices/${bill.id}/print`"
                            target="_blank"
                            class="text-surface-400 hover:text-[#1c3633] transition-colors p-0.5"
                            title="View/Print Bill"
                        >
                            <ExternalLink class="h-3 w-3" />
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div v-else class="my-2 border border-surface-200 bg-surface-50 p-3 text-xs text-surface-600">
        {{ action.result?.message || 'Customer record not found.' }}
    </div>
</template>
