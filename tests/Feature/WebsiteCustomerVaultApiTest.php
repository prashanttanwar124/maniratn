<?php

use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('public website api returns customer digital vault data by token', function () {
    $business = BusinessSetting::create([
        'store_name' => 'Maniratn Jewellers',
        'phone' => '9892820518',
        'email' => 'hello@maniratnjewellers.com',
        'website' => 'https://maniratnjewellers.com',
    ]);

    $user = User::factory()->create();

    $customer = Customer::create([
        'name' => 'Arjun Solanki',
        'mobile' => '9876543210',
        'city' => 'Mumbai',
        'vault_token' => 'vault_TEST123456',
        'card_status' => 'ISSUED',
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-001',
        'customer_id' => $customer->id,
        'user_id' => $user->id,
        'date' => now()->toDateString(),
        'gold_rate_applied' => 7000,
        'total_amount' => 50000,
        'status' => 'COMPLETED',
    ]);

    $category = \App\Models\Category::create(['name' => 'Rings', 'code' => 'RNG']);
    $purity = \App\Models\Purity::create(['name' => '22K']);
    $supplier = \App\Models\Supplier::create(['contact_person' => 'Gold Supplier', 'company_name' => 'Gold Supplier Corp', 'mobile' => '9898989898']);

    $product = Product::create([
        'barcode' => 'G00001',
        'name' => 'Gold Ring 22K',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 5.25,
        'net_weight' => 5.00,
        'making_charge' => 500,
        'is_sold' => true,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'description' => 'Gold Ring 22K',
        'weight' => 5.25,
        'purity' => '22K',
        'rate' => 7000,
        'final_price' => 35000,
    ]);

    $response = $this->getJson(route('website.vault.show', 'vault_TEST123456'));

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'customer' => [
                'name' => 'Arjun Solanki',
                'city' => 'Mumbai',
            ],
            'stats' => [
                'total_items' => 1,
                'total_gold_weight' => 5.25,
                'total_invoices' => 1,
            ],
        ]);
});

test('public website api returns 404 for disabled or non-existent vault cards', function () {
    $customer = Customer::create([
        'name' => 'Disabled Customer',
        'mobile' => '9876543211',
        'vault_token' => 'vault_DISABLED123',
        'card_status' => 'DISABLED',
    ]);

    $this->getJson(route('website.vault.show', 'vault_DISABLED123'))
        ->assertNotFound();

    $this->getJson(route('website.vault.show', 'vault_INVALID'))
        ->assertNotFound();
});
