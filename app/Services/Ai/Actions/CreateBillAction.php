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
        $barcode = trim((string) ($args['barcode'] ?? ''));
        $itemName = trim((string) ($args['item_name'] ?? 'Gold Ornament'));
        $weight = floatval($args['weight'] ?? 10);
        $metal = strtoupper($args['metal'] ?? 'GOLD');
        $purityStr = strtoupper($args['purity'] ?? '22K');
        $customRate = (isset($args['rate_per_gm']) || isset($args['rate'])) ? floatval($args['rate_per_gm'] ?? $args['rate']) : null;
        $makingPercent = isset($args['making_percent']) ? floatval($args['making_percent']) : null;
        $makingPerGm = isset($args['making_charge_per_gram']) ? floatval($args['making_charge_per_gram']) : (isset($args['making_per_gram']) ? floatval($args['making_per_gram']) : null);
        $makingFlat = isset($args['making_charge_flat']) ? floatval($args['making_charge_flat']) : (isset($args['making_flat']) ? floatval($args['making_flat']) : null);
        $paymentMode = strtoupper((string) ($args['payment_mode'] ?? 'CASH'));
        $paymentAmount = isset($args['payment_amount']) ? floatval($args['payment_amount']) : null;
        $discountAmount = floatval($args['discount_amount'] ?? 0);

        $matchedProduct = null;
        $matchedSilverProduct = null;

        // 1. If Barcode provided, fetch exact stock item from database
        if (! empty($barcode)) {
            $matchedProduct = Product::where('barcode', $barcode)->first();
            if (! $matchedProduct) {
                $matchedSilverProduct = SilverProduct::where('barcode', $barcode)->first();
            }

            if (! $matchedProduct && ! $matchedSilverProduct) {
                return [
                    'found' => false,
                    'message' => "Barcode '{$barcode}' database me nahi mila. Kripya barcode check karein.",
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
                $weight = floatval($matchedProduct->net_weight);
                $metal = 'GOLD';
                $purityStr = $matchedProduct->purity?->name ?? '22K';
                if ($matchedProduct->making_charge > 0 && $makingPercent === null && $makingPerGm === null && $makingFlat === null) {
                    $makingPerGm = floatval($matchedProduct->making_charge);
                }
            } elseif ($matchedSilverProduct) {
                if ($matchedSilverProduct->is_sold) {
                    return [
                        'found' => false,
                        'message' => "Silver item '{$matchedSilverProduct->name}' (Barcode: {$barcode}) pehle se sold hai!",
                        'status' => 'PRODUCT_ALREADY_SOLD',
                    ];
                }
                $itemName = $matchedSilverProduct->name;
                $weight = floatval($matchedSilverProduct->net_weight);
                $metal = 'SILVER';
                $purityStr = 'Silver';
                if ($matchedSilverProduct->making_charge > 0 && $makingPercent === null && $makingPerGm === null && $makingFlat === null) {
                    $makingPerGm = floatval($matchedSilverProduct->making_charge);
                }
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
                if (str_contains($purityStr, '18') || str_contains($purityStr, '750')) {
                    $effectiveRate = $gold24k * 0.750;
                } elseif (str_contains($purityStr, '24') || str_contains($purityStr, '999')) {
                    $effectiveRate = $gold24k;
                } elseif (str_contains($purityStr, '14') || str_contains($purityStr, '585')) {
                    $effectiveRate = $gold24k * 0.585;
                } else {
                    $effectiveRate = round($gold24k * 0.916, 2); // Default 22K (916)
                }
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
