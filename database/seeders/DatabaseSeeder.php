<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Karigar;
use App\Models\Customer;
use App\Models\Mortgage;
use App\Models\Transaction;
use App\Models\Vault;
use App\Models\VaultMovement;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {


        // 2. Default Login User
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'prashanttanwar148@gmail.com',
            'password' => Hash::make('p@@@@@@@@'),
        ]);

        $faker = Faker::create('en_IN'); // Indian Data

        // ---------------------------------------------------------
        // 3. Create SUPPLIERS (B2B Partners) - NEW SECTION
        // ---------------------------------------------------------
        $supplierTypes = ['GOLD', 'SILVER', 'DIAMOND', 'PACKAGING'];

        // Let's create 10 realistic suppliers
        foreach (range(1, 10) as $index) {
            // Generate a realistic PAN
            $pan = $faker->regexify('[A-Z]{5}[0-9]{4}[A-Z]{1}');

            Supplier::create([
                // e.g. "Maniratn Gold House"
                'company_name'   => $faker->firstName . ' ' . $faker->randomElement(['Gold', 'Jewellers', 'Bullion', 'Traders', 'Diamonds']),
                'contact_person' => $faker->name,
                'mobile'         => '9' . $faker->numerify('#########'), // 9823...

                // Taxation
                // GST Format: 27 (State) + PAN + 1Z5 (Random check digits)
                'gst_number'     => '27' . $pan . '1Z5',
                'pan_no'         => $pan,

                // Bank Details
                'bank_name'      => $faker->randomElement(['HDFC Bank', 'ICICI Bank', 'SBI', 'Kotak Mahindra', 'Axis Bank']),
                'account_no'     => $faker->numerify('##############'), // 14 digits
                'ifsc_code'      => $faker->regexify('[A-Z]{4}0[A-Z0-9]{6}'), // e.g. HDFC0001234

                // Category
                'type'           => $faker->randomElement($supplierTypes),
            ]);
        }

        // ---------------------------------------------------------
        // 4. Create CUSTOMERS & THEIR DATA
        // ---------------------------------------------------------
        foreach (range(1, 50) as $index) {
            $customer = Customer::create([
                'name' => $faker->name,
                'mobile' => '98' . $faker->numerify('########'),
                'email' => $faker->safeEmail,
                'city' => $faker->randomElement(['Virar', 'Vasai', 'Borivali', 'Andheri', 'Dadar']),
                'pan_no' => $faker->regexify('[A-Z]{5}[0-9]{4}[A-Z]{1}'),
                'created_at' => $faker->dateTimeBetween('-1 year', 'now'),
            ]);

            // ... (Your existing Transaction logic) ...
            for ($i = 0; $i < rand(3, 8); $i++) {
                $type = $faker->randomElement(['SALE', 'SALE', 'PAYMENT']);
                $amount = ($type == 'SALE')
                    ? $faker->numberBetween(15000, 250000)
                    : $faker->numberBetween(5000, 50000);

                Transaction::create([
                    'transactable_type' => Customer::class,
                    'transactable_id'   => $customer->id,
                    'type'   => $type,
                    'amount' => $amount,
                    'description' => ($type == 'SALE')
                        ? 'Purchased ' . $faker->randomElement(['Gold Ring', 'Chain', 'Bangle', 'Earrings'])
                        : 'Cash Payment Received',
                    'date' => $faker->dateTimeBetween('-6 months', 'now'),
                ]);
            }

            // ... (Your existing Mortgage logic) ...
            for ($j = 0; $j < rand(0, 3); $j++) {
                $status = $faker->randomElement(['ACTIVE', 'RELEASED']);
                $startDate = $faker->dateTimeBetween('-1 year', '-1 month');
                $endDate = ($status === 'RELEASED') ? $faker->dateTimeBetween($startDate, 'now') : null;
                $weight = $faker->randomFloat(3, 5, 40);
                $loanAmount = (int)($weight * 3500);

                Mortgage::create([
                    'customer_id'   => $customer->id,
                    'item_name'     => $faker->randomElement(['Gold Chain', 'Ladies Ring', 'Mangalsutra', 'Bangle', 'Ear Tops']),
                    'gross_weight'  => $weight,
                    'net_weight'    => $weight * 0.9,
                    'loan_amount'   => $loanAmount,
                    'interest_rate' => 2.00,
                    'status'        => $status,
                    'start_date'    => $startDate,
                    'end_date'      => $endDate,
                    'notes'         => 'Bag No: ' . $faker->numberBetween(100, 999),
                    'created_at'    => $startDate,
                ]);
            }
        }

        // karighar

        $karigars = [
            [
                'name' => 'Ramesh Bengali',
                'mobile' => '9876543210'
            ],
            [
                'name' => 'Suresh Gold Works',
                'mobile' => '9123456789'
            ],
            [
                'name' => 'Abdul Chain Maker',
                'mobile' => '9898989898'
            ],
            [
                'name' => 'Ganesh Polisher',
                'mobile' => '9000000001'
            ],
            [
                'name' => 'Kishore Diamond Setter',
                'mobile' => '8888888888'
            ],
            [
                'name' => 'Manish Casting',
                'mobile' => '7777777777'
            ],
        ];

        foreach ($karigars as $karigar) {
            Karigar::create($karigar);
        }


        // 1. Physical Gold Safe
        DB::table('vaults')->insertOrIgnore([
            'name' => 'Main Iron Safe',
            'type' => 'GOLD',
            'balance' => 0.000, // Start with 0g
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Physical Cash Drawer
        DB::table('vaults')->insertOrIgnore([
            'name' => 'Counter Drawer',
            'type' => 'CASH',
            'balance' => 0.000, // Start with ₹0
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Bank Account (Digital)
        DB::table('vaults')->insertOrIgnore([
            'name' => 'HDFC Bank (UPI/Online)',
            'type' => 'BANK',
            'balance' => 0.000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Silver (Optional)
        DB::table('vaults')->insertOrIgnore([
            'name' => 'Silver Drawer',
            'type' => 'SILVER',
            'balance' => 0.000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1. Run Product Seeder first (if you have one)
        $this->call(ProductSeeder::class);
        $this->call(SilverProductSeeder::class);
        $this->call(RolesAndPermissionsSeeder::class);

        $this->syncSeededVaultBalances();
    }

    private function syncSeededVaultBalances(): void
    {
        $cashVault = Vault::query()->where('type', 'CASH')->first();
        $bankVault = Vault::query()->where('type', 'BANK')->first();
        $silverVault = Vault::query()->where('type', 'SILVER')->first();

        foreach ([$cashVault, $bankVault, $silverVault] as $vault) {
            if (! $vault) {
                continue;
            }

            VaultMovement::create([
                'vault_id' => $vault->id,
                'vault_type' => $vault->type,
                'direction' => 'CREDIT',
                'amount' => (float) $vault->balance,
                'balance_before' => 0,
                'balance_after' => (float) $vault->balance,
                'source_type' => self::class,
                'reference' => 'Seeder Sync',
                'note' => "Seeded opening balance for {$vault->type} vault",
                'user_id' => 1,
                'recorded_at' => now(),
            ]);
        }
    }
}
