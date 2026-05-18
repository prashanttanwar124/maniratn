<?php

use App\Models\Category;
use App\Models\DailyRegister;
use App\Models\SilverProduct;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VerificationTag;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();

    $this->seed(RolesAndPermissionsSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin');

    $this->actingAs($this->user);
});

it('filters silver inventory by stock status supplier category and pricing mode', function () {
    [$category, $supplier] = silverDependencies();

    $otherCategory = Category::create([
        'name' => 'Coins',
        'code' => 'CON',
        'metal_type' => 'SILVER',
    ]);

    $otherSupplier = Supplier::create([
        'company_name' => 'Silver Star',
        'contact_person' => 'Star Bhai',
        'mobile' => '7777777772',
        'type' => 'SILVER',
    ]);

    $matching = SilverProduct::create([
        'name' => 'Silver Chain',
        'category_id' => $category->id,
        'supplier_id' => $supplier->id,
        'pricing_mode' => 'PIECE',
        'quantity' => 2,
        'gross_weight' => 10,
        'net_weight' => 9.5,
        'piece_price' => 1000,
        'making_charge' => 100,
    ]);

    SilverProduct::create([
        'name' => 'Silver Sold',
        'category_id' => $category->id,
        'supplier_id' => $supplier->id,
        'pricing_mode' => 'PIECE',
        'quantity' => 1,
        'gross_weight' => 8,
        'net_weight' => 7.5,
        'piece_price' => 900,
        'making_charge' => 90,
        'is_sold' => true,
    ]);

    SilverProduct::create([
        'name' => 'Weight Product',
        'category_id' => $category->id,
        'supplier_id' => $supplier->id,
        'pricing_mode' => 'WEIGHT',
        'quantity' => 1,
        'gross_weight' => 12,
        'net_weight' => 11.5,
        'piece_price' => null,
        'making_charge' => 95,
    ]);

    SilverProduct::create([
        'name' => 'Other Supplier',
        'category_id' => $category->id,
        'supplier_id' => $otherSupplier->id,
        'pricing_mode' => 'PIECE',
        'quantity' => 1,
        'gross_weight' => 7,
        'net_weight' => 6.5,
        'piece_price' => 800,
        'making_charge' => 85,
    ]);

    SilverProduct::create([
        'name' => 'Other Category',
        'category_id' => $otherCategory->id,
        'supplier_id' => $supplier->id,
        'pricing_mode' => 'PIECE',
        'quantity' => 1,
        'gross_weight' => 7,
        'net_weight' => 6.5,
        'piece_price' => 800,
        'making_charge' => 85,
    ]);

    $response = $this->get(route('silver-products.index', [
        'stock_status' => 'available',
        'supplier_id' => $supplier->id,
        'category_id' => $category->id,
        'pricing_mode' => 'PIECE',
    ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('silver-products/Index')
            ->where('summary.total_items', 1)
            ->where('summary.available_items', 1)
            ->where('summary.sold_items', 0));

    $productIds = collect($response->viewData('page')['props']['silverProducts']['data'])->pluck('id')->all();

    expect($productIds)->toBe([$matching->id]);
});

it('updates selected silver products through bulk action', function () {
    openShopDayForSilverFeatures($this->user);

    [$category, $supplier] = silverDependencies();

    $newCategory = Category::create([
        'name' => 'Bracelets',
        'code' => 'BRC',
        'metal_type' => 'SILVER',
    ]);

    $newSupplier = Supplier::create([
        'company_name' => 'Silver City',
        'contact_person' => 'City Bhai',
        'mobile' => '7777777773',
        'type' => 'SILVER',
    ]);

    $products = collect(range(1, 2))->map(fn ($index) => SilverProduct::create([
        'name' => 'Bulk Silver ' . $index,
        'category_id' => $category->id,
        'supplier_id' => $supplier->id,
        'pricing_mode' => 'PIECE',
        'quantity' => 1,
        'gross_weight' => 5 + $index,
        'net_weight' => 4.5 + $index,
        'piece_price' => 1000 + $index,
        'making_charge' => 100,
    ]));

    $this->post(route('silver-products.bulk-update'), [
        'product_ids' => $products->pluck('id')->all(),
        'category_id' => $newCategory->id,
        'supplier_id' => $newSupplier->id,
        'pricing_mode' => 'WEIGHT',
        'making_charge' => 150,
        'piece_price' => 0,
        'notes' => 'Updated in bulk',
    ])->assertRedirect();

    $products->each(function (SilverProduct $product) use ($newCategory, $newSupplier) {
        expect($product->fresh()->category_id)->toBe($newCategory->id)
            ->and($product->fresh()->supplier_id)->toBe($newSupplier->id)
            ->and($product->fresh()->pricing_mode)->toBe('WEIGHT')
            ->and((float) $product->fresh()->making_charge)->toBe(150.0)
            ->and($product->fresh()->notes)->toBe('Updated in bulk');
    });
});

it('duplicates a silver product with a new barcode', function () {
    openShopDayForSilverFeatures($this->user);

    [$category, $supplier] = silverDependencies();

    $product = SilverProduct::create([
        'name' => 'Silver Ring',
        'category_id' => $category->id,
        'supplier_id' => $supplier->id,
        'pricing_mode' => 'PIECE',
        'quantity' => 2,
        'gross_weight' => 10,
        'net_weight' => 9.5,
        'piece_price' => 1200,
        'making_charge' => 150,
        'notes' => 'Original',
    ]);

    $this->post(route('silver-products.duplicate', $product))
        ->assertRedirect();

    expect(SilverProduct::count())->toBe(2);

    $duplicate = SilverProduct::query()->whereKeyNot($product->id)->firstOrFail();

    expect($duplicate->barcode)->not->toBe($product->barcode)
        ->and($duplicate->category_id)->toBe($product->category_id)
        ->and($duplicate->supplier_id)->toBe($product->supplier_id)
        ->and($duplicate->pricing_mode)->toBe($product->pricing_mode)
        ->and((int) $duplicate->quantity)->toBe((int) $product->quantity);
});

it('finds a silver product by barcode in quick scan route', function () {
    [$category, $supplier] = silverDependencies();

    $product = SilverProduct::create([
        'name' => 'Silver Scan',
        'category_id' => $category->id,
        'supplier_id' => $supplier->id,
        'pricing_mode' => 'PIECE',
        'quantity' => 1,
        'gross_weight' => 8,
        'net_weight' => 7.5,
        'piece_price' => 950,
        'making_charge' => 100,
    ]);

    $this->getJson(route('silver-products.scan', ['barcode' => $product->barcode]))
        ->assertOk()
        ->assertJsonPath('product.id', $product->id)
        ->assertJsonPath('product.barcode', $product->barcode);
});

it('returns silver product history timeline for drawer', function () {
    [$category, $supplier] = silverDependencies();

    $product = SilverProduct::create([
        'name' => 'Silver History',
        'category_id' => $category->id,
        'supplier_id' => $supplier->id,
        'pricing_mode' => 'PIECE',
        'quantity' => 1,
        'gross_weight' => 8,
        'net_weight' => 7.5,
        'piece_price' => 950,
        'making_charge' => 100,
    ]);

    VerificationTag::create([
        'token' => VerificationTag::generateToken(),
        'tag_type' => 'NFC',
        'status' => 'PENDING',
        'is_active' => true,
        'silver_product_id' => $product->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->getJson(route('silver-products.history', $product));

    $response->assertOk()
        ->assertJsonPath('product.id', $product->id)
        ->assertJsonPath('product.barcode', $product->barcode);

    expect(collect($response->json('timeline'))->pluck('title')->all())
        ->toContain('Silver product created')
        ->toContain('Verification tag created');
});

it('keeps silver inventory pagination stable when many products share same timestamp', function () {
    [$category, $supplier] = silverDependencies();

    for ($index = 1; $index <= 11; $index++) {
        SilverProduct::create([
            'name' => 'Silver Stable ' . $index,
            'category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'pricing_mode' => 'PIECE',
            'quantity' => 1,
            'gross_weight' => 5 + $index,
            'net_weight' => 4.5 + $index,
            'piece_price' => 1000 + $index,
            'making_charge' => 100,
        ]);
    }

    $pageOneResponse = $this->get(route('silver-products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('silver-products/Index'));

    $pageTwoResponse = $this->get(route('silver-products.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('silver-products/Index'));

    $pageOneIds = collect($pageOneResponse->viewData('page')['props']['silverProducts']['data'])->pluck('id')->all();
    $pageTwoIds = collect($pageTwoResponse->viewData('page')['props']['silverProducts']['data'])->pluck('id')->all();

    expect($pageOneIds)->toHaveCount(10)
        ->and($pageTwoIds)->toHaveCount(1)
        ->and(array_intersect($pageOneIds, $pageTwoIds))->toBe([]);
});

function silverDependencies(): array
{
    $category = Category::firstOrCreate([
        'name' => 'Chain',
        'code' => 'CHS',
    ], [
        'metal_type' => 'SILVER',
    ]);

    $supplier = Supplier::firstOrCreate([
        'company_name' => 'Raj Silver House',
    ], [
        'contact_person' => 'Rajesh Bhai',
        'mobile' => '7777777771',
        'type' => 'SILVER',
    ]);

    return [$category, $supplier];
}

function openShopDayForSilverFeatures(User $user): void
{
    DailyRegister::create([
        'date' => today()->toDateString(),
        'opening_cash' => 0,
        'opening_gold' => 0,
        'opening_silver' => 0,
        'opened_by' => $user->id,
    ]);
}
