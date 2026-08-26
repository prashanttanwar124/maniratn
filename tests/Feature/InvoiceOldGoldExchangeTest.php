<?php

use App\Enums\VaultType;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DailyRate;
use App\Models\DailyRegister;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceOldGold;
use App\Models\Product;
use App\Models\Purity;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vault;
use App\Services\VaultService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Carbon::setTestNow('2026-08-26 10:00:00');

    $this->seed(RolesAndPermissionsSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin');

    actingAs($this->user);

    DailyRate::query()->create([
        'date' => '2026-08-26',
        'gold_sell' => 7000,
        'gold_buy' => 6850,
        'silver_sell' => 90,
    ]);

    Vault::updateOrCreate(['type' => VaultType::CASH->value], ['name' => 'Cash Vault', 'balance' => 10000]);
    Vault::updateOrCreate(['type' => VaultType::BANK->value], ['name' => 'Bank Vault', 'balance' => 0]);
    Vault::updateOrCreate(['type' => VaultType::GOLD->value], ['name' => 'Gold Vault', 'balance' => 100]);
    Vault::updateOrCreate(['type' => VaultType::SILVER->value], ['name' => 'Silver Vault', 'balance' => 500]);

    DailyRegister::query()->create([
        'date' => '2026-08-26',
        'opening_cash' => 10000,
        'opening_gold' => 100,
        'status' => 'OPEN',
        'opened_by' => $this->user->id,
        'opened_at' => now(),
    ]);

    $this->supplier = Supplier::create([
        'company_name' => 'Supplier Co',
        'contact_person' => 'Contact',
        'mobile' => '9999900001',
        'type' => 'GOLD',
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('creates an invoice with old gold exchange and cash payment, updating vault and ledger accurately', function () {
    $initialGoldVault = VaultService::getBalance(VaultType::GOLD);
    $initialCashVault = VaultService::getBalance(VaultType::CASH);

    $customer = Customer::create([
        'name' => 'Prashant Tanwar',
        'mobile' => '9876543210',
        'city' => 'Mumbai',
    ]);

    $purity = Purity::firstOrCreate(['name' => '22K']);
    $category = Category::firstOrCreate(['name' => 'Ring', 'code' => 'RNG']);

    // Create a stock product: 10g 22K Ring, rate = ₹7,000/g, making = ₹500 flat -> item price = ₹70,500
    // Taxable = ₹70,500, 3% GST = ₹2,115, Grand Total = ₹72,615
    $product = Product::create([
        'name' => 'Gold Ring 22K',
        'barcode' => 'RNG-TEST-001',
        'purity_id' => $purity->id,
        'category_id' => $category->id,
        'supplier_id' => $this->supplier->id,
        'gross_weight' => 10.000,
        'net_weight' => 10.000,
        'making_charge' => 500,
        'making_charge_type' => 'flat',
        'is_sold' => false,
    ]);

    // Customer provides Old Gold: 5g gross, 0.2g wastage => 4.8g net at ₹6,274.60/g (22K buy rate) = ₹30,118.08
    // Grand Total: ₹72,615
    // Old Gold Value: ₹30,118.08
    // Net Payable: ₹72,615 - ₹30,118.08 = ₹42,496.92
    // Customer pays ₹42,496.92 in cash
    $oldGoldExchangeValue = 30118.08;
    $cashPaid = 42496.92;

    $response = post(route('invoices.store'), [
        'customer_id' => $customer->id,
        'gold_rate' => 7000,
        'silver_rate' => 90,
        'date' => '2026-08-26',
        'discount_type' => 'amount',
        'discount_value' => 0,
        'items' => [
            [
                'type' => 'product',
                'id' => $product->id,
                'rate' => 7000,
                'making_charges' => 500,
                'making_charge_type' => 'flat',
                'quantity' => 1,
            ],
        ],
        'old_golds' => [
            [
                'metal_type' => 'GOLD',
                'description' => 'Old Gold 22K Chain',
                'gross_weight' => 5.000,
                'wastage_weight' => 0.200,
                'net_weight' => 4.800,
                'purity' => '22K',
                'rate' => 6274.60,
                'final_price' => $oldGoldExchangeValue,
            ],
        ],
        'payment_cash' => $cashPaid,
        'payment_card' => 0,
    ]);

    $response->assertRedirect(route('invoices.index'));
    $response->assertSessionHas('success');

    // 1. Verify Invoice
    $invoice = Invoice::with(['oldGolds', 'items', 'transactions'])->latest('id')->first();
    expect($invoice)->not->toBeNull();
    expect((float) $invoice->total_amount)->toBe(72615.00);
    expect((float) $invoice->old_gold_amount)->toBe(30118.08);
    expect((float) $invoice->old_gold_weight)->toBe(5.000);
    expect($invoice->oldGolds)->toHaveCount(1);
    expect($invoice->oldGolds->first()->description)->toBe('Old Gold 22K Chain');
    expect((float) $invoice->oldGolds->first()->gross_weight)->toBe(5.000);
    expect((float) $invoice->oldGolds->first()->net_weight)->toBe(4.800);

    // 2. Product is marked as sold
    expect($product->fresh()->is_sold)->toBeTrue();

    // 3. Verify Gold Vault: Credited by 5.000g old gold
    $currentGoldVault = VaultService::getBalance(VaultType::GOLD);
    expect($currentGoldVault - $initialGoldVault)->toBe(5.000);

    // 4. Verify Cash Vault: Credited ONLY by cash paid (₹42,496.92), NOT old gold value
    $currentCashVault = VaultService::getBalance(VaultType::CASH);
    expect($currentCashVault - $initialCashVault)->toBe(42496.92);

    // 5. Verify Customer Ledger Transactions
    $saleTxn = $invoice->transactions->where('entry_type_code', 'INVOICE_SALE')->first();
    expect($saleTxn)->not->toBeNull();
    expect((float) $saleTxn->amount)->toBe(72615.00);
    expect($saleTxn->type)->toBe('SALE');

    $oldGoldTxn = $invoice->transactions->where('entry_type_code', 'INVOICE_OLD_GOLD')->first();
    expect($oldGoldTxn)->not->toBeNull();
    expect((float) $oldGoldTxn->amount)->toBe(30118.08);
    expect($oldGoldTxn->payment_method)->toBe('OLD_GOLD');
    expect($oldGoldTxn->type)->toBe('PAYMENT');

    $cashTxn = $invoice->transactions->where('entry_type_code', 'INVOICE_PAYMENT')->first();
    expect($cashTxn)->not->toBeNull();
    expect((float) $cashTxn->amount)->toBe(42496.92);
    expect($cashTxn->payment_method)->toBe('CASH');

    // Net ledger balance for customer on this bill should be 0 (₹72,615 sale - ₹30,118.08 old gold - ₹42,496.92 cash = 0)
    $totalPaidOnBill = $invoice->transactions->where('type', 'PAYMENT')->sum('amount');
    expect((float) $totalPaidOnBill)->toBe(72615.00);
    $pending = max(0, (float) $invoice->total_amount - $totalPaidOnBill);
    expect($pending)->toBe(0);
});

it('rejects cash/card payment exceeding net payable after old gold deduction', function () {
    $customer = Customer::create([
        'name' => 'Ramesh Bhai',
        'mobile' => '9876543211',
    ]);

    $purity = Purity::firstOrCreate(['name' => '22K']);
    $category = Category::firstOrCreate(['name' => 'Chain', 'code' => 'CHN']);

    // 10g chain at ₹7,000 = ₹70,000 + 3% GST = ₹72,100
    $product = Product::create([
        'name' => 'Gold Chain 22K',
        'barcode' => 'CHN-TEST-002',
        'purity_id' => $purity->id,
        'category_id' => $category->id,
        'supplier_id' => $this->supplier->id,
        'gross_weight' => 10.000,
        'net_weight' => 10.000,
        'making_charge' => 0,
        'making_charge_type' => 'percentage',
        'is_sold' => false,
    ]);

    // Old gold value = ₹50,000. Net payable = ₹72,100 - ₹50,000 = ₹22,100
    // Attempt to pay ₹30,000 cash (exceeds ₹22,100 net payable)
    $response = post(route('invoices.store'), [
        'customer_id' => $customer->id,
        'gold_rate' => 7000,
        'date' => '2026-08-26',
        'items' => [
            [
                'type' => 'product',
                'id' => $product->id,
                'rate' => 7000,
                'making_charges' => 0,
                'quantity' => 1,
            ],
        ],
        'old_golds' => [
            [
                'metal_type' => 'GOLD',
                'description' => 'Old Gold Set',
                'gross_weight' => 8.000,
                'net_weight' => 8.000,
                'purity' => '22K',
                'rate' => 6250,
                'final_price' => 50000,
            ],
        ],
        'payment_cash' => 30000,
        'payment_card' => 0,
    ]);

    $response->assertSessionHasErrors(['payment_cash']);
});

it('reverses old metal vault credit and voids transaction when invoice is cancelled', function () {
    $initialGoldVault = VaultService::getBalance(VaultType::GOLD);
    $initialCashVault = VaultService::getBalance(VaultType::CASH);

    $customer = Customer::create([
        'name' => 'Sanjay Patel',
        'mobile' => '9876543212',
    ]);

    $purity = Purity::firstOrCreate(['name' => '22K']);
    $category = Category::firstOrCreate(['name' => 'Bangles', 'code' => 'BGL']);

    $product = Product::create([
        'name' => 'Gold Bangles',
        'barcode' => 'BGL-TEST-003',
        'purity_id' => $purity->id,
        'category_id' => $category->id,
        'supplier_id' => $this->supplier->id,
        'gross_weight' => 20.000,
        'net_weight' => 20.000,
        'making_charge' => 0,
        'making_charge_type' => 'percentage',
        'is_sold' => false,
    ]);

    // Item: ₹1,40,000 + 3% GST = ₹1,44,200
    // Old Gold: 10g gross = ₹60,000
    // Net Payable: ₹84,200
    // Cash Paid: ₹84,200
    post(route('invoices.store'), [
        'customer_id' => $customer->id,
        'gold_rate' => 7000,
        'date' => '2026-08-26',
        'items' => [
            [
                'type' => 'product',
                'id' => $product->id,
                'rate' => 7000,
                'making_charges' => 0,
                'quantity' => 1,
            ],
        ],
        'old_golds' => [
            [
                'metal_type' => 'GOLD',
                'description' => 'Old 22K Bangles',
                'gross_weight' => 10.000,
                'wastage_weight' => 0,
                'net_weight' => 10.000,
                'purity' => '22K',
                'rate' => 6000,
                'final_price' => 60000,
            ],
        ],
        'payment_cash' => 84200,
        'payment_card' => 0,
    ]);

    $invoice = Invoice::latest('id')->first();
    expect($invoice->status)->toBe('VALID');
    expect(VaultService::getBalance(VaultType::GOLD) - $initialGoldVault)->toBe(10.000);
    expect(VaultService::getBalance(VaultType::CASH) - $initialCashVault)->toBe(84200.0);
    expect($product->fresh()->is_sold)->toBeTrue();

    // Now Void the Invoice with refund mode
    $voidResponse = post(route('invoices.cancel', $invoice->id), [
        'mode' => 'refund',
        'reason' => 'Customer cancelled deal',
    ]);

    $voidResponse->assertRedirect();
    $invoice->refresh();

    expect($invoice->status)->toBe('CANCELLED');
    expect($invoice->cancellation_mode)->toBe('refund');

    // 1. Stock product restored
    expect($product->fresh()->is_sold)->toBeFalse();

    // 2. Old Gold Vault balance reversed back to original
    expect(VaultService::getBalance(VaultType::GOLD))->toBe($initialGoldVault);

    // 3. Cash Vault balance refunded back to original
    expect(VaultService::getBalance(VaultType::CASH))->toBe($initialCashVault);

    // 4. Old gold and sale transactions marked VOID
    $oldGoldTxn = $invoice->transactions()->where('entry_type_code', 'VOID_INVOICE_OLD_GOLD')->first();
    expect($oldGoldTxn)->not->toBeNull();
    expect($oldGoldTxn->type)->toBe('VOID');

    $saleTxn = $invoice->transactions()->where('entry_type_code', 'VOID_INVOICE_SALE')->first();
    expect($saleTxn)->not->toBeNull();
    expect($saleTxn->type)->toBe('VOID');

    $refundTxn = $invoice->transactions()->where('entry_type_code', 'INVOICE_REFUND')->first();
    expect($refundTxn)->not->toBeNull();
    expect($refundTxn->type)->toBe('VOID');
});

it('keeps both old gold value and cash as customer advance in ledger when voided with keep_advance mode', function () {
    $initialGoldVault = VaultService::getBalance(VaultType::GOLD);
    $initialCashVault = VaultService::getBalance(VaultType::CASH);

    $customer = Customer::create([
        'name' => 'Rameshwar Vyas',
        'mobile' => '9876543277',
    ]);

    $purity = Purity::firstOrCreate(['name' => '22K']);
    $category = Category::firstOrCreate(['name' => 'Ring', 'code' => 'RNG']);

    $product = Product::create([
        'name' => 'Gold Solitaire Ring',
        'barcode' => 'RNG-TEST-005',
        'purity_id' => $purity->id,
        'category_id' => $category->id,
        'supplier_id' => $this->supplier->id,
        'gross_weight' => 10.000,
        'net_weight' => 10.000,
        'making_charge' => 0,
        'making_charge_type' => 'percentage',
        'is_sold' => false,
    ]);

    // Item: ₹70,000 + 3% GST = ₹72,100
    // Old Gold: 5g gross = ₹30,000
    // Net Payable: ₹42,100
    // Cash Paid: ₹42,100
    post(route('invoices.store'), [
        'customer_id' => $customer->id,
        'gold_rate' => 7000,
        'date' => '2026-08-26',
        'items' => [
            [
                'type' => 'product',
                'id' => $product->id,
                'rate' => 7000,
                'making_charges' => 0,
                'quantity' => 1,
            ],
        ],
        'old_golds' => [
            [
                'metal_type' => 'GOLD',
                'description' => 'Old 22K Ring',
                'gross_weight' => 5.000,
                'wastage_weight' => 0,
                'net_weight' => 5.000,
                'purity' => '22K',
                'rate' => 6000,
                'final_price' => 30000,
            ],
        ],
        'payment_cash' => 42100,
        'payment_card' => 0,
    ]);

    $invoice = Invoice::latest('id')->first();
    expect($invoice->status)->toBe('VALID');
    expect(VaultService::getBalance(VaultType::GOLD) - $initialGoldVault)->toBe(5.000);
    expect(VaultService::getBalance(VaultType::CASH) - $initialCashVault)->toBe(42100.0);

    // Void with both Old Gold and Cash kept as advance
    $voidResponse = post(route('invoices.cancel', $invoice->id), [
        'mode' => 'keep_advance',
        'old_gold_mode' => 'keep_advance',
        'reason' => 'Customer wants to keep full amount in account advance',
    ]);

    $voidResponse->assertRedirect();
    $invoice->refresh();

    expect($invoice->status)->toBe('CANCELLED');

    // 1. Stock product restored
    expect($product->fresh()->is_sold)->toBeFalse();

    // 2. Gold Vault retains the 5g old gold
    expect(VaultService::getBalance(VaultType::GOLD) - $initialGoldVault)->toBe(5.000);

    // 3. Cash Vault retains the ₹42,100 cash
    expect(VaultService::getBalance(VaultType::CASH) - $initialCashVault)->toBe(42100.0);

    // 4. Old Gold and Cash transactions both remain active PAYMENT type (advance credits)
    $oldGoldTxn = $invoice->transactions()->where('entry_type_code', 'INVOICE_OLD_GOLD')->first();
    expect($oldGoldTxn)->not->toBeNull();
    expect($oldGoldTxn->type)->toBe('PAYMENT');
    expect((float) $oldGoldTxn->amount)->toBe(30000.0);

    $cashTxn = $invoice->transactions()->where('entry_type_code', 'INVOICE_PAYMENT')->first();
    expect($cashTxn)->not->toBeNull();
    expect($cashTxn->type)->toBe('PAYMENT');
    expect((float) $cashTxn->amount)->toBe(42100.0);

    // 5. Customer Ledger Calculations: Total Paid = ₹72,100, Total Sales = ₹0 => Advance = ₹72,100
    $totalSales = $customer->transactions()->where('type', 'SALE')->sum('amount');
    $totalPaid = $customer->transactions()->where('type', 'PAYMENT')->sum('amount');
    expect((float) $totalSales)->toBe(0.0);
    expect((float) $totalPaid)->toBe(72100.0);
    expect((float) ($totalSales - $totalPaid))->toBe(-72100.0); // Negative means Advance credit!
});

it('supports combined old gold and old silver exchange in the same invoice', function () {
    $initialGoldVault = VaultService::getBalance(VaultType::GOLD);
    $initialSilverVault = VaultService::getBalance(VaultType::SILVER);
    $initialCashVault = VaultService::getBalance(VaultType::CASH);

    $customer = Customer::create([
        'name' => 'Mehul Shah',
        'mobile' => '9876543299',
    ]);

    $purity = Purity::firstOrCreate(['name' => '22K']);
    $category = Category::firstOrCreate(['name' => 'Necklace', 'code' => 'NCK']);

    // 20g Gold Necklace at ₹7,000 = ₹1,40,000 + 3% GST = ₹1,44,200
    $product = Product::create([
        'name' => 'Gold Necklace 22K',
        'barcode' => 'NCK-TEST-004',
        'purity_id' => $purity->id,
        'category_id' => $category->id,
        'supplier_id' => $this->supplier->id,
        'gross_weight' => 20.000,
        'net_weight' => 20.000,
        'making_charge' => 0,
        'making_charge_type' => 'percentage',
        'is_sold' => false,
    ]);

    // Trade-in:
    // 1. Old Gold: 10g gross -> ₹60,000
    // 2. Old Silver: 100g gross -> ₹8,000
    // Total Trade-in: ₹68,000
    // Net Payable: ₹1,44,200 - ₹68,000 = ₹76,200
    // Cash Paid: ₹76,200
    $response = post(route('invoices.store'), [
        'customer_id' => $customer->id,
        'gold_rate' => 7000,
        'silver_rate' => 90,
        'date' => '2026-08-26',
        'items' => [
            [
                'type' => 'product',
                'id' => $product->id,
                'rate' => 7000,
                'making_charges' => 0,
                'quantity' => 1,
            ],
        ],
        'old_golds' => [
            [
                'metal_type' => 'GOLD',
                'description' => 'Old Gold 22K Chain',
                'gross_weight' => 10.000,
                'wastage_weight' => 0.500,
                'net_weight' => 9.500,
                'purity' => '22K',
                'rate' => 6315.789,
                'final_price' => 60000,
            ],
            [
                'metal_type' => 'SILVER',
                'description' => 'Old Silver Plate',
                'gross_weight' => 100.000,
                'wastage_weight' => 0,
                'net_weight' => 100.000,
                'purity' => '92.5',
                'rate' => 80,
                'final_price' => 8000,
            ],
        ],
        'payment_cash' => 76200,
        'payment_card' => 0,
    ]);

    $response->assertRedirect(route('invoices.index'));

    $invoice = Invoice::with('oldGolds')->latest('id')->first();
    expect($invoice)->not->toBeNull();
    expect((float) $invoice->old_gold_amount)->toBe(68000.0);
    expect((float) $invoice->old_gold_weight)->toBe(110.000);
    expect($invoice->oldGolds)->toHaveCount(2);

    // Verify both Gold and Silver vaults are credited
    expect(VaultService::getBalance(VaultType::GOLD) - $initialGoldVault)->toBe(10.000);
    expect(VaultService::getBalance(VaultType::SILVER) - $initialSilverVault)->toBe(100.000);
    expect(VaultService::getBalance(VaultType::CASH) - $initialCashVault)->toBe(76200.0);
});

it('saves and loads old gold exchange rows in draft persistence', function () {
    $customer = Customer::create([
        'name' => 'Draft Customer',
        'mobile' => '9876543298',
    ]);

    $purity = Purity::firstOrCreate(['name' => '22K']);
    $category = Category::firstOrCreate(['name' => 'Ring', 'code' => 'RNG']);

    $product = Product::create([
        'name' => 'Draft Ring',
        'barcode' => 'DRF-TEST-005',
        'purity_id' => $purity->id,
        'category_id' => $category->id,
        'supplier_id' => $this->supplier->id,
        'gross_weight' => 5.000,
        'net_weight' => 5.000,
        'making_charge' => 0,
        'is_sold' => false,
    ]);

    // Save draft with old gold
    $saveResponse = post(route('invoices.drafts.store'), [
        'customer_id' => $customer->id,
        'gold_rate' => 7000,
        'date' => '2026-08-26',
        'items' => [
            [
                'type' => 'product',
                'id' => $product->id,
                'description' => 'Draft Ring',
                'weight' => 5.000,
                'rate' => 7000,
                'making_charges' => 0,
                'quantity' => 1,
            ],
        ],
        'old_golds' => [
            [
                'metal_type' => 'GOLD',
                'description' => 'Customer Old Earring',
                'gross_weight' => 3.500,
                'wastage_weight' => 0.100,
                'net_weight' => 3.400,
                'purity' => '22K',
                'rate' => 6000,
                'final_price' => 20400,
            ],
        ],
        'payment_cash' => 0,
        'payment_card' => 0,
    ]);

    $saveResponse->assertOk();
    $draftId = $saveResponse->json('draft.id');
    expect($draftId)->not->toBeNull();

    // Now verify draft in database and response
    expect($saveResponse->json('draft.data.old_golds'))->toHaveCount(1);
    expect($saveResponse->json('draft.data.old_golds.0.description'))->toBe('Customer Old Earring');
    expect((float) $saveResponse->json('draft.data.old_golds.0.gross_weight'))->toBe(3.500);

    $draft = \App\Models\InvoiceDraft::find($draftId);
    expect($draft)->not->toBeNull();
    expect($draft->draft_data['old_golds'])->toHaveCount(1);
    expect($draft->draft_data['old_golds'][0]['description'])->toBe('Customer Old Earring');
    expect((float) $draft->draft_data['old_golds'][0]['gross_weight'])->toBe(3.500);
    expect((float) $draft->draft_data['old_golds'][0]['final_price'])->toBe(20400.0);
});

