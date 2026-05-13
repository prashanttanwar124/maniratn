<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Purity;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();

    $this->seed(RolesAndPermissionsSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin');

    $this->actingAs($this->user);
});

it('filters product inventory by stock status supplier purity and category', function () {
    $category = Category::create([
        'name' => 'Ring',
        'code' => 'RNG',
        'metal_type' => 'GOLD',
    ]);

    $otherCategory = Category::create([
        'name' => 'Chain',
        'code' => 'CHN',
        'metal_type' => 'GOLD',
    ]);

    $purity = Purity::create(['name' => '22K']);
    $otherPurity = Purity::create(['name' => '18K']);

    $supplier = Supplier::create([
        'company_name' => 'Raj Gold House',
        'contact_person' => 'Rajesh Bhai',
        'mobile' => '8888888881',
        'type' => 'GOLD',
    ]);

    $otherSupplier = Supplier::create([
        'company_name' => 'Milan Gold House',
        'contact_person' => 'Milan Bhai',
        'mobile' => '8888888882',
        'type' => 'GOLD',
    ]);

    $matchingProduct = Product::create([
        'name' => 'Ring One',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 10,
        'net_weight' => 9.5,
        'making_charge' => 12,
        'is_sold' => false,
    ]);

    Product::create([
        'name' => 'Ring Sold',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 11,
        'net_weight' => 10.5,
        'making_charge' => 12,
        'is_sold' => true,
    ]);

    Product::create([
        'name' => 'Ring Other Purity',
        'category_id' => $category->id,
        'purity_id' => $otherPurity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 12,
        'net_weight' => 11.5,
        'making_charge' => 12,
        'is_sold' => false,
    ]);

    Product::create([
        'name' => 'Ring Other Supplier',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $otherSupplier->id,
        'gross_weight' => 13,
        'net_weight' => 12.5,
        'making_charge' => 12,
        'is_sold' => false,
    ]);

    Product::create([
        'name' => 'Chain One',
        'category_id' => $otherCategory->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 14,
        'net_weight' => 13.5,
        'making_charge' => 12,
        'is_sold' => false,
    ]);

    $response = $this->get(route('products.index', [
        'stock_status' => 'available',
        'supplier_id' => $supplier->id,
        'purity_id' => $purity->id,
        'category_id' => $category->id,
    ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/Index')
            ->where('summary.total_items', 1)
            ->where('summary.available_items', 1)
            ->where('summary.sold_items', 0));

    $productIds = collect($response->viewData('page')['props']['products']['data'])->pluck('id')->all();

    expect($productIds)->toBe([$matchingProduct->id]);
});
