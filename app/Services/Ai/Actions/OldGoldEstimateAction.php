<?php

namespace App\Services\Ai\Actions;

use App\Models\DailyRate;
use App\Services\Ai\Contracts\AiActionInterface;
use Carbon\Carbon;

class OldGoldEstimateAction implements AiActionInterface
{
    public function handle(array $args): array
    {
        $weight = floatval($args['weight'] ?? 10);
        $itemName = trim((string) ($args['item_name'] ?? ($args['name'] ?? 'Old Gold / Purana Sona')));
        $deductionPercent = floatval($args['deduction_percent'] ?? ($args['wastage_percent'] ?? 0));
        $customRate = (isset($args['custom_rate']) || isset($args['rate'])) ? floatval($args['custom_rate'] ?? $args['rate']) : null;

        // Purity multiplier resolved directly from AI or standard math formula
        $purityMultiplier = floatval($args['purity_multiplier'] ?? ($args['purity_fraction'] ?? 0));
        $resolvedPurity = trim((string) ($args['purity_label'] ?? ($args['purity'] ?? '22K')));

        if ($purityMultiplier <= 0) {
            if (preg_match('/(\d+(?:\.\d+)?)\s*K/i', $resolvedPurity, $m)) {
                $karat = floatval($m[1]);
                $purityMultiplier = round($karat / 24, 4);
                $resolvedPurity = "{$karat}K (" . round(($karat / 24) * 100, 2) . '%)';
            } elseif (preg_match('/(\d+(?:\.\d+)?)\s*%/i', $resolvedPurity, $m)) {
                $pct = floatval($m[1]);
                $purityMultiplier = round($pct / 100, 4);
                $resolvedPurity = "{$pct}% (" . round(($pct / 100) * 24, 1) . 'K)';
            } elseif (preg_match('/\b(999|916|750|585)\b/', $resolvedPurity, $m)) {
                $purityMultiplier = round(floatval($m[1]) / 1000, 4);
                $resolvedPurity = "{$m[1]} Hallmark";
            } else {
                $purityMultiplier = 0.916;
                $resolvedPurity = '22K (916 Hallmark)';
            }
        }

        // Live Rate
        $rateRecord = DailyRate::whereDate('date', Carbon::today())
            ->where(function ($q) {
                $q->where('gold_buy', '>', 0)->orWhere('gold_sell', '>', 0);
            })
            ->first();

        if (! $rateRecord) {
            $rateRecord = DailyRate::where(function ($q) {
                $q->where('gold_buy', '>', 0)->orWhere('gold_sell', '>', 0);
            })->latest('date')->first();
        }

        if ($customRate === null && ! $rateRecord) {
            return [
                'found' => false,
                'message' => 'Aaj ka live gold bhav database me add nahi hai.',
                'status' => 'RATE_NOT_SET_TODAY',
            ];
        }

        $base24kBuyRate = floatval(($rateRecord?->gold_buy > 0) ? $rateRecord->gold_buy : ($rateRecord?->gold_sell ?? 0));
        $ratePerGm = ($customRate !== null && $customRate > 0) ? $customRate : round($base24kBuyRate * $purityMultiplier, 2);

        // Mathematical Valuation
        $fineGoldWeight = round($weight * $purityMultiplier, 3);
        $grossValuation = round($weight * $ratePerGm, 2);
        $deductionAmount = round($grossValuation * ($deductionPercent / 100), 2);
        $netPayout = round($grossValuation - $deductionAmount, 2);

        return [
            'found' => true,
            'is_old_gold' => true,
            'item_name' => $itemName,
            'weight' => number_format($weight, 3) . ' g',
            'weight_numeric' => $weight,
            'fine_gold_weight' => number_format($fineGoldWeight, 3) . ' g (24K Pure)',
            'purity' => $resolvedPurity,
            'purity_multiplier' => $purityMultiplier,
            'base_24k_rate' => '₹' . number_format($base24kBuyRate, 2),
            'rate_per_gm' => '₹' . number_format($ratePerGm, 2),
            'rate_per_gm_numeric' => $ratePerGm,
            'gross_valuation' => '₹' . number_format($grossValuation, 2),
            'deduction_percent' => $deductionPercent,
            'deduction_amount' => '₹' . number_format($deductionAmount, 2),
            'total_estimate' => '₹' . number_format($netPayout, 2),
            'total_estimate_numeric' => $netPayout,
            'status' => 'OLD_GOLD_VALUATION',
            'note' => 'Old Gold Buyback / Exchange Value (No Making Charges, No GST)',
        ];
    }
}
