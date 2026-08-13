<?php

use App\Models\Counter;
use App\Models\DailyRegister;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    DailyRegister::create([
        'date' => today()->toDateString(),
        'opening_cash' => 0,
        'opening_gold' => 0,
        'opening_silver' => 0,
        'opened_by' => $this->user->id,
    ]);
});

it('manages reusable product counters', function () {
    $this->post(route('counters.store'), ['name' => 'Main Counter'])
        ->assertSessionHasNoErrors();

    $counter = Counter::query()->firstOrFail();

    $this->get(route('counters.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('counters/Index')
            ->where('counters.0.name', 'Main Counter'));

    $this->patch(route('counters.update', $counter), ['name' => 'Bridal Counter'])
        ->assertSessionHasNoErrors();

    expect($counter->refresh()->name)->toBe('Bridal Counter');
});
