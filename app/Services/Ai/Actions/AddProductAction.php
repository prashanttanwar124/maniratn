<?php

namespace App\Services\Ai\Actions;

use App\Services\Ai\Contracts\AiActionInterface;

class AddProductAction implements AiActionInterface
{
    public function handle(array $args): array
    {
        $name = $args['name'] ?? 'Gold Ornament';
        $weight = floatval($args['weight'] ?? 0);
        $quantity = max(1, intval($args['quantity'] ?? ($args['qty'] ?? 1)));
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
            'quantity' => $quantity,
            'total_weight' => round($weight * $quantity, 3),
            'category' => $catName,
            'making_charge_per_gm' => $makingCharge,
            'status' => 'CONFIRMATION_REQUIRED',
        ];
    }
}
