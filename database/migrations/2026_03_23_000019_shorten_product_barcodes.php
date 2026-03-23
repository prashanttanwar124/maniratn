<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->orderBy('id')
            ->get(['id'])
            ->each(function ($product) {
                DB::table('products')
                    ->where('id', $product->id)
                    ->update([
                        'barcode' => 'G' . str_pad((string) $product->id, 5, '0', STR_PAD_LEFT),
                    ]);
            });

        DB::table('silver_products')
            ->orderBy('id')
            ->get(['id'])
            ->each(function ($product) {
                DB::table('silver_products')
                    ->where('id', $product->id)
                    ->update([
                        'barcode' => 'S' . str_pad((string) $product->id, 5, '0', STR_PAD_LEFT),
                    ]);
            });
    }

    public function down(): void
    {
        DB::table('products')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->orderBy('products.id')
            ->get(['products.id', 'categories.code'])
            ->each(function ($product) {
                DB::table('products')
                    ->where('id', $product->id)
                    ->update([
                        'barcode' => 'MJ-' . $product->code . '-' . str_pad((string) $product->id, 5, '0', STR_PAD_LEFT),
                    ]);
            });

        DB::table('silver_products')
            ->join('categories', 'categories.id', '=', 'silver_products.category_id')
            ->orderBy('silver_products.id')
            ->get(['silver_products.id', 'categories.code'])
            ->each(function ($product) {
                DB::table('silver_products')
                    ->where('id', $product->id)
                    ->update([
                        'barcode' => 'MS-' . $product->code . '-' . str_pad((string) $product->id, 5, '0', STR_PAD_LEFT),
                    ]);
            });
    }
};
