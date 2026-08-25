<script setup lang="ts">
import { GOLD_MAKING_CHARGE_TYPE_OPTIONS, type GoldProductFormModel } from '@/domain/products/goldProductForm';
import { ImagePlus } from 'lucide-vue-next';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import { computed } from 'vue';

interface MasterOption {
    id: number;
    name?: string;
    company_name?: string;
}

const props = withDefaults(
    defineProps<{
        model: GoldProductFormModel;
        errors?: Record<string, string | undefined>;
        categories: MasterOption[];
        purities: MasterOption[];
        suppliers: MasterOption[];
        counters: MasterOption[];
        imagePreview?: string | null;
        showWeights?: boolean;
        autofocusName?: boolean;
    }>(),
    {
        errors: () => ({}),
        imagePreview: null,
        showWeights: true,
        autofocusName: false,
    },
);

const emit = defineEmits<{
    (event: 'image-selected', file: File): void;
    (event: 'field-blur'): void;
    (event: 'update-field', update: { field: keyof GoldProductFormModel; value: GoldProductFormModel[keyof GoldProductFormModel] }): void;
}>();

const fieldModel = <Key extends keyof GoldProductFormModel>(field: Key) =>
    computed({
        get: () => props.model[field],
        set: (value: GoldProductFormModel[Key]) => emit('update-field', { field, value }),
    });

const name = fieldModel('name');
const supplierId = fieldModel('supplier_id');
const counterId = fieldModel('counter_id');
const categoryId = fieldModel('category_id');
const purityId = fieldModel('purity_id');
const grossWeight = fieldModel('gross_weight');
const netWeight = fieldModel('net_weight');
const makingCharge = fieldModel('making_charge');
const makingChargeType = fieldModel('making_charge_type');

const selectImage = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (file) emit('image-selected', file);
};
</script>

<template>
    <div class="space-y-4">
        <div class="rounded-lg border border-surface-200 bg-surface-50 p-3">
            <label class="mb-2 block text-xs font-medium text-surface-700">Product photo <span class="font-normal text-surface-400">(optional, max 2 MB)</span></label>
            <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-dashed border-surface-300 bg-white px-3 py-3 hover:border-[#c08f34] hover:bg-amber-50/40">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-[#b07b24]"><ImagePlus class="h-4 w-4" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-xs font-medium text-surface-700">{{ model.image?.name || 'Choose product photo' }}</span>
                    <span class="mt-0.5 block text-[10px] text-surface-400">JPG, PNG or WEBP</span>
                </span>
                <img v-if="imagePreview" :src="imagePreview" alt="Product preview" class="h-12 w-12 shrink-0 rounded-md border border-surface-200 object-cover" />
                <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="selectImage" />
            </label>
            <small v-if="errors.image" class="mt-1 block text-xs text-red-600">{{ errors.image }}</small>
        </div>

        <div>
            <label class="mb-1.5 block text-xs font-medium text-surface-700">Product name <span class="text-red-500">*</span></label>
            <InputText v-model="name" :autofocus="autofocusName" class="w-full" :invalid="Boolean(errors.name)" @blur="emit('field-blur')" />
            <small v-if="errors.name" class="mt-1 block text-xs text-red-600">{{ errors.name }}</small>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-xs font-medium text-surface-700">Supplier <span class="text-red-500">*</span></label>
                <Select
                    v-model="supplierId"
                    :options="suppliers"
                    option-label="company_name"
                    option-value="id"
                    filter
                    placeholder="Select supplier"
                    class="w-full"
                    :invalid="Boolean(errors.supplier_id)"
                    @change="emit('field-blur')"
                />
                <small v-if="errors.supplier_id" class="mt-1 block text-xs text-red-600">{{ errors.supplier_id }}</small>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-medium text-surface-700">Counter <span class="font-normal text-surface-400">(optional)</span></label>
                <Select
                    v-model="counterId"
                    :options="counters"
                    option-label="name"
                    option-value="id"
                    filter
                    show-clear
                    placeholder="Select counter"
                    class="w-full"
                    :invalid="Boolean(errors.counter_id)"
                />
                <small v-if="errors.counter_id" class="mt-1 block text-xs text-red-600">{{ errors.counter_id }}</small>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-medium text-surface-700">Category <span class="text-red-500">*</span></label>
                <Select
                    v-model="categoryId"
                    :options="categories"
                    option-label="name"
                    option-value="id"
                    filter
                    placeholder="Select category"
                    class="w-full"
                    :invalid="Boolean(errors.category_id)"
                    @change="emit('field-blur')"
                />
                <small v-if="errors.category_id" class="mt-1 block text-xs text-red-600">{{ errors.category_id }}</small>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-medium text-surface-700">Purity <span class="text-red-500">*</span></label>
                <Select
                    v-model="purityId"
                    :options="purities"
                    option-label="name"
                    option-value="id"
                    filter
                    placeholder="Select purity"
                    class="w-full"
                    :invalid="Boolean(errors.purity_id)"
                    @change="emit('field-blur')"
                />
                <small v-if="errors.purity_id" class="mt-1 block text-xs text-red-600">{{ errors.purity_id }}</small>
            </div>
        </div>

        <div v-if="showWeights" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-xs font-medium text-surface-700">Gross weight <span class="text-red-500">*</span></label>
                <InputNumber
                    v-model="grossWeight"
                    :min="0.001"
                    :min-fraction-digits="3"
                    :max-fraction-digits="3"
                    suffix=" g"
                    class="w-full"
                    input-class="w-full"
                    :invalid="Boolean(errors.gross_weight)"
                    @blur="emit('field-blur')"
                />
                <small v-if="errors.gross_weight" class="mt-1 block text-xs text-red-600">{{ errors.gross_weight }}</small>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-medium text-surface-700">Net weight <span class="text-red-500">*</span></label>
                <InputNumber
                    v-model="netWeight"
                    :min="0.001"
                    :min-fraction-digits="3"
                    :max-fraction-digits="3"
                    suffix=" g"
                    class="w-full"
                    input-class="w-full"
                    :invalid="Boolean(errors.net_weight)"
                    @blur="emit('field-blur')"
                />
                <small v-if="errors.net_weight" class="mt-1 block text-xs text-red-600">{{ errors.net_weight }}</small>
            </div>
        </div>

        <div>
            <label class="mb-1.5 block text-xs font-medium text-surface-700">Making charge <span class="text-red-500">*</span></label>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_180px]">
                <InputNumber
                    v-model="makingCharge"
                    mode="decimal"
                    :prefix="model.making_charge_type !== 'percentage' ? '₹ ' : undefined"
                    :suffix="model.making_charge_type === 'percentage' ? ' %' : model.making_charge_type === 'per_gram' ? ' /g' : undefined"
                    :min="0"
                    :max="model.making_charge_type === 'percentage' ? 100 : undefined"
                    :min-fraction-digits="model.making_charge_type === 'percentage' ? 2 : 0"
                    :max-fraction-digits="2"
                    class="w-full"
                    input-class="w-full"
                    :invalid="Boolean(errors.making_charge)"
                    @blur="emit('field-blur')"
                />
                <Select v-model="makingChargeType" :options="GOLD_MAKING_CHARGE_TYPE_OPTIONS" option-label="label" option-value="value" class="w-full" @change="emit('field-blur')" />
            </div>
            <small class="mt-1 block text-[11px] text-surface-500">
                {{
                    model.making_charge_type === 'percentage' ? 'Example: enter 10 for 10% making.' : model.making_charge_type === 'flat' ? 'Fixed lump-sum making charge.' : 'Making charge per gram.'
                }}
            </small>
            <small v-if="errors.making_charge" class="mt-1 block text-xs text-red-600">{{ errors.making_charge }}</small>
            <small v-if="errors.making_charge_type" class="mt-1 block text-xs text-red-600">{{ errors.making_charge_type }}</small>
        </div>
    </div>
</template>
