<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Karigar;
use App\Models\Mortgage;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            StoreBootstrapSeeder::class,
        ]);

        $this->seedDemoUsers();
        $this->seedSuppliers();
        $this->seedCustomersWithHistory();
        $this->seedKarigars();

        $this->call([
            ProductSeeder::class,
            SilverProductSeeder::class,
        ]);
    }

    private function seedDemoUsers(): void
    {
        $staffUser = User::query()->firstOrCreate(
            ['email' => 'staff@maniratn.test'],
            [
                'name' => 'Staff User',
                'password' => Hash::make('staff@123'),
            ]
        );

        $staffUser->forceFill([
            'name' => 'Staff User',
            'attendance_enabled' => true,
            'attendance_passcode' => Hash::make('1234'),
        ])->save();

        $staffUser->syncRoles(['staff']);
    }

    private function seedSuppliers(): void
    {
        if (Supplier::query()->exists()) {
            return;
        }

        $faker = Faker::create('en_IN');
        $supplierTypes = ['GOLD', 'SILVER', 'DIAMOND', 'PACKAGING'];

        foreach (range(1, 10) as $index) {
            $pan = $faker->regexify('[A-Z]{5}[0-9]{4}[A-Z]{1}');

            Supplier::create([
                'company_name' => $faker->firstName . ' ' . $faker->randomElement(['Gold', 'Jewellers', 'Bullion', 'Traders', 'Diamonds']),
                'contact_person' => $faker->name,
                'mobile' => '9' . $faker->numerify('#########'),
                'gst_number' => '27' . $pan . '1Z5',
                'pan_no' => $pan,
                'bank_name' => $faker->randomElement(['HDFC Bank', 'ICICI Bank', 'SBI', 'Kotak Mahindra', 'Axis Bank']),
                'account_no' => $faker->numerify('##############'),
                'ifsc_code' => $faker->regexify('[A-Z]{4}0[A-Z0-9]{6}'),
                'type' => $faker->randomElement($supplierTypes),
            ]);
        }
    }

    private function seedCustomersWithHistory(): void
    {
        if (Customer::query()->exists()) {
            return;
        }

        $faker = Faker::create('en_IN');

        foreach (range(1, 50) as $index) {
            $dob = $faker->optional(0.7)->dateTimeBetween('-60 years', '-18 years');
            $anniversaryDate = $faker->optional(0.45)->dateTimeBetween('-20 years', '-1 year');

            $customer = Customer::create([
                'name' => $faker->name,
                'mobile' => '98' . $faker->numerify('########'),
                'email' => $faker->safeEmail,
                'city' => $faker->randomElement(['Virar', 'Vasai', 'Borivali', 'Andheri', 'Dadar']),
                'pan_no' => $faker->regexify('[A-Z]{5}[0-9]{4}[A-Z]{1}'),
                'dob' => $dob?->format('Y-m-d'),
                'anniversary_date' => $anniversaryDate?->format('Y-m-d'),
                'created_at' => $faker->dateTimeBetween('-1 year', 'now'),
                'updated_at' => now(),
            ]);

            foreach (range(1, rand(3, 8)) as $i) {
                $type = $faker->randomElement(['SALE', 'SALE', 'PAYMENT']);
                $amount = $type === 'SALE'
                    ? $faker->numberBetween(15000, 250000)
                    : $faker->numberBetween(5000, 50000);

                Transaction::create([
                    'transactable_type' => Customer::class,
                    'transactable_id' => $customer->id,
                    'type' => $type,
                    'payment_method' => $faker->randomElement(['CASH', 'UPI', 'BANK']),
                    'amount' => $amount,
                    'description' => $type === 'SALE'
                        ? 'Purchased ' . $faker->randomElement(['Gold Ring', 'Chain', 'Bangle', 'Earrings'])
                        : 'Customer Payment Received',
                    'date' => $faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
                ]);
            }

            foreach (range(1, rand(0, 3)) as $j) {
                $status = $faker->randomElement(['ACTIVE', 'RELEASED']);
                $startDate = $faker->dateTimeBetween('-1 year', '-1 month');
                $endDate = $status === 'RELEASED' ? $faker->dateTimeBetween($startDate, 'now') : null;
                $weight = $faker->randomFloat(3, 5, 40);
                $loanAmount = (int) ($weight * 3500);

                Mortgage::create([
                    'customer_id' => $customer->id,
                    'item_name' => $faker->randomElement(['Gold Chain', 'Ladies Ring', 'Mangalsutra', 'Bangle', 'Ear Tops']),
                    'gross_weight' => $weight,
                    'net_weight' => round($weight * 0.9, 3),
                    'loan_amount' => $loanAmount,
                    'interest_rate' => 2.00,
                    'status' => $status,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate?->format('Y-m-d'),
                    'notes' => 'Bag No: ' . $faker->numberBetween(100, 999),
                    'created_at' => $startDate,
                    'updated_at' => $endDate ?? now(),
                ]);
            }
        }
    }

    private function seedKarigars(): void
    {
        if (Karigar::query()->exists()) {
            return;
        }

        $karigars = [
            ['name' => 'Ramesh Bengali', 'mobile' => '9876543210'],
            ['name' => 'Suresh Gold Works', 'mobile' => '9123456789'],
            ['name' => 'Abdul Chain Maker', 'mobile' => '9898989898'],
            ['name' => 'Ganesh Polisher', 'mobile' => '9000000001'],
            ['name' => 'Kishore Diamond Setter', 'mobile' => '8888888888'],
            ['name' => 'Manish Casting', 'mobile' => '7777777777'],
        ];

        foreach ($karigars as $karigar) {
            Karigar::create($karigar);
        }
    }
}
