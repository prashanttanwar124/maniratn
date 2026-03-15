<?php

use App\Enums\VaultType;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DailyRegister;
use App\Models\Invoice;
use App\Models\Karigar;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Purity;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultMovement;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
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

    $response->assertCreated();

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
    ])->assertCreated();

    $invoice = Invoice::query()->latest('id')->firstOrFail();

    expect((float) $invoice->discount_value)->toBe(10.0)
        ->and((float) $invoice->discount_amount)->toBe(1100.0)
        ->and((float) $invoice->tax_amount)->toBe(297.0)
        ->and((float) $invoice->total_amount)->toBe(10197.0)
        ->and((float) Transaction::query()->where('invoice_id', $invoice->id)->where('type', 'SALE')->value('amount'))->toBe(10197.0);
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
