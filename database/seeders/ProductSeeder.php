<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;
use App\Models\Purity;
use Faker\Factory as Faker;

class ProductSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create('en_IN');

        // 1. Define your Master Data
        $categoriesList = [
            // Name       => Code
            'Ring'        => 'RNG',
            'Chain'       => 'CHN',
            'Bangle'      => 'BGL',
            'Necklace'    => 'NCK',
            'Earrings'    => 'EAR',
            'Coin'        => 'COIN',
            'Pendant'     => 'PEN'
        ];

        $puritiesList = ['22K', '18K', '24K', '916 Hallmark'];

        // 2. Create Categories & Cache IDs (To avoid querying DB 50 times)
        $catIds = [];
        foreach ($categoriesList as $name => $code) {
            // "firstOrCreate" checks if 'name' exists. If not, creates it with 'code'.
            $category = Category::firstOrCreate(
                ['name' => $name],
                ['code' => $code]
            );
            $catIds[$name] = $category; // Store the whole object to access ->code later
        }

        // 3. Create Purities & Cache IDs
        $purityIds = [];
        foreach ($puritiesList as $name) {
            $purity = Purity::firstOrCreate(['name' => $name]);
            $purityIds[] = $purity->id;
        }

        // 4. Generate 50 Dummy Products
        foreach (range(1, 50) as $index) {

            // Pick a random category name (e.g. "Ring")
            $randomCatName = array_rand($categoriesList);
            $selectedCategory = $catIds[$randomCatName]; // Get the ID and CODE

            // Pick a random purity
            $randomPurityId = $purityIds[array_rand($purityIds)];



            // Logic: Rings are lighter (2-8g), Chains are heavier (15-50g)
            $grossWeight = ($randomCatName == 'Ring' || $randomCatName == 'Earrings')
                ? $faker->randomFloat(3, 2, 8)
                : $faker->randomFloat(3, 10, 60);

            $netWeight = $grossWeight * 0.95; // 5% weight loss for stones/dust

            Product::create([
                // The Foreign Keys
                'category_id' => $selectedCategory->id,
                'purity_id'   => $randomPurityId,
                'supplier_id'   => $faker->numberBetween(1, 10),

                // The Barcode logic: CODE + NUMBER (e.g., RNG-1001)
                'barcode'     => $selectedCategory->code . '-' . (1000 + $index),

                'name'        => $randomCatName . ' ' . $faker->randomElement(['Classic', 'Modern', 'Royal', 'Antique']),
                'gross_weight' => $grossWeight,
                'net_weight'  => $netWeight,
                'making_charge' => $faker->numberBetween(450, 1200), // Charge per gram logic
                'is_sold'     => false,
            ]);
        }
    }
}
