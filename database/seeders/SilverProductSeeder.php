<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\SilverProduct;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class SilverProductSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('en_IN');

        $categoriesList = [
            'Silver Ring' => 'SRNG',
            'Silver Chain' => 'SCHN',
            'Silver Payal' => 'SPYL',
            'Silver Anklet' => 'SANK',
            'Silver Idol' => 'SIDL',
            'Silver Coin' => 'SCOI',
            'Silver Gift' => 'SGFT',
        ];

        $categoryMap = [];

        foreach ($categoriesList as $name => $code) {
            $categoryMap[$name] = Category::firstOrCreate(
                ['name' => $name],
                ['code' => $code, 'metal_type' => 'SILVER']
            );
            if ($categoryMap[$name]->metal_type !== 'SILVER') {
                $categoryMap[$name]->update(['metal_type' => 'SILVER']);
            }
        }

        foreach (range(1, 10) as $index) {
            $randomCategoryName = array_rand($categoriesList);
            $selectedCategory = $categoryMap[$randomCategoryName];
            $pricingMode = $faker->randomElement(['PIECE', 'WEIGHT']);
            $quantity = $pricingMode === 'PIECE' ? $faker->numberBetween(1, 6) : 1;

            $grossWeight = match ($randomCategoryName) {
                'Silver Ring' => $faker->randomFloat(3, 4, 12),
                'Silver Chain', 'Silver Anklet' => $faker->randomFloat(3, 18, 60),
                'Silver Payal' => $faker->randomFloat(3, 25, 90),
                'Silver Idol', 'Silver Gift' => $faker->randomFloat(3, 30, 180),
                default => $faker->randomFloat(3, 10, 40),
            };

            $netWeight = round($grossWeight * 0.97, 3);

            SilverProduct::create([
                'category_id' => $selectedCategory->id,
                'supplier_id' => $faker->numberBetween(1, 10),
                'pricing_mode' => $pricingMode,
                'name' => $faker->randomElement([
                    'Classic',
                    'Temple',
                    'Royal',
                    'Premium',
                    'Daily Wear',
                    'Gift Edition',
                ]) . ' ' . $randomCategoryName,
                'quantity' => $quantity,
                'gross_weight' => $grossWeight,
                'net_weight' => $netWeight,
                'piece_price' => $pricingMode === 'PIECE'
                    ? $faker->numberBetween(850, 8500)
                    : null,
                'making_charge' => $faker->numberBetween(80, 950),
                'is_sold' => false,
                'notes' => $faker->randomElement([
                    'Counter silver collection',
                    'Festival stock batch',
                    'Fast-moving silver item',
                    'Premium shelf display',
                ]),
            ]);
        }
    }
}
