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

it('shows website product catalog page', function () {
    $this->get(route('website-products.manage'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('website-products/Index')
            ->has('products.data')
            ->where('endpointUrl', url('/api/website/products')));
});

it('updates website visibility from separate website catalog page', function () {
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
        'name' => 'Website Ring',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 10.500,
        'net_weight' => 10.100,
        'making_charge' => 12.50,
        'is_visible_on_website' => false,
    ]);

    $this->patch(route('website-products.update', $product), [
        'is_visible_on_website' => true,
    ])->assertRedirect();

    expect((bool) $product->fresh()->is_visible_on_website)->toBeTrue();
});
