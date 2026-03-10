<?php

namespace App\Services;

use App\Models\Vault;
use App\Enums\VaultType;
use Exception;

class VaultService
{
    /**
     * Add money/metal to a vault (Money In).
     */
    public static function credit(VaultType $type, float $amount)
    {
        if ($amount <= 0) return;

        $vault = Vault::firstOrCreate(['type' => $type->value], ['name' => $type->value]);
        $vault->increment('balance', $amount);
    }

    /**
     * Remove money/metal from a vault (Money Out).
     * Automatically checks for sufficient funds.
     */
    public static function debit(VaultType $type, float $amount)
    {
        if ($amount <= 0) return;

        $vault = Vault::where('type', $type->value)->first();

        if (!$vault || $vault->balance < $amount) {
            throw new Exception("Insufficient funds in " . $type->value . " Vault! Current: " . ($vault->balance ?? 0));
        }

        $vault->decrement('balance', $amount);
    }

    /**
     * Get the current balance of any vault.
     */
    public static function getBalance(VaultType $type): float
    {
        return Vault::where('type', $type->value)->value('balance') ?? 0.0;
    }
}
