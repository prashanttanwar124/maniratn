<?php

use App\Models\Category;
use App\Models\DailyRegister;
use App\Models\Product;
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

it('updates selected products through bulk action', function () {
    openShopDayForProductActions($this->user);

    [$category, $purity, $supplier] = productActionDependencies();

    $newCategory = Category::create([
        'name' => 'Bangle',
        'code' => 'BNG',
        'metal_type' => 'GOLD',
    ]);

    $newPurity = Purity::create(['name' => '18K']);

    $newSupplier = Supplier::create([
        'company_name' => 'Milan Gold House',
        'contact_person' => 'Milan Bhai',
        'mobile' => '8888888882',
        'type' => 'GOLD',
    ]);

    $products = collect(range(1, 2))->map(fn ($index) => Product::create([
        'name' => 'Bulk Product ' . $index,
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 10 + $index,
        'net_weight' => 9 + $index,
        'making_charge' => 10,
    ]));

    $this->post(route('products.bulk-update'), [
        'product_ids' => $products->pluck('id')->all(),
        'category_id' => $newCategory->id,
        'purity_id' => $newPurity->id,
        'supplier_id' => $newSupplier->id,
        'making_charge' => 15.5,
    ])->assertRedirect();

    $products->each(function (Product $product) use ($newCategory, $newPurity, $newSupplier) {
        expect($product->fresh()->category_id)->toBe($newCategory->id)
            ->and($product->fresh()->purity_id)->toBe($newPurity->id)
            ->and($product->fresh()->supplier_id)->toBe($newSupplier->id)
            ->and((float) $product->fresh()->making_charge)->toBe(15.5);
    });
});

it('duplicates a product with a new barcode', function () {
    openShopDayForProductActions($this->user);

    [$category, $purity, $supplier] = productActionDependencies();

    $product = Product::create([
        'name' => 'Gold Ring - G00001',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 10,
        'net_weight' => 9.5,
        'making_charge' => 12.5,
    ]);

    $this->post(route('products.duplicate', $product))
        ->assertRedirect();

    expect(Product::count())->toBe(2);

    $duplicate = Product::query()->whereKeyNot($product->id)->firstOrFail();

    expect($duplicate->barcode)->not->toBe($product->barcode)
        ->and($duplicate->category_id)->toBe($product->category_id)
        ->and($duplicate->purity_id)->toBe($product->purity_id)
        ->and($duplicate->supplier_id)->toBe($product->supplier_id)
        ->and((float) $duplicate->gross_weight)->toBe((float) $product->gross_weight)
        ->and((float) $duplicate->net_weight)->toBe((float) $product->net_weight);
});

it('finds a product by barcode in quick scan route', function () {
    [$category, $purity, $supplier] = productActionDependencies();

    $product = Product::create([
        'name' => 'Gold Ring',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 10,
        'net_weight' => 9.5,
        'making_charge' => 12.5,
    ]);

    $this->getJson(route('products.scan', ['barcode' => $product->barcode]))
        ->assertOk()
        ->assertJsonPath('product.id', $product->id)
        ->assertJsonPath('product.barcode', $product->barcode);
});

function productActionDependencies(): array
{
    $category = Category::firstOrCreate([
        'name' => 'Ring',
        'code' => 'RNG',
    ], [
        'metal_type' => 'GOLD',
    ]);

    $purity = Purity::firstOrCreate([
        'name' => '22K',
    ]);

    $supplier = Supplier::firstOrCreate([
        'company_name' => 'Raj Gold House',
    ], [
        'contact_person' => 'Rajesh Bhai',
        'mobile' => '8888888881',
        'type' => 'GOLD',
    ]);

    return [$category, $purity, $supplier];
}

function openShopDayForProductActions(User $user): void
{
    DailyRegister::create([
        'date' => today()->toDateString(),
        'opening_cash' => 0,
        'opening_gold' => 0,
        'opening_silver' => 0,
        'opened_by' => $user->id,
    ]);
}
