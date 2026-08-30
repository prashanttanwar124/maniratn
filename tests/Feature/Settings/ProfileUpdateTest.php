<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('admin can delete their account', function () {
    $role = \Spatie\Permission\Models\Role::findOrCreate('admin');
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this
        ->actingAs($admin)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($admin->fresh())->toBeNull();
});

test('non-admin staff cannot delete their account', function () {
    $role = \Spatie\Permission\Models\Role::findOrCreate('staff');
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $response = $this
        ->actingAs($staff)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response->assertForbidden();
    expect($staff->fresh())->not->toBeNull();
});

test('correct password must be provided by admin to delete account', function () {
    $role = \Spatie\Permission\Models\Role::findOrCreate('admin');
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this
        ->actingAs($admin)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($admin->fresh())->not->toBeNull();
});