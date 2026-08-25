<?php

namespace App\Services\Ai\Actions;

use App\Models\DailyRate;
use App\Models\Product;
use App\Models\SilverProduct;
use App\Services\Ai\Contracts\AiActionInterface;
use Carbon\Carbon;

class CreateBillAction implements AiActionInterface
{
    public function handle(array $args): array
    {
        $customerName = trim((string) ($args['customer_name'] ?? 'Walk-in Customer'));
        $customerPhone = trim((string) ($args['customer_phone'] ?? ''));
        $barcode = strtoupper(trim((string) ($args['barcode'] ?? '')));
        $customRate = (isset($args['rate_per_gm']) || isset($args['rate'])) ? floatval($args['rate_per_gm'] ?? $args['rate']) : null;
        $makingPercent = isset($args['making_percent']) ? floatval($args['making_percent']) : null;
        $makingPerGm = isset($args['making_charge_per_gram']) ? floatval($args['making_charge_per_gram']) : (isset($args['making_per_gram']) ? floatval($args['making_per_gram']) : null);
        $makingFlat = isset($args['making_charge_flat']) ? floatval($args['making_charge_flat']) : (isset($args['making_flat']) ? floatval($args['making_flat']) : null);
        $paymentMode = strtoupper((string) ($args['payment_mode'] ?? 'CASH'));
        $paymentAmount = isset($args['payment_amount']) ? floatval($args['payment_amount']) : null;
        $discountAmount = floatval($args['discount_amount'] ?? 0);

        // 1. Barcode is strictly mandatory for all showroom sales
        if (empty($barcode)) {
            return [
                'found' => false,
                'message' => 'Showroom billing ke liye Product Barcode zaroori hai. Kripya item ka barcode scan karein ya batayein (e.g. G00021 ya S00005).',
                'status' => 'BARCODE_REQUIRED',
            ];
        }

        $matchedProduct = Product::where('barcode', $barcode)->first();
        if (! $matchedProduct && preg_match('/^G(\d{5})$/', $barcode, $m)) {
            $matchedProduct = Product::find((int) $m[1]);
        }

        $matchedSilverProduct = null;
        if (! $matchedProduct) {
            $matchedSilverProduct = SilverProduct::where('barcode', $barcode)->first();
            if (! $matchedSilverProduct && preg_match('/^S(\d{5})$/', $barcode, $m)) {
                $matchedSilverProduct = SilverProduct::find((int) $m[1]);
            }
        }

        if (! $matchedProduct && ! $matchedSilverProduct) {
            return [
                'found' => false,
                'message' => "Barcode '{$barcode}' showroom inventory me nahi mila. Kripya valid product barcode check karein.",
                'status' => 'BARCODE_NOT_FOUND',
            ];
        }

        if ($matchedProduct) {
            if ($matchedProduct->is_sold) {
                return [
                    'found' => false,
                    'message' => "Product '{$matchedProduct->name}' (Barcode: {$barcode}) pehle se hi sold hai!",
                    'status' => 'PRODUCT_ALREADY_SOLD',
                ];
            }
            $itemName = $matchedProduct->name;
            $weight = floatval($matchedProduct->net_weight ?: $matchedProduct->gross_weight);
            $metal = 'GOLD';
            $purityStr = $matchedProduct->purity?->name ?? '22K';
            if ($matchedProduct->making_charge > 0 && $makingPercent === null && $makingPerGm === null && $makingFlat === null) {
                if (($matchedProduct->making_charge_type ?? 'percentage') === 'percentage') {
                    $makingPercent = floatval($matchedProduct->making_charge);
                } elseif ($matchedProduct->making_charge_type === 'flat') {
                    $makingFlat = floatval($matchedProduct->making_charge);
                } else {
                    $makingPerGm = floatval($matchedProduct->making_charge);
                }
            }
        } else {
            if ($matchedSilverProduct->is_sold || ($matchedSilverProduct->pricing_mode === 'PIECE' && $matchedSilverProduct->quantity <= 0)) {
                return [
                    'found' => false,
                    'message' => "Silver item '{$matchedSilverProduct->name}' (Barcode: {$barcode}) pehle se sold out hai!",
                    'status' => 'PRODUCT_ALREADY_SOLD',
                ];
            }
            $itemName = $matchedSilverProduct->name;
            $weight = floatval($matchedSilverProduct->net_weight ?: $matchedSilverProduct->gross_weight);
            $metal = 'SILVER';
            $purityStr = 'Silver (92.5)';
            if ($matchedSilverProduct->making_charge > 0 && $makingPercent === null && $makingPerGm === null && $makingFlat === null) {
                $makingPerGm = floatval($matchedSilverProduct->making_charge);
            }
        }

        // 2. Fetch live daily rate from database
        $rateRecord = DailyRate::whereDate('date', Carbon::today())->where('gold_sell', '>', 0)->first();
        if (! $rateRecord) {
            $rateRecord = DailyRate::where('gold_sell', '>', 0)->latest('date')->first();
        }

        if ($customRate !== null && $customRate > 0) {
            $effectiveRate = $customRate;
        } elseif ($rateRecord) {
            if ($metal === 'SILVER') {
                $effectiveRate = floatval($rateRecord->silver_sell ?: 89.0);
            } else {
                $gold24k = floatval($rateRecord->gold_sell ?: 7450.0);
                $purityMultiplier = floatval($args['purity_multiplier'] ?? ($args['purity_fraction'] ?? 0));
                if ($purityMultiplier <= 0) {
                    if (preg_match('/(\d+(?:\.\d+)?)\s*K/i', $purityStr, $m)) {
                        $purityMultiplier = round(floatval($m[1]) / 24, 4);
                    } elseif (preg_match('/(\d+(?:\.\d+)?)\s*%/i', $purityStr, $m)) {
                        $purityMultiplier = round(floatval($m[1]) / 100, 4);
                    } elseif (preg_match('/\b(999|916|750|585)\b/', $purityStr, $m)) {
                        $purityMultiplier = round(floatval($m[1]) / 1000, 4);
                    } else {
                        $purityMultiplier = 0.916;
                    }
                }
                $effectiveRate = round($gold24k * $purityMultiplier, 2);
            }
        } else {
            $effectiveRate = ($metal === 'SILVER') ? 89.0 : 6830.0;
        }
        $effectiveRate = round((float) $effectiveRate, 2);

        // 3. Compute Metal value, making charges, GST & Totals
        $metalValue = round($weight * $effectiveRate, 2);

        if ($makingFlat !== null && $makingFlat > 0) {
            $makingTotal = round($makingFlat, 2);
            $makingType = 'flat';
            $makingValue = $makingFlat;
            $makingLabel = "(₹{$makingFlat} Flat)";
        } elseif ($makingPerGm !== null && $makingPerGm > 0) {
            $makingTotal = round($weight * $makingPerGm, 2);
            $makingType = 'per_gram';
            $makingValue = $makingPerGm;
            $makingLabel = "(@ ₹{$makingPerGm}/g)";
        } elseif ($makingPercent !== null && $makingPercent > 0) {
            $makingTotal = round($metalValue * ($makingPercent / 100), 2);
            $makingType = 'percentage';
            $makingValue = $makingPercent;
            $makingLabel = "({$makingPercent}%)";
        } else {
            $makingType = 'percentage';
            $makingValue = 12.0;
            $makingTotal = round($metalValue * 0.12, 2);
            $makingLabel = "(12%)";
        }

        $subtotal = max(0, $metalValue + $makingTotal - $discountAmount);
        $gstAmount = round($subtotal * 0.03, 2);
        $grandTotal = round($subtotal + $gstAmount, 2);

        // ALWAYS RETURN DRAFT PREVIEW FOR USER VERIFICATION FIRST
        return [
            'found' => true,
            'is_preview' => true,
            'action_type' => 'CREATE_BILL',
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'barcode' => $barcode ?: ($matchedProduct?->barcode ?? $matchedSilverProduct?->barcode ?? ''),
            'item_name' => ! empty($itemName) ? $itemName : "{$purityStr} {$metal} Ornament",
            'weight' => $weight,
            'metal' => $metal,
            'purity' => $purityStr,
            'rate_per_gm' => $effectiveRate,
            'metal_value' => $metalValue,
            'making_type' => $makingType,
            'making_value' => $makingValue,
            'making_label' => $makingLabel,
            'making_charges' => $makingTotal,
            'discount_amount' => $discountAmount,
            'subtotal' => $subtotal,
            'gst_3_percent' => $gstAmount,
            'grand_total' => $grandTotal,
            'payment_mode' => $paymentMode,
            'payment_amount' => $paymentAmount ?? $grandTotal,
            'status' => 'CONFIRMATION_REQUIRED',
        ];
    }
}
