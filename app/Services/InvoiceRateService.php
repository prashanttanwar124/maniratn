<?php

namespace App\Services;

use App\Models\DailyRate;
use App\Models\Product;
use App\Models\SilverProduct;
use Carbon\Carbon;

class InvoiceRateService
{
    public function currentRates(): ?DailyRate
    {
        return DailyRate::query()->whereDate('date', Carbon::today())->first()
            ?? DailyRate::query()->latest('date')->first();
    }

    public function multiplierFor(string $inventoryType, Product|SilverProduct $item): float
    {
        if ($inventoryType !== 'product') {
            return 1.0;
        }

        return $this->purityMultiplier((string) ($item->purity?->name ?? '22K'));
    }

    public function rateFor(
        string $inventoryType,
        Product|SilverProduct $item,
        float $goldRate,
        float $silverRate,
    ): float {
        if ($inventoryType === 'silver_product') {
            return $item->pricing_mode === 'PIECE'
                ? round(max(0, (float) ($item->piece_price ?? 0)), 2)
                : round(max(0, $silverRate), 2);
        }

        return round(max(0, $goldRate) * $this->multiplierFor($inventoryType, $item), 2);
    }

    /** @return array{gold_rate: float, silver_rate: float, rate_multiplier: float, rate: float} */
    public function defaultsFor(string $inventoryType, Product|SilverProduct $item): array
    {
        $rates = $this->currentRates();
        $goldRate = (float) ($rates?->gold_sell ?? 0);
        $silverRate = (float) ($rates?->silver_sell ?? 0);

        return [
            'gold_rate' => $goldRate,
            'silver_rate' => $silverRate,
            'rate_multiplier' => $this->multiplierFor($inventoryType, $item),
            'rate' => $this->rateFor($inventoryType, $item, $goldRate, $silverRate),
        ];
    }

    private function purityMultiplier(string $purity): float
    {
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:K|KT|CT|CARAT|KARAT)\b/i', $purity, $matches)) {
            return round((float) $matches[1] / 24, 4);
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*%/i', $purity, $matches)) {
            return round((float) $matches[1] / 100, 4);
        }

        if (preg_match('/\b(999|916|750|585)\b/', $purity, $matches)) {
            return round((float) $matches[1] / 1000, 4);
        }

        return 0.916;
    }
}
