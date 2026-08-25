<script setup lang="ts">
import GoldProductFields from '@/components/products/GoldProductFields.vue';
import { useProductDraftTray, type ProductDraftSource } from '@/composables/useProductDraftTray';
import { validateGoldProduct, type GoldProductFormModel } from '@/domain/products/goldProductForm';
import axios from 'axios';
import { AlertTriangle, Check, CheckCircle2, ChevronDown, ChevronUp, ImagePlus, Layers3, PackagePlus, Save, Sparkles, Trash2, X } from 'lucide-vue-next';
import Button from 'primevue/button';
import Drawer from 'primevue/drawer';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import { computed, ref, watch } from 'vue';

interface Option {
    id: number;
    name?: string;
    company_name?: string;
    metal_type?: string;
    type?: string;
}

interface ProductDraft {
    id: string;
    source: ProductDraftSource;
    expanded: boolean;
    saved: boolean;
    saving: boolean;
    name: string;
    metal: 'GOLD' | 'SILVER';
    category_id: number | null;
    purity_id: number | null;
    supplier_id: number | null;
    counter_id: number | null;
    gross_weight: number | null;
    net_weight: number | null;
    quantity: number;
    making_charge: number;
    making_charge_type: 'percentage' | 'flat' | 'per_gram';
    categoryHint: string;
    purityHint: string;
    image: File | null;
    imagePreview: string | null;
    errors: Record<string, string>;
    result?: Record<string, any>;
}

const { visible, sources, close } = useProductDraftTray();
const drafts = ref<ProductDraft[]>([]);
const categories = ref<Option[]>([]);
const purities = ref<Option[]>([]);
const suppliers = ref<Option[]>([]);
const counters = ref<Option[]>([]);
const optionsLoaded = ref(false);
const loadingOptions = ref(false);
const optionsError = ref('');
const saveError = ref('');
const savingAll = ref(false);

const metalOptions = [
    { label: 'Gold', value: 'GOLD' },
    { label: 'Silver', value: 'SILVER' },
];

const makingTypeOptions = [
    { label: '₹ per gram', value: 'per_gram' },
    { label: 'Percentage', value: 'percentage' },
    { label: 'Flat amount', value: 'flat' },
];

const normalise = (value: unknown) =>
    String(value ?? '')
        .toLowerCase()
        .replace(/[^a-z0-9]/g, '');

const matchOption = (list: Option[], hint: string, label: (option: Option) => string) => {
    const needle = normalise(hint);
    if (!needle) return null;

    const exact = list.find((option) => normalise(label(option)) === needle);
    if (exact) return exact.id;

    return (
        list.find((option) => {
            const candidate = normalise(label(option));
            return candidate.includes(needle) || needle.includes(candidate);
        })?.id ?? null
    );
};

const compatibleSuppliers = (metal: 'GOLD' | 'SILVER') => {
    return suppliers.value.filter((supplier) => {
        const type = String(supplier.type || '').toUpperCase();
        return !type || type === metal || type === 'JEWELLERY' || type === 'BOTH';
    });
};

const categoryOptions = (draft: ProductDraft) => categories.value.filter((category) => String(category.metal_type).toUpperCase() === draft.metal);

const supplierOptions = (draft: ProductDraft) => {
    const compatible = compatibleSuppliers(draft.metal);
    return compatible.length > 0 ? compatible : suppliers.value;
};

const hydrateMasterData = (draft: ProductDraft) => {
    const matchingCategories = categoryOptions(draft);
    if (!matchingCategories.some((item) => item.id === draft.category_id)) {
        draft.category_id = matchOption(matchingCategories, draft.categoryHint, (item) => item.name || '');
    }

    if (draft.metal === 'GOLD' && !purities.value.some((item) => item.id === draft.purity_id)) {
        draft.purity_id = matchOption(purities.value, draft.purityHint, (item) => item.name || '');
    }

    const matchingSuppliers = supplierOptions(draft);
    if (!draft.supplier_id && matchingSuppliers.length === 1) {
        draft.supplier_id = matchingSuppliers[0].id;
    }
};

const loadOptions = async () => {
    if (optionsLoaded.value || loadingOptions.value) return;

    loadingOptions.value = true;
    optionsError.value = '';
    try {
        const response = await axios.get('/api/ai/copilot/product-drafts/options');
        categories.value = response.data.categories || [];
        purities.value = response.data.purities || [];
        suppliers.value = response.data.suppliers || [];
        counters.value = response.data.counters || [];
        optionsLoaded.value = true;
        drafts.value.forEach(hydrateMasterData);
    } catch (error: any) {
        optionsError.value = error.response?.status === 403 ? 'Aapke account ko product stock manage karne ki permission nahi hai.' : 'Product master data load nahi ho saka. Dobara try karein.';
    } finally {
        loadingOptions.value = false;
    }
};

const createDraft = (source: ProductDraftSource): ProductDraft => {
    const result = source.action.result || {};
    const metal = String(result.metal || '').toUpperCase() === 'SILVER' ? 'SILVER' : 'GOLD';
    const weight = Number(result.weight ?? result.net_weight ?? result.gross_weight ?? 0);
    const makingType = result.making_charge_type === 'lump_sum' ? 'flat' : result.making_charge_type;

    return {
        id: `${source.actionIndex}-${source.messageId}`.slice(0, 100),
        source,
        expanded: true,
        saved: false,
        saving: false,
        name: String(result.name || '').trim(),
        metal,
        category_id: Number(result.category_id) || null,
        purity_id: Number(result.purity_id) || null,
        supplier_id: Number(result.supplier_id) || null,
        counter_id: Number(result.counter_id) || null,
        gross_weight: weight > 0 ? weight : null,
        net_weight: weight > 0 ? weight : null,
        quantity: Math.max(1, Number(result.quantity || 1)),
        making_charge: Math.max(0, Number(result.making_charge_per_gm ?? result.making_charge ?? 0)),
        making_charge_type: ['percentage', 'flat', 'per_gram'].includes(makingType) ? makingType : 'per_gram',
        categoryHint: String(result.category || ''),
        purityHint: String(result.purity || ''),
        image: null,
        imagePreview: null,
        errors: {},
    };
};

watch(
    sources,
    (nextSources) => {
        drafts.value = nextSources.map(createDraft);
        saveError.value = '';
        if (optionsLoaded.value) {
            drafts.value.forEach(hydrateMasterData);
        }
    },
    { immediate: true },
);

watch(visible, (isVisible) => {
    if (isVisible) loadOptions();
});

const validateDraft = (draft: ProductDraft) => {
    const errors: Record<string, string> = draft.metal === 'GOLD' ? validateGoldProduct(draft as unknown as GoldProductFormModel) : {};

    if (draft.metal === 'SILVER') {
        if (!draft.name.trim()) errors.name = 'Product name required hai.';
        if (!draft.category_id) errors.category_id = 'Category select karein.';
        if (!draft.supplier_id) errors.supplier_id = 'Supplier select karein.';
        if (!draft.gross_weight || draft.gross_weight < 0.001) errors.gross_weight = 'Gross weight 0 se zyada hona chahiye.';
        if (!draft.net_weight || draft.net_weight < 0.001) errors.net_weight = 'Net weight 0 se zyada hona chahiye.';
        if (draft.net_weight && draft.gross_weight && draft.net_weight > draft.gross_weight) errors.net_weight = 'Net weight gross weight se zyada nahi ho sakta.';
        if (draft.making_charge < 0) errors.making_charge = 'Making charge negative nahi ho sakta.';
        if (draft.making_charge_type === 'percentage' && draft.making_charge > 100) errors.making_charge = 'Percentage 100 se zyada nahi ho sakta.';
    }

    if (!Number.isInteger(Number(draft.quantity)) || draft.quantity < 1 || draft.quantity > 10) errors.quantity = 'Quantity 1 se 10 ke beech rakhein.';

    draft.errors = errors;
    return Object.keys(errors).length === 0;
};

const pendingDrafts = computed(() => drafts.value.filter((draft) => !draft.saved));
const savedCount = computed(() => drafts.value.filter((draft) => draft.saved).length);
const readyDrafts = computed(() =>
    pendingDrafts.value.filter((draft) => {
        const snapshot = { ...draft, errors: { ...draft.errors } } as ProductDraft;
        return validateDraft(snapshot);
    }),
);

const updateMetal = (draft: ProductDraft) => {
    draft.category_id = null;
    draft.purity_id = null;
    draft.supplier_id = null;
    hydrateMasterData(draft);
    validateDraft(draft);
};

const updateDraftField = (draft: ProductDraft, update: { field: keyof GoldProductFormModel; value: GoldProductFormModel[keyof GoldProductFormModel] }) => {
    (draft as any)[update.field] = update.value;
    delete draft.errors[update.field];
};

const pickImage = (draft: ProductDraft, file: File) => {
    if (draft.imagePreview) URL.revokeObjectURL(draft.imagePreview);
    draft.image = file;
    draft.imagePreview = URL.createObjectURL(file);
};

const pickImageEvent = (draft: ProductDraft, event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (file) pickImage(draft, file);
};

const appendItem = (formData: FormData, draft: ProductDraft, index: number) => {
    const prefix = `items[${index}]`;
    formData.append(`${prefix}[draft_id]`, draft.id);
    formData.append(`${prefix}[action_index]`, String(draft.source.actionIndex));
    formData.append(`${prefix}[name]`, draft.name.trim());
    formData.append(`${prefix}[metal]`, draft.metal);
    formData.append(`${prefix}[category_id]`, String(draft.category_id));
    if (draft.metal === 'GOLD' && draft.purity_id) formData.append(`${prefix}[purity_id]`, String(draft.purity_id));
    formData.append(`${prefix}[supplier_id]`, String(draft.supplier_id));
    if (draft.counter_id) formData.append(`${prefix}[counter_id]`, String(draft.counter_id));
    formData.append(`${prefix}[gross_weight]`, String(draft.gross_weight));
    formData.append(`${prefix}[net_weight]`, String(draft.net_weight));
    formData.append(`${prefix}[quantity]`, String(draft.quantity));
    formData.append(`${prefix}[making_charge]`, String(draft.making_charge));
    formData.append(`${prefix}[making_charge_type]`, draft.making_charge_type);
    if (draft.image) formData.append(`${prefix}[image]`, draft.image);
};

const applyServerErrors = (selected: ProductDraft[], errors: Record<string, string[] | string>) => {
    Object.entries(errors).forEach(([key, messages]) => {
        const match = key.match(/^items\.(\d+)\.(.+)$/);
        if (!match) return;
        const draft = selected[Number(match[1])];
        if (draft) draft.errors[match[2]] = Array.isArray(messages) ? messages[0] : messages;
    });
};

const saveDrafts = async (selected: ProductDraft[]) => {
    saveError.value = '';
    if (selected.length === 0) return;

    const invalid = selected.filter((draft) => !validateDraft(draft));
    if (invalid.length > 0) {
        invalid[0].expanded = true;
        saveError.value = 'Highlighted fields complete karke dobara save karein.';
        return;
    }

    const formData = new FormData();
    formData.append('message_id', selected[0].source.messageId);
    selected.forEach((draft, index) => {
        draft.saving = true;
        draft.errors = {};
        appendItem(formData, draft, index);
    });

    try {
        const response = await axios.post('/api/ai/copilot/product-drafts', formData);
        const results: Record<string, any>[] = response.data.items || [];

        results.forEach((result) => {
            const draft = selected.find((item) => item.id === result.draft_id);
            if (!draft) return;
            draft.saved = true;
            draft.expanded = false;
            draft.result = result;
            draft.source.action.result = {
                ...draft.source.action.result,
                ...result,
                is_preview: false,
                status: 'IN_STOCK_REAL_DB',
            };
        });
    } catch (error: any) {
        if (error.response?.status === 422 && error.response?.data?.errors) {
            applyServerErrors(selected, error.response.data.errors);
            saveError.value = 'Kuch details valid nahi hain. Highlighted fields check karein.';
        } else if (error.response?.data?.error === 'DAY_NOT_OPEN') {
            saveError.value = 'Showroom day open nahi hai. Pehle day open karein.';
        } else {
            saveError.value = error.response?.data?.message || 'Products save nahi ho sake. Dobara try karein.';
        }
    } finally {
        selected.forEach((draft) => {
            draft.saving = false;
        });
    }
};

const saveOne = (draft: ProductDraft) => saveDrafts([draft]);

const saveAllReady = async () => {
    pendingDrafts.value.forEach(validateDraft);
    const selected = pendingDrafts.value.filter((draft) => Object.keys(draft.errors).length === 0);
    if (selected.length === 0) {
        saveError.value = 'Save karne se pehle required details complete karein.';
        return;
    }

    savingAll.value = true;
    await saveDrafts(selected);
    savingAll.value = false;
};

const discardDraft = async (draft: ProductDraft) => {
    draft.source.action.result = {
        ...draft.source.action.result,
        is_preview: false,
        is_discarded: true,
        status: 'DISCARDED',
    };
    drafts.value = drafts.value.filter((item) => item.id !== draft.id);

    try {
        await axios.post('/api/ai/copilot/discard-action', {
            message_id: draft.source.messageId,
            action_tool: 'add_product',
        });
    } catch {
        // The local draft remains discarded even if optional history sync fails.
    }

    if (drafts.value.length === 0) close();
};
</script>

<template>
    <Drawer
        :visible="visible"
        position="right"
        class="!w-full !border-l !border-surface-200 !p-0 font-sans !shadow-2xl md:!w-[720px] xl:!w-[780px]"
        :modal="true"
        :dismissable="true"
        :show-close-icon="false"
        :pt="{
            root: { class: '!p-0 !rounded-none !bg-[#f7f8f8]' },
            header: { class: '!hidden !p-0' },
            content: { class: '!p-0 !overflow-hidden flex flex-col h-full' },
        }"
        @update:visible="$event ? undefined : close()"
    >
        <div class="flex h-full min-h-0 flex-col">
            <header class="shrink-0 border-b border-surface-200 bg-white px-4 py-4 sm:px-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex min-w-0 items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#1c3633] text-[#e5c278] shadow-sm">
                            <PackagePlus class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-semibold tracking-tight text-[#172b29]">Product Draft Tray</h2>
                                <span class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-800">
                                    <Sparkles class="h-3 w-3" /> AI prepared
                                </span>
                            </div>
                            <p class="mt-1 text-xs leading-5 text-surface-500">Current screen wahi rahegi. Details verify karke individual ya bulk save karein.</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-surface-200 text-surface-500 hover:bg-surface-100 hover:text-surface-800"
                        aria-label="Close product draft tray"
                        @click="close"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="mt-4 grid grid-cols-3 divide-x divide-surface-200 rounded-lg border border-surface-200 bg-surface-50 py-2 text-center">
                    <div>
                        <p class="text-lg font-semibold text-surface-900">{{ drafts.length }}</p>
                        <p class="text-[10px] font-medium tracking-wide text-surface-500 uppercase">Drafts</p>
                    </div>
                    <div>
                        <p class="text-lg font-semibold text-emerald-700">{{ readyDrafts.length }}</p>
                        <p class="text-[10px] font-medium tracking-wide text-surface-500 uppercase">Ready</p>
                    </div>
                    <div>
                        <p class="text-lg font-semibold text-[#b07b24]">{{ savedCount }}</p>
                        <p class="text-[10px] font-medium tracking-wide text-surface-500 uppercase">Saved</p>
                    </div>
                </div>
            </header>

            <main class="min-h-0 flex-1 overflow-y-auto px-3 py-4 sm:px-6">
                <div v-if="loadingOptions" class="flex min-h-48 flex-col items-center justify-center gap-3 text-center text-sm text-surface-500">
                    <span class="h-7 w-7 animate-spin rounded-full border-2 border-surface-200 border-t-[#b07b24]"></span>
                    Product master data load ho raha hai…
                </div>

                <div v-else-if="optionsError" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <div class="flex items-start gap-2">
                        <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" /><span>{{ optionsError }}</span>
                    </div>
                    <Button label="Retry" size="small" outlined severity="danger" class="mt-3" @click="loadOptions" />
                </div>

                <div v-else class="space-y-3">
                    <article
                        v-for="(draft, index) in drafts"
                        :key="draft.id"
                        :class="[
                            'overflow-hidden rounded-xl border bg-white shadow-sm',
                            draft.saved ? 'border-emerald-200' : Object.keys(draft.errors).length ? 'border-red-200' : 'border-surface-200',
                        ]"
                    >
                        <button type="button" class="flex w-full items-center gap-3 px-3 py-3 text-left sm:px-4" @click="draft.expanded = !draft.expanded">
                            <span :class="['flex h-9 w-9 shrink-0 items-center justify-center rounded-lg', draft.saved ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-[#b07b24]']">
                                <CheckCircle2 v-if="draft.saved" class="h-4 w-4" />
                                <Layers3 v-else class="h-4 w-4" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="truncate text-sm font-semibold text-surface-900">{{ draft.name || `Product ${index + 1}` }}</span>
                                    <span class="rounded-full bg-surface-100 px-2 py-0.5 text-[10px] font-semibold text-surface-600">{{ draft.metal === 'GOLD' ? 'Gold' : 'Silver' }}</span>
                                    <span v-if="draft.quantity > 1" class="rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700">{{ draft.quantity }} pcs</span>
                                </span>
                                <span class="mt-0.5 block text-[11px] text-surface-500">
                                    {{ draft.saved ? `Saved · ${draft.result?.barcode}` : `${draft.net_weight || 0} g per piece · ${draft.categoryHint || 'Category pending'}` }}
                                </span>
                            </span>
                            <span v-if="draft.saved" class="text-[11px] font-semibold text-emerald-700">Saved</span>
                            <span v-else-if="Object.keys(draft.errors).length" class="text-[11px] font-semibold text-red-600">Needs details</span>
                            <ChevronUp v-if="draft.expanded" class="h-4 w-4 shrink-0 text-surface-400" />
                            <ChevronDown v-else class="h-4 w-4 shrink-0 text-surface-400" />
                        </button>

                        <div v-if="draft.expanded && !draft.saved" class="border-t border-surface-200 px-3 py-4 sm:px-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div v-if="draft.metal === 'SILVER'" class="sm:col-span-2">
                                    <label class="mb-1.5 block text-xs font-medium text-surface-700">Product name <span class="text-red-500">*</span></label>
                                    <InputText v-model="draft.name" class="w-full" :invalid="Boolean(draft.errors.name)" @blur="validateDraft(draft)" />
                                    <small v-if="draft.errors.name" class="mt-1 block text-xs text-red-600">{{ draft.errors.name }}</small>
                                </div>

                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-surface-700">Metal <span class="text-red-500">*</span></label>
                                    <Select v-model="draft.metal" :options="metalOptions" option-label="label" option-value="value" class="w-full" @change="updateMetal(draft)" />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-surface-700">Quantity <span class="text-red-500">*</span></label>
                                    <InputNumber
                                        v-model="draft.quantity"
                                        :min="1"
                                        :max="10"
                                        :use-grouping="false"
                                        class="w-full"
                                        input-class="w-full"
                                        :invalid="Boolean(draft.errors.quantity)"
                                        @blur="validateDraft(draft)"
                                    />
                                    <small v-if="draft.errors.quantity" class="mt-1 block text-xs text-red-600">{{ draft.errors.quantity }}</small>
                                </div>

                                <div v-if="draft.metal === 'SILVER'">
                                    <label class="mb-1.5 block text-xs font-medium text-surface-700">Category <span class="text-red-500">*</span></label>
                                    <Select
                                        v-model="draft.category_id"
                                        :options="categoryOptions(draft)"
                                        option-label="name"
                                        option-value="id"
                                        filter
                                        placeholder="Select category"
                                        class="w-full"
                                        :invalid="Boolean(draft.errors.category_id)"
                                        @change="validateDraft(draft)"
                                    />
                                    <small v-if="draft.errors.category_id" class="mt-1 block text-xs text-red-600">{{ draft.errors.category_id }}</small>
                                </div>
                                <div v-if="draft.metal === 'SILVER'">
                                    <label class="mb-1.5 block text-xs font-medium text-surface-700">Supplier <span class="text-red-500">*</span></label>
                                    <Select
                                        v-model="draft.supplier_id"
                                        :options="supplierOptions(draft)"
                                        option-label="company_name"
                                        option-value="id"
                                        filter
                                        placeholder="Select supplier"
                                        class="w-full"
                                        :invalid="Boolean(draft.errors.supplier_id)"
                                        @change="validateDraft(draft)"
                                    />
                                    <small v-if="draft.errors.supplier_id" class="mt-1 block text-xs text-red-600">{{ draft.errors.supplier_id }}</small>
                                </div>
                                <div v-if="draft.metal === 'SILVER'">
                                    <label class="mb-1.5 block text-xs font-medium text-surface-700">Counter <span class="font-normal text-surface-400">(optional)</span></label>
                                    <Select v-model="draft.counter_id" :options="counters" option-label="name" option-value="id" filter show-clear placeholder="Select counter" class="w-full" />
                                </div>

                                <div v-if="draft.metal === 'SILVER'">
                                    <label class="mb-1.5 block text-xs font-medium text-surface-700">Gross weight / piece <span class="text-red-500">*</span></label>
                                    <InputNumber
                                        v-model="draft.gross_weight"
                                        :min="0.001"
                                        :min-fraction-digits="3"
                                        :max-fraction-digits="3"
                                        suffix=" g"
                                        class="w-full"
                                        input-class="w-full"
                                        :invalid="Boolean(draft.errors.gross_weight)"
                                        @blur="validateDraft(draft)"
                                    />
                                    <small v-if="draft.errors.gross_weight" class="mt-1 block text-xs text-red-600">{{ draft.errors.gross_weight }}</small>
                                </div>
                                <div v-if="draft.metal === 'SILVER'">
                                    <label class="mb-1.5 block text-xs font-medium text-surface-700">Net weight / piece <span class="text-red-500">*</span></label>
                                    <InputNumber
                                        v-model="draft.net_weight"
                                        :min="0.001"
                                        :min-fraction-digits="3"
                                        :max-fraction-digits="3"
                                        suffix=" g"
                                        class="w-full"
                                        input-class="w-full"
                                        :invalid="Boolean(draft.errors.net_weight)"
                                        @blur="validateDraft(draft)"
                                    />
                                    <small v-if="draft.errors.net_weight" class="mt-1 block text-xs text-red-600">{{ draft.errors.net_weight }}</small>
                                </div>

                                <div v-if="draft.metal === 'SILVER'">
                                    <label class="mb-1.5 block text-xs font-medium text-surface-700">Making charge type</label>
                                    <Select v-model="draft.making_charge_type" :options="makingTypeOptions" option-label="label" option-value="value" class="w-full" @change="validateDraft(draft)" />
                                </div>
                                <div v-if="draft.metal === 'SILVER'">
                                    <label class="mb-1.5 block text-xs font-medium text-surface-700">Making charge</label>
                                    <InputNumber
                                        v-model="draft.making_charge"
                                        :min="0"
                                        :max="draft.making_charge_type === 'percentage' ? 100 : undefined"
                                        :prefix="draft.making_charge_type === 'percentage' ? undefined : '₹ '"
                                        :suffix="draft.making_charge_type === 'percentage' ? '%' : undefined"
                                        class="w-full"
                                        input-class="w-full"
                                        :invalid="Boolean(draft.errors.making_charge)"
                                        @blur="validateDraft(draft)"
                                    />
                                    <small v-if="draft.errors.making_charge" class="mt-1 block text-xs text-red-600">{{ draft.errors.making_charge }}</small>
                                </div>

                                <div v-if="draft.metal === 'SILVER'" class="sm:col-span-2">
                                    <label class="mb-1.5 block text-xs font-medium text-surface-700">Product photo <span class="font-normal text-surface-400">(optional, max 2 MB)</span></label>
                                    <label
                                        class="flex cursor-pointer items-center gap-3 rounded-lg border border-dashed border-surface-300 bg-surface-50 px-3 py-3 hover:border-[#c08f34] hover:bg-amber-50/40"
                                    >
                                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-white text-[#b07b24] shadow-sm"><ImagePlus class="h-4 w-4" /></span>
                                        <span class="min-w-0 flex-1"
                                            ><span class="block truncate text-xs font-medium text-surface-700">{{ draft.image?.name || 'Photo choose karein' }}</span
                                            ><span class="mt-0.5 block text-[10px] text-surface-400">JPG, PNG or WEBP</span></span
                                        >
                                        <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="pickImageEvent(draft, $event)" />
                                    </label>
                                </div>
                            </div>

                            <GoldProductFields
                                v-if="draft.metal === 'GOLD'"
                                class="mt-4"
                                :model="draft"
                                :errors="draft.errors"
                                :categories="categoryOptions(draft)"
                                :purities="purities"
                                :suppliers="supplierOptions(draft)"
                                :counters="counters"
                                :image-preview="draft.imagePreview"
                                @image-selected="pickImage(draft, $event)"
                                @field-blur="validateDraft(draft)"
                                @update-field="updateDraftField(draft, $event)"
                            />

                            <div class="mt-4 flex flex-col-reverse gap-2 border-t border-surface-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium text-red-600 hover:bg-red-50"
                                    @click="discardDraft(draft)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />Discard
                                </button>
                                <Button label="Save this product" size="small" :loading="draft.saving" :disabled="savingAll" @click="saveOne(draft)"
                                    ><template #icon><Save class="h-3.5 w-3.5" /></template
                                ></Button>
                            </div>
                        </div>

                        <div v-else-if="draft.saved" class="flex items-start gap-2 border-t border-emerald-100 bg-emerald-50/60 px-4 py-3 text-xs text-emerald-800">
                            <Check class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                            <span
                                >{{ draft.quantity }} item(s) saved. Barcode: <strong>{{ draft.result?.barcode }}</strong></span
                            >
                        </div>
                    </article>

                    <div v-if="drafts.length === 0" class="flex min-h-56 flex-col items-center justify-center rounded-xl border border-dashed border-surface-300 bg-white p-6 text-center">
                        <CheckCircle2 class="h-9 w-9 text-emerald-600" />
                        <p class="mt-3 text-sm font-semibold text-surface-800">No pending product drafts</p>
                        <p class="mt-1 text-xs text-surface-500">AI chat se product add command dene par drafts yahan aayenge.</p>
                    </div>
                </div>
            </main>

            <footer class="shrink-0 border-t border-surface-200 bg-white px-4 py-3 sm:px-6">
                <div v-if="saveError" class="mb-3 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                    <AlertTriangle class="mt-0.5 h-3.5 w-3.5 shrink-0" /><span>{{ saveError }}</span>
                </div>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-[11px] text-surface-500">
                        <strong class="text-surface-700">{{ readyDrafts.length }}</strong> of {{ pendingDrafts.length }} pending drafts ready to save
                    </p>
                    <div class="flex gap-2">
                        <Button label="Close" severity="secondary" outlined class="flex-1 sm:flex-none" @click="close" />
                        <Button :label="`Save ready (${readyDrafts.length})`" :loading="savingAll" :disabled="readyDrafts.length === 0" class="flex-1 sm:flex-none" @click="saveAllReady"
                            ><template #icon><Check class="h-4 w-4" /></template
                        ></Button>
                    </div>
                </div>
            </footer>
        </div>
    </Drawer>
</template>
