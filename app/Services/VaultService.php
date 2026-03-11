<?php

namespace App\Services;

use App\Models\Vault;
use App\Models\VaultMovement;
use App\Enums\VaultType;
use Exception;
use Illuminate\Support\Facades\Auth;

class VaultService
{
    /**
     * Add money/metal to a vault (Money In).
     */
    public static function credit(VaultType $type, float $amount, array $context = [])
    {
        if ($amount <= 0) return;

        $vault = Vault::firstOrCreate(['type' => $type->value], ['name' => $type->value]);
        $before = (float) $vault->balance;
        $after = $before + $amount;

        $vault->update(['balance' => $after]);

        self::recordMovement($vault, 'CREDIT', $amount, $before, $after, $context);
    }

    /**
     * Remove money/metal from a vault (Money Out).
     * Automatically checks for sufficient funds.
     */
    public static function debit(VaultType $type, float $amount, array $context = [])
    {
        if ($amount <= 0) return;

        $vault = Vault::where('type', $type->value)->first();

        if (!$vault || $vault->balance < $amount) {
            throw new Exception("Insufficient funds in " . $type->value . " Vault! Current: " . ($vault->balance ?? 0));
        }

        $before = (float) $vault->balance;
        $after = $before - $amount;

        $vault->update(['balance' => $after]);

        self::recordMovement($vault, 'DEBIT', $amount, $before, $after, $context);
    }

    /**
     * Get the current balance of any vault.
     */
    public static function getBalance(VaultType $type): float
    {
        return Vault::where('type', $type->value)->value('balance') ?? 0.0;
    }

    private static function recordMovement(Vault $vault, string $direction, float $amount, float $before, float $after, array $context = []): void
    {
        VaultMovement::create([
            'vault_id' => $vault->id,
            'vault_type' => $vault->type,
            'direction' => $direction,
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'source_type' => $context['source_type'] ?? null,
            'source_id' => $context['source_id'] ?? null,
            'reference' => $context['reference'] ?? null,
            'note' => $context['note'] ?? self::defaultNote($vault->type, $direction, $amount),
            'user_id' => $context['user_id'] ?? Auth::id(),
            'recorded_at' => $context['recorded_at'] ?? now(),
        ]);
    }

    private static function defaultNote(string $vaultType, string $direction, float $amount): string
    {
        $verb = $direction === 'CREDIT' ? 'credited to' : 'debited from';

        return number_format($amount, 3, '.', '') . " moved {$verb} {$vaultType} vault";
    }
}
