<?php

use App\Enums\VaultType;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DailyRegister;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Karigar;
use App\Models\CustomerGoldScheme;
use App\Models\GoldSchemeInstallment;
use App\Models\MetalTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Purity;
use App\Models\SilverProduct;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultMovement;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\patch;

beforeEach(function () {
    Carbon::setTestNow('2026-03-10 10:00:00');

    $this->seed(RolesAndPermissionsSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin');

    actingAs($this->user);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('keeps invoice, payment, and vault balances aligned for partial payment and refund void', function () {
    openShopDay($this->user, 0, 10);

    $customer = Customer::create([
        'name' => 'prashant tanwar',
        'mobile' => '9999999991',
        'city' => 'virar',
    ]);

    $supplier = Supplier::create([
        'company_name' => 'raj gold house',
        'contact_person' => 'rajesh bhai',
        'mobile' => '8888888881',
        'type' => 'GOLD',
    ]);

    $category = new Category();
    $category->name = 'Ring';
    $category->code = 'RNG';
    $category->save();

    $purity = new Purity();
    $purity->name = '22K';
    $purity->save();

    $product = Product::create([
        'name' => 'Gold Ring',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 1.500,
        'net_weight' => 1.500,
        'making_charge' => 1000,
    ]);

    $response = post(route('invoices.store'), [
        'customer_id' => $customer->id,
        'gold_rate' => 7000,
        'date' => today()->toDateString(),
        'items' => [
            [
                'type' => 'product',
                'id' => $product->id,
                'making_charges' => 1000,
            ],
        ],
        'payment_cash' => 1000,
        'payment_card' => 553,
    ]);

    $response->assertRedirect();

    $invoice = Invoice::with('transactions')->firstOrFail();

    expect((float) $invoice->total_amount)->toBe(11845.00)
        ->and($invoice->invoice_number)->toMatch('/^INV-20260310-\d{6}$/')
        ->and((bool) $product->fresh()->is_sold)->toBeTrue()
        ->and((float) Vault::where('type', VaultType::GOLD->value)->value('balance'))->toBe(10.0)
        ->and((float) Vault::where('type', VaultType::CASH->value)->value('balance'))->toBe(1000.0)
        ->and((float) Vault::where('type', VaultType::BANK->value)->value('balance'))->toBe(553.0)
        ->and(VaultMovement::count())->toBeGreaterThanOrEqual(2);

    expect((float) Transaction::where('invoice_id', $invoice->id)->where('type', 'SALE')->sum('amount'))->toBe(11845.0)
        ->and((float) Transaction::where('invoice_id', $invoice->id)->where('type', 'PAYMENT')->sum('amount'))->toBe(1553.0);

    $voidResponse = post(route('invoices.cancel', $invoice->id), [
        'mode' => 'refund',
        'reason' => 'Customer cancelled purchase and amount was refunded.',
    ]);

    $voidResponse->assertRedirect();

    expect($invoice->fresh()->status)->toBe('CANCELLED')
        ->and((bool) $product->fresh()->is_sold)->toBeFalse()
        ->and((float) Vault::where('type', VaultType::GOLD->value)->value('balance'))->toBe(10.0)
        ->and((float) Vault::where('type', VaultType::CASH->value)->value('balance'))->toBe(0.0)
        ->and((float) Vault::where('type', VaultType::BANK->value)->value('balance'))->toBe(0.0);
});

it('restores full quantity when a weight-based silver invoice is cancelled', function () {
    openShopDay($this->user, 1000, 0);

    $customer = Customer::create([
        'name' => 'silver quantity customer',
        'mobile' => '9999999994',
        'city' => 'mumbai',
    ]);

    $supplier = Supplier::create([
        'company_name' => 'silver supplier',
        'contact_person' => 'silver bhai',
        'mobile' => '8888888894',
        'type' => 'SILVER',
    ]);

    $category = Category::create([
        'name' => 'Silver Idol',
        'code' => 'SID',
        'metal_type' => 'SILVER',
    ]);

    $silverProduct = SilverProduct::create([
        'name' => 'Silver Idol Pair',
        'category_id' => $category->id,
        'supplier_id' => $supplier->id,
        'pricing_mode' => 'WEIGHT',
        'quantity' => 4,
        'gross_weight' => 80.000,
        'net_weight' => 72.000,
        'piece_price' => null,
        'making_charge' => 500,
        'notes' => 'Weight-based silver stock',
    ]);

    post(route('invoices.store'), [
        'customer_id' => $customer->id,
        'silver_rate' => 100,
        'date' => today()->toDateString(),
        'items' => [
            [
                'type' => 'silver_product',
                'id' => $silverProduct->id,
                'making_charges' => 500,
                'quantity' => 1,
            ],
        ],
        'payment_cash' => 0,
        'payment_card' => 0,
    ])->assertRedirect();

    $invoice = Invoice::query()->latest('id')->firstOrFail();
    $invoiceItem = InvoiceItem::query()->where('invoice_id', $invoice->id)->where('silver_product_id', $silverProduct->id)->firstOrFail();

    expect($silverProduct->fresh()->quantity)->toBe(0)
        ->and((bool) $silverProduct->fresh()->is_sold)->toBeTrue()
        ->and($invoiceItem->quantity)->toBe(4);

    post(route('invoices.cancel', $invoice->id), [
        'mode' => 'refund',
        'reason' => 'Reverse weight-based silver sale.',
    ])->assertRedirect();

    expect($silverProduct->fresh()->quantity)->toBe(4)
        ->and((bool) $silverProduct->fresh()->is_sold)->toBeFalse();
});

it('stores invoice discount and calculates final total after discount plus gst', function () {
    openShopDay($this->user, 1000, 10);

    $customer = Customer::create([
        'name' => 'discount customer',
        'mobile' => '9999999992',
        'city' => 'mumbai',
    ]);

    $supplier = Supplier::create([
        'company_name' => 'discount supplier',
        'contact_person' => 'mohan bhai',
        'mobile' => '8888888891',
        'type' => 'GOLD',
    ]);

    $category = new Category();
    $category->name = 'Pendant';
    $category->code = 'PND';
    $category->save();

    $purity = new Purity();
    $purity->name = '22K';
    $purity->save();

    $product = Product::create([
        'name' => 'Pendant Stock',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 2.000,
        'net_weight' => 2.000,
        'making_charge' => 1000,
    ]);

    post(route('invoices.store'), [
        'customer_id' => $customer->id,
        'gold_rate' => 5000,
        'date' => today()->toDateString(),
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'items' => [
            [
                'type' => 'product',
                'id' => $product->id,
                'making_charges' => 1000,
            ],
        ],
        'payment_cash' => 0,
        'payment_card' => 0,
    ])->assertRedirect();

    $invoice = Invoice::query()->latest('id')->firstOrFail();

    expect((float) $invoice->discount_value)->toBe(10.0)
        ->and((float) $invoice->discount_amount)->toBe(1100.0)
        ->and((float) $invoice->tax_amount)->toBe(297.0)
        ->and((float) $invoice->total_amount)->toBe(10197.0)
        ->and((float) Transaction::query()->where('invoice_id', $invoice->id)->where('type', 'SALE')->value('amount'))->toBe(10197.0);
});

it('blocks gold scheme edit when a paid installment exists live', function () {
    openShopDay($this->user, 1000, 0);

    $customer = Customer::create([
        'name' => 'scheme customer',
        'mobile' => '9999999995',
        'city' => 'mumbai',
    ]);

    $scheme = CustomerGoldScheme::create([
        'customer_id' => $customer->id,
        'start_date' => today(),
        'maturity_date' => today()->addMonths(10),
        'status' => 'ACTIVE',
        'monthly_amount' => 100000,
        'total_months' => 11,
        'bonus_amount' => 100000,
        'paid_total' => 0,
        'paid_installments_count' => 0,
        'notes' => 'Editable until paid',
    ]);

    GoldSchemeInstallment::create([
        'customer_gold_scheme_id' => $scheme->id,
        'installment_no' => 1,
        'due_date' => today(),
        'amount_due' => 100000,
        'amount_paid' => 100000,
        'paid_on' => today(),
        'payment_method' => 'CASH',
        'status' => 'PAID',
        'collected_by' => $this->user->id,
    ]);

    GoldSchemeInstallment::create([
        'customer_gold_scheme_id' => $scheme->id,
        'installment_no' => 2,
        'due_date' => today()->addMonth(),
        'amount_due' => 100000,
        'status' => 'PENDING',
    ]);

    patch(route('gold-schemes.update', $scheme), [
        'customer_id' => $customer->id,
        'start_date' => today()->toDateString(),
        'monthly_amount' => 90000,
        'total_months' => 10,
        'bonus_amount' => 90000,
        'notes' => 'Updated notes',
    ])->assertSessionHasErrors([
        'scheme' => 'This scheme already has collected installments and can no longer be edited.',
    ]);

    expect($scheme->fresh()->monthly_amount)->toBe('100000.00')
        ->and($scheme->installments()->count())->toBe(2)
        ->and($scheme->installments()->where('status', 'PAID')->count())->toBe(1);
});

it('updates vault balances when manual ledger cash entries are created and edited', function () {
    openShopDay($this->user, 5000, 0);

    $supplier = Supplier::create([
        'company_name' => 'mahavir bullion',
        'contact_person' => 'amit shah',
        'mobile' => '8888888882',
        'type' => 'GOLD',
    ]);

    $createResponse = post(route('ledger.store-entry'), [
        'party_type' => Supplier::class,
        'party_id' => $supplier->id,
        'entry_type' => 'PAY_CASH',
        'cash_amount' => 1000,
        'payment_method' => 'CASH',
        'description' => 'Manual settlement',
        'date' => today()->toDateString(),
    ]);

    $createResponse->assertRedirect();

    $transaction = Transaction::query()
        ->where('transactable_type', Supplier::class)
        ->where('transactable_id', $supplier->id)
        ->latest('id')
        ->firstOrFail();

    expect((float) Vault::where('type', VaultType::CASH->value)->value('balance'))->toBe(4000.0)
        ->and($transaction->entry_type_code)->toBe('PAY_CASH');

    $editResponse = patch(route('ledger.update-entry', ['category' => 'cash', 'id' => $transaction->id]), [
        'party_type' => Supplier::class,
        'party_id' => $supplier->id,
        'entry_type' => 'RECEIVE_CASH',
        'cash_amount' => 250,
        'payment_method' => 'CASH',
        'description' => 'Correction entry',
        'date' => today()->toDateString(),
    ]);

    $editResponse->assertRedirect();

    expect((float) Vault::where('type', VaultType::CASH->value)->value('balance'))->toBe(5250.0)
        ->and($transaction->fresh()->type)->toBe('RECEIPT')
        ->and($transaction->fresh()->entry_type_code)->toBe('RECEIVE_CASH')
        ->and((float) $transaction->fresh()->amount)->toBe(250.0);
});

it('uses the selected payment method for manual ledger cash entries', function () {
    openShopDay($this->user, 0, 0);

    Vault::updateOrCreate(
        ['type' => VaultType::BANK->value],
        ['name' => VaultType::BANK->value, 'balance' => 3000]
    );

    $karigar = Karigar::create([
        'name' => 'bank karigar',
        'mobile' => '7777777775',
        'work_type' => 'rings',
        'city' => 'mumbai',
    ]);

    post(route('ledger.store-entry'), [
        'party_type' => Karigar::class,
        'party_id' => $karigar->id,
        'entry_type' => 'PAY_CASH',
        'cash_amount' => 1200,
        'payment_method' => 'BANK',
        'description' => 'Bank payment to karigar',
        'date' => today()->toDateString(),
    ])->assertRedirect();

    expect((float) Vault::where('type', VaultType::BANK->value)->value('balance'))->toBe(1800.0)
        ->and((float) Vault::where('type', VaultType::CASH->value)->value('balance'))->toBe(0.0)
        ->and(Transaction::query()->where('transactable_type', Karigar::class)->where('payment_method', 'BANK')->count())->toBe(1);
});

it('updates the silver vault when manual silver ledger entries are created', function () {
    openShopDay($this->user, 0, 0);

    Vault::updateOrCreate(
        ['type' => VaultType::SILVER->value],
        ['name' => VaultType::SILVER->value, 'balance' => 5]
    );

    $supplier = Supplier::create([
        'company_name' => 'silver source',
        'contact_person' => 'dinesh',
        'mobile' => '8888888884',
        'type' => 'SILVER',
    ]);

    post(route('ledger.store-entry'), [
        'party_type' => Supplier::class,
        'party_id' => $supplier->id,
        'entry_type' => 'ISSUE_SILVER',
        'gold_weight' => 2,
        'purity' => 92.5,
        'description' => 'Silver issued manually',
        'date' => today()->toDateString(),
    ])->assertRedirect();

    expect((float) Vault::where('type', VaultType::SILVER->value)->value('balance'))->toBe(3.0);

    post(route('ledger.store-entry'), [
        'party_type' => Supplier::class,
        'party_id' => $supplier->id,
        'entry_type' => 'RECEIVE_SILVER',
        'gold_weight' => 1.250,
        'purity' => 92.5,
        'description' => 'Silver received back',
        'date' => today()->toDateString(),
    ])->assertRedirect();

    expect((float) Vault::where('type', VaultType::SILVER->value)->value('balance'))->toBe(4.25)
        ->and((float) Vault::where('type', VaultType::GOLD->value)->value('balance'))->toBe(0.0)
        ->and(MetalTransaction::query()->where('party_type', Supplier::class)->where('party_id', $supplier->id)->where('metal_type', 'SILVER')->count())->toBe(2);
});

it('handles gold and silver cash adjustment entries against the correct vaults', function () {
    openShopDay($this->user, 10000, 2);

    Vault::updateOrCreate(
        ['type' => VaultType::SILVER->value],
        ['name' => VaultType::SILVER->value, 'balance' => 5]
    );

    $karigar = Karigar::create([
        'name' => 'adjustment karigar',
        'mobile' => '7777777776',
        'work_type' => 'silver work',
        'city' => 'mumbai',
    ]);

    post(route('ledger.store-entry'), [
        'party_type' => Karigar::class,
        'party_id' => $karigar->id,
        'entry_type' => 'CASH_TO_GOLD',
        'cash_amount' => 7000,
        'rate' => 7000,
        'purity' => 91.6,
        'description' => 'Cash converted into gold settlement',
        'date' => today()->toDateString(),
    ])->assertRedirect();

    expect((float) Vault::where('type', VaultType::CASH->value)->value('balance'))->toBe(3000.0)
        ->and((float) Vault::where('type', VaultType::GOLD->value)->value('balance'))->toBe(3.0)
        ->and((float) Vault::where('type', VaultType::SILVER->value)->value('balance'))->toBe(5.0);

    post(route('ledger.store-entry'), [
        'party_type' => Karigar::class,
        'party_id' => $karigar->id,
        'entry_type' => 'CASH_TO_SILVER',
        'cash_amount' => 2000,
        'rate' => 1000,
        'purity' => 92.5,
        'description' => 'Cash converted into silver settlement',
        'date' => today()->toDateString(),
    ])->assertRedirect();

    expect((float) Vault::where('type', VaultType::CASH->value)->value('balance'))->toBe(1000.0)
        ->and((float) Vault::where('type', VaultType::GOLD->value)->value('balance'))->toBe(3.0)
        ->and((float) Vault::where('type', VaultType::SILVER->value)->value('balance'))->toBe(7.0);

    post(route('ledger.store-entry'), [
        'party_type' => Karigar::class,
        'party_id' => $karigar->id,
        'entry_type' => 'SILVER_TO_CASH',
        'gold_weight' => 1.500,
        'rate' => 1000,
        'purity' => 92.5,
        'description' => 'Silver adjusted instead of cash receipt',
        'date' => today()->toDateString(),
    ])->assertRedirect();

    expect((float) Vault::where('type', VaultType::CASH->value)->value('balance'))->toBe(1000.0)
        ->and((float) Vault::where('type', VaultType::GOLD->value)->value('balance'))->toBe(3.0)
        ->and((float) Vault::where('type', VaultType::SILVER->value)->value('balance'))->toBe(8.5);
});

it('shows ledger transactions in business-date order for running balances', function () {
    openShopDay($this->user, 5000, 0);

    $supplier = Supplier::create([
        'company_name' => 'sorting supplier',
        'contact_person' => 'harish',
        'mobile' => '8888888885',
        'type' => 'GOLD',
    ]);

    post(route('ledger.store-entry'), [
        'party_type' => Supplier::class,
        'party_id' => $supplier->id,
        'entry_type' => 'PAY_CASH',
        'cash_amount' => 1000,
        'payment_method' => 'CASH',
        'description' => 'Later business date',
        'date' => '2026-03-10',
    ])->assertRedirect();

    post(route('ledger.store-entry'), [
        'party_type' => Supplier::class,
        'party_id' => $supplier->id,
        'entry_type' => 'RECEIVE_CASH',
        'cash_amount' => 250,
        'payment_method' => 'CASH',
        'description' => 'Earlier business date added later',
        'date' => '2026-03-09',
    ])->assertRedirect();

    get(route('ledger.show', ['type' => 'suppliers', 'id' => $supplier->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledger/Show')
            ->where('transactions.0.date', '2026-03-09')
            ->where('transactions.1.date', '2026-03-10'));
});

it('keeps stock product inventory separate from the loose gold vault', function () {
    openShopDay($this->user, 0, 0);

    $supplier = Supplier::create([
        'company_name' => 'stock supplier',
        'contact_person' => 'bharat',
        'mobile' => '8888888883',
        'type' => 'GOLD',
    ]);

    $category = new Category();
    $category->name = 'Chain';
    $category->code = 'CHN';
    $category->save();

    $purity = new Purity();
    $purity->name = '22K';
    $purity->save();

    post(route('products.store'), [
        'name' => 'Chain Stock',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 5,
        'net_weight' => 4.5,
        'making_charge' => 1200,
    ])->assertRedirect();

    $product = Product::firstOrFail();

    expect((float) Vault::where('type', VaultType::GOLD->value)->value('balance'))->toBe(0.0);

    patch(route('products.update', $product), [
        'name' => 'Chain Stock',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 5.2,
        'net_weight' => 4.8,
        'making_charge' => 1200,
    ])->assertRedirect();

    expect((float) Vault::where('type', VaultType::GOLD->value)->value('balance'))->toBe(0.0);

    delete(route('products.destroy', $product))->assertRedirect();

    expect(Product::count())->toBe(0)
        ->and((float) Vault::where('type', VaultType::GOLD->value)->value('balance'))->toBe(0.0);
});

it('transfers money between cash and bank vaults without touching party ledgers', function () {
    openShopDay($this->user, 0, 0);

    Vault::updateOrCreate(
        ['type' => VaultType::CASH->value],
        ['name' => VaultType::CASH->value, 'balance' => 5000]
    );

    Vault::updateOrCreate(
        ['type' => VaultType::BANK->value],
        ['name' => VaultType::BANK->value, 'balance' => 1000]
    );

    post(route('dashboard.add-funds'), [
        'from_vault' => 'CASH',
        'to_vault' => 'BANK',
        'amount' => 1200,
        'date' => today()->toDateString(),
        'note' => 'Cash deposited in bank',
    ])->assertRedirect();

    expect((float) Vault::where('type', VaultType::CASH->value)->value('balance'))->toBe(3800.0)
        ->and((float) Vault::where('type', VaultType::BANK->value)->value('balance'))->toBe(2200.0)
        ->and(VaultMovement::query()->where('reference', 'Vault Transfer CASH->BANK')->count())->toBe(2);
});

it('updates gold vault through order assignment and completion', function () {
    openShopDay($this->user, 0, 50);

    $customer = Customer::create([
        'name' => 'deepak soni',
        'mobile' => '9999999992',
        'city' => 'virar',
    ]);

    $karigar = Karigar::create([
        'name' => 'ramesh',
        'mobile' => '7777777771',
        'work_type' => 'rings',
        'city' => 'mumbai',
    ]);

    $order = Order::create([
        'order_number' => 'ORD-0001',
        'customer_id' => $customer->id,
        'due_date' => today()->addDays(7)->toDateString(),
    ]);

    $item = OrderItem::create([
        'order_id' => $order->id,
        'item_name' => 'Custom Ring',
        'target_weight' => 5,
        'purity' => 91.60,
        'status' => 'NEW',
    ]);

    $assignResponse = post(route('orders.assign', $item), [
        'type' => 'Karigar',
        'id' => $karigar->id,
        'issue_gold' => 5,
    ]);

    $assignResponse->assertRedirect();

    expect((float) Vault::where('type', VaultType::GOLD->value)->value('balance'))->toBe(45.0)
        ->and($item->fresh()->status)->toBe('ASSIGNED');

    $completeResponse = post(route('orders.complete', $item), [
        'received_weight' => 4.2,
        'wastage' => 0.3,
    ]);

    $completeResponse->assertRedirect();

    expect((float) Vault::where('type', VaultType::GOLD->value)->value('balance'))->toBe(49.5)
        ->and($item->fresh()->status)->toBe('READY')
        ->and((float) $item->fresh()->finished_weight)->toBe(4.2)
        ->and((float) $item->fresh()->wastage)->toBe(0.3);
});

it('updates silver vault through silver order assignment and completion', function () {
    openShopDay($this->user, 0, 0);

    Vault::updateOrCreate(
        ['type' => VaultType::SILVER->value],
        ['name' => VaultType::SILVER->value, 'balance' => 20]
    );

    $customer = Customer::create([
        'name' => 'silver customer',
        'mobile' => '9999999995',
        'city' => 'virar',
    ]);

    $karigar = Karigar::create([
        'name' => 'silver maker',
        'mobile' => '7777777777',
        'work_type' => 'silver',
        'city' => 'mumbai',
    ]);

    $order = Order::create([
        'order_number' => 'ORD-0100',
        'customer_id' => $customer->id,
        'due_date' => today()->addDays(7)->toDateString(),
    ]);

    $item = OrderItem::create([
        'order_id' => $order->id,
        'item_name' => 'Silver Bracelet',
        'metal_type' => 'SILVER',
        'target_weight' => 3,
        'purity' => 92.50,
        'status' => 'NEW',
    ]);

    post(route('orders.assign', $item), [
        'type' => 'Karigar',
        'id' => $karigar->id,
        'issue_gold' => 3,
    ])->assertRedirect();

    $issueTxn = MetalTransaction::query()
        ->where('party_type', Karigar::class)
        ->where('party_id', $karigar->id)
        ->where('entry_type_code', 'ORDER_ISSUE_SILVER')
        ->latest('id')
        ->firstOrFail();

    expect((float) Vault::where('type', VaultType::SILVER->value)->value('balance'))->toBe(17.0)
        ->and((float) $issueTxn->fine_weight)->toBe(2.775);

    post(route('orders.complete', $item), [
        'received_weight' => 2.800,
        'wastage' => 0.100,
    ])->assertRedirect();

    $receiptTxn = MetalTransaction::query()
        ->where('party_type', Karigar::class)
        ->where('party_id', $karigar->id)
        ->where('entry_type_code', 'ORDER_RECEIVE_SILVER')
        ->latest('id')
        ->firstOrFail();

    expect((float) Vault::where('type', VaultType::SILVER->value)->value('balance'))->toBe(19.9)
        ->and($item->fresh()->status)->toBe('READY')
        ->and((float) $receiptTxn->fine_weight)->toBe(2.683);
});

it('invoices silver custom order items against the silver rate and silver vault', function () {
    openShopDay($this->user, 0, 0);

    Vault::updateOrCreate(
        ['type' => VaultType::SILVER->value],
        ['name' => VaultType::SILVER->value, 'balance' => 10]
    );

    $customer = Customer::create([
        'name' => 'silver invoice customer',
        'mobile' => '9999999996',
        'city' => 'mumbai',
    ]);

    $order = Order::create([
        'order_number' => 'ORD-0200',
        'customer_id' => $customer->id,
        'due_date' => today()->addDays(7)->toDateString(),
    ]);

    $item = OrderItem::create([
        'order_id' => $order->id,
        'item_name' => 'Silver Anklet',
        'metal_type' => 'SILVER',
        'target_weight' => 2.500,
        'finished_weight' => 2.500,
        'purity' => 92.50,
        'status' => 'READY',
    ]);

    post(route('invoices.store'), [
        'customer_id' => $customer->id,
        'silver_rate' => 1000,
        'date' => today()->toDateString(),
        'items' => [
            [
                'type' => 'order_item',
                'id' => $item->id,
                'making_charges' => 250,
            ],
        ],
        'payment_cash' => 0,
        'payment_card' => 0,
    ])->assertRedirect();

    $invoice = Invoice::query()->latest('id')->firstOrFail();
    $invoiceItem = InvoiceItem::query()->where('invoice_id', $invoice->id)->firstOrFail();

    expect((float) $invoice->total_amount)->toBe(2832.5)
        ->and((float) $invoiceItem->rate)->toBe(1000.0)
        ->and((float) $invoiceItem->final_price)->toBe(2750.0)
        ->and($item->fresh()->status)->toBe('DELIVERED')
        ->and((float) Vault::where('type', VaultType::SILVER->value)->value('balance'))->toBe(7.5)
        ->and((float) Vault::where('type', VaultType::GOLD->value)->value('balance'))->toBe(0.0);

    post(route('invoices.cancel', $invoice->id), [
        'mode' => 'refund',
        'reason' => 'Silver order billing reversed.',
    ])->assertRedirect();

    expect($invoice->fresh()->status)->toBe('CANCELLED')
        ->and($item->fresh()->status)->toBe('READY')
        ->and((float) Vault::where('type', VaultType::SILVER->value)->value('balance'))->toBe(10.0)
        ->and((float) Vault::where('type', VaultType::GOLD->value)->value('balance'))->toBe(0.0);
});

it('requires explicit extra gold when finished order weight exceeds issued gold', function () {
    openShopDay($this->user, 0, 10);

    $customer = Customer::create([
        'name' => 'mahesh',
        'mobile' => '9999999993',
        'city' => 'virar',
    ]);

    $karigar = Karigar::create([
        'name' => 'suresh',
        'mobile' => '7777777772',
        'work_type' => 'chains',
        'city' => 'mumbai',
    ]);

    $order = Order::create([
        'order_number' => 'ORD-0002',
        'customer_id' => $customer->id,
        'due_date' => today()->addDays(7)->toDateString(),
    ]);

    $item = OrderItem::create([
        'order_id' => $order->id,
        'item_name' => 'Custom Chain',
        'target_weight' => 3,
        'purity' => 91.60,
        'status' => 'NEW',
    ]);

    post(route('orders.assign', $item), [
        'type' => 'Karigar',
        'id' => $karigar->id,
        'issue_gold' => 2,
    ])->assertRedirect();

    post(route('orders.complete', $item), [
        'received_weight' => 3,
        'wastage' => 0,
    ])->assertSessionHasErrors([
        'extra_gold_added' => 'Finished return exceeds issued gold by 1 g. Record that extra gold before receiving the item.',
    ]);

    post(route('orders.complete', $item), [
        'received_weight' => 3,
        'wastage' => 0,
        'extra_gold_added' => 1,
        'mismatch_note' => 'Additional gold was provided during production.',
    ])->assertSessionHasErrors([
        'extra_gold_source' => 'Select where the extra gold came from before receiving this item.',
    ]);

    expect((float) Vault::where('type', VaultType::GOLD->value)->value('balance'))->toBe(8.0)
        ->and($item->fresh()->status)->toBe('ASSIGNED');

    post(route('orders.complete', $item), [
        'received_weight' => 3,
        'wastage' => 0,
        'extra_gold_added' => 1,
        'extra_gold_source' => 'CUSTOMER',
        'mismatch_note' => 'Additional gold added to complete the chain weight.',
    ])->assertRedirect();

    expect((float) Vault::where('type', VaultType::GOLD->value)->value('balance'))->toBe(11.0)
        ->and($item->fresh()->status)->toBe('READY')
        ->and((float) $item->fresh()->finished_weight)->toBe(3.0)
        ->and((float) $customer->metalTransactions()->where('type', 'RECEIPT')->sum('gross_weight'))->toBe(1.0);
});

it('can source extra production gold from a karigar', function () {
    openShopDay($this->user, 0, 10);

    $customer = Customer::create([
        'name' => 'jitendra',
        'mobile' => '9999999994',
        'city' => 'virar',
    ]);

    $assignedKarigar = Karigar::create([
        'name' => 'omkar',
        'mobile' => '7777777773',
        'work_type' => 'chains',
        'city' => 'mumbai',
    ]);

    $sourceKarigar = Karigar::create([
        'name' => 'vikas',
        'mobile' => '7777777774',
        'work_type' => 'chains',
        'city' => 'mumbai',
    ]);

    $order = Order::create([
        'order_number' => 'ORD-0003',
        'customer_id' => $customer->id,
        'due_date' => today()->addDays(7)->toDateString(),
    ]);

    $item = OrderItem::create([
        'order_id' => $order->id,
        'item_name' => 'Fancy Chain',
        'target_weight' => 3,
        'purity' => 91.60,
        'status' => 'NEW',
    ]);

    post(route('orders.assign', $item), [
        'type' => 'Karigar',
        'id' => $assignedKarigar->id,
        'issue_gold' => 2,
    ])->assertRedirect();

    post(route('orders.complete', $item), [
        'received_weight' => 3,
        'wastage' => 0,
        'extra_gold_added' => 1,
        'extra_gold_source' => 'KARIGAR',
        'extra_gold_karigar_id' => $sourceKarigar->id,
        'mismatch_note' => 'Extra gram provided by another karigar.',
    ])->assertRedirect();

    expect((float) Vault::where('type', VaultType::GOLD->value)->value('balance'))->toBe(11.0)
        ->and((float) $sourceKarigar->metalTransactions()->where('type', 'RECEIPT')->sum('gross_weight'))->toBe(1.0)
        ->and((float) $assignedKarigar->metalTransactions()->where('type', 'ISSUE')->sum('gross_weight'))->toBe(3.0)
        ->and((float) $assignedKarigar->metalTransactions()->where('type', 'RECEIPT')->sum('gross_weight'))->toBe(3.0);
});

it('reopens the same day without creating a duplicate register', function () {
    $firstOpen = post(route('dashboard.open-day'), [
        'opening_cash' => 5000,
        'opening_gold' => 50,
    ]);

    $firstOpen->assertRedirect();

    $register = DailyRegister::firstOrFail();
    $register->update([
        'closing_cash' => 4800,
        'closing_gold' => 49,
        'difference_cash' => -200,
        'closed_at' => now(),
        'closed_by' => $this->user->id,
    ]);

    $secondOpen = post(route('dashboard.open-day'), [
        'opening_cash' => 7000,
        'opening_gold' => 70,
        'mismatch_reason' => 'Physical opening count differs from previous close after manual reconciliation.',
        'reopen_reason' => 'Day was closed early and reopened after final count.',
    ]);

    $secondOpen->assertRedirect();

    $cashVault = Vault::query()->where('type', VaultType::CASH->value)->first();
    $goldVault = Vault::query()->where('type', VaultType::GOLD->value)->first();
    $reopenedRegister = DailyRegister::query()->latest('id')->first();

    expect(DailyRegister::count())->toBe(2)
        ->and($register->fresh()->closed_at)->not->toBeNull()
        ->and((int) $register->fresh()->session_number)->toBe(1)
        ->and($reopenedRegister->closed_at)->toBeNull()
        ->and((int) $reopenedRegister->session_number)->toBe(2)
        ->and((float) $reopenedRegister->opening_cash)->toBe(7000.0)
        ->and((float) $reopenedRegister->opening_gold)->toBe(70.0)
        ->and((float) $reopenedRegister->expected_opening_cash)->toBe(4800.0)
        ->and((float) $reopenedRegister->expected_opening_gold)->toBe(49.0)
        ->and($reopenedRegister->opening_mismatch_reason)->toBe('Physical opening count differs from previous close after manual reconciliation.')
        ->and($reopenedRegister->reopen_reason)->toBe('Day was closed early and reopened after final count.')
        ->and((int) $reopenedRegister->reopened_from_id)->toBe($register->id)
        ->and((float) $cashVault->balance)->toBe(5000.0)
        ->and((float) $goldVault->balance)->toBe(50.0);
});

it('stores both cash and gold reconciliation differences when closing the day', function () {
    post(route('dashboard.open-day'), [
        'opening_cash' => 5000,
        'opening_gold' => 50,
    ])->assertRedirect();

    Vault::query()->updateOrCreate(
        ['type' => VaultType::CASH->value],
        ['name' => VaultType::CASH->value, 'balance' => 1000]
    );

    Vault::query()->updateOrCreate(
        ['type' => VaultType::GOLD->value],
        ['name' => VaultType::GOLD->value, 'balance' => 10]
    );

    post(route('dashboard.close-day'), [
        'closing_cash' => 950,
        'closing_gold' => 9.250,
    ])->assertRedirect(route('dashboard'));

    $register = DailyRegister::query()->latest('id')->firstOrFail();

    expect((float) $register->closing_cash)->toBe(950.0)
        ->and((float) $register->closing_gold)->toBe(9.250)
        ->and((float) $register->difference_cash)->toBe(-50.0)
        ->and((float) $register->difference_gold)->toBe(-0.750)
        ->and($register->closed_at)->not->toBeNull();
});

it('initializes vault balances from the first-ever opening day', function () {
    post(route('dashboard.open-day'), [
        'opening_cash' => 25000,
        'opening_gold' => 50,
    ])->assertRedirect();

    $register = DailyRegister::query()->latest('id')->firstOrFail();
    $cashVault = Vault::query()->where('type', VaultType::CASH->value)->firstOrFail();
    $goldVault = Vault::query()->where('type', VaultType::GOLD->value)->firstOrFail();

    expect((float) $register->opening_cash)->toBe(25000.0)
        ->and((float) $register->opening_gold)->toBe(50.0)
        ->and((float) $cashVault->balance)->toBe(25000.0)
        ->and((float) $goldVault->balance)->toBe(50.0)
        ->and(VaultMovement::query()->where('reference', 'Initial Opening Balance')->count())->toBe(2);
});

function openShopDay(User $user, float $cash, float $gold): void
{
    DailyRegister::create([
        'date' => today()->toDateString(),
        'opening_cash' => $cash,
        'opening_gold' => $gold,
        'opened_by' => $user->id,
    ]);

    Vault::updateOrCreate(
        ['type' => VaultType::CASH->value],
        ['name' => VaultType::CASH->value, 'balance' => $cash]
    );

    Vault::updateOrCreate(
        ['type' => VaultType::GOLD->value],
        ['name' => VaultType::GOLD->value, 'balance' => $gold]
    );
}
