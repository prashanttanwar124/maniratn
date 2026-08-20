<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    \Spatie\Permission\Models\Permission::findOrCreate('view_dashboard');
    $user->givePermissionTo('view_dashboard');
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertStatus(200);
});

test('admin can visit the dashboard with analytics charts and metrics', function () {
    $role = \Spatie\Permission\Models\Role::findOrCreate('admin');
    \Spatie\Permission\Models\Permission::findOrCreate('view_dashboard');
    $role->givePermissionTo('view_dashboard');

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $response = $this->get(route('dashboard'));
    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('dashboard/AdminDashboard')
        ->has('analytics')
        ->has('analytics.sales_trend')
        ->has('analytics.bullion_trend')
        ->has('analytics.metal_mix')
        ->has('analytics.payment_modes')
        ->has('analytics.valuations')
    );
});