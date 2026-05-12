<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Purity;
use App\Models\Supplier;

it('returns only website-visible unsold gold products for the public website api', function () {
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

    $visibleProduct = Product::create([
        'name' => 'Website Ring',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 10.500,
        'net_weight' => 10.100,
        'making_charge' => 12.50,
        'is_sold' => false,
        'is_visible_on_website' => true,
    ]);

    Product::create([
        'name' => 'Hidden Ring',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 11.500,
        'net_weight' => 11.100,
        'making_charge' => 10.00,
        'is_sold' => false,
        'is_visible_on_website' => false,
    ]);

    Product::create([
        'name' => 'Sold Ring',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 9.500,
        'net_weight' => 9.100,
        'making_charge' => 8.00,
        'is_sold' => true,
        'is_visible_on_website' => true,
    ]);

    $this->getJson(route('website-products.index'))
        ->assertOk()
        ->assertJsonCount(1, 'products')
        ->assertJsonPath('products.0.id', $visibleProduct->id)
        ->assertJsonPath('products.0.barcode', $visibleProduct->barcode)
        ->assertJsonPath('products.0.category', 'Ring')
        ->assertJsonPath('products.0.purity', '22K')
        ->assertJsonPath('products.0.net_weight', 10.1);
});
