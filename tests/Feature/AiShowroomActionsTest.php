<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\DailyRate;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Purity;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ai\AiActionDispatcher;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'manage_invoices']);
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'manage_products']);
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'manage_daily_rates']);
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'manage_vault']);

    \App\Models\Vault::firstOrCreate(['type' => 'GOLD'], ['name' => 'Main Gold Vault', 'balance' => 1000.0]);
    \App\Models\Vault::firstOrCreate(['type' => 'SILVER'], ['name' => 'Main Silver Vault', 'balance' => 5000.0]);
    \App\Models\Vault::firstOrCreate(['type' => 'CASH'], ['name' => 'Main Cash Vault', 'balance' => 100000.0]);
});

test('get_customer_khata action correctly calculates customer pending balance and recent bills', function () {
    $dispatcher = app(AiActionDispatcher::class);

    $customer = Customer::create([
        'name' => 'Rajesh Sharma',
        'mobile' => '9876543210',
        'city' => 'Jaipur',
    ]);

    $user = User::factory()->create();

    $invoice = Invoice::create([
        'invoice_number' => 'INV-20260825-000001',
        'customer_id' => $customer->id,
        'user_id' => $user->id,
        'date' => '2026-08-25',
        'gold_rate_applied' => 7200,
        'total_amount' => 50000,
        'status' => 'PENDING',
    ]);

    Transaction::create([
        'transactable_type' => Customer::class,
        'transactable_id' => $customer->id,
        'invoice_id' => $invoice->id,
        'type' => 'PAYMENT',
        'amount' => 30000,
        'date' => '2026-08-25',
    ]);

    $result = $dispatcher->dispatch('get_customer_khata', ['phone' => '9876543210']);

    expect($result['found'])->toBeTrue()
        ->and($result['customer_name'])->toBe('Rajesh Sharma')
        ->and($result['pending_due_numeric'])->toBe(20000.0)
        ->and($result['status_type'])->toBe('DUE')
        ->and(count($result['recent_bills']))->toBe(1);
});

test('search_invoices action finds previous invoices by customer phone or name', function () {
    $dispatcher = app(AiActionDispatcher::class);

    $customer = Customer::create([
        'name' => 'Pooja Agarwal',
        'mobile' => '9811223344',
    ]);

    $user = User::factory()->create();

    $inv = Invoice::create([
        'invoice_number' => 'INV-20260825-000002',
        'customer_id' => $customer->id,
        'user_id' => $user->id,
        'date' => '2026-08-25',
        'gold_rate_applied' => 7200,
        'total_amount' => 35000,
        'status' => 'COMPLETED',
    ]);

    Transaction::create([
        'transactable_type' => Customer::class,
        'transactable_id' => $customer->id,
        'invoice_id' => $inv->id,
        'type' => 'PAYMENT',
        'amount' => 35000,
        'date' => '2026-08-25',
    ]);

    InvoiceItem::create([
        'invoice_id' => $inv->id,
        'description' => '22K Gold Antique Ring',
        'weight' => 4.5,
        'purity' => '916 Hallmark',
        'rate' => 6800,
        'making_charges' => 10,
        'final_price' => 35000,
    ]);

    $result = $dispatcher->dispatch('search_invoices', ['query' => '9811223344']);

    expect($result['found'])->toBeTrue()
        ->and($result['count'])->toBe(1)
        ->and($result['invoices'][0]['invoice_number'])->toBe('INV-20260825-000002')
        ->and($result['invoices'][0]['customer_name'])->toBe('Pooja Agarwal');
});

test('get_sales_summary action returns accurate daily counter report', function () {
    $dispatcher = app(AiActionDispatcher::class);

    $customer = Customer::create([
        'name' => 'Walk-in Customer',
        'mobile' => '9999999999',
    ]);
    $user = User::factory()->create();

    $inv = Invoice::create([
        'invoice_number' => 'INV-20260825-000003',
        'customer_id' => $customer->id,
        'user_id' => $user->id,
        'date' => Carbon::today()->toDateString(),
        'gold_rate_applied' => 7200,
        'total_amount' => 80000,
        'status' => 'COMPLETED',
    ]);

    Transaction::create([
        'transactable_type' => Customer::class,
        'transactable_id' => $customer->id,
        'invoice_id' => $inv->id,
        'type' => 'PAYMENT',
        'amount' => 80000,
        'payment_method' => 'UPI',
        'date' => Carbon::today()->toDateString(),
    ]);

    InvoiceItem::create([
        'invoice_id' => $inv->id,
        'description' => 'Gold Chain',
        'weight' => 10.0,
        'purity' => '22K',
        'rate' => 7200,
        'making_charges' => 12,
        'final_price' => 80000,
    ]);

    $result = $dispatcher->dispatch('get_sales_summary', ['period' => 'today']);

    expect($result['found'])->toBeTrue()
        ->and($result['total_bills'])->toBe(1)
        ->and($result['total_sales_numeric'])->toBe(80000.0)
        ->and($result['gold_weight_sold'])->toContain('10.000 g');
});

test('calculate_estimate action works with product barcode and 916 purity', function () {
    $dispatcher = app(AiActionDispatcher::class);

    DailyRate::create([
        'date' => Carbon::today()->toDateString(),
        'gold_sell' => 7000,
        'silver_sell' => 90,
    ]);

    $cat = Category::create(['name' => 'Chain', 'code' => 'CH']);
    $purity = Purity::create(['name' => '916 Hallmark']);
    $sup = Supplier::create(['company_name' => 'ABC Jewellers', 'contact_person' => 'Sunil Kumar', 'mobile' => '9988776655']);

    $prod = Product::create([
        'name' => 'Royal Gold Chain',
        'barcode' => 'G00075',
        'category_id' => $cat->id,
        'supplier_id' => $sup->id,
        'gross_weight' => 10.0,
        'net_weight' => 10.0,
        'purity_id' => $purity->id,
        'making_charge' => 10,
        'making_charge_type' => 'percentage',
        'is_sold' => false,
    ]);

    $result = $dispatcher->dispatch('calculate_estimate', ['barcode' => $prod->barcode]);

    expect($result['found'])->toBeTrue()
        ->and($result['barcode'])->toBe($prod->barcode)
        ->and($result['item_name'])->toBe('Royal Gold Chain')
        ->and($result['rate_per_gm_numeric'])->toBe(6412.0); // 7000 * 0.916 = 6412
});

test('calculate_old_gold action accurately evaluates 12g 17k old gold with no making and no gst', function () {
    $dispatcher = app(AiActionDispatcher::class);

    DailyRate::create([
        'date' => Carbon::today()->toDateString(),
        'gold_buy' => 7200,
        'gold_sell' => 7400,
        'silver_sell' => 90,
    ]);

    $result = $dispatcher->dispatch('calculate_old_gold', [
        'weight' => 12,
        'purity' => '17k',
    ]);

    // 17/24 = 0.7083
    // Rate per gm = 7200 * 0.7083 = 5099.76
    // Valuation = 12 * 5099.76 = 61197.12
    expect($result['found'])->toBeTrue()
        ->and($result['is_old_gold'])->toBeTrue()
        ->and($result['purity'])->toBe('17K (70.83%)')
        ->and($result['fine_gold_weight'])->toContain('8.500 g')
        ->and($result['rate_per_gm_numeric'])->toBe(5099.76)
        ->and($result['total_estimate_numeric'])->toBe(61197.12);
});

test('confirm-product endpoint adds gold and silver products with safe unique categories', function () {
    $user = \App\Models\User::factory()->create();
    $user->givePermissionTo('manage_products');

    \App\Models\DailyRegister::create([
        'date' => Carbon::today()->toDateString(),
        'opening_cash' => 100000,
        'opening_gold' => 500,
        'opened_by' => $user->id,
    ]);

    // 1. Confirm Gold Product
    $goldResponse = $this->actingAs($user)->postJson('/api/ai/copilot/confirm-product', [
        'name' => 'Royal Emerald Ring',
        'weight' => 6.5,
        'metal' => 'GOLD',
        'purity' => '22K (916 Hallmark)',
        'category' => 'Emerald Rings',
        'making_charge_per_gm' => 500,
    ]);

    $goldResponse->assertOk()
        ->assertJson([
            'success' => true,
            'name' => 'Royal Emerald Ring',
            'metal' => 'GOLD',
        ]);

    $this->assertDatabaseHas('products', [
        'name' => 'Royal Emerald Ring',
        'gross_weight' => 6.5,
    ]);

    // 2. Confirm Silver Product (verifying routing to silver_products table)
    $silverResponse = $this->actingAs($user)->postJson('/api/ai/copilot/confirm-product', [
        'name' => 'Designer Silver Payal',
        'weight' => 45.0,
        'metal' => 'SILVER',
        'category' => 'Anklets / Payal',
        'making_charge_per_gm' => 15,
    ]);

    $silverResponse->assertOk()
        ->assertJson([
            'success' => true,
            'name' => 'Designer Silver Payal',
            'metal' => 'SILVER',
        ]);

    $this->assertDatabaseHas('silver_products', [
        'name' => 'Designer Silver Payal',
        'gross_weight' => 45.0,
    ]);
});

test('confirm-bill endpoint reuses master walk-in customer and avoids fake phone number duplicates', function () {
    $user = \App\Models\User::factory()->create();
    $user->givePermissionTo('manage_invoices');

    \App\Models\DailyRegister::create([
        'date' => Carbon::today()->toDateString(),
        'opening_cash' => 100000,
        'opening_gold' => 500,
        'opened_by' => $user->id,
    ]);

    DailyRate::create([
        'date' => Carbon::today()->toDateString(),
        'gold_sell' => 7400,
        'silver_sell' => 90,
    ]);

    $cat = Category::firstOrCreate(['name' => 'Coins', 'code' => 'CN']);
    $purity = Purity::firstOrCreate(['name' => '24K']);
    $sup = Supplier::firstOrCreate(['company_name' => 'Test Supplier', 'contact_person' => 'C', 'mobile' => '9999999999', 'type' => 'GOLD']);
    $prod1 = Product::create([
        'name' => 'Gold Coin 5g',
        'category_id' => $cat->id,
        'purity_id' => $purity->id,
        'supplier_id' => $sup->id,
        'gross_weight' => 5.0,
        'net_weight' => 5.0,
        'making_charge' => 12,
        'making_charge_type' => 'percentage',
        'is_sold' => false,
    ]);
    $prod2 = Product::create([
        'name' => 'Gold Chain 10g',
        'category_id' => $cat->id,
        'purity_id' => $purity->id,
        'supplier_id' => $sup->id,
        'gross_weight' => 10.0,
        'net_weight' => 10.0,
        'making_charge' => 12,
        'making_charge_type' => 'percentage',
        'is_sold' => false,
    ]);

    // First bill for Ramesh
    $res1 = $this->actingAs($user)->postJson('/api/ai/copilot/confirm-bill', [
        'customer_name' => 'Ramesh Kumar',
        'customer_phone' => '9811223344',
        'barcode' => $prod1->barcode,
        'rate_per_gm' => 7400,
        'making_type' => 'flat',
        'making_value' => 200,
        'payment_mode' => 'CASH',
    ]);

    $res1->assertOk()->assertJson(['success' => true]);

    // Second bill for same Ramesh (same mobile number)
    $res2 = $this->actingAs($user)->postJson('/api/ai/copilot/confirm-bill', [
        'customer_name' => 'Ramesh Kumar',
        'customer_phone' => '9811223344',
        'barcode' => $prod2->barcode,
        'rate_per_gm' => 6778.4,
        'payment_mode' => 'UPI',
    ]);

    $res2->assertOk()->assertJson(['success' => true]);

    // Verify only ONE customer account exists for this mobile number
    $customerCount = \App\Models\Customer::where('mobile', '9811223344')->count();
    expect($customerCount)->toBe(1);
});
