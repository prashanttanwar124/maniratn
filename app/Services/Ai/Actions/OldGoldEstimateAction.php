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
        $purityInput = trim((string) ($args['purity'] ?? '22K'));
        $itemName = trim((string) ($args['item_name'] ?? ($args['name'] ?? 'Old Gold / Purana Sona')));
        $deductionPercent = floatval($args['deduction_percent'] ?? ($args['wastage_percent'] ?? 0));
        $customRate = (isset($args['custom_rate']) || isset($args['rate'])) ? floatval($args['custom_rate'] ?? $args['rate']) : null;

        // Dynamic Purity Multiplier
        $purityStr = strtoupper($purityInput);
        $purityMultiplier = 0.916;
        $resolvedPurity = '22K (916 Hallmark)';

        if (preg_match('/(\d+(?:\.\d+)?)\s*K/i', $purityStr, $m)) {
            $karat = floatval($m[1]);
            $purityMultiplier = round($karat / 24, 4);
            $pct = round(($karat / 24) * 100, 2);
            $resolvedPurity = "{$karat}K ({$pct}%)";
        } elseif (preg_match('/(\d+(?:\.\d+)?)\s*%/i', $purityStr, $m)) {
            $pct = floatval($m[1]);
            $purityMultiplier = round($pct / 100, 4);
            $karat = round(($pct / 100) * 24, 1);
            $resolvedPurity = "{$pct}% ({$karat}K)";
        } elseif (str_contains($purityStr, '999') || str_contains($purityStr, '24K') || str_contains($purityStr, '24')) {
            $purityMultiplier = 1.0;
            $resolvedPurity = '24K (99.9%)';
        } elseif (str_contains($purityStr, '916') || str_contains($purityStr, '22K') || str_contains($purityStr, '22')) {
            $purityMultiplier = 0.916;
            $resolvedPurity = '22K (916 Hallmark)';
        } elseif (str_contains($purityStr, '750') || str_contains($purityStr, '18K') || str_contains($purityStr, '18')) {
            $purityMultiplier = 0.750;
            $resolvedPurity = '18K (750 Hallmark)';
        } elseif (str_contains($purityStr, '585') || str_contains($purityStr, '14K') || str_contains($purityStr, '14')) {
            $purityMultiplier = 0.585;
            $resolvedPurity = '14K (585 Hallmark)';
        }

        // Fetch Live Rate
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

        if ($customRate !== null && $customRate > 0) {
            $ratePerGm = $customRate;
        } else {
            $ratePerGm = round($base24kBuyRate * $purityMultiplier, 2);
        }

        // Calculations
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
