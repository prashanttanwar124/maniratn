<?php

namespace Database\Seeders;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Counter;
use App\Models\Customer;
use App\Models\Purity;
use App\Models\User;
use App\Models\Vault;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class StoreBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Setup All System Roles and Permissions
        $this->call(RolesAndPermissionsSeeder::class);

        // 2. Admin User
        $adminUser = User::query()->firstOrCreate(
            ['email' => 'prashanttanwar148@gmail.com'],
            [
                'name' => 'Prashant Tanwar',
                'password' => Hash::make('p@@@@@@@@'),
            ]
        );

        $adminUser->forceFill([
            'name' => 'Prashant Tanwar',
            'attendance_enabled' => false,
        ])->save();

        $adminUser->syncRoles(['admin']);

        // 3. Store Profile & Karat AI Voice Configuration
        $this->seedBusinessProfile();

        // 4. Financial & Metal Vaults
        $this->seedVaults();

        // 5. Showroom Physical Display Counters
        $this->seedCounters();

        // 6. Jewellery Categories (Gold & Silver)
        $this->seedCategories();

        // 7. Hallmarked Purities
        $this->seedPurities();

        // 8. Default Walk-in Customer for Fast Billing
        $this->seedDefaultCustomer();
    }

    private function seedBusinessProfile(): void
    {
        BusinessSetting::query()->updateOrCreate(
            ['id' => 1],
            [
                'store_name' => 'Maniratn Jewellers',
                'address' => 'Shop No. 13, Shivraj Bldg, Opp. Axis Bank, Agashi Bolinj Road, Shivastan Virar (W) - 401 303.',
                'phone' => '9892820518',
                'email' => 'hello@maniratnjewellers.com',
                'website' => 'https://maniratnjewellers.com/',
                'google_review_url' => 'https://g.page/r/maniratn-jewellers/review',
                'gst_number' => '27MANIRATN1234Z',
                // Karat AI Voice Assistant Configuration
                'ai_enabled' => true,
                'ai_hub_url' => 'http://localhost:8001',
                'ai_api_key' => 'mn_live_d8f4e2a1c90b6732e45a89f0',
                'ai_voice_name' => 'Aoede',
                'ai_voice_enabled' => false,
            ]
        );
    }

    private function seedVaults(): void
    {
        $vaults = [
            ['type' => 'CASH', 'name' => 'Counter Drawer'],
            ['type' => 'GOLD', 'name' => 'Main Gold Safe'],
            ['type' => 'SILVER', 'name' => 'Silver Drawer'],
            ['type' => 'BANK', 'name' => 'Primary Bank / UPI'],
        ];

        foreach ($vaults as $vault) {
            $storedVault = Vault::query()->firstOrCreate(
                ['type' => $vault['type']],
                [
                    'name' => $vault['name'],
                    'balance' => 0,
                ]
            );

            if ($storedVault->name !== $vault['name']) {
                $storedVault->update(['name' => $vault['name']]);
            }
        }
    }

    private function seedCounters(): void
    {
        $counters = [
            ['name' => 'Main Counter'],
            ['name' => 'Gold Showcase'],
            ['name' => 'Silver Section'],
            ['name' => 'Safe Locker'],
        ];

        foreach ($counters as $counter) {
            Counter::query()->firstOrCreate(['name' => $counter['name']]);
        }
    }

    private function seedCategories(): void
    {
        $categories = [
            // Gold Categories
            ['name' => 'Ring', 'code' => 'RNG', 'metal_type' => 'GOLD'],
            ['name' => 'Chain', 'code' => 'CHN', 'metal_type' => 'GOLD'],
            ['name' => 'Bangle', 'code' => 'BGL', 'metal_type' => 'GOLD'],
            ['name' => 'Necklace', 'code' => 'NCK', 'metal_type' => 'GOLD'],
            ['name' => 'Pendant', 'code' => 'PEN', 'metal_type' => 'GOLD'],
            ['name' => 'Earrings', 'code' => 'EAR', 'metal_type' => 'GOLD'],
            ['name' => 'Mangalsutra', 'code' => 'MGL', 'metal_type' => 'GOLD'],
            ['name' => 'Bracelet', 'code' => 'BRC', 'metal_type' => 'GOLD'],
            ['name' => 'Nose Pin', 'code' => 'NPIN', 'metal_type' => 'GOLD'],
            ['name' => 'Coin', 'code' => 'COIN', 'metal_type' => 'GOLD'],

            // Silver Categories
            ['name' => 'Silver Ring', 'code' => 'SRNG', 'metal_type' => 'SILVER'],
            ['name' => 'Silver Chain', 'code' => 'SCHN', 'metal_type' => 'SILVER'],
            ['name' => 'Silver Payal', 'code' => 'SPYL', 'metal_type' => 'SILVER'],
            ['name' => 'Silver Anklet', 'code' => 'SANK', 'metal_type' => 'SILVER'],
            ['name' => 'Silver Idol / Murti', 'code' => 'SIDL', 'metal_type' => 'SILVER'],
            ['name' => 'Silver Coin', 'code' => 'SCOI', 'metal_type' => 'SILVER'],
            ['name' => 'Silver Utensils / Bartan', 'code' => 'SUTN', 'metal_type' => 'SILVER'],
            ['name' => 'Silver Gift', 'code' => 'SGFT', 'metal_type' => 'SILVER'],
        ];

        foreach ($categories as $category) {
            Category::query()->updateOrCreate(
                ['code' => $category['code']],
                [
                    'name' => $category['name'],
                    'metal_type' => $category['metal_type'],
                ]
            );
        }
    }

    private function seedPurities(): void
    {
        $purities = [
            '24K (99.9% Pure)',
            '22K (91.6% Hallmark)',
            '18K (75.0% Hallmark)',
            '14K (58.5% Hallmark)',
            '916 Hallmark',
            '92.5 Sterling Silver',
            'Silver (99.9%)',
            'Silver',
        ];

        foreach ($purities as $name) {
            Purity::query()->firstOrCreate(['name' => $name]);
        }
    }

    private function seedDefaultCustomer(): void
    {
        Customer::query()->firstOrCreate(
            ['mobile' => '9999999999'],
            [
                'name' => 'Walk-in Customer',
                'city' => 'Virar',
                'address' => 'Counter Retail Sale',
                'vault_token' => Customer::generateVaultToken(),
            ]
        );
    }
}
