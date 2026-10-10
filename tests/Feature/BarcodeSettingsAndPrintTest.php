<?php

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Product;
use App\Models\Purity;
use App\Models\SilverProduct;
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

it('updates and persists barcode_settings in business settings', function () {
    $response = $this->patch(route('business-settings.update'), [
        'store_name' => 'Maniratn Jewellers',
        'barcode_settings' => [
            'preset' => 'butterfly_84_12',
            'paper_width' => 84,
            'paper_height' => 12,
            'barcode_width' => 41,
            'barcode_height' => 6.8,
            'store_suffix' => 'MRTN-TEST',
            'show_guides' => false,
        ],
    ]);

    $response->assertRedirect();
    $setting = BusinessSetting::first();

    expect($setting->barcode_settings)->toBeArray()
        ->and((float) $setting->barcode_settings['paper_width'])->toBe(84.0)
        ->and($setting->barcode_settings['store_suffix'])->toBe('MRTN-TEST')
        ->and((bool) $setting->barcode_settings['show_guides'])->toBeFalse();
});

it('streams sample test barcode PDF from settings route', function () {
    $response = $this->get(route('business-settings.barcode.test'));

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toContain('%PDF-');
});

it('prints products barcodes with dynamic sizes and query overrides', function () {
    $category = Category::create(['name' => 'Ring', 'code' => 'RNG', 'metal_type' => 'GOLD']);
    $purity = Purity::create(['name' => '22K']);
    $supplier = Supplier::create([
        'company_name' => 'Gold Supplier',
        'contact_person' => 'Person',
        'mobile' => '9999999992',
        'type' => 'GOLD',
    ]);

    $product1 = Product::create([
        'name' => 'Gold Ring 1',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'barcode' => 'MJ-00101',
        'gross_weight' => 5.250,
        'net_weight' => 5.100,
        'making_charge' => 10,
    ]);

    $product2 = Product::create([
        'name' => 'Gold Ring 2',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'barcode' => 'MJ-00102',
        'gross_weight' => 3.500,
        'net_weight' => 3.400,
        'making_charge' => 10,
    ]);

    // Test default print
    $resDefault = $this->get(route('products.print_barcodes') . '?ids=' . $product1->id . ',' . $product2->id);
    $resDefault->assertStatus(200);
    $resDefault->assertHeader('Content-Type', 'application/pdf');
    expect($resDefault->getContent())->toContain('%PDF-');

    // Test with query overrides
    $resOverride = $this->get(route('products.print_barcodes') . '?ids=' . $product1->id . '&paper_width=75&paper_height=12');
    $resOverride->assertStatus(200);
    $resOverride->assertHeader('Content-Type', 'application/pdf');
    expect($resOverride->getContent())->toContain('%PDF-');
});

it('prints silver products barcodes with dynamic sizes', function () {
    $category = Category::create(['name' => 'Payal', 'code' => 'PAY', 'metal_type' => 'SILVER']);
    $supplier = Supplier::create([
        'company_name' => 'Silver Supplier',
        'contact_person' => 'Person',
        'mobile' => '9999999993',
        'type' => 'SILVER',
    ]);

    $silver = SilverProduct::create([
        'name' => 'Silver Payal',
        'category_id' => $category->id,
        'supplier_id' => $supplier->id,
        'pricing_mode' => 'WEIGHT',
        'gross_weight' => 35.500,
        'net_weight' => 35.500,
        'making_charge' => 50,
    ]);

    $response = $this->get(route('silver-products.print_barcodes') . '?ids=' . $silver->id);
    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toContain('%PDF-');
});
