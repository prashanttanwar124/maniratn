<?php

namespace App\Services\Ai\Actions;

use App\Models\DailyRate;
use App\Services\Ai\Contracts\AiActionInterface;
use Carbon\Carbon;

class EstimateQuotationAction implements AiActionInterface
{
    public function handle(array $args): array
    {
        $weight = floatval($args['weight'] ?? 10);
        $metal = strtoupper($args['metal'] ?? 'GOLD');
        $purity = $args['purity'] ?? '22K';
        $customRate = (isset($args['custom_rate']) || isset($args['rate'])) ? floatval($args['custom_rate'] ?? $args['rate']) : null;
        $makingPercent = isset($args['making_percent']) ? floatval($args['making_percent']) : null;
        $makingPerGm = isset($args['making_charge_per_gram']) ? floatval($args['making_charge_per_gram']) : null;

        // Fetch today's rate
        $rateRecord = DailyRate::whereDate('date', Carbon::today())->where('gold_sell', '>', 0)->first();
        if (! $rateRecord) {
            $rateRecord = DailyRate::where('gold_sell', '>', 0)->latest('date')->first();
        }

        if ($customRate === null && ! $rateRecord) {
            return [
                'found' => false,
                'message' => 'Aaj ka live gold bhav database me add nahi hai.',
                'status' => 'RATE_NOT_SET_TODAY',
            ];
        }

        if ($customRate !== null && $customRate > 0) {
            $ratePerGm = $customRate;
        } else {
            $ratePerGm = ($metal === 'SILVER')
                ? floatval($rateRecord->silver_sell)
                : (floatval($rateRecord->gold_sell) * 0.916);
        }

        $metalValue = $weight * $ratePerGm;

        if ($makingPercent !== null && $makingPercent > 0) {
            $makingTotal = $metalValue * ($makingPercent / 100);
            $makingLabel = "({$makingPercent}%)";
        } elseif ($makingPerGm !== null && $makingPerGm > 0) {
            $makingTotal = $weight * $makingPerGm;
            $makingLabel = "(@ ₹{$makingPerGm}/g)";
        } else {
            $makingTotal = $metalValue * 0.12; // Standard 12% making
            $makingLabel = "(12%)";
        }

        $subtotal = $metalValue + $makingTotal;
        $gst = $subtotal * 0.03;
        $grandTotal = $subtotal + $gst;

        return [
            'found' => true,
            'weight' => $weight . ' g',
            'metal' => $metal,
            'purity' => $purity,
            'rate_per_gm' => '₹' . number_format($ratePerGm, 2),
            'metal_value' => '₹' . number_format($metalValue, 2),
            'making_charges' => '₹' . number_format($makingTotal, 2) . " {$makingLabel}",
            'subtotal' => '₹' . number_format($subtotal, 2),
            'gst_3_percent' => '₹' . number_format($gst, 2),
            'total_estimate' => '₹' . number_format($grandTotal, 2),
        ];
    }
}
