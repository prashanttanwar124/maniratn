<?php

namespace App\Support\Inventory;

use Illuminate\Validation\Rule;

final class GoldProductRules
{
    public static function rules(bool $requireWeights = true, ?string $makingChargeType = null): array
    {
        $weightPresence = $requireWeights ? 'required' : 'nullable';
        $makingChargeRules = ['required', 'numeric', 'min:0'];

        if ($makingChargeType === 'percentage') {
            $makingChargeRules[] = 'max:100';
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('metal_type', 'GOLD'))],
            'purity_id' => ['required', 'exists:purities,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'counter_id' => ['nullable', 'exists:counters,id'],
            'gross_weight' => [$weightPresence, 'numeric', 'min:0.001'],
            'net_weight' => [$weightPresence, 'numeric', 'min:0.001', 'lte:gross_weight'],
            'making_charge' => $makingChargeRules,
            'making_charge_type' => ['nullable', 'string', 'in:percentage,flat,per_gram'],
        ];
    }
}
