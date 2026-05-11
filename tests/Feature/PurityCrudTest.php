<?php

use App\Models\Category;
use App\Models\DailyRegister;
use App\Models\Product;
use App\Models\Purity;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Carbon::setTestNow('2026-03-10 10:00:00');
    $this->withoutVite();

    $this->seed(RolesAndPermissionsSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin');

    $this->actingAs($this->user);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('shows the purity register', function () {
    Purity::create(['name' => '22K']);

    $this->get(route('purities'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purities/Index')
            ->has('purities', 1)
            ->where('summary.total_purities', 1));
});

it('creates and updates a purity', function () {
    openShopDayForPurityCrud();

    $this->post(route('purities.store'), [
        'name' => '22K',
    ])->assertRedirect();

    $purity = Purity::firstOrFail();

    expect($purity->name)->toBe('22K');

    $this->patch(route('purities.update', $purity), [
        'name' => '916 Hallmark',
    ])->assertRedirect();

    expect($purity->fresh()->name)->toBe('916 Hallmark');
});

it('blocks deleting a linked purity', function () {
    openShopDayForPurityCrud();

    $category = Category::create([
        'name' => 'Ring',
        'code' => 'RNG',
        'metal_type' => 'GOLD',
    ]);

    $supplier = Supplier::create([
        'company_name' => 'Raj Gold House',
        'contact_person' => 'Rajesh Bhai',
        'mobile' => '8888888881',
        'type' => 'GOLD',
    ]);

    $purity = Purity::create(['name' => '22K']);

    Product::create([
        'barcode' => 'G00001',
        'name' => 'Gold Ring',
        'category_id' => $category->id,
        'purity_id' => $purity->id,
        'supplier_id' => $supplier->id,
        'gross_weight' => 1.500,
        'net_weight' => 1.500,
        'making_charge' => 1000,
    ]);

    $this->delete(route('purities.destroy', $purity))
        ->assertSessionHasErrors('purity');

    expect(Purity::query()->whereKey($purity->id)->exists())->toBeTrue();
});

function openShopDayForPurityCrud(): void
{
    DailyRegister::create([
        'date' => today()->toDateString(),
        'opening_cash' => 0,
        'opening_gold' => 0,
        'opening_silver' => 0,
        'opened_by' => auth()->id(),
    ]);
}
