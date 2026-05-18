<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';
import { ScanLine } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    dayOpen: Boolean,
    session: Object,
    summary: Object,
    recentCounted: Array,
    missingProducts: Array,
});

const toast = useToast();
const scanInput = ref('');
const scanning = ref(false);
const completing = ref(false);
const summary = ref(props.summary);
const session = ref(props.session);
const recentCounted = ref(props.recentCounted || []);
const missingProducts = ref(props.missingProducts || []);

const isCompleteReady = computed(() => props.dayOpen && Number(summary.value?.remaining_items || 0) === 0 && Number(summary.value?.expected_items || 0) > 0);

const formatWeight = (value) => `${Number(value || 0).toFixed(3)} g`;
const formatDateTime = (value) => {
    if (!value) return '—';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return '—';

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
};

const scanBarcode = async () => {
    const barcode = scanInput.value.trim();

    if (!barcode || scanning.value || !props.dayOpen) return;

    scanning.value = true;

    try {
        const response = await axios.post(route('gold-stock-count.scan'), {
            barcode,
        });

        const payload = response.data || {};

        summary.value = payload.summary;
        recentCounted.value = payload.recentCounted || [];
        missingProducts.value = payload.missingProducts || [];
        scanInput.value = '';

        toast.add({
            severity: 'success',
            summary: 'Counted',
            detail: `${payload.countedProduct?.barcode} added to counted stock.`,
            life: 2500,
        });
    } catch (error) {
        const message = error?.response?.data?.message || 'Unable to count this barcode.';
        toast.add({
            severity: 'warn',
            summary: 'Scan Stopped',
            detail: message,
            life: 3000,
        });
    } finally {
        scanning.value = false;
    }
};

const markComplete = async () => {
    if (!isCompleteReady.value || completing.value) return;

    completing.value = true;

    try {
        const response = await axios.post(route('gold-stock-count.complete'));
        const payload = response.data || {};

        session.value = {
            ...(session.value || {}),
            ...(payload.session || {}),
        };

        toast.add({
            severity: 'success',
            summary: 'Count Complete',
            detail: 'Gold stock count marked complete for today.',
            life: 3000,
        });
    } catch (error) {
        const message = error?.response?.data?.message || 'Unable to complete gold stock count.';
        toast.add({
            severity: 'error',
            summary: 'Complete Failed',
            detail: message,
            life: 3000,
        });
    } finally {
        completing.value = false;
    }
};
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <div class="border-b border-surface-200 bg-white px-5 py-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-2xl font-semibold tracking-tight text-surface-900">Gold Stock Count</h1>
                            <Tag value="Night Count" severity="warn" />
                            <Tag v-if="session?.status === 'COMPLETED'" value="Completed" severity="success" />
                        </div>
                        <p class="mt-1 text-sm text-surface-500">Scan unsold gold stock before close day and compare counted items with system stock.</p>
                    </div>

                    <Button
                        label="Mark Complete"
                        icon="pi pi-check"
                        :disabled="!isCompleteReady"
                        :loading="completing"
                        @click="markComplete"
                        class="!w-auto shrink-0 whitespace-nowrap"
                    />
                </div>
            </div>

            <div v-if="!dayOpen" class="border border-amber-200 bg-amber-50 px-5 py-4">
                <p class="text-sm font-medium text-amber-900">Open day required</p>
                <p class="mt-1 text-sm text-amber-800">Open today’s shop day before scanning gold stock count.</p>
            </div>

            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Expected Items</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ summary?.expected_items || 0 }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Counted Items</p>
                    <p class="mt-2 text-2xl font-semibold text-emerald-600">{{ summary?.counted_items || 0 }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Remaining</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-600">{{ summary?.remaining_items || 0 }}</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Match %</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ summary?.match_percentage || 0 }}%</p>
                </div>
                <div class="border border-surface-200 bg-white px-5 py-4">
                    <p class="text-sm text-surface-500">Counted Net Weight</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ formatWeight(summary?.counted_net_weight) }}</p>
                    <p class="mt-1 text-xs text-surface-500">Expected: {{ formatWeight(summary?.expected_net_weight) }}</p>
                </div>
            </div>

            <div v-if="dayOpen" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_24rem]">
                <div class="border border-surface-200 bg-white px-5 py-5">
                    <div class="flex items-start gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center border border-surface-200 bg-surface-50 text-surface-600">
                            <ScanLine class="h-5 w-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-surface-900">Scanner</p>
                            <p class="mt-1 text-xs text-surface-500">Scan gold barcode and it will be added to counted list once only.</p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                        <div class="relative min-w-0 flex-1">
                            <i class="pi pi-barcode pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-surface-400" />
                            <InputText v-model="scanInput" placeholder="Scan barcode or enter gold product code..." class="w-full !pl-10" @keydown.enter.prevent="scanBarcode" />
                        </div>
                        <Button label="Count Item" icon="pi pi-plus" :loading="scanning" @click="scanBarcode" class="!w-full sm:!w-auto shrink-0 whitespace-nowrap" />
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-2 text-xs text-surface-500">
                        <span class="border border-surface-200 bg-surface-50 px-2 py-1">Example: G00025</span>
                        <span>Duplicate scan will be blocked</span>
                    </div>
                </div>

                <div class="border border-surface-200 bg-white px-5 py-5">
                    <p class="text-sm font-medium text-surface-900">Count Session</p>
                    <div class="mt-4 space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-surface-500">Register Date</span>
                            <span class="font-medium text-surface-900">{{ summary?.register_date || '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-surface-500">Status</span>
                            <Tag :value="session?.status || 'Not Started'" :severity="session?.status === 'COMPLETED' ? 'success' : 'warn'" />
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-surface-500">Started</span>
                            <span class="font-medium text-surface-900">{{ formatDateTime(session?.started_at) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-surface-500">Completed</span>
                            <span class="font-medium text-surface-900">{{ formatDateTime(session?.completed_at) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="dayOpen" class="grid gap-4 xl:grid-cols-2">
                <div class="border border-surface-200 bg-white">
                    <div class="border-b border-surface-200 px-5 py-4">
                        <h3 class="text-base font-semibold text-surface-900">Recently Counted</h3>
                        <p class="mt-1 text-sm text-surface-500">Latest scanned gold items in this count session.</p>
                    </div>

                    <div class="p-4">
                        <DataTable :value="recentCounted" stripedRows rowHover tableStyle="min-width: 36rem">
                            <template #empty>
                                <div class="py-12 text-center text-surface-500">No gold items counted yet</div>
                            </template>

                            <Column field="barcode" header="Barcode" style="width: 140px" />
                            <Column header="Product" style="min-width: 200px">
                                <template #body="{ data }">
                                    <div>
                                        <p class="font-medium text-surface-900">{{ data.name }}</p>
                                        <p class="mt-1 text-xs text-surface-500">{{ data.category || '—' }}</p>
                                    </div>
                                </template>
                            </Column>
                            <Column header="Scanned" style="width: 160px">
                                <template #body="{ data }">
                                    <div class="text-sm font-medium text-surface-900">{{ formatDateTime(data.scanned_at) }}</div>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>

                <div class="border border-surface-200 bg-white">
                    <div class="border-b border-surface-200 px-5 py-4">
                        <h3 class="text-base font-semibold text-surface-900">Missing Gold Stock</h3>
                        <p class="mt-1 text-sm text-surface-500">Open stock still not counted in this session. Showing first 100 items.</p>
                    </div>

                    <div class="p-4">
                        <DataTable :value="missingProducts" stripedRows rowHover tableStyle="min-width: 36rem">
                            <template #empty>
                                <div class="py-12 text-center text-emerald-600">All open gold stock counted</div>
                            </template>

                            <Column field="barcode" header="Barcode" style="width: 140px" />
                            <Column header="Product" style="min-width: 200px">
                                <template #body="{ data }">
                                    <div>
                                        <p class="font-medium text-surface-900">{{ data.name }}</p>
                                        <p class="mt-1 text-xs text-surface-500">{{ data.category || '—' }}</p>
                                    </div>
                                </template>
                            </Column>
                            <Column header="Net Weight" style="width: 140px">
                                <template #body="{ data }">
                                    <span class="font-medium text-surface-900">{{ formatWeight(data.net_weight) }}</span>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
