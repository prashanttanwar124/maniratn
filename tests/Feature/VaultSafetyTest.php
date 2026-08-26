<?php

use App\Enums\VaultType;
use App\Models\MetalTransaction;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultMovement;
use App\Services\LedgerImpactService;
use App\Services\VaultService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('vault mutations are idempotent for the same audited source operation', function () {
    $user = User::factory()->create();
    Vault::create(['type' => VaultType::GOLD->value, 'name' => 'Gold Vault', 'balance' => 100]);
    $context = [
        'source_type' => MetalTransaction::class,
        'source_id' => 501,
        'reference' => 'MT-501',
        'correlation_id' => 'PUR-000501',
        'operation_key' => 'purchase-501-gold-debit',
        'user_id' => $user->id,
        'gross_weight' => 10,
        'fine_weight' => 9.16,
        'purity_percent' => 91.6,
    ];

    VaultService::debit(VaultType::GOLD, 10, $context);
    VaultService::debit(VaultType::GOLD, 10, $context);

    $movement = VaultMovement::sole();

    expect((float) Vault::where('type', VaultType::GOLD->value)->value('balance'))->toBe(90.0)
        ->and($movement->operation_key)->not->toBeNull()
        ->and($movement->correlation_id)->toBe('PUR-000501')
        ->and((float) $movement->gross_weight)->toBe(10.0)
        ->and((float) $movement->fine_weight)->toBe(9.16)
        ->and((float) $movement->purity_percent)->toBe(91.6);
});

test('metal ledger effects copy gross fine and purity data into the vault audit trail', function () {
    $user = User::factory()->create();
    Vault::create(['type' => VaultType::GOLD->value, 'name' => 'Gold Vault', 'balance' => 20]);
    $transaction = MetalTransaction::create([
        'party_type' => User::class,
        'party_id' => $user->id,
        'type' => 'ISSUE',
        'metal_type' => 'GOLD',
        'gross_weight' => 5,
        'fine_weight' => 4.58,
        'description' => 'Production issue',
        'date' => today(),
        'entry_type_code' => 'ISSUE_GOLD',
    ]);

    LedgerImpactService::applyMetalTransaction($transaction);

    $movement = VaultMovement::sole();

    expect((float) Vault::where('type', VaultType::GOLD->value)->value('balance'))->toBe(15.0)
        ->and($movement->source_type)->toBe(MetalTransaction::class)
        ->and($movement->source_id)->toBe($transaction->id)
        ->and($movement->correlation_id)->toBe("METAL-TXN-{$transaction->id}")
        ->and((float) $movement->gross_weight)->toBe(5.0)
        ->and((float) $movement->fine_weight)->toBe(4.58)
        ->and((float) $movement->purity_percent)->toBe(91.6);
});

test('failed vault debit leaves both balance and audit trail unchanged', function () {
    Vault::create(['type' => VaultType::GOLD->value, 'name' => 'Gold Vault', 'balance' => 5]);

    expect(fn () => VaultService::debit(VaultType::GOLD, 6, [
        'source_type' => MetalTransaction::class,
        'source_id' => 777,
    ]))->toThrow(Exception::class);

    expect((float) Vault::where('type', VaultType::GOLD->value)->value('balance'))->toBe(5.0)
        ->and(VaultMovement::count())->toBe(0);
});
