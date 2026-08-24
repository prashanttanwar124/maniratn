<?php

namespace App\Services\Ai\Actions;

use App\Services\Ai\Contracts\AiActionInterface;

class AddProductAction implements AiActionInterface
{
    public function handle(array $args): array
    {
        $name = $args['name'] ?? 'Gold Ornament';
        $weight = floatval($args['weight'] ?? 0);
        $metal = strtoupper($args['metal'] ?? 'GOLD');
        $purityName = $args['purity'] ?? ($metal === 'GOLD' ? '22K' : '92.5');
        $catName = $args['category'] ?? 'General';
        $makingCharge = floatval($args['making_charge_per_gram'] ?? 450);

        // ALWAYS RETURN DRAFT PREVIEW FOR USER VERIFICATION FIRST
        return [
            'found' => true,
            'is_preview' => true,
            'action_type' => 'ADD_PRODUCT',
            'name' => $name,
            'metal' => $metal,
            'purity' => $purityName,
            'weight' => $weight,
            'category' => $catName,
            'making_charge_per_gm' => $makingCharge,
            'status' => 'CONFIRMATION_REQUIRED',
        ];
    }
}
