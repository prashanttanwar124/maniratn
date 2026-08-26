<?php

use App\Models\DailyRegister;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'manage_products']);
    Permission::firstOrCreate(['name' => 'manage_daily_rates']);
});

test('product and daily-rate confirmations reject negative values', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['manage_products', 'manage_daily_rates']);

    DailyRegister::create([
        'date' => Carbon::today()->toDateString(),
        'opening_cash' => 100000,
        'opening_gold' => 500,
        'opened_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->postJson('/api/ai/copilot/confirm-product', [
            'name' => 'Bad Product',
            'weight' => -5.0,
        ])
        ->assertUnprocessable();

    $this->actingAs($user)
        ->postJson('/api/ai/copilot/confirm-rates', [
            'gold_24k_sell' => -7000,
            'silver_sell' => -90,
        ])
        ->assertUnprocessable();
});
