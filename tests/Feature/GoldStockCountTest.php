<?php

use App\Models\Category;
use App\Models\DailyRegister;
use App\Models\GoldStockCountEntry;
use App\Models\GoldStockCountSession;
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

it('shows gold stock count page', function () {
    $this->get(route('gold-stock-count.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('gold-stock-count/Index')
            ->where('dayOpen', false));
});

it('counts an unsold gold product into today session', function () {
    openShopDayForGoldCount($this->user);

    $product = goldCountProduct([
        'name' => 'Counted Ring',
        'is_sold' => false,
    ]);

    $response = $this->postJson(route('gold-stock-count.scan'), [
        'barcode' => $product->barcode,
    ]);

    $response->assertOk()
        ->assertJsonPath('countedProduct.id', $product->id)
        ->assertJsonPath('summary.expected_items', 1)
        ->assertJsonPath('summary.counted_items', 1)
        ->assertJsonPath('summary.remaining_items', 0);

    expect(GoldStockCountSession::count())->toBe(1)
        ->and(GoldStockCountEntry::count())->toBe(1);
});

it('blocks duplicate scan of same gold product', function () {
    openShopDayForGoldCount($this->user);

    $product = goldCountProduct([
        'name' => 'Duplicate Scan Ring',
        'is_sold' => false,
    ]);

    $this->postJson(route('gold-stock-count.scan'), [
        'barcode' => $product->barcode,
    ])->assertOk();

    $this->postJson(route('gold-stock-count.scan'), [
        'barcode' => $product->barcode,
    ])->assertStatus(422);
});

it('does not count sold gold product', function () {
    openShopDayForGoldCount($this->user);

    $product = goldCountProduct([
        'name' => 'Sold Ring',
        'is_sold' => true,
    ]);

    $this->postJson(route('gold-stock-count.scan'), [
        'barcode' => $product->barcode,
    ])->assertStatus(422);
});

function goldCountProduct(array $overrides = []): Product
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

    return Product::create(array_merge([
        'name' => 'Gold Ring',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 10,
        'net_weight' => 9.5,
        'making_charge' => 12.5,
        'is_sold' => false,
    ], $overrides));
}

function openShopDayForGoldCount(User $user): void
{
    DailyRegister::create([
        'date' => today()->toDateString(),
        'opening_cash' => 0,
        'opening_gold' => 0,
        'opening_silver' => 0,
        'opened_by' => $user->id,
    ]);
}
