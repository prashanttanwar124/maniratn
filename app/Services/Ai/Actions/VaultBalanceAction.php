<?php

namespace App\Services\Ai\Actions;

use App\Models\Vault;
use App\Services\Ai\Contracts\AiActionInterface;

class VaultBalanceAction implements AiActionInterface
{
    public function handle(array $args): array
    {
        $cash = Vault::whereIn('type', ['CASH', 'cash'])->sum('balance');
        $gold = Vault::whereIn('type', ['GOLD', 'gold'])->sum('balance');
        $silver = Vault::whereIn('type', ['SILVER', 'silver'])->sum('balance');
        $bank = Vault::whereIn('type', ['BANK', 'bank'])->sum('balance');

        return [
            'cash_in_hand' => '₹' . number_format($cash, 2),
            'gold_in_vault' => number_format($gold, 3) . ' g',
            'silver_in_vault' => number_format($silver, 3) . ' g',
            'bank_balance' => '₹' . number_format($bank, 2),
            'status' => 'LIVE_ERP_VAULT',
        ];
    }
}
