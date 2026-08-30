<?php

use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('invoice print page includes customer digital vault qrcode on top right', function () {
    BusinessSetting::create([
        'store_name' => 'Maniratn Jewellers',
        'phone' => '9892820518',
        'email' => 'hello@maniratnjewellers.com',
        'website' => 'https://maniratnjewellers.com',
    ]);

    Permission::findOrCreate('manage_invoices');

    $user = User::factory()->create();
    $user->givePermissionTo('manage_invoices');

    $customer = Customer::create([
        'name' => 'Karan Sharma',
        'mobile' => '9988776655',
        'city' => 'Delhi',
        'vault_token' => 'vault_ABC123XYZ',
        'card_status' => 'ISSUED',
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-20260815-000001',
        'customer_id' => $customer->id,
        'user_id' => $user->id,
        'date' => '2026-08-15',
        'gold_rate_applied' => 7200,
        'total_amount' => 45000,
        'status' => 'COMPLETED',
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'description' => 'Gold Chain 22K',
        'weight' => 6.25,
        'purity' => '22K',
        'rate' => 7200,
        'making_charges' => 12,
        'final_price' => 45000,
    ]);

    $response = $this->actingAs($user)->get(route('invoices.print', $invoice));

    $response->assertOk();
    $response->assertSee('Retail Invoice');
    $response->assertSee('Customer Digital Vault QR Code');
});


test('invoice print generates vault token for customer if missing', function () {
    BusinessSetting::create([
        'store_name' => 'Maniratn Jewellers',
        'website' => 'https://maniratnjewellers.com',
    ]);

    Permission::findOrCreate('manage_invoices');

    $user = User::factory()->create();
    $user->givePermissionTo('manage_invoices');

    $customer = Customer::create([
        'name' => 'Ravi Verma',
        'mobile' => '9911223344',
    ]);

    expect($customer->vault_token)->toBeNull();

    $invoice = Invoice::create([
        'invoice_number' => 'INV-20260815-000002',
        'customer_id' => $customer->id,
        'user_id' => $user->id,
        'date' => '2026-08-15',
        'gold_rate_applied' => 7200,
        'total_amount' => 25000,
        'status' => 'COMPLETED',
    ]);

    $response = $this->actingAs($user)->get(route('invoices.print', $invoice));

    $response->assertOk();
    $customer->refresh();
    expect($customer->vault_token)->not->toBeNull();
    expect($customer->card_status)->toBe('ISSUED');

    $expectedVaultUrl = 'https://maniratnjewellers.com/vault/' . $customer->vault_token;
    $response->assertSee($expectedVaultUrl);
    $response->assertSee('Digital Vault');
});
