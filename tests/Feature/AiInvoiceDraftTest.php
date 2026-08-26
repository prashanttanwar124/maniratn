<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\DailyRate;
use App\Models\DailyRegister;
use App\Models\Invoice;
use App\Models\InvoiceDraft;
use App\Models\Product;
use App\Models\Purity;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Ai\Actions\CreateBillAction;
use App\Services\AiInvoiceDraftService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function createAiBillProduct(string $name, float $weight): Product
{
    $category = Category::firstOrCreate(['name' => 'AI Billing', 'code' => 'AIB']);
    $purity = Purity::firstOrCreate(['name' => '22K']);
    $supplier = Supplier::firstOrCreate(
        ['mobile' => '9999999999'],
        [
            'company_name' => 'AI Billing Supplier',
            'contact_person' => 'Counter',
            'type' => 'GOLD',
        ],
    );

    return Product::create([
        'name' => $name,
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => $weight,
        'net_weight' => $weight,
        'making_charge' => 10,
        'making_charge_type' => 'percentage',
        'is_sold' => false,
    ]);
}

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'manage_invoices']);

    DailyRate::create([
        'date' => Carbon::today()->toDateString(),
        'gold_sell' => 7500,
        'silver_sell' => 90,
    ]);
});

test('AI chat returns a link to the canonical invoice draft', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('manage_invoices');
    $product = createAiBillProduct('Gold Bracelet 7g', 7);

    DailyRegister::create([
        'date' => Carbon::today()->toDateString(),
        'opening_cash' => 100000,
        'opening_gold' => 500,
        'opened_by' => $user->id,
    ]);

    Http::fake([
        '*/api/ai/chat' => Http::response([
            'reply' => 'Bill ready.',
            'message_id' => 'hub-message-404',
            'actions' => [[
                'tool' => 'create_bill',
                'args' => [
                    'customer_name' => 'Neha Gupta',
                    'customer_phone' => '9898989898',
                    'barcode' => $product->barcode,
                ],
            ]],
        ]),
        '*/api/ai/history/update-action' => Http::response(['success' => true]),
    ]);

    $response = $this->actingAs($user)->postJson('/api/ai/copilot/chat', [
        'message' => "{$product->barcode} ka bill Neha Gupta 9898989898 ke naam draft karo",
        'include_audio' => false,
    ]);

    $response->assertOk()
        ->assertJsonPath('actions.0.result.status', 'INVOICE_DRAFT_SAVED')
        ->assertJsonPath('actions.0.result.draft_url', '/invoices/create?draft=1');

    expect(InvoiceDraft::count())->toBe(1)
        ->and(Invoice::count())->toBe(0)
        ->and((bool) $product->fresh()->is_sold)->toBeFalse();
});

test('AI billing creates a regular persistent invoice draft without posting a sale', function () {
    $user = User::factory()->create();
    $product = createAiBillProduct('Gold Ring 5g', 5);
    $command = app(CreateBillAction::class)->handle([
        'customer_name' => 'Ramesh Sharma',
        'customer_phone' => '9876543210',
        'barcode' => strtolower($product->barcode),
    ]);

    $result = app(AiInvoiceDraftService::class)->createOrAppend(
        $user,
        $command,
        'message-101:9876543210',
    );

    expect($command['status'])->toBe('READY_FOR_INVOICE_DRAFT')
        ->and($result['status'])->toBe('INVOICE_DRAFT_SAVED')
        ->and($result['draft_url'])->toBe('/invoices/create?draft='.$result['draft_id'])
        ->and($result['item_count'])->toBe(1)
        ->and(Invoice::count())->toBe(0)
        ->and(Customer::where('mobile', '9876543210')->count())->toBe(1);

    $draft = InvoiceDraft::findOrFail($result['draft_id']);
    $item = $draft->draft_data['items'][0];

    expect($draft->source_type)->toBe('AI_COPILOT')
        ->and($draft->source_reference)->toBe('message-101:9876543210')
        ->and($item['type'])->toBe('product')
        ->and($item['id'])->toBe($product->id)
        ->and($item['description'])->toContain($product->barcode)
        ->and((float) $item['rate'])->toBe(6875.25)
        ->and((bool) $product->fresh()->is_sold)->toBeFalse();
});

test('replaying an AI message is idempotent and a second barcode appends to the same draft', function () {
    $user = User::factory()->create();
    $ring = createAiBillProduct('Gold Ring', 5);
    $chain = createAiBillProduct('Gold Chain', 10);
    $service = app(AiInvoiceDraftService::class);
    $action = app(CreateBillAction::class);
    $customer = [
        'customer_name' => 'Pooja Jain',
        'customer_phone' => '9811122233',
    ];
    $source = 'message-202:9811122233';

    $first = $service->createOrAppend($user, $action->handle($customer + ['barcode' => $ring->barcode]), $source);
    $replayed = $service->createOrAppend($user, $action->handle($customer + ['barcode' => $ring->barcode]), $source);
    $second = $service->createOrAppend($user, $action->handle($customer + ['barcode' => $chain->barcode]), $source);

    expect($first['draft_id'])->toBe($replayed['draft_id'])
        ->and($first['draft_id'])->toBe($second['draft_id'])
        ->and($replayed['item_count'])->toBe(1)
        ->and($second['item_count'])->toBe(2)
        ->and(InvoiceDraft::count())->toBe(1)
        ->and(Customer::where('mobile', '9811122233')->count())->toBe(1)
        ->and(Invoice::count())->toBe(0)
        ->and((bool) $ring->fresh()->is_sold)->toBeFalse()
        ->and((bool) $chain->fresh()->is_sold)->toBeFalse();
});

test('sold stock cannot enter an AI invoice draft', function () {
    $user = User::factory()->create();
    $product = createAiBillProduct('Sold Necklace', 8);
    $product->update(['is_sold' => true]);

    $command = app(CreateBillAction::class)->handle([
        'customer_name' => 'Aman Verma',
        'customer_phone' => '9876543211',
        'barcode' => $product->barcode,
    ]);

    expect($command['found'])->toBeFalse()
        ->and($command['status'])->toBe('PRODUCT_ALREADY_SOLD')
        ->and(fn () => app(AiInvoiceDraftService::class)->createOrAppend(
            $user,
            $command + ['barcode' => $product->barcode],
            'message-303:9876543211',
        ))->toThrow(ValidationException::class)
        ->and(InvoiceDraft::count())->toBe(0);
});

test('legacy direct AI bill confirmation endpoint no longer exists', function () {
    $this->postJson('/api/ai/copilot/confirm-bill')->assertNotFound();
});

test('flexible barcode patterns resolve correctly for gold and silver items', function () {
    $user = User::factory()->create();
    $goldProduct = createAiBillProduct('Gold Ring 4g', 4);
    $silverCategory = Category::firstOrCreate(['name' => 'Silver Items', 'code' => 'SLV']);
    $silverSupplier = Supplier::firstOrCreate(['mobile' => '8888888888'], [
        'company_name' => 'Silver Supplier',
        'contact_person' => 'Supplier',
        'type' => 'SILVER',
    ]);
    $silverProduct = \App\Models\SilverProduct::create([
        'name' => 'Silver Coin 10g',
        'category_id' => $silverCategory->id,
        'supplier_id' => $silverSupplier->id,
        'pricing_mode' => 'PIECE',
        'piece_price' => 1200,
        'quantity' => 10,
        'gross_weight' => 10,
        'net_weight' => 10,
        'making_charge' => 20,
        'making_charge_type' => 'per_gram',
        'is_sold' => false,
    ]);

    // Test G1, G0001 short forms for gold
    $shortGoldBarcode = 'G' . (int) substr($goldProduct->barcode, 1);
    $goldCmd = app(CreateBillAction::class)->handle([
        'customer' => 'Short Barcode Customer',
        'customer_mobile' => '9800000001',
        'barcode' => $shortGoldBarcode,
    ]);
    expect($goldCmd['found'])->toBeTrue()
        ->and($goldCmd['barcode'])->toBe($goldProduct->barcode);

    // Test S1 short form for silver
    $shortSilverBarcode = 'S' . (int) substr($silverProduct->barcode, 1);
    $silverCmd = app(CreateBillAction::class)->handle([
        'customer_name' => 'Silver Short Barcode',
        'customer_phone' => '9800000002',
        'barcode' => $shortSilverBarcode,
        'quantity' => 3,
    ]);
    expect($silverCmd['found'])->toBeTrue()
        ->and($silverCmd['barcode'])->toBe($silverProduct->barcode);

    // Test draft creation for silver piece with quantity 3 and per-gram making charges
    $silverDraft = app(AiInvoiceDraftService::class)->createOrAppend(
        $user,
        $silverCmd,
        'msg-silver:9800000002',
    );

    $draft = InvoiceDraft::findOrFail($silverDraft['draft_id']);
    $item = $draft->draft_data['items'][0];

    // Base: 1200 * 3 = 3600
    // Making: 10g * 3 qty * 20/gm = 600
    // Total: 3600 + 600 = 4200
    // GST (3%): 4200 * 0.03 = 126
    // Grand Total: 4326.0
    expect((int) $item['quantity'])->toBe(3)
        ->and((float) $item['rate'])->toBe(1200.0)
        ->and((float) $item['final_price'])->toBe(4200.0)
        ->and((float) $draft->grand_total)->toBe(4326.0);
});

test('purity multiplier resolution correctly calculates rates for various karat and hallmarking labels', function () {
    $rateService = app(\App\Services\InvoiceRateService::class);
    $product22k = createAiBillProduct('22K Product', 5);
    $product18k = createAiBillProduct('18K Product', 5);
    $product18k->purity()->associate(Purity::firstOrCreate(['name' => '18K (750 Hallmark)']))->save();

    // 7500 gold sell rate
    // 22K (22/24 = 0.9167) -> 7500 * 0.9167 = 6875.25
    expect($rateService->rateFor('product', $product22k, 7500, 90))->toBe(6875.25);

    // 18K (18/24 = 0.75) -> 7500 * 0.75 = 5625.0
    expect($rateService->rateFor('product', $product18k, 7500, 90))->toBe(5625.0);
});
