<?php

namespace App\Services;

use App\Enums\VaultType;
use App\Models\Vault;
use App\Models\VaultMovement;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VaultService
{
    /**
     * Add money or metal to a vault and write its audit movement atomically.
     */
    public static function credit(VaultType $type, float $amount, array $context = []): ?VaultMovement
    {
        return self::mutate($type, 'CREDIT', $amount, $context);
    }

    /**
     * Remove money or metal after checking the locked live balance.
     */
    public static function debit(VaultType $type, float $amount, array $context = []): ?VaultMovement
    {
        return self::mutate($type, 'DEBIT', $amount, $context);
    }

    public static function getBalance(VaultType $type): float
    {
        return (float) (Vault::query()->where('type', $type->value)->value('balance') ?? 0);
    }

    private static function mutate(VaultType $type, string $direction, float $amount, array $context): ?VaultMovement
    {
        $amount = self::roundAmount($type, $amount);
        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($type, $direction, $amount, $context) {
            $vault = Vault::query()
                ->where('type', $type->value)
                ->lockForUpdate()
                ->first();

            if (! $vault && $direction === 'CREDIT') {
                Vault::query()->create([
                    'type' => $type->value,
                    'name' => $type->value,
                    'balance' => 0,
                ]);

                $vault = Vault::query()
                    ->where('type', $type->value)
                    ->lockForUpdate()
                    ->first();
            }

            if (! $vault) {
                throw new Exception("{$type->value} Vault does not exist.");
            }

            $operationKey = self::operationKey($vault, $direction, $context);
            if ($operationKey) {
                $existing = VaultMovement::query()->where('operation_key', $operationKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $before = self::roundAmount($type, (float) $vault->balance);
            if ($direction === 'DEBIT' && $before < $amount) {
                throw new Exception("Insufficient funds in {$type->value} Vault! Current: {$before}");
            }

            $after = self::roundAmount(
                $type,
                $direction === 'CREDIT' ? $before + $amount : $before - $amount,
            );

            $vault->update(['balance' => $after]);

            return self::recordMovement(
                $vault,
                $direction,
                $amount,
                $before,
                $after,
                $operationKey,
                $context,
            );
        });
    }

    private static function recordMovement(
        Vault $vault,
        string $direction,
        float $amount,
        float $before,
        float $after,
        ?string $operationKey,
        array $context,
    ): VaultMovement {
        $isMetal = in_array($vault->type, [VaultType::GOLD->value, VaultType::SILVER->value], true);
        $sourceType = $context['source_type'] ?? null;
        $sourceId = $context['source_id'] ?? null;
        $reference = $context['reference'] ?? null;

        return VaultMovement::query()->create([
            'vault_id' => $vault->id,
            'vault_type' => $vault->type,
            'direction' => $direction,
            'amount' => $amount,
            'gross_weight' => $isMetal ? self::nullableNumber($context['gross_weight'] ?? $amount, 3) : null,
            'fine_weight' => $isMetal ? self::nullableNumber($context['fine_weight'] ?? null, 3) : null,
            'purity_percent' => $isMetal ? self::nullableNumber($context['purity_percent'] ?? null, 4) : null,
            'balance_before' => $before,
            'balance_after' => $after,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'reference' => $reference,
            'correlation_id' => self::correlationId($sourceType, $sourceId, $reference, $context),
            'operation_key' => $operationKey,
            'note' => $context['note'] ?? self::defaultNote($vault->type, $direction, $amount),
            'user_id' => $context['user_id'] ?? Auth::id(),
            'recorded_at' => $context['recorded_at'] ?? now(),
        ]);
    }

    private static function operationKey(Vault $vault, string $direction, array $context): ?string
    {
        if (empty($context['operation_key'])) {
            return null;
        }

        return hash('sha256', implode('|', [
            (string) $context['operation_key'],
            $vault->type,
            $direction,
        ]));
    }

    private static function correlationId(mixed $sourceType, mixed $sourceId, mixed $reference, array $context): ?string
    {
        $value = $context['correlation_id'] ?? $reference;
        if (! $value && $sourceType && $sourceId) {
            $value = class_basename((string) $sourceType).'-'.$sourceId;
        }

        $value = trim((string) $value);

        return $value !== '' ? mb_substr($value, 0, 120) : null;
    }

    private static function nullableNumber(mixed $value, int $precision): ?float
    {
        return is_numeric($value) ? round((float) $value, $precision) : null;
    }

    private static function roundAmount(VaultType $type, float $amount): float
    {
        return round($amount, in_array($type, [VaultType::CASH, VaultType::BANK], true) ? 2 : 3);
    }

    private static function defaultNote(string $vaultType, string $direction, float $amount): string
    {
        $verb = $direction === 'CREDIT' ? 'credited to' : 'debited from';

        return number_format($amount, 3, '.', '')." moved {$verb} {$vaultType} vault";
    }
}
