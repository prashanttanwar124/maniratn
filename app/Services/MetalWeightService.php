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
        if (is_numeric($purity)) {
            $number = (float) $purity;

            if ($number > 100 && $number <= 1000) {
                return round($number / 10, 4);
            }

            return $number > 0 && $number <= 100 ? round($number, 4) : null;
        }

        $label = trim((string) $purity);
        if ($label === '') {
            return null;
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*K/i', $label, $matches)) {
            return round(((float) $matches[1] / 24) * 100, 4);
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*%/', $label, $matches)) {
            return round((float) $matches[1], 4);
        }

        if (preg_match('/\b(999|916|750|585)\b/', $label, $matches)) {
            return round((float) $matches[1] / 10, 4);
        }

        return null;
    }
}
