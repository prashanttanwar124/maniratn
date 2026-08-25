<script setup lang="ts">
import { ExternalLink, Printer, Receipt, User } from 'lucide-vue-next';

defineProps<{
    action: {
        tool: string;
        args: Record<string, any>;
        result: Record<string, any>;
    };
}>();
</script>

<template>
    <div
        v-if="action.result?.found && action.result.invoices?.length"
        class="my-3 overflow-hidden border border-l-[3px] border-surface-300 border-l-[#c08f34] bg-white font-sans shadow-[0_4px_14px_rgba(15,23,42,0.06)]"
    >
        <!-- Header -->
        <div class="flex items-center justify-between gap-3 border-b border-surface-200 bg-[#f8f6f0] px-3.5 py-2.5">
            <div class="flex items-center gap-2.5">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center bg-[#1c3633] text-[#e5c278]">
                    <Receipt class="h-3.5 w-3.5" />
                </span>
                <div class="flex flex-col justify-center">
                    <p class="!m-0 !p-0 !text-xs font-semibold tracking-wide text-surface-900 !leading-tight">
                        Previous Purchase Invoices
                    </p>
                    <p class="!m-0 !p-0 !text-[10px] font-normal text-surface-500 !leading-tight">
                        Found {{ action.result.count }} bill(s)
                    </p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1 border border-amber-300 bg-amber-50 px-2 py-0.5 text-[9.5px] font-semibold tracking-wide text-amber-900 uppercase">
                Purchase History
            </span>
        </div>

        <!-- Invoices List -->
        <div class="divide-y divide-surface-100 bg-white">
            <div
                v-for="inv in action.result.invoices"
                :key="inv.id"
                class="p-3 text-xs transition-colors hover:bg-surface-50/60"
            >
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-bold text-surface-900 text-xs">{{ inv.invoice_number }}</span>
                            <span class="text-[10px] text-surface-400 font-normal">({{ inv.date }})</span>
                        </div>
                        <p class="mt-0.5 text-[11px] font-medium text-surface-700">
                            👤 {{ inv.customer_name }} <span v-if="inv.customer_mobile !== '—'" class="text-surface-400">· {{ inv.customer_mobile }}</span>
                        </p>
                        <p class="mt-1 text-[10.5px] text-surface-500 line-clamp-1 italic">
                            🛍️ {{ inv.items_summary }}
                        </p>
                    </div>

                    <div class="text-right flex flex-col items-end shrink-0">
                        <span class="font-mono text-xs font-bold text-[#1c3633]">{{ inv.total_amount }}</span>
                        <span
                            :class="[
                                'text-[9.5px] font-semibold tracking-wide mt-0.5 uppercase',
                                inv.status === 'COMPLETED' ? 'text-emerald-700' : 'text-amber-700'
                            ]"
                        >
                            {{ inv.status === 'COMPLETED' ? 'Paid' : 'Pending ' + inv.pending_amount }}
                        </span>
                        <a
                            :href="inv.print_url"
                            target="_blank"
                            class="mt-1.5 inline-flex items-center gap-1 bg-[#1c3633] hover:bg-[#284c47] text-white px-2 py-0.5 text-[10px] font-medium tracking-wide transition-colors"
                        >
                            <Printer class="h-2.5 w-2.5" />
                            Print Bill
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div v-else class="my-2 border border-surface-200 bg-surface-50 p-3 text-xs text-surface-600">
        {{ action.result?.message || 'No invoices found.' }}
    </div>
</template>
