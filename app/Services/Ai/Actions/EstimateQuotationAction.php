<?php

namespace App\Services\Ai\Actions;

use App\Models\DailyRate;
use App\Models\Product;
use App\Models\SilverProduct;
use App\Services\Ai\Contracts\AiActionInterface;
use Carbon\Carbon;

class EstimateQuotationAction implements AiActionInterface
{
    public function handle(array $args): array
    {
        $barcode = trim($args['barcode'] ?? $args['code'] ?? $args['item_barcode'] ?? '');
        $product = null;
        $isSilver = false;

        // If barcode given or present in query
        if (! empty($barcode)) {
            $normalized = strtoupper($barcode);
            $product = Product::with(['category', 'purity'])
                ->where('barcode', $normalized)
                ->first();

            if (! $product && preg_match('/^G(\d{5})$/', $normalized, $matches)) {
                $product = Product::with(['category', 'purity'])->find((int) $matches[1]);
            }

            if (! $product) {
                $product = SilverProduct::with('category')
                    ->where('barcode', $normalized)
                    ->first();
                if (! $product && preg_match('/^S(\d{5})$/', $normalized, $matches)) {
                    $product = SilverProduct::with('category')->find((int) $matches[1]);
                }
                if ($product) {
                    $isSilver = true;
                }
            }
        }

        // Determine parameters from product or fallback to args
        if ($product) {
            $name = $product->name;
            $weight = floatval($product->net_weight ?: $product->gross_weight ?: ($args['weight'] ?? 10));
            $metal = $isSilver ? 'SILVER' : 'GOLD';
            $purity = $isSilver ? 'Silver 999' : ($product->purity?->name ?? ($args['purity'] ?? '916 Hallmark'));
            $makingCharge = isset($args['making_percent']) || isset($args['making_charge_per_gram'])
                ? floatval($args['making_percent'] ?? $args['making_charge_per_gram'])
                : floatval($product->making_charge ?? 12);
            $makingChargeType = $product->making_charge_type ?? ($isSilver ? 'per_gram' : 'percentage');
            $itemBarcode = $product->barcode;
            $categoryName = $product->category?->name ?? 'Jewellery';
        } else {
            $name = $args['item_name'] ?? ($args['name'] ?? 'Ornament');
            $weight = floatval($args['weight'] ?? 10);
            $metal = strtoupper($args['metal'] ?? 'GOLD');
            $purity = $args['purity'] ?? '916 Hallmark';
            $itemBarcode = $barcode ?: null;
            $categoryName = $args['category'] ?? 'Jewellery';

            if (isset($args['making_charge_per_gram'])) {
                $makingCharge = floatval($args['making_charge_per_gram']);
                $makingChargeType = 'per_gram';
            } elseif (isset($args['making_percent'])) {
                $makingCharge = floatval($args['making_percent']);
                $makingChargeType = 'percentage';
            } else {
                $makingCharge = 12.0;
                $makingChargeType = 'percentage';
            }
        }

        $customRate = (isset($args['custom_rate']) || isset($args['rate'])) ? floatval($args['custom_rate'] ?? $args['rate']) : null;

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

        // Detect if this is an old gold / buyback inquiry
        $isOldGold = ! empty($args['is_old_gold'])
            || str_contains(strtolower($name), 'old gold')
            || str_contains(strtolower($name), 'purana sona')
            || str_contains(strtolower($name), 'old');

        if ($isOldGold) {
            $makingCharge = 0;
        }

        // Purity resolution & dynamic multiplier (handles 17K, 20K, 22K, 916, 750, 85%, etc.)
        $purityStr = strtoupper(trim((string) $purity));
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
        } elseif (str_contains($purityStr, '24K') || str_contains($purityStr, '999') || str_contains($purityStr, '24')) {
            $purityMultiplier = 1.0;
            $resolvedPurity = '24K (99.9%)';
        } elseif (str_contains($purityStr, '18K') || str_contains($purityStr, '750') || str_contains($purityStr, '18')) {
            $purityMultiplier = 0.750;
            $resolvedPurity = '18K (750 Hallmark)';
        } elseif (str_contains($purityStr, '14K') || str_contains($purityStr, '585') || str_contains($purityStr, '14')) {
            $purityMultiplier = 0.585;
            $resolvedPurity = '14K (585 Hallmark)';
        } elseif (str_contains($purityStr, '916') || str_contains($purityStr, '22K') || str_contains($purityStr, '22')) {
            $purityMultiplier = 0.916;
            $resolvedPurity = '22K (916 Hallmark)';
        }

        // Live Rate
        $baseGoldRate = $isOldGold && ($rateRecord?->gold_buy > 0)
            ? floatval($rateRecord->gold_buy)
            : floatval($rateRecord?->gold_sell ?? 0);

        if ($customRate !== null && $customRate > 0) {
            $ratePerGm = $customRate;
        } else {
            $ratePerGm = ($metal === 'SILVER')
                ? floatval($rateRecord->silver_sell)
                : round($baseGoldRate * $purityMultiplier, 2);
        }

        $base24kRate = $baseGoldRate;
        $metalValue = round($weight * $ratePerGm, 2);

        // Making Charges & GST calculation (Old Gold = 0% making, 0% GST)
        if ($isOldGold) {
            $makingTotal = 0;
            $makingLabel = '(No Making / Old Gold)';
            $subtotal = $metalValue;
            $gst = 0;
            $grandTotal = $metalValue;
        } else {
            if ($makingChargeType === 'flat') {
                $makingTotal = round($makingCharge, 2);
                $makingLabel = '(₹' . number_format($makingCharge) . ' Flat)';
            } elseif ($makingChargeType === 'per_gram') {
                $makingTotal = round($weight * $makingCharge, 2);
                $makingLabel = '(@ ₹' . number_format($makingCharge) . '/g)';
            } else {
                $makingTotal = round($metalValue * ($makingCharge / 100), 2);
                $makingLabel = "({$makingCharge}%)";
            }

            $subtotal = round($metalValue + $makingTotal, 2);
            $gst = round($subtotal * 0.03, 2);
            $grandTotal = round($subtotal + $gst, 2);
        }

        $fineGoldWeight = round($weight * $purityMultiplier, 3);

        return [
            'found' => true,
            'is_old_gold' => $isOldGold,
            'barcode' => $itemBarcode,
            'item_name' => $name,
            'category' => $categoryName,
            'weight' => $weight . ' g',
            'weight_numeric' => $weight,
            'fine_gold_weight' => number_format($fineGoldWeight, 3) . ' g',
            'metal' => $metal,
            'purity' => $resolvedPurity,
            'base_24k_rate' => $base24kRate > 0 ? '₹' . number_format($base24kRate) . '/g' : null,
            'rate_per_gm' => '₹' . number_format($ratePerGm, 2),
            'rate_per_gm_numeric' => $ratePerGm,
            'metal_value' => '₹' . number_format($metalValue, 2),
            'metal_value_numeric' => $metalValue,
            'making_charge_type' => $makingChargeType,
            'making_charge_rate' => $makingCharge,
            'making_charges' => '₹' . number_format($makingTotal, 2) . " {$makingLabel}",
            'making_charges_numeric' => $makingTotal,
            'subtotal' => '₹' . number_format($subtotal, 2),
            'subtotal_numeric' => $subtotal,
            'gst_3_percent' => '₹' . number_format($gst, 2),
            'gst_numeric' => $gst,
            'total_estimate' => '₹' . number_format($grandTotal, 2),
            'total_estimate_numeric' => $grandTotal,
        ];
    }
}

