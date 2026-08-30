<?php

use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;
use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->setting = BusinessSetting::updateOrCreate(
        ['id' => 1],
        [
            'store_name' => 'Maniratn Jewellers',
            'website' => 'https://maniratnjewellers.com',
            'qr_onboarding_enabled' => true,
            'qr_onboarding_token' => 'secret_test_token_123',
            'qr_onboarding_pin' => '4123',
        ]
    );
});

test('public website api registers new customer with valid token and pin', function () {
    $response = postJson(route('website.customers.register'), [
        'token' => 'secret_test_token_123',
        'pin' => '4123',
        'name' => 'Aarav Mehta',
        'mobile' => '9820012345',
        'email' => 'aarav@example.com',
        'city' => 'Mumbai',
        'dob' => '1995-05-15',
        'anniversary_date' => '2020-11-20',
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'customer' => [
                'name' => 'Aarav Mehta',
                'mobile' => '9820012345',
                'city' => 'Mumbai',
                'dob' => '1995-05-15',
            ],
        ]);

    $customer = Customer::query()->where('mobile', '9820012345')->first();
    expect($customer)->not->toBeNull()
        ->and($customer->vault_token)->not->toBeNull()
        ->and($customer->card_status)->toBe('ISSUED');
});

test('public website api rejects registration with invalid token', function () {
    $response = postJson(route('website.customers.register'), [
        'token' => 'invalid_random_token',
        'pin' => '4123',
        'name' => 'Aarav Mehta',
        'mobile' => '9820012345',
        'dob' => '1995-05-15',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'Invalid or expired counter registration token.',
        ]);

    expect(Customer::query()->where('mobile', '9820012345')->exists())->toBeFalse();
});

test('public website api rejects registration with incorrect counter pin', function () {
    $response = postJson(route('website.customers.register'), [
        'token' => 'secret_test_token_123',
        'pin' => '9999',
        'name' => 'Aarav Mehta',
        'mobile' => '9820012345',
        'dob' => '1995-05-15',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'Incorrect store counter PIN provided.',
        ]);
});

test('public website api rejects registration when onboarding is disabled', function () {
    $this->setting->update(['qr_onboarding_enabled' => false]);

    $response = postJson(route('website.customers.register'), [
        'token' => 'secret_test_token_123',
        'pin' => '4123',
        'name' => 'Aarav Mehta',
        'mobile' => '9820012345',
        'dob' => '1995-05-15',
    ]);


    $response->assertStatus(403)
        ->assertJson([
            'success' => false,
            'message' => 'Online customer registration is currently disabled by the store.',
        ]);
});

test('public website api updates existing customer without duplicate records', function () {
    $existing = Customer::create([
        'name' => 'Pooja Sharma',
        'mobile' => '9876543210',
        'city' => 'Thane',
    ]);

    $response = postJson(route('website.customers.register'), [
        'token' => 'secret_test_token_123',
        'pin' => '4123',
        'name' => 'Pooja Sharma',
        'mobile' => '9876543210',
        'email' => 'pooja@example.com',
        'dob' => '1998-08-10',
    ]);

    $response->assertStatus(201);
    expect(Customer::query()->where('mobile', '9876543210')->count())->toBe(1);

    $refreshed = $existing->fresh();
    expect($refreshed->email)->toBe('pooja@example.com')
        ->and($refreshed->dob)->toBe('1998-08-10')
        ->and($refreshed->vault_token)->not->toBeNull();
});

test('admin can render printable onboarding standee with qr code', function () {
    actingAs($this->admin);

    $response = get(route('business-settings.onboarding-standee.print'));
    $response->assertOk()
        ->assertSee('VIP Gold Club')
        ->assertSee('Join Our Customer Club')
        ->assertSee('Counter Code:');

});

test('it generates tokens with karatsetu_ prefix', function () {
    $token = BusinessSetting::generateQrOnboardingToken();
    expect($token)->toStartWith('karatsetu_');
});

