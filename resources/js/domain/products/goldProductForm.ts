export type GoldMakingChargeType = 'percentage' | 'flat' | 'per_gram';

export interface GoldProductFormModel {
    name: string;
    category_id: number | null;
    purity_id: number | null;
    supplier_id: number | null;
    counter_id: number | null;
    gross_weight: number | null;
    net_weight: number | null;
    making_charge: number | null;
    making_charge_type: GoldMakingChargeType;
    image?: File | null;
}

export interface GoldProductValidationOptions {
    includeWeights?: boolean;
}

export const GOLD_MAKING_CHARGE_TYPE_OPTIONS = [
    { label: '% (Percentage)', value: 'percentage' },
    { label: '₹ Flat (Lump sum)', value: 'flat' },
    { label: '₹/g (Per gram)', value: 'per_gram' },
];

export const validateGoldProduct = (model: GoldProductFormModel, options: GoldProductValidationOptions = {}): Record<string, string> => {
    const includeWeights = options.includeWeights ?? true;
    const errors: Record<string, string> = {};

    if (!String(model.name || '').trim()) errors.name = 'Product name is required.';
    if (!model.supplier_id) errors.supplier_id = 'Supplier is required.';
    if (!model.category_id) errors.category_id = 'Category is required.';
    if (!model.purity_id) errors.purity_id = 'Purity is required.';

    if (includeWeights) {
        if (!model.gross_weight || Number(model.gross_weight) < 0.001) errors.gross_weight = 'Gross weight must be at least 0.001 g.';
        if (!model.net_weight || Number(model.net_weight) < 0.001) errors.net_weight = 'Net weight must be at least 0.001 g.';
        if (model.gross_weight && model.net_weight && Number(model.net_weight) > Number(model.gross_weight)) {
            errors.net_weight = 'Net weight cannot exceed gross weight.';
        }
    }

    if (model.making_charge === null || model.making_charge === undefined || Number(model.making_charge) < 0) {
        errors.making_charge = 'Making charge must be zero or more.';
    } else if (model.making_charge_type === 'percentage' && Number(model.making_charge) > 100) {
        errors.making_charge = 'Percentage cannot exceed 100.';
    }

    if (model.image && model.image.size > 2_000_000) errors.image = 'Image must not be larger than 2 MB.';

    return errors;
};
