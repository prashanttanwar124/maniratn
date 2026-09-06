<?php

namespace App\Services;

class MetalWeightService
{
    public static function purityFromWeights(float $grossWeight, float $fineWeight): ?float
    {
        if ($grossWeight <= 0 || $fineWeight < 0) {
            return null;
        }

        return round(min(100, ($fineWeight / $grossWeight) * 100), 4);
    }

    public static function fineWeight(float $grossWeight, mixed $purity): ?float
    {
        $purityPercent = self::purityPercent($purity);

        return $purityPercent === null
            ? null
            : round(max(0, $grossWeight) * ($purityPercent / 100), 3);
    }

    public static function purityPercent(mixed $purity): ?float
    {
        if ($purity === null || $purity === '') {
            return null;
        }

        if (is_numeric($purity)) {
            $number = (float) $purity;

            // 3-digit millesimal fineness (e.g. 999, 995, 925, 916, 800, 750, 585, 375)
            if ($number > 100 && $number <= 1000) {
                return round($number / 10, 4);
            }

            return $number > 0 && $number <= 100 ? round($number, 4) : null;
        }

        $label = trim((string) $purity);
        if ($label === '') {
            return null;
        }

        // 1. Explicit percentage in label: e.g. "24K (99.9%)", "22K (91.6%)", "Custom (84.5%)", "84.5%"
        if (preg_match('/(\d+(?:\.\d+)?)\s*%/', $label, $matches)) {
            return round((float) $matches[1], 4);
        }

        // 2. Standard Indian Hallmark millesimal numbers (999, 995, 925, 916, 800, 750, 585, 375)
        // e.g. "Silver 925", "Silver 800", "Gold 916", "999 Pure"
        if (preg_match('/\b(999|995|925|916|800|750|585|375)\b/', $label, $matches)) {
            return round((float) $matches[1] / 10, 4);
        }

        // 3. Standard BIS Karats (e.g. 24K, 22K, 18K, 14K, 9K)
        if (preg_match('/(\d+(?:\.\d+)?)\s*K/i', $label, $matches)) {
            $k = (int) round((float) $matches[1]);
            $standardKarats = [
                24 => 99.9,
                22 => 91.6,
                18 => 75.0,
                14 => 58.5,
                9  => 37.5,
            ];
            if (isset($standardKarats[$k])) {
                return $standardKarats[$k];
            }

            // Fallback for non-standard Karats (e.g. 21K, 20K, 10K)
            return round(((float) $matches[1] / 24) * 100, 4);
        }

        return null;
    }
}
