<script setup lang="ts">
import { Search, PackageCheck } from 'lucide-vue-next';

interface ActionItem {
    tool: string;
    args: Record<string, any>;
    result: Record<string, any>;
}

defineProps<{
    action: ActionItem;
}>();
</script>

<template>
    <div class="border border-slate-300 bg-white shadow-xs rounded-none overflow-hidden my-2">
        <div class="px-3 py-2 bg-[#1c3633] text-white flex items-center justify-between border-b-2 border-b-[#c08f34] rounded-none">
            <div class="flex items-center gap-1.5">
                <Search class="w-3.5 h-3.5 text-[#c08f34]" />
                <span class="font-serif text-xs font-bold tracking-wide uppercase">Live Inventory Stock</span>
            </div>
            <span class="text-[10px] text-[#c08f34] font-mono font-bold">{{ action.result.total_items }} Available</span>
        </div>

        <div v-if="action.result.items && action.result.items.length > 0" class="divide-y divide-slate-200">
            <div
                v-for="item in action.result.items"
                :key="item.id"
                class="p-2.5 hover:bg-slate-50 flex items-center justify-between text-xs transition-colors"
            >
                <div>
                    <div class="font-bold text-slate-900">{{ item.name }}</div>
                    <div class="text-[10.5px] text-slate-500">
                        {{ item.category }} • {{ item.purity }} • Making: ₹{{ item.making_charge }}/g
                    </div>
                </div>
                <div class="text-right">
                    <div class="font-mono font-bold text-xs text-[#1c3633]">{{ item.weight }} g</div>
                    <span class="font-mono text-[10px] px-1.5 py-0.5 bg-slate-100 text-slate-700 font-bold rounded-none">
                        {{ item.barcode }}
                    </span>
                </div>
            </div>
        </div>

        <div v-else class="p-3 text-center text-xs text-slate-500 italic">
            Koi item nahi mila.
        </div>
    </div>
</template>
