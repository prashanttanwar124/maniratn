<?php

namespace App\Services\Ai\Actions;

use App\Services\Ai\Contracts\AiActionInterface;
use App\Services\InventoryBarcodeService;

class CreateBillAction implements AiActionInterface
{
    public function __construct(
        private readonly InventoryBarcodeService $inventory,
    ) {}

    public function handle(array $args): array
    {
        $customerName = trim((string) ($args['customer_name'] ?? $args['customer'] ?? $args['name'] ?? ''));
        $rawPhone = trim((string) ($args['customer_phone'] ?? $args['customer_mobile'] ?? $args['phone'] ?? $args['mobile'] ?? ''));
        $customerPhone = preg_replace('/\s+|-/', '', $rawPhone);
        $barcode = $this->inventory->normalize((string) ($args['barcode'] ?? ''));

        if ($customerName === '' || $customerPhone === '') {
            return [
                'found' => false,
                'message' => 'Invoice draft ke liye customer name aur mobile number zaroori hai.',
                'status' => 'CUSTOMER_DETAILS_REQUIRED',
            ];
        }

        if ($barcode === '') {
            return [
                'found' => false,
                'message' => 'Showroom billing ke liye product barcode zaroori hai. Barcode scan karein ya batayein.',
                'status' => 'BARCODE_REQUIRED',
            ];
        }

        $inventory = $this->inventory->find($barcode);
        if (! $inventory) {
            return [
                'found' => false,
                'message' => "Barcode '{$barcode}' showroom inventory me nahi mila.",
                'status' => 'BARCODE_NOT_FOUND',
            ];
        }

        $item = $inventory['item'];
        $soldOut = (bool) $item->is_sold
            || ($inventory['type'] === 'silver_product' && $item->pricing_mode === 'PIECE' && (int) $item->quantity <= 0);

        if ($soldOut) {
            return [
                'found' => false,
                'message' => "Product '{$item->name}' ({$item->barcode}) already sold hai.",
                'status' => 'PRODUCT_ALREADY_SOLD',
            ];
        }

        [$makingType, $makingValue] = $this->makingOverride($args);

        return [
            'found' => true,
            'action_type' => 'CREATE_INVOICE_DRAFT',
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'barcode' => $item->barcode,
            'inventory_type' => $inventory['type'],
            'item_name' => $item->name,
            'quantity' => max(1, (int) ($args['quantity'] ?? 1)),
            'rate_per_gm' => $this->positiveNumber($args['rate_per_gm'] ?? $args['rate'] ?? null),
            'making_type' => $makingType,
            'making_value' => $makingValue,
            'discount_amount' => max(0, (float) ($args['discount_amount'] ?? 0)),
            'payment_mode' => strtoupper((string) ($args['payment_mode'] ?? 'CASH')),
            'payment_amount' => $this->positiveNumber($args['payment_amount'] ?? null),
            'status' => 'READY_FOR_INVOICE_DRAFT',
        ];
    }

    /** @return array{0: string|null, 1: float|null} */
    private function makingOverride(array $args): array
    {
        $candidates = [
            'flat' => $args['making_charge_flat'] ?? $args['making_flat'] ?? null,
            'per_gram' => $args['making_charge_per_gram'] ?? $args['making_per_gram'] ?? null,
            'percentage' => $args['making_percent'] ?? null,
        ];

        foreach ($candidates as $type => $value) {
            $number = $this->positiveNumber($value);
            if ($number !== null) {
                return [$type, $number];
            }
        }

        if (isset($args['making_type'], $args['making_value'])) {
            return [(string) $args['making_type'], max(0, (float) $args['making_value'])];
        }

        return [null, null];
    }

    private function positiveNumber(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return $number > 0 ? $number : null;
    }
}
