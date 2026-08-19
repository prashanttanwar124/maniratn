<?php

use App\Enums\VaultType;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DailyRegister;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Purity;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vault;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    Vault::updateOrCreate(['type' => VaultType::CASH->value], ['name' => 'Cash Vault', 'balance' => 1000]);
    Vault::updateOrCreate(['type' => VaultType::BANK->value], ['name' => 'Bank Vault', 'balance' => 0]);
    Vault::updateOrCreate(['type' => VaultType::GOLD->value], ['name' => 'Gold Vault', 'balance' => 100]);
    Vault::updateOrCreate(['type' => VaultType::SILVER->value], ['name' => 'Silver Vault', 'balance' => 0]);

    DailyRegister::create([
        'date' => today()->toDateString(),
        'opening_cash' => 1000,
        'opening_gold' => 100,
        'opened_by' => $this->user->id,
        'status' => 'OPEN',
    ]);

    $this->customer = Customer::create([
        'name' => 'Making Charge Customer',
        'mobile' => '9876500001',
        'city' => 'Jaipur',
    ]);

    $this->category = Category::create(['name' => 'Ring', 'code' => 'RNG']);
    $this->purity = Purity::create(['name' => '22K']);
    $this->supplier = Supplier::create([
        'company_name' => 'Supplier Co',
        'contact_person' => 'Contact',
        'mobile' => '9999900001',
        'type' => 'GOLD',
    ]);
});

it('creates invoice with percentage making charges', function () {
    $product = Product::create([
        'name' => 'Gold Ring 22K',
        'category_id' => $this->category->id,
        'purity_id' => $this->purity->id,
        'supplier_id' => $this->supplier->id,
        'gross_weight' => 2.0,
        'net_weight' => 2.0,
        'making_charge' => 10,
    ]);

    $response = $this->post(route('invoices.store'), [
        'customer_id' => $this->customer->id,
        'date' => today()->toDateString(),
        'gold_rate' => 7000,
        'items' => [
            [
                'type' => 'product',
                'id' => $product->id,
                'rate' => 7000,
                'making_charges' => 10,
                'making_charge_type' => 'percentage',
                'quantity' => 1,
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $item = InvoiceItem::where('product_id', $product->id)->first();
    expect($item)->not->toBeNull()
        ->and($item->making_charge_type)->toBe('percentage')
        ->and((float) $item->making_charges)->toBe(10.0)
        // 2g * 7000 = 14000; + 10% making (1400) = 15400
        ->and((float) $item->final_price)->toBe(15400.0);
});

it('creates invoice with flat lump-sum making charges', function () {
    $product = Product::create([
        'name' => 'Gold Bangles 22K',
        'category_id' => $this->category->id,
        'purity_id' => $this->purity->id,
        'supplier_id' => $this->supplier->id,
        'gross_weight' => 5.0,
        'net_weight' => 5.0,
        'making_charge' => 0,
    ]);

    $response = $this->post(route('invoices.store'), [
        'customer_id' => $this->customer->id,
        'date' => today()->toDateString(),
        'gold_rate' => 7000,
        'items' => [
            [
                'type' => 'product',
                'id' => $product->id,
                'rate' => 7000,
                'making_charges' => 1500, // Flat ₹1,500 lump sum making
                'making_charge_type' => 'flat',
                'quantity' => 1,
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $item = InvoiceItem::where('product_id', $product->id)->first();
    expect($item)->not->toBeNull()
        ->and($item->making_charge_type)->toBe('flat')
        ->and((float) $item->making_charges)->toBe(1500.0)
        // 5g * 7000 = 35000; + 1500 flat making = 36500
        ->and((float) $item->final_price)->toBe(36500.0);
});

it('creates invoice with per-gram making charges', function () {
    $product = Product::create([
        'name' => 'Gold Chain 22K',
        'category_id' => $this->category->id,
        'purity_id' => $this->purity->id,
        'supplier_id' => $this->supplier->id,
        'gross_weight' => 4.0,
        'net_weight' => 4.0,
        'making_charge' => 0,
    ]);

    $response = $this->post(route('invoices.store'), [
        'customer_id' => $this->customer->id,
        'date' => today()->toDateString(),
        'gold_rate' => 7000,
        'items' => [
            [
                'type' => 'product',
                'id' => $product->id,
                'rate' => 7000,
                'making_charges' => 500, // ₹500/g
                'making_charge_type' => 'per_gram',
                'quantity' => 1,
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $item = InvoiceItem::where('product_id', $product->id)->first();
    expect($item)->not->toBeNull()
        ->and($item->making_charge_type)->toBe('per_gram')
        ->and((float) $item->making_charges)->toBe(500.0)
        // 4g * 7000 = 28000; + 4g * 500 = 2000 => 30000
        ->and((float) $item->final_price)->toBe(30000.0);
});
