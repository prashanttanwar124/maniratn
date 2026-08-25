<script setup lang="ts">
import { CalendarDays, CheckCircle2, Clock3, FileSearch, Phone, Printer, ReceiptText, ShoppingBag, UserRound, XCircle } from 'lucide-vue-next';

defineProps<{
    action: {
        tool: string;
        args: Record<string, any>;
        result: Record<string, any>;
    };
}>();

const hasMobile = (mobile: unknown) => Boolean(mobile && mobile !== '—' && mobile !== '0000000000');

const statusLabel = (status: string) => {
    if (status === 'COMPLETED') return 'Paid';
    if (status === 'CANCELLED') return 'Cancelled';
    return 'Payment due';
};

const statusClass = (status: string) => {
    if (status === 'COMPLETED') return 'border-emerald-300 bg-emerald-50 text-emerald-800';
    if (status === 'CANCELLED') return 'border-rose-300 bg-rose-50 text-rose-800';
    return 'border-amber-300 bg-amber-50 text-amber-900';
};
</script>

<template>
    <section
        v-if="action.result?.found && action.result.invoices?.length"
        class="my-3 overflow-hidden border border-l-[3px] border-surface-300 border-l-[#c08f34] bg-white font-sans shadow-[0_5px_18px_rgba(15,23,42,0.07)]"
        aria-label="Previous purchase invoices"
    >
        <header class="border-b border-surface-200 bg-[#f8f6f0] px-3.5 py-2.5">
            <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-2.5">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center bg-[#1c3633] text-[#e5c278]">
                        <ReceiptText class="h-3.5 w-3.5" />
                    </span>
                    <div class="flex flex-col justify-center">
                        <p class="!m-0 !p-0 !text-xs font-semibold tracking-wide text-surface-900 !leading-tight">Previous Purchase Invoices</p>
                        <p class="!m-0 !p-0 !text-[10px] font-normal text-surface-500 !leading-tight">Latest purchase records matching your search</p>
                    </div>
                </div>

                <span class="inline-flex shrink-0 items-center border border-amber-300 bg-amber-50 px-2 py-0.5 text-[9.5px] font-semibold tracking-wide text-amber-900 uppercase">
                    {{ action.result.count ?? action.result.invoices.length }} found
                </span>
            </div>
        </header>

        <div class="bg-surface-50/70 p-2.5">
            <div class="space-y-2">
                <article v-for="inv in action.result.invoices" :key="inv.id" class="group border border-surface-200 bg-white transition-colors hover:border-surface-400">
                    <div class="flex items-start justify-between gap-3 border-b border-surface-100 px-3 py-2.5">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="font-mono text-[11.5px] font-bold tracking-tight text-[#1c3633]">{{ inv.invoice_number }}</span>
                                <span class="inline-flex items-center gap-1 text-[10px] text-surface-500">
                                    <CalendarDays class="h-3 w-3 text-surface-400" />
                                    {{ inv.date }}
                                </span>
                            </div>
                            <div class="mt-1.5 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-[10.5px] text-surface-600">
                                <span class="inline-flex min-w-0 items-center gap-1 font-semibold text-surface-800">
                                    <UserRound class="h-3 w-3 shrink-0 text-[#b07b24]" />
                                    <span class="truncate">{{ inv.customer_name }}</span>
                                </span>
                                <span v-if="hasMobile(inv.customer_mobile)" class="inline-flex items-center gap-1 text-surface-500">
                                    <Phone class="h-3 w-3" />
                                    {{ inv.customer_mobile }}
                                </span>
                            </div>
                        </div>

                        <span :class="['inline-flex shrink-0 items-center gap-1 border px-2 py-0.5 text-[9px] font-bold tracking-wide uppercase', statusClass(inv.status)]">
                            <CheckCircle2 v-if="inv.status === 'COMPLETED'" class="h-3 w-3" />
                            <XCircle v-else-if="inv.status === 'CANCELLED'" class="h-3 w-3" />
                            <Clock3 v-else class="h-3 w-3" />
                            {{ statusLabel(inv.status) }}
                        </span>
                    </div>

                    <div class="px-3 py-2">
                        <div class="flex items-start gap-2 border-l-2 border-amber-300 bg-amber-50/40 px-2.5 py-2">
                            <ShoppingBag class="mt-0.5 h-3.5 w-3.5 shrink-0 text-[#a8741f]" />
                            <div class="min-w-0">
                                <p class="!m-0 !p-0 text-[9px] font-bold tracking-wider text-amber-900 uppercase">Purchased items</p>
                                <p class="!m-0 mt-0.5 line-clamp-2 !p-0 text-[10.5px] leading-4 text-surface-700" :title="inv.items_summary">
                                    {{ inv.items_summary }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 divide-x divide-surface-200 border-t border-surface-200 bg-surface-50 text-left">
                        <div class="px-2.5 py-2">
                            <span class="block text-[9px] font-medium tracking-wide text-surface-400 uppercase">Bill total</span>
                            <span class="mt-0.5 block font-mono text-[11px] font-bold text-surface-900">{{ inv.total_amount }}</span>
                        </div>
                        <div class="px-2.5 py-2">
                            <span class="block text-[9px] font-medium tracking-wide text-surface-400 uppercase">Paid</span>
                            <span class="mt-0.5 block font-mono text-[11px] font-bold text-emerald-700">{{ inv.paid_amount }}</span>
                        </div>
                        <div :class="['px-2.5 py-2', inv.status === 'DUE' ? 'bg-amber-50/70' : '']">
                            <span class="block text-[9px] font-medium tracking-wide text-surface-400 uppercase">Balance</span>
                            <span :class="['mt-0.5 block font-mono text-[11px] font-bold', inv.status === 'DUE' ? 'text-amber-800' : 'text-surface-600']">
                                {{ inv.pending_amount }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3 border-t border-surface-200 bg-white px-3 py-2">
                        <span class="text-[9.5px] font-medium text-surface-400">Payment: {{ inv.payment_method || 'CASH' }}</span>
                        <a
                            :href="inv.print_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex min-h-7 items-center justify-center gap-1.5 border border-[#1c3633] bg-[#1c3633] px-3 py-1 text-[10px] font-semibold tracking-wide text-white transition-colors hover:bg-[#284c47] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#c08f34]"
                            :aria-label="`Print invoice ${inv.invoice_number}`"
                        >
                            <Printer class="h-3 w-3 text-[#e5c278]" />
                            View / Print bill
                        </a>
                    </div>
                </article>
            </div>
        </div>
    </section>
</template>
