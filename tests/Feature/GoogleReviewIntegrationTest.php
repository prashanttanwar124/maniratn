<?php

use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Category;
use App\Models\Purity;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin');

    $this->actingAs($this->user);
});

it('updates and persists google_review_url in business settings', function () {
    $response = $this->patch(route('business-settings.update'), [
        'store_name' => 'Maniratn Jewellers',
        'address' => 'Main Market, City',
        'phone' => '9876543210',
        'email' => 'contact@maniratnjewellers.com',
        'website' => 'https://maniratnjewellers.com',
        'google_review_url' => 'https://g.page/r/maniratn/review',
        'gst_number' => '24ABCDE1234F1Z5',
    ]);

    $response->assertRedirect();
    $setting = BusinessSetting::first();
    expect($setting->google_review_url)->toBe('https://g.page/r/maniratn/review')
        ->and($setting->store_name)->toBe('Maniratn Jewellers');
});

it('includes google review qr code and url in invoice print view', function () {
    BusinessSetting::updateOrCreate(
        ['id' => 1],
        [
            'store_name' => 'Maniratn Jewellers',
            'google_review_url' => 'https://maps.app.goo.gl/testreviewlink',
        ]
    );

    $customer = Customer::create([
        'name' => 'Review Test Customer',
        'mobile' => '9876543200',
        'city' => 'Mumbai',
    ]);

    $supplier = Supplier::create([
        'company_name' => 'Supplier Co',
        'contact_person' => 'Supplier Person',
        'mobile' => '9999999991',
        'type' => 'GOLD',
    ]);

    $category = Category::create(['name' => 'Ring', 'code' => 'RNG']);
    $purity = Purity::create(['name' => '22K']);

    $product = Product::create([
        'name' => 'Gold Ring 22K',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 2.0,
        'net_weight' => 2.0,
        'making_charge' => 0,
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-REVIEW-001',
        'customer_id' => $customer->id,
        'user_id' => $this->user->id,
        'gold_rate_applied' => 7000,
        'date' => today()->toDateString(),
        'total_amount' => 14000,
        'status' => 'PAID',
    ]);

    $response = $this->get(route('invoices.print', $invoice->id));
    $response->assertOk();
    $response->assertSee('RATE YOUR EXPERIENCE ON GOOGLE');
    $response->assertSee('Scan QR code to leave us a 5-star review on Google Maps!');
});

it('renders printable counter review standee with QR code', function () {
    BusinessSetting::updateOrCreate(
        ['id' => 1],
        [
            'store_name' => 'Maniratn Jewellers',
            'google_review_url' => 'https://maps.app.goo.gl/teststandeelink',
            'phone' => '9876543210',
            'address' => 'Station Road, Showroom 1',
        ]
    );

    $response = $this->get(route('business-settings.standee.print'));
    $response->assertOk();
    $response->assertSee('Rate Us On Google');
    $response->assertSee('Maniratn Jewellers');
    $response->assertSee('Station Road, Showroom 1');
});
