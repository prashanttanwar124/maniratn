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

it('creates a new gold product successfully with and without image', function () {
    openShopDayForProductActions($this->user);

    [$category, $purity, $supplier] = productActionDependencies();

    \Illuminate\Support\Facades\Storage::fake('public');

    $file = \Illuminate\Http\UploadedFile::fake()->image('gold_ring.jpg');

    $this->post(route('products.store'), [
        'name' => 'Royal Gold Ring',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 8.5,
        'net_weight' => 8.2,
        'making_charge' => 14,
        'image' => $file,
    ])->assertRedirect();

    $product = Product::latest('id')->firstOrFail();
    expect($product->name)->toContain('Royal Gold Ring')
        ->and((float) $product->gross_weight)->toBe(8.5)
        ->and((float) $product->net_weight)->toBe(8.2)
        ->and($product->image_path)->not->toBeNull();

    \Illuminate\Support\Facades\Storage::disk('public')->assertExists($product->image_path);
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

it('blocks deletion of sold products with error', function () {
    openShopDayForProductActions($this->user);

    [$category, $purity, $supplier] = productActionDependencies();

    $product = Product::create([
        'name' => 'Sold Gold Ring',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 5,
        'net_weight' => 5,
        'making_charge' => 10,
        'is_sold' => true,
    ]);

    $response = $this->delete(route('products.destroy', $product));

    $response->assertSessionHasErrors(['product']);
    expect(Product::whereKey($product->id)->exists())->toBeTrue();
});

it('includes sold invoice details in products index', function () {
    openShopDayForProductActions($this->user);

    [$category, $purity, $supplier] = productActionDependencies();

    $customer = \App\Models\Customer::create([
        'name' => 'Test Customer',
        'mobile' => '9988776655',
    ]);

    $product = Product::create([
        'name' => 'Sold Gold Chain',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 8,
        'net_weight' => 8,
        'making_charge' => 12,
        'is_sold' => true,
    ]);

    $invoice = \App\Models\Invoice::create([
        'invoice_number' => 'INV-2026-TEST01',
        'customer_id' => $customer->id,
        'user_id' => $this->user->id,
        'gold_rate_applied' => 7000,
        'date' => today()->toDateString(),
        'total_amount' => 56000,
        'status' => 'PAID',
    ]);

    \App\Models\InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'description' => 'Sold Gold Chain',
        'weight' => 8,
        'purity' => '22K',
        'rate' => 7000,
        'making_charges' => 12,
        'final_price' => 56000,
    ]);

    $response = $this->get(route('products.index'));
    $response->assertOk();

    $products = $response->viewData('page')['props']['products']['data'];
    $soldProd = collect($products)->firstWhere('id', $product->id);

    expect($soldProd)->not->toBeNull()
        ->and($soldProd['sold_invoice'])->not->toBeNull()
        ->and($soldProd['sold_invoice']['invoice_number'])->toBe('INV-2026-TEST01')
        ->and($soldProd['sold_invoice']['customer_name'])->toBe('Test Customer');
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
