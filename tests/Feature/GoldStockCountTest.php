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
        ->assertJsonPath('countedProduct.purity', '22K')
        ->assertJsonPath('countedProduct.net_weight', 9.5)
        ->assertJsonPath('summary.expected_items', 1)
        ->assertJsonPath('summary.counted_items', 1)
        ->assertJsonPath('summary.remaining_items', 0);

    $response->assertJsonPath('recentCounted.0.purity', '22K')
        ->assertJsonPath('recentCounted.0.net_weight', 9.5);

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

it('counts and summarizes gold stock by selected category', function () {
    openShopDayForGoldCount($this->user);

    $ring = goldCountProduct(['name' => 'Category Ring']);
    $chainCategory = Category::create([
        'name' => 'Chain',
        'code' => 'CHN',
        'metal_type' => 'GOLD',
    ]);
    $chain = goldCountProduct([
        'name' => 'Category Chain',
        'category_id' => $chainCategory->id,
    ]);

    $this->get(route('gold-stock-count.index', ['category_id' => $chainCategory->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedCategoryId', $chainCategory->id)
            ->where('summary.expected_items', 1)
            ->where('missingProducts.0.id', $chain->id)
            ->where('missingProducts.0.purity', '22K')
            ->where('missingProducts.0.net_weight', 9.5));

    $this->postJson(route('gold-stock-count.scan'), [
        'barcode' => $ring->barcode,
        'category_id' => $chainCategory->id,
    ])->assertStatus(422);

    $this->postJson(route('gold-stock-count.scan'), [
        'barcode' => $chain->barcode,
        'category_id' => $chainCategory->id,
    ])->assertOk()
        ->assertJsonPath('summary.counted_items', 1)
        ->assertJsonPath('summary.remaining_items', 0)
        ->assertJsonPath('summary.overall_remaining_items', 1);

    $this->postJson(route('gold-stock-count.complete'))->assertStatus(422);
});

it('allows viewing past gold stock count records by date', function () {
    $pastDate = now()->subDays(2)->toDateString();

    $pastRegister = DailyRegister::create([
        'date' => $pastDate,
        'opening_cash' => 0,
        'opening_gold' => 0,
        'opening_silver' => 0,
        'opened_by' => $this->user->id,
        'closed_at' => now()->subDays(2)->setTime(21, 0),
    ]);

    $pastSession = GoldStockCountSession::create([
        'daily_register_id' => $pastRegister->id,
        'count_date' => $pastDate,
        'status' => 'COMPLETED',
        'started_by' => $this->user->id,
        'started_at' => now()->subDays(2)->setTime(20, 0),
        'completed_by' => $this->user->id,
        'completed_at' => now()->subDays(2)->setTime(20, 30),
    ]);

    $product = goldCountProduct(['name' => 'Past Counted Ring']);

    GoldStockCountEntry::create([
        'session_id' => $pastSession->id,
        'product_id' => $product->id,
        'scanned_barcode' => $product->barcode,
        'scanned_by' => $this->user->id,
        'scanned_at' => now()->subDays(2)->setTime(20, 15),
    ]);

    $this->get(route('gold-stock-count.index', ['date' => $pastDate]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('isToday', false)
            ->where('selectedDate', $pastDate)
            ->where('session.status', 'COMPLETED')
            ->where('summary.counted_items', 1)
            ->where('recentCounted.0.barcode', $product->barcode)
            ->where('recentCounted.0.id', $product->id));
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
