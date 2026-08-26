<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SilverProduct;

class InventoryBarcodeService
{
    /**
     * @return array{type: 'product'|'silver_product', item: Product|SilverProduct}|null
     */
    public function find(string $barcode): ?array
    {
        $normalized = $this->normalize($barcode);

        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^S\d+$/i', $normalized)) {
            $silver = $this->findSilver($normalized);

            return $silver ? ['type' => 'silver_product', 'item' => $silver] : null;
        }

        if (preg_match('/^G\d+$/i', $normalized)) {
            $gold = $this->findGold($normalized);

            return $gold ? ['type' => 'product', 'item' => $gold] : null;
        }

        $gold = $this->findGold($normalized);
        if ($gold) {
            return ['type' => 'product', 'item' => $gold];
        }

        $silver = $this->findSilver($normalized);

        return $silver ? ['type' => 'silver_product', 'item' => $silver] : null;
    }

    public function normalize(string $barcode): string
    {
        return strtoupper(trim($barcode));
    }

    private function findGold(string $barcode): ?Product
    {
        $product = Product::with(['category', 'purity', 'supplier'])
            ->where('barcode', $barcode)
            ->first();

        if (! $product && preg_match('/^G(\d+)$/i', $barcode, $matches)) {
            $padded = 'G'.str_pad($matches[1], 5, '0', STR_PAD_LEFT);
            $product = Product::with(['category', 'purity', 'supplier'])
                ->where('barcode', $padded)
                ->first()
                ?? Product::with(['category', 'purity', 'supplier'])->find((int) $matches[1]);
        }

        return $product;
    }

    private function findSilver(string $barcode): ?SilverProduct
    {
        $product = SilverProduct::with(['category', 'supplier'])
            ->where('barcode', $barcode)
            ->first();

        if (! $product && preg_match('/^S(\d+)$/i', $barcode, $matches)) {
            $padded = 'S'.str_pad($matches[1], 5, '0', STR_PAD_LEFT);
            $product = SilverProduct::with(['category', 'supplier'])
                ->where('barcode', $padded)
                ->first()
                ?? SilverProduct::with(['category', 'supplier'])->find((int) $matches[1]);
        }

        return $product;
    }
}
