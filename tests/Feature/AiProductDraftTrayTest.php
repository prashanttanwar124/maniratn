<?php

use App\Models\Category;
use App\Models\Counter;
use App\Models\DailyRegister;
use App\Models\Product;
use App\Models\Purity;
use App\Models\SilverProduct;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'manage_products']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('manage_products');

    DailyRegister::create([
        'date' => Carbon::today()->toDateString(),
        'opening_cash' => 100000,
        'opening_gold' => 500,
        'opening_silver' => 1000,
        'opened_by' => $this->user->id,
    ]);

    $this->goldCategory = Category::create([
        'name' => 'Chains',
        'code' => 'CHN',
        'metal_type' => 'GOLD',
    ]);
    $this->silverCategory = Category::create([
        'name' => 'Silver Rings',
        'code' => 'SRNG',
        'metal_type' => 'SILVER',
    ]);
    $this->purity = Purity::create(['name' => '22K (916 Hallmark)']);
    $this->supplier = Supplier::create([
        'company_name' => 'Trusted Jewellery Supplier',
        'contact_person' => 'Ramesh Kumar',
        'mobile' => '9876543210',
        'type' => 'GOLD',
    ]);
    $this->counter = Counter::create(['name' => 'Main Counter']);
});

test('product draft options return existing master data', function () {
    $response = $this->actingAs($this->user)->getJson('/api/ai/copilot/product-drafts/options');

    $response->assertOk()
        ->assertJsonPath('categories.0.id', $this->goldCategory->id)
        ->assertJsonFragment(['company_name' => 'Trusted Jewellery Supplier'])
        ->assertJsonFragment(['name' => 'Main Counter']);
});

test('reviewed gold and silver drafts save together with unique stock records', function () {
    $response = $this->actingAs($this->user)->postJson('/api/ai/copilot/product-drafts', [
        'message_id' => 'ai-message-1001',
        'items' => [
            [
                'draft_id' => 'draft-chain',
                'action_index' => 0,
                'name' => 'Classic Gold Chain',
                'metal' => 'GOLD',
                'category_id' => $this->goldCategory->id,
                'purity_id' => $this->purity->id,
                'supplier_id' => $this->supplier->id,
                'counter_id' => $this->counter->id,
                'gross_weight' => 5.250,
                'net_weight' => 5.000,
                'quantity' => 2,
                'making_charge' => 450,
                'making_charge_type' => 'per_gram',
            ],
            [
                'draft_id' => 'draft-ring',
                'action_index' => 1,
                'name' => 'Silver Ring',
                'metal' => 'SILVER',
                'category_id' => $this->silverCategory->id,
                'supplier_id' => $this->supplier->id,
                'gross_weight' => 8.000,
                'net_weight' => 7.800,
                'quantity' => 1,
                'making_charge' => 25,
                'making_charge_type' => 'per_gram',
            ],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('created_count', 3)
        ->assertJsonCount(2, 'items')
        ->assertJsonCount(2, 'items.0.barcodes')
        ->assertJsonCount(1, 'items.1.barcodes');

    expect(Product::count())->toBe(2)
        ->and(SilverProduct::count())->toBe(1)
        ->and(Product::where('supplier_id', $this->supplier->id)->count())->toBe(2)
        ->and(Product::where('counter_id', $this->counter->id)->count())->toBe(2);

    $this->actingAs($this->user)
        ->postJson('/api/ai/copilot/product-drafts', [
            'message_id' => 'ai-message-1001',
            'items' => [
                [
                    'draft_id' => 'draft-chain',
                    'action_index' => 0,
                    'name' => 'Classic Gold Chain',
                    'metal' => 'GOLD',
                    'category_id' => $this->goldCategory->id,
                    'purity_id' => $this->purity->id,
                    'supplier_id' => $this->supplier->id,
                    'counter_id' => $this->counter->id,
                    'gross_weight' => 5.250,
                    'net_weight' => 5.000,
                    'quantity' => 2,
                    'making_charge' => 450,
                    'making_charge_type' => 'per_gram',
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonCount(2, 'items.0.barcodes');

    expect(Product::count())->toBe(2)
        ->and(SilverProduct::count())->toBe(1);
});

test('draft tray rejects a category from the wrong metal without creating partial stock', function () {
    $response = $this->actingAs($this->user)->postJson('/api/ai/copilot/product-drafts', [
        'message_id' => 'ai-message-invalid',
        'items' => [
            [
                'draft_id' => 'invalid-silver-category',
                'action_index' => 0,
                'name' => 'Wrongly Categorized Gold Ring',
                'metal' => 'GOLD',
                'category_id' => $this->silverCategory->id,
                'purity_id' => $this->purity->id,
                'supplier_id' => $this->supplier->id,
                'gross_weight' => 4,
                'net_weight' => 4,
                'quantity' => 1,
                'making_charge' => 10,
                'making_charge_type' => 'percentage',
            ],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.category_id']);

    expect(Product::count())->toBe(0)
        ->and(SilverProduct::count())->toBe(0);
});

test('AI gold drafts use the same ten-item batch limit as the gold product form', function () {
    $response = $this->actingAs($this->user)->postJson('/api/ai/copilot/product-drafts', [
        'message_id' => 'ai-message-quantity-limit',
        'items' => [
            [
                'draft_id' => 'too-many-chains',
                'action_index' => 0,
                'name' => 'Gold Chain',
                'metal' => 'GOLD',
                'category_id' => $this->goldCategory->id,
                'purity_id' => $this->purity->id,
                'supplier_id' => $this->supplier->id,
                'gross_weight' => 5,
                'net_weight' => 5,
                'quantity' => 11,
                'making_charge' => 10,
                'making_charge_type' => 'percentage',
            ],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.quantity']);

    expect(Product::count())->toBe(0);
});
