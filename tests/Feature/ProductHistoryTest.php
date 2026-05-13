<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Purity;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VerificationTag;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->withoutVite();

    $this->seed(RolesAndPermissionsSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin');

    $this->actingAs($this->user);
});

it('returns product history timeline for drawer', function () {
    $category = Category::create([
        'name' => 'Ring',
        'code' => 'RNG',
        'metal_type' => 'GOLD',
    ]);

    $purity = Purity::create(['name' => '22K']);

    $supplier = Supplier::create([
        'company_name' => 'Raj Gold House',
        'contact_person' => 'Rajesh Bhai',
        'mobile' => '8888888881',
        'type' => 'GOLD',
    ]);

    $product = Product::create([
        'name' => 'History Ring',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 10,
        'net_weight' => 9.5,
        'making_charge' => 12.5,
    ]);

    $product->update([
        'making_charge' => 13.5,
    ]);

    VerificationTag::create([
        'token' => VerificationTag::generateToken(),
        'tag_type' => 'NFC',
        'status' => 'PENDING',
        'is_active' => true,
        'product_id' => $product->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->getJson(route('products.history', $product));

    $response->assertOk()
        ->assertJsonPath('product.id', $product->id)
        ->assertJsonPath('product.barcode', $product->barcode);

    expect(collect($response->json('timeline'))->pluck('title')->all())
        ->toContain('Product created')
        ->toContain('Verification tag created');
});
