<?php

namespace App\Services;

use App\Enums\VaultType;
use App\Models\DailyRegister;
use App\Models\Vault;
use App\Models\VaultMovement;

class DayOpeningService
{
    /**
     * Return the balances that the next day opening should verify.
     *
     * A closed register is authoritative. Before the first register exists,
     * preserve any already-audited/imported vault state instead of treating it
     * as an empty installation.
     */
    public static function expectation(?DailyRegister $lastClosedRegister): array
    {
        if ($lastClosedRegister) {
            return [
                'cash' => (float) ($lastClosedRegister->closing_cash ?? 0),
                'gold' => (float) ($lastClosedRegister->closing_gold ?? 0),
                'silver' => (float) ($lastClosedRegister->closing_silver ?? 0),
                'date' => optional($lastClosedRegister->date)?->toDateString(),
                'has_expectation' => true,
            ];
        }

        $balances = Vault::query()
            ->whereIn('type', [
                VaultType::CASH->value,
                VaultType::GOLD->value,
                VaultType::SILVER->value,
            ])
            ->pluck('balance', 'type');

        $cash = (float) ($balances[VaultType::CASH->value] ?? 0);
        $gold = (float) ($balances[VaultType::GOLD->value] ?? 0);
        $silver = (float) ($balances[VaultType::SILVER->value] ?? 0);
        $hasVaultState = VaultMovement::query()->exists()
            || abs($cash) > 0.0001
            || abs($gold) > 0.0001
            || abs($silver) > 0.0001;

        return [
            'cash' => $hasVaultState ? $cash : 0.0,
            'gold' => $hasVaultState ? $gold : 0.0,
            'silver' => $hasVaultState ? $silver : 0.0,
            'date' => null,
            'has_expectation' => $hasVaultState,
        ];
    }
}
