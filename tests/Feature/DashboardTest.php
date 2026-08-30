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

test('staff can visit the dashboard with counter tools, tasks, and metrics', function () {
    $staffRole = \Spatie\Permission\Models\Role::findOrCreate('staff');
    \Spatie\Permission\Models\Permission::findOrCreate('view_dashboard');
    $staffRole->givePermissionTo('view_dashboard');

    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $this->actingAs($staff);

    $response = $this->get(route('dashboard'));
    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('dashboard/StaffDashboard')
        ->has('metrics')
        ->has('metrics.my_sales')
        ->has('metrics.my_collections')
        ->has('metrics.my_month_sales')
        ->has('rates')
        ->has('ready_for_delivery')
        ->has('attention_items')
        ->has('customer_reminders')
        ->has('my_tasks')
        ->has('my_attendance')
    );
});