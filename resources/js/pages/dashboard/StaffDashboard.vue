<script setup>
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps({
    user: Object,
    rates: Object, // Read only rates
    metrics: Object, // { my_sales: 50000, pending_orders: 5 }
});

const formatCurrency = (val) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(val);
</script>

<template>
    <AppLayout>
        <div class="min-h-screen space-y-6 bg-gray-50 p-6">
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-3 text-sm shadow-sm">
                <div class="flex gap-6">
                    <div>
                        <span class="font-bold text-gray-500">Gold Sell:</span> <span class="font-mono font-bold text-gray-800">{{ formatCurrency(rates.gold_sell) }}</span>
                    </div>
                    <div>
                        <span class="font-bold text-gray-500">Silver:</span> <span class="font-mono font-bold text-gray-800">{{ formatCurrency(rates.silver_sell) }}</span>
                    </div>
                </div>
                <div class="text-xs text-gray-400">{{ new Date().toLocaleDateString() }}</div>
            </div>

            <div class="flex flex-col gap-6 md:flex-row">
                <div class="flex flex-1 flex-col justify-center rounded-2xl bg-blue-600 p-6 text-white shadow-lg">
                    <h1 class="text-2xl font-bold">Hello, {{ user.name }}! 👋</h1>
                    <p class="mt-1 text-blue-100 opacity-90">
                        You have <span class="font-bold underline">{{ metrics.pending_orders }} orders</span> to process.
                    </p>
                    <div class="mt-6 flex gap-3">
                        <button class="flex items-center gap-2 rounded-lg bg-white px-4 py-2 font-bold text-blue-600 shadow transition hover:bg-gray-100"><i class="pi pi-plus"></i> New Sale</button>
                        <button class="rounded-lg bg-blue-700 px-4 py-2 font-bold text-white transition hover:bg-blue-800">Check Stock</button>
                    </div>
                </div>

                <div class="flex flex-col justify-center rounded-2xl border-l-4 border-green-500 bg-white p-6 shadow md:w-80">
                    <div class="text-xs font-bold tracking-widest text-gray-400 uppercase">My Sales Today</div>
                    <div class="mt-2 text-4xl font-black text-gray-800">{{ formatCurrency(metrics.my_sales) }}</div>
                    <div class="mt-2 inline-block w-fit rounded bg-green-50 px-2 py-1 text-xs font-bold text-green-600"><i class="pi pi-chart-line"></i> Keep it up!</div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 p-4">
                    <h3 class="font-bold text-gray-700">Orders Needing Attention</h3>
                </div>
                <div class="p-4 text-center text-sm text-gray-400" v-if="metrics.pending_orders === 0">No pending orders. Relax! ☕</div>
                <div class="space-y-2 p-2" v-else>
                    <div class="flex cursor-pointer items-center justify-between rounded-lg border border-gray-100 bg-gray-50 p-3 transition hover:border-blue-300">
                        <div>
                            <div class="text-sm font-bold text-gray-800">Order #1024 - Gold Ring</div>
                            <div class="text-xs text-gray-500">Customer: Amit Kumar</div>
                        </div>
                        <i class="pi pi-chevron-right text-gray-300"></i>
                    </div>
                    <div class="flex cursor-pointer items-center justify-between rounded-lg border border-gray-100 bg-gray-50 p-3 transition hover:border-blue-300">
                        <div>
                            <div class="text-sm font-bold text-gray-800">Order #1025 - Chain Repair</div>
                            <div class="text-xs text-gray-500">Customer: Sunita Devi</div>
                        </div>
                        <i class="pi pi-chevron-right text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
