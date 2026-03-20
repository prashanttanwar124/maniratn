<?php

namespace App\Services;

use App\Enums\VaultType;
use App\Models\Customer;
use App\Models\Karigar;
use App\Models\MetalTransaction;
use App\Models\Supplier;
use App\Models\Transaction;

class LedgerImpactService
{
    public static function applyCashTransaction(Transaction $transaction): void
    {
        self::runCashEffect($transaction, false);
    }

    public static function reverseCashTransaction(Transaction $transaction): void
    {
        self::runCashEffect($transaction, true);
    }

    public static function applyMetalTransaction(MetalTransaction $transaction): void
    {
        self::runMetalEffect($transaction, false);
    }

    public static function reverseMetalTransaction(MetalTransaction $transaction): void
    {
        self::runMetalEffect($transaction, true);
    }

    private static function runCashEffect(Transaction $transaction, bool $reverse): void
    {
        [$direction, $vaultType] = self::cashEffect($transaction);

        if (! $direction || ! $vaultType) {
            return;
        }

        if ($reverse) {
            $direction = $direction === 'credit' ? 'debit' : 'credit';
        }

        $context = self::cashContext($transaction, $direction, $reverse);

        if ($direction === 'credit') {
            VaultService::credit($vaultType, (float) $transaction->amount, $context);
        } else {
            VaultService::debit($vaultType, (float) $transaction->amount, $context);
        }
    }

    private static function runMetalEffect(MetalTransaction $transaction, bool $reverse): void
    {
        $direction = self::metalEffect($transaction);
        $vaultType = self::metalVaultType($transaction);

        if (! $direction || ! $vaultType) {
            return;
        }

        if ($reverse) {
            $direction = $direction === 'credit' ? 'debit' : 'credit';
        }

        $context = self::metalContext($transaction, $direction, $reverse);

        if ($direction === 'credit') {
            VaultService::credit($vaultType, (float) $transaction->gross_weight, $context);
        } else {
            VaultService::debit($vaultType, (float) $transaction->gross_weight, $context);
        }
    }

    private static function cashEffect(Transaction $transaction): array
    {
        $code = $transaction->entry_type_code;
        $paymentMethod = strtoupper((string) ($transaction->payment_method ?: 'CASH'));
        $vaultType = self::cashVaultType($paymentMethod);

        return match ($code) {
            'PAY_CASH', 'CASH_TO_GOLD', 'CASH_TO_SILVER', 'ORDER_CASH_PAYMENT', 'INVOICE_REFUND' => ['debit', $vaultType],
            'RECEIVE_CASH', 'INVOICE_PAYMENT' => ['credit', $vaultType],
            'GOLD_TO_CASH', 'SILVER_TO_CASH', 'INVOICE_SALE', 'VOID_INVOICE_SALE' => [null, null],
            default => self::cashEffectFromLegacyFields($transaction, $vaultType),
        };
    }

    private static function cashEffectFromLegacyFields(Transaction $transaction, VaultType $vaultType): array
    {
        if ($transaction->invoice_id && $transaction->type === 'PAYMENT') {
            return ['credit', $vaultType];
        }

        if ($transaction->transactable_type === Customer::class && $transaction->type === 'PAYMENT') {
            return ['credit', $vaultType];
        }

        if ($transaction->transactable_type === Customer::class && $transaction->type === 'RECEIPT') {
            return ['credit', $vaultType];
        }

        if (in_array($transaction->transactable_type, [Supplier::class, Karigar::class], true) && $transaction->type === 'PAYMENT') {
            return ['debit', $vaultType];
        }

        if (in_array($transaction->transactable_type, [Supplier::class, Karigar::class], true) && $transaction->type === 'RECEIPT') {
            return ['credit', $vaultType];
        }

        return [null, null];
    }

    private static function metalEffect(MetalTransaction $transaction): ?string
    {
        return match ($transaction->entry_type_code) {
            'ISSUE_GOLD', 'ORDER_ISSUE_GOLD' => 'debit',
            'RECEIVE_GOLD', 'GOLD_TO_CASH', 'CASH_TO_GOLD', 'ORDER_RECEIVE_GOLD' => 'credit',
            'ISSUE_SILVER', 'ORDER_ISSUE_SILVER' => 'debit',
            'RECEIVE_SILVER', 'SILVER_TO_CASH', 'CASH_TO_SILVER', 'ORDER_RECEIVE_SILVER' => 'credit',
            default => match ($transaction->type) {
                'ISSUE' => 'debit',
                'RECEIPT' => 'credit',
                default => null,
            },
        };
    }

    private static function metalVaultType(MetalTransaction $transaction): ?VaultType
    {
        $metalType = strtoupper((string) ($transaction->metal_type ?: ''));

        if ($metalType === 'SILVER') {
            return VaultType::SILVER;
        }

        if ($metalType === 'GOLD') {
            return VaultType::GOLD;
        }

        return str_contains((string) $transaction->entry_type_code, 'SILVER')
            ? VaultType::SILVER
            : VaultType::GOLD;
    }

    private static function cashVaultType(string $paymentMethod): VaultType
    {
        return in_array($paymentMethod, ['CARD', 'BANK', 'UPI'], true)
            ? VaultType::BANK
            : VaultType::CASH;
    }

    private static function cashContext(Transaction $transaction, string $direction, bool $reverse): array
    {
        $party = class_basename((string) $transaction->transactable_type);
        $action = strtoupper($direction) === 'CREDIT' ? 'inflow' : 'outflow';

        return [
            'source_type' => Transaction::class,
            'source_id' => $transaction->id,
            'reference' => $transaction->invoice_id ? 'Invoice #' . $transaction->invoice_id : null,
            'user_id' => $transaction->user_id,
            'recorded_at' => $transaction->created_at ?? now(),
            'note' => trim(($reverse ? 'Reversal: ' : '') . ($transaction->description ?: "{$party} cash {$action}")),
        ];
    }

    private static function metalContext(MetalTransaction $transaction, string $direction, bool $reverse): array
    {
        $party = class_basename((string) $transaction->party_type);
        $action = strtoupper($direction) === 'CREDIT' ? 'inflow' : 'outflow';

        return [
            'source_type' => MetalTransaction::class,
            'source_id' => $transaction->id,
            'user_id' => $transaction->user_id ?? null,
            'recorded_at' => $transaction->created_at ?? now(),
            'note' => trim(($reverse ? 'Reversal: ' : '') . ($transaction->description ?: "{$party} " . strtolower((string) ($transaction->metal_type ?: 'gold')) . " {$action}")),
        ];
    }
}
