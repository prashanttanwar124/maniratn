<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\DailyRate;
use App\Models\DailyRegister;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Purity;
use App\Models\SilverProduct;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vault;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Setup permissions
    Permission::firstOrCreate(['name' => 'manage_invoices']);
    Permission::firstOrCreate(['name' => 'manage_products']);
    Permission::firstOrCreate(['name' => 'manage_daily_rates']);
    Permission::firstOrCreate(['name' => 'manage_vault']);

    Vault::firstOrCreate(['type' => 'GOLD'], ['name' => 'Main Gold Vault', 'balance' => 1000.0]);
    Vault::firstOrCreate(['type' => 'SILVER'], ['name' => 'Main Silver Vault', 'balance' => 5000.0]);
    Vault::firstOrCreate(['type' => 'CASH'], ['name' => 'Main Cash Vault', 'balance' => 100000.0]);
});

test('unauthenticated request to ai copilot is rejected', function () {
    $res = $this->postJson('/api/ai/copilot/confirm-bill', [
        'weight' => 5.0,
        'rate_per_gm' => 7400,
    ]);

    $res->assertStatus(401);
});

test('authenticated request is blocked by day.open middleware if shop day is not opened', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('manage_invoices');

    $res = $this->actingAs($user)->postJson('/api/ai/copilot/confirm-bill', [
        'weight' => 5.0,
        'rate_per_gm' => 7400,
    ]);

    $res->assertStatus(403)
        ->assertJson([
            'error' => 'DAY_NOT_OPEN',
        ]);
});

test('confirm-bill debits gold vault and applies cash ledger impact when authenticated and day is open', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['manage_invoices', 'manage_vault']);

    // Open Day
    DailyRegister::create([
        'date' => Carbon::today()->toDateString(),
        'opening_cash' => 100000,
        'opening_gold' => 500,
        'opened_by' => $user->id,
    ]);

    DailyRate::create([
        'date' => Carbon::today()->toDateString(),
        'gold_sell' => 7500,
        'silver_sell' => 90,
    ]);

    $goldVault = Vault::where('type', 'GOLD')->first();
    $cashVault = Vault::where('type', 'CASH')->first();

    $res = $this->actingAs($user)->postJson('/api/ai/copilot/confirm-bill', [
        'customer_name' => 'Aman Verma',
        'customer_phone' => '9876543211',
        'item_name' => 'Gold Kada 10g',
        'weight' => 10.0,
        'metal' => 'GOLD',
        'purity' => '22K',
        'rate_per_gm' => 7000,
        'making_type' => 'per_gram',
        'making_value' => 400,
        'discount_amount' => 500,
        'payment_mode' => 'CASH',
        'payment_amount' => 75705, // Full cash payment
        'message_id' => 'test_msg_unique_101',
    ]);

    $res->assertOk()
        ->assertJson(['success' => true]);

    $invoiceId = $res->json('invoice_id');
    $invoice = Invoice::find($invoiceId);
    expect($invoice)->not->toBeNull();

    $saleTx = Transaction::where('invoice_id', $invoiceId)->where('type', 'SALE')->first();
    expect($saleTx)->not->toBeNull();
    expect($saleTx->description)->toContain('[AI_MSG:test_msg_unique_101]');

    // Check Vault Debits: Gold vault should decrease by 10g from initial 1000g -> 990g
    $goldVault->refresh();
    expect((float) $goldVault->balance)->toBe(990.0);

    // Check Cash Vault: Cash vault should increase by invoice cash payment
    $cashVault->refresh();
    expect((float) $cashVault->balance)->toBeGreaterThan(100000.0);
});

test('confirm-bill is idempotent and prevents duplicate billing on message_id re-submission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('manage_invoices');

    DailyRegister::create([
        'date' => Carbon::today()->toDateString(),
        'opening_cash' => 100000,
        'opening_gold' => 500,
        'opened_by' => $user->id,
    ]);

    DailyRate::create([
        'date' => Carbon::today()->toDateString(),
        'gold_sell' => 7500,
        'silver_sell' => 90,
    ]);

    // First Call
    $res1 = $this->actingAs($user)->postJson('/api/ai/copilot/confirm-bill', [
        'customer_name' => 'Pooja Jain',
        'weight' => 5.0,
        'rate_per_gm' => 7000,
        'message_id' => 'idempotency_token_999',
    ]);
    $res1->assertOk()->assertJson(['success' => true]);
    $inv1Id = $res1->json('invoice_id');

    // Second Duplicate Call with same message_id
    $res2 = $this->actingAs($user)->postJson('/api/ai/copilot/confirm-bill', [
        'customer_name' => 'Pooja Jain',
        'weight' => 5.0,
        'rate_per_gm' => 7000,
        'message_id' => 'idempotency_token_999',
    ]);

    $res2->assertOk()
        ->assertJson([
            'success' => true,
            'already_confirmed' => true,
            'invoice_id' => $inv1Id,
        ]);

    // Verify only ONE invoice transaction was created in total
    $txCount = Transaction::where('description', 'LIKE', '%idempotency_token_999%')->count();
    expect($txCount)->toBe(1);
});

test('confirm-bill with barcode overrides client payload with authoritative stock weight and purity', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('manage_invoices');

    DailyRegister::create([
        'date' => Carbon::today()->toDateString(),
        'opening_cash' => 100000,
        'opening_gold' => 500,
        'opened_by' => $user->id,
    ]);

    DailyRate::create([
        'date' => Carbon::today()->toDateString(),
        'gold_sell' => 7500,
        'silver_sell' => 90,
    ]);

    $cat = Category::firstOrCreate(['name' => 'Chains', 'code' => 'CHN']);
    $purity = Purity::firstOrCreate(['name' => '22K (916 Hallmark)']);
    $supplier = Supplier::firstOrCreate([
        'company_name' => 'Gold House',
        'contact_person' => 'Supplier Contact',
        'mobile' => '9999999999',
        'type' => 'GOLD',
    ]);

    $stockProduct = Product::create([
        'name' => 'Authoritative 22K Royal Chain',
        'category_id' => $cat->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 15.5,
        'net_weight' => 15.5,
        'making_charge' => 450,
        'making_charge_type' => 'per_gram',
        'is_sold' => false,
    ]);

    // Client maliciously/erroneously submits 1.0g instead of 15.5g
    $res = $this->actingAs($user)->postJson('/api/ai/copilot/confirm-bill', [
        'barcode' => $stockProduct->barcode,
        'item_name' => 'Hacked 1g Chain',
        'weight' => 1.0, // Should be overridden by 15.5g from DB
        'rate_per_gm' => 7000,
    ]);

    $res->assertOk();
    expect($res->json('weight'))->toBe(15.5);
    expect($res->json('item_name'))->toBe('Authoritative 22K Royal Chain');

    $stockProduct->refresh();
    expect((bool) $stockProduct->is_sold)->toBeTrue();
});

test('confirm-bill with silver piece inventory decrements quantity instead of selling record if qty > 1', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('manage_invoices');

    DailyRegister::create([
        'date' => Carbon::today()->toDateString(),
        'opening_cash' => 100000,
        'opening_gold' => 500,
        'opened_by' => $user->id,
    ]);

    DailyRate::create([
        'date' => Carbon::today()->toDateString(),
        'gold_sell' => 7500,
        'silver_sell' => 90,
    ]);

    $cat = Category::firstOrCreate(['name' => 'Coins', 'code' => 'COIN']);
    $supplier = Supplier::firstOrCreate([
        'company_name' => 'Silver Mint',
        'contact_person' => 'Mint Contact',
        'mobile' => '9999999999',
        'type' => 'SILVER',
    ]);

    $silverCoin = SilverProduct::create([
        'name' => 'Lakshmi Silver Coin 10g',
        'category_id' => $cat->id,
        'supplier_id' => $supplier->id,
        'pricing_mode' => 'PIECE',
        'quantity' => 5, // 5 pieces in stock
        'gross_weight' => 10.0,
        'net_weight' => 10.0,
        'making_charge' => 50,
        'is_sold' => false,
    ]);

    $res = $this->actingAs($user)->postJson('/api/ai/copilot/confirm-bill', [
        'barcode' => $silverCoin->barcode,
        'metal' => 'SILVER',
        'weight' => 10.0,
        'rate_per_gm' => 90,
    ]);

    $res->assertOk();

    $silverCoin->refresh();
    expect($silverCoin->quantity)->toBe(4);
    expect((bool) $silverCoin->is_sold)->toBeFalse();
});

test('confirm-product and confirm-rates validate positive values', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['manage_products', 'manage_daily_rates']);

    DailyRegister::create([
        'date' => Carbon::today()->toDateString(),
        'opening_cash' => 100000,
        'opening_gold' => 500,
        'opened_by' => $user->id,
    ]);

    // Negative weight should fail validation (422)
    $resProduct = $this->actingAs($user)->postJson('/api/ai/copilot/confirm-product', [
        'name' => 'Bad Product',
        'weight' => -5.0,
    ]);
    $resProduct->assertStatus(422);

    // Negative rates should fail validation (422)
    $resRates = $this->actingAs($user)->postJson('/api/ai/copilot/confirm-rates', [
        'gold_24k_sell' => -7000,
        'silver_sell' => -90,
    ]);
    $resRates->assertStatus(422);
});
