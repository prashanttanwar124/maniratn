<?php

namespace Database\Seeders;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Purity;
use App\Models\User;
use App\Models\Vault;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StoreBootstrapSeeder extends Seeder
{
    public function run(): void
    {
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

        $this->seedBusinessProfile();
        $this->seedVaults();
        $this->seedCategories();
        $this->seedPurities();
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
            Vault::query()->updateOrCreate(
                ['type' => $vault['type']],
                [
                    'name' => $vault['name'],
                    'balance' => 0,
                ]
            );
        }
    }

    private function seedCategories(): void
    {
        $categories = [
            ['name' => 'Ring', 'code' => 'RNG', 'metal_type' => 'GOLD'],
            ['name' => 'Chain', 'code' => 'CHN', 'metal_type' => 'GOLD'],
            ['name' => 'Bangle', 'code' => 'BGL', 'metal_type' => 'GOLD'],
            ['name' => 'Necklace', 'code' => 'NCK', 'metal_type' => 'GOLD'],
            ['name' => 'Pendant', 'code' => 'PEN', 'metal_type' => 'GOLD'],
            ['name' => 'Earrings', 'code' => 'EAR', 'metal_type' => 'GOLD'],
            ['name' => 'Coin', 'code' => 'COIN', 'metal_type' => 'GOLD'],
            ['name' => 'Silver Ring', 'code' => 'SRNG', 'metal_type' => 'SILVER'],
            ['name' => 'Silver Chain', 'code' => 'SCHN', 'metal_type' => 'SILVER'],
            ['name' => 'Silver Payal', 'code' => 'SPYL', 'metal_type' => 'SILVER'],
            ['name' => 'Silver Anklet', 'code' => 'SANK', 'metal_type' => 'SILVER'],
            ['name' => 'Silver Idol', 'code' => 'SIDL', 'metal_type' => 'SILVER'],
            ['name' => 'Silver Coin', 'code' => 'SCOI', 'metal_type' => 'SILVER'],
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
        foreach (['14K', '18K', '22K', '24K', '916 Hallmark', 'Silver'] as $name) {
            Purity::query()->firstOrCreate(['name' => $name]);
        }
    }
}
