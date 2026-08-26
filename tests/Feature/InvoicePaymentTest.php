<?php

use App\Enums\VaultType;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DailyRegister;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Purity;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vault;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    actingAs($this->user);
});

function openShopDayForTest(User $user, float $cash = 1000, float $gold = 10): DailyRegister
{
    Vault::updateOrCreate(['type' => VaultType::CASH->value], ['name' => 'Cash Vault', 'balance' => $cash]);
    Vault::updateOrCreate(['type' => VaultType::BANK->value], ['name' => 'Bank Vault', 'balance' => 0]);
    Vault::updateOrCreate(['type' => VaultType::GOLD->value], ['name' => 'Gold Vault', 'balance' => $gold]);
    Vault::updateOrCreate(['type' => VaultType::SILVER->value], ['name' => 'Silver Vault', 'balance' => 0]);

    return DailyRegister::create([
        'date' => today()->toDateString(),
        'opening_cash' => $cash,
        'opening_gold' => $gold,
        'opened_by' => $user->id,
        'status' => 'OPEN',
    ]);
}

it('records a direct payment on an invoice and updates both invoice pending balance and customer ledger', function () {
    openShopDayForTest($this->user, 1000, 10);

    $customer = Customer::create([
        'name' => 'Ramesh Patel',
        'mobile' => '9876543210',
        'city' => 'Surat',
    ]);

    $supplier = Supplier::create([
        'company_name' => 'Gold Supplier Ltd',
        'contact_person' => 'Supplier Person',
        'mobile' => '9999999999',
        'type' => 'GOLD',
    ]);

    $category = Category::create(['name' => 'Bangle', 'code' => 'BGL']);
    $purity = Purity::create(['name' => '22K']);

    $product = Product::create([
        'name' => 'Gold Bangle',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 5.0,
        'net_weight' => 5.0,
        'making_charge' => 0,
    ]);

    // Create invoice for 35,000 + 3% tax (1,050) = 36,050. Paid 31,050 cash, pending 5,000.
    $response = post(route('invoices.store'), [
        'customer_id' => $customer->id,
        'gold_rate' => 7000,
        'date' => today()->toDateString(),
        'items' => [
            [
                'type' => 'product',
                'id' => $product->id,
                'rate' => 7000,
                'making_charges' => 0,
            ],
        ],
        'payment_cash' => 31050,
        'payment_card' => 0,
    ]);

    $response->assertRedirect();

    $invoice = Invoice::with('transactions')->firstOrFail();
    expect((float) $invoice->total_amount)->toBe(36050.0)
        ->and((float) Transaction::where('invoice_id', $invoice->id)->where('type', 'PAYMENT')->sum('amount'))->toBe(31050.0)
        ->and((float) $customer->fresh()->balance)->toBe(5000.0);

    // Now record subsequent payment of 5,000 via invoices.payment endpoint
    $payResponse = post(route('invoices.payment', $invoice->id), [
        'amount' => 5000,
        'payment_method' => 'CASH',
        'date' => today()->toDateString(),
        'note' => 'Final settlement cash payment',
    ]);

    $payResponse->assertRedirect();
    $payResponse->assertSessionHas('success');

    // Verify invoice is fully settled
    $paidAmount = (float) Transaction::where('invoice_id', $invoice->id)->where('type', 'PAYMENT')->sum('amount');
    expect($paidAmount)->toBe(36050.0)
        ->and((float) $customer->fresh()->balance)->toBe(0.0)
        ->and((float) Vault::where('type', VaultType::CASH->value)->value('balance'))->toBe(37050.0); // 1000 open + 31050 bill + 5000 payment
});

it('supports partial payments against an invoice', function () {
    openShopDayForTest($this->user, 500, 10);

    $customer = Customer::create([
        'name' => 'Suresh Kumar',
        'mobile' => '9876543211',
        'city' => 'Mumbai',
    ]);

    $supplier = Supplier::create([
        'company_name' => 'Supplier Co',
        'contact_person' => 'Supplier Person',
        'mobile' => '9999999999',
        'type' => 'GOLD',
    ]);

    $category = Category::create(['name' => 'Chain', 'code' => 'CHN']);
    $purity = Purity::create(['name' => '22K']);

    $product = Product::create([
        'name' => 'Gold Chain',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 2.0,
        'net_weight' => 2.0,
        'making_charge' => 0,
    ]);

    // 2g * 7000 = 14,000 + 3% tax (420) = 14,420. Paid 9,420, pending 5,000.
    post(route('invoices.store'), [
        'customer_id' => $customer->id,
        'gold_rate' => 7000,
        'date' => today()->toDateString(),
        'items' => [
            [
                'type' => 'product',
                'id' => $product->id,
                'rate' => 7000,
                'making_charges' => 0,
            ],
        ],
        'payment_cash' => 9420,
        'payment_card' => 0,
    ]);

    $invoice = Invoice::firstOrFail(); // Total 14,420, Paid 9,420, Pending 5,000

    // Pay 2,000 UPI
    post(route('invoices.payment', $invoice->id), [
        'amount' => 2000,
        'payment_method' => 'UPI',
        'date' => today()->toDateString(),
    ])->assertRedirect();

    $paidAmount = (float) Transaction::where('invoice_id', $invoice->id)->where('type', 'PAYMENT')->sum('amount');
    expect($paidAmount)->toBe(11420.0)
        ->and((float) $customer->fresh()->balance)->toBe(3000.0)
        ->and((float) Vault::where('type', VaultType::BANK->value)->value('balance'))->toBe(2000.0);

    // Pay remaining 3,000 CASH
    post(route('invoices.payment', $invoice->id), [
        'amount' => 3000,
        'payment_method' => 'CASH',
        'date' => today()->toDateString(),
    ])->assertRedirect();

    $finalPaid = (float) Transaction::where('invoice_id', $invoice->id)->where('type', 'PAYMENT')->sum('amount');
    expect($finalPaid)->toBe(14420.0)
        ->and((float) $customer->fresh()->balance)->toBe(0.0);
});

it('rejects payments greater than pending balance', function () {
    openShopDayForTest($this->user, 500, 10);

    $customer = Customer::create([
        'name' => 'Amit Sharma',
        'mobile' => '9876543212',
        'city' => 'Delhi',
    ]);

    $supplier = Supplier::create([
        'company_name' => 'Supplier Co',
        'contact_person' => 'Supplier Person',
        'mobile' => '9999999999',
        'type' => 'GOLD',
    ]);

    $category = Category::create(['name' => 'Ring', 'code' => 'RNG']);
    $purity = Purity::create(['name' => '22K']);

    $product = Product::create([
        'name' => 'Gold Ring',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 1.0,
        'net_weight' => 1.0,
        'making_charge' => 0,
    ]);

    // 1g * 7000 = 7000 + 3% tax (210) = 7,210. Paid 4,710, Pending 2,500.
    post(route('invoices.store'), [
        'customer_id' => $customer->id,
        'gold_rate' => 7000,
        'date' => today()->toDateString(),
        'items' => [
            [
                'type' => 'product',
                'id' => $product->id,
                'rate' => 7000,
                'making_charges' => 0,
            ],
        ],
        'payment_cash' => 4710,
        'payment_card' => 0,
    ]);

    $invoice = Invoice::firstOrFail(); // Total 7,210, Paid 4,710, Pending 2,500

    // Attempting to pay 3,000 should fail validation
    $response = post(route('invoices.payment', $invoice->id), [
        'amount' => 3000,
        'payment_method' => 'CASH',
        'date' => today()->toDateString(),
    ]);

    $response->assertSessionHasErrors(['amount']);
});

it('records payment from customer ledger entry with linked invoice_id', function () {
    openShopDayForTest($this->user, 1000, 10);

    $customer = Customer::create([
        'name' => 'Pooja Verma',
        'mobile' => '9876543213',
        'city' => 'Ahmedabad',
    ]);

    $supplier = Supplier::create([
        'company_name' => 'Supplier Co',
        'contact_person' => 'Supplier Person',
        'mobile' => '9999999999',
        'type' => 'GOLD',
    ]);

    $category = Category::create(['name' => 'Pendant', 'code' => 'PND']);
    $purity = Purity::create(['name' => '22K']);

    $product = Product::create([
        'name' => 'Gold Pendant',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 2.0,
        'net_weight' => 2.0,
        'making_charge' => 0,
    ]);

    // 2g * 7000 = 14,000 + 3% tax (420) = 14,420. Paid 9,420, Pending 5,000.
    post(route('invoices.store'), [
        'customer_id' => $customer->id,
        'gold_rate' => 7000,
        'date' => today()->toDateString(),
        'items' => [
            [
                'type' => 'product',
                'id' => $product->id,
                'rate' => 7000,
                'making_charges' => 0,
            ],
        ],
        'payment_cash' => 9420,
        'payment_card' => 0,
    ]);

    $invoice = Invoice::firstOrFail(); // Total 14,420, Pending 5,000

    // Post entry from customer ledger with invoice_id
    $entryResponse = post(route('ledger.store-entry'), [
        'party_type' => Customer::class,
        'party_id' => $customer->id,
        'entry_type' => 'RECEIVE_CASH',
        'cash_amount' => 5000,
        'payment_method' => 'CASH',
        'invoice_id' => $invoice->id,
        'date' => today()->toDateString(),
    ]);

    $entryResponse->assertRedirect();
    $entryResponse->assertSessionHas('success');

    // Verify invoice is settled in Invoice History
    $paidAmount = (float) Transaction::where('invoice_id', $invoice->id)->where('type', 'PAYMENT')->sum('amount');
    expect($paidAmount)->toBe(14420.0)
        ->and((float) $customer->fresh()->balance)->toBe(0.0);
});

it('correctly voids an unpaid invoice without creating phantom advance', function () {
    openShopDayForTest($this->user, 1000, 10);

    $customer = Customer::create([
        'name' => 'Zero Paid Customer',
        'mobile' => '9876543299',
        'city' => 'Delhi',
    ]);

    $supplier = Supplier::create([
        'company_name' => 'Supplier Gold',
        'contact_person' => 'Supplier Person',
        'mobile' => '9999999998',
        'type' => 'GOLD',
    ]);

    $category = Category::create(['name' => 'Ring', 'code' => 'RNG']);
    $purity = Purity::create(['name' => '22K']);

    $product = Product::create([
        'name' => 'Gold Ring 22K',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 1.0,
        'net_weight' => 1.0,
        'making_charge' => 0,
    ]);

    // Create invoice with 0 payment: 1g * 7000 = 7000 + 3% GST (210) = 7210.
    post(route('invoices.store'), [
        'customer_id' => $customer->id,
        'gold_rate' => 7000,
        'date' => today()->toDateString(),
        'items' => [
            [
                'type' => 'product',
                'id' => $product->id,
                'rate' => 7000,
                'making_charges' => 0,
            ],
        ],
        'payment_cash' => 0,
        'payment_card' => 0,
    ])->assertRedirect();

    $invoice = Invoice::latest('id')->firstOrFail();
    expect((bool) $product->fresh()->is_sold)->toBeTrue()
        ->and((float) $customer->fresh()->balance)->toBe(7210.0);

    // Void the unpaid invoice
    $voidResponse = post(route('invoices.cancel', $invoice->id), [
        'mode' => 'none',
        'reason' => 'Customer changed mind before payment',
    ]);

    $voidResponse->assertRedirect();
    $voidResponse->assertSessionHas('success');

    // Verify invoice cancelled, stock restored, and customer balance reset to 0
    expect($invoice->fresh()->status)->toBe('CANCELLED')
        ->and($invoice->fresh()->cancellation_mode)->toBe('none')
        ->and((bool) $product->fresh()->is_sold)->toBeFalse()
        ->and((float) $customer->fresh()->balance)->toBe(0.0);
});

it('rejects adding payment to a cancelled invoice with validation error', function () {
    openShopDayForTest($this->user, 1000, 10);

    $customer = Customer::create([
        'name' => 'Voided Invoice Payment Customer',
        'mobile' => '9876543201',
        'city' => 'Delhi',
    ]);

    $supplier = Supplier::create([
        'company_name' => 'Supplier Gold',
        'contact_person' => 'Supplier Person',
        'mobile' => '9999999998',
        'type' => 'GOLD',
    ]);

    $category = Category::create(['name' => 'Ring', 'code' => 'RNG']);
    $purity = Purity::create(['name' => '22K']);

    $product = Product::create([
        'name' => 'Gold Ring 22K',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 1.0,
        'net_weight' => 1.0,
        'making_charge' => 0,
        'is_sold' => false,
    ]);

    post(route('invoices.store'), [
        'customer_id' => $customer->id,
        'gold_rate' => 7000,
        'date' => today()->toDateString(),
        'items' => [
            [
                'type' => 'product',
                'id' => $product->id,
                'rate' => 7000,
                'making_charges' => 0,
            ],
        ],
        'payment_cash' => 0,
        'payment_card' => 0,
    ])->assertRedirect();

    $invoice = Invoice::latest('id')->firstOrFail();

    // Cancel invoice
    post(route('invoices.cancel', $invoice->id), [
        'mode' => 'none',
        'reason' => 'Void test',
    ])->assertRedirect();

    expect($invoice->fresh()->status)->toBe('CANCELLED');

    // Attempt to add payment to cancelled invoice
    $payResponse = post(route('invoices.payment', $invoice->id), [
        'amount' => 1000,
        'payment_method' => 'CASH',
        'date' => today()->toDateString(),
    ]);

    $payResponse->assertSessionHasErrors('amount');
});

it('rejects duplicate cancellation attempts and does not double restore stock', function () {
    openShopDayForTest($this->user, 1000, 10);

    $customer = Customer::create([
        'name' => 'Double Void Customer',
        'mobile' => '9876543202',
        'city' => 'Delhi',
    ]);

    $supplier = Supplier::create([
        'company_name' => 'Silver Supplier Ltd',
        'contact_person' => 'Supplier Person',
        'mobile' => '9999999997',
        'type' => 'SILVER',
    ]);

    $category = Category::create(['name' => 'Silver Item', 'code' => 'SLV']);

    $silverProduct = \App\Models\SilverProduct::create([
        'name' => 'Silver Glass',
        'category_id' => $category->id,
        'supplier_id' => $supplier->id,
        'pricing_mode' => 'PIECE',
        'quantity' => 10,
        'gross_weight' => 50.0,
        'net_weight' => 50.0,
        'piece_price' => 2000,
        'making_charge' => 0,
    ]);

    // Buy 2 pieces (quantity left = 8)
    post(route('invoices.store'), [
        'customer_id' => $customer->id,
        'silver_rate' => 90,
        'date' => today()->toDateString(),
        'items' => [
            [
                'type' => 'silver_product',
                'id' => $silverProduct->id,
                'rate' => 2000,
                'making_charges' => 0,
                'quantity' => 2,
            ],
        ],
        'payment_cash' => 4120, // 4000 + 3% GST (120)
        'payment_card' => 0,
    ])->assertRedirect();

    $invoice = Invoice::latest('id')->firstOrFail();
    expect($silverProduct->fresh()->quantity)->toBe(8);

    // Cancel 1: Should restore quantity to 10
    $firstCancel = post(route('invoices.cancel', $invoice->id), [
        'mode' => 'refund',
        'reason' => 'First cancel attempt',
    ]);
    $firstCancel->assertRedirect();
    $firstCancel->assertSessionHas('success');
    expect($silverProduct->fresh()->quantity)->toBe(10);

    // Cancel 2 (duplicate/replay): Should fail with error and quantity must REMAIN 10 (not 12!)
    $secondCancel = post(route('invoices.cancel', $invoice->id), [
        'mode' => 'refund',
        'reason' => 'Second duplicate cancel attempt',
    ]);
    $secondCancel->assertSessionHasErrors('invoice');
    expect($silverProduct->fresh()->quantity)->toBe(10);
});
