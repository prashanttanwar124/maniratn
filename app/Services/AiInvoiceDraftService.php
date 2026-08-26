<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\InvoiceDraft;
use App\Models\Product;
use App\Models\SilverProduct;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AiInvoiceDraftService
{
    public function __construct(
        private readonly InventoryBarcodeService $inventory,
        private readonly InvoiceRateService $invoiceRates,
    ) {}

    /**
     * Add an AI-resolved barcode to one persistent invoice draft.
     * Replaying the same AI message updates the same draft and never duplicates the item.
     *
     * @return array<string, mixed>
     */
    public function createOrAppend(User $user, array $action, string $sourceReference): array
    {
        return DB::transaction(function () use ($user, $action, $sourceReference) {
            $inventory = $this->inventory->find((string) ($action['barcode'] ?? ''));

            if (! $inventory) {
                throw ValidationException::withMessages([
                    'barcode' => 'Product barcode showroom inventory me nahi mila.',
                ]);
            }

            $item = $inventory['item'];
            $this->ensureAvailable($inventory['type'], $item);

            $customer = $this->resolveCustomer($action);
            $reference = mb_substr(trim($sourceReference), 0, 120);

            $draft = InvoiceDraft::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'source_type' => 'AI_COPILOT',
                    'source_reference' => $reference,
                ],
                [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'item_count' => 0,
                    'grand_total' => 0,
                    'draft_data' => $this->emptyDraftData($customer),
                ],
            );
            $draft = InvoiceDraft::query()->lockForUpdate()->findOrFail($draft->id);
            $data = (array) $draft->draft_data;

            $data['customer_id'] = $customer->id;
            $data['customer_obj'] = [
                'id' => $customer->id,
                'name' => $customer->name,
                'mobile' => $customer->mobile,
            ];

            $draftItem = $this->buildDraftItem($inventory['type'], $item, $action, $data);
            $items = collect($data['items'] ?? []);
            $existingIndex = $items->search(fn (array $existing) => ($existing['type'] ?? null) === $draftItem['type']
                && (int) ($existing['id'] ?? 0) === $draftItem['id']
            );

            if ($existingIndex === false) {
                $items->push($draftItem);
            } else {
                $items->put($existingIndex, $draftItem);
            }

            $data['items'] = $items->values()->all();
            $data['discount_type'] = $data['discount_type'] ?? 'amount';
            $data['discount_value'] = count($data['items']) > 1
                ? (float) ($data['discount_value'] ?? 0)
                : max(0, (float) ($action['discount_amount'] ?? $data['discount_value'] ?? 0));
            $data['payment_cash'] = (float) ($data['payment_cash'] ?? 0);
            $data['payment_card'] = (float) ($data['payment_card'] ?? 0);
            $data['card_note'] = (string) ($data['card_note'] ?? '');

            $grandTotal = $this->calculateGrandTotal($data);

            $draft->fill([
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'item_count' => count($data['items']),
                'grand_total' => $grandTotal,
                'draft_data' => $data,
            ])->save();

            return [
                'draft_id' => $draft->id,
                'draft_url' => route('invoices.create', ['draft' => $draft->id], false),
                'item_count' => count($data['items']),
                'grand_total' => $grandTotal,
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->mobile,
                'barcode' => $item->barcode,
                'item_name' => $item->name,
                'is_preview' => false,
                'status' => 'INVOICE_DRAFT_SAVED',
            ];
        });
    }

    private function resolveCustomer(array $action): Customer
    {
        $name = trim((string) ($action['customer_name'] ?? $action['customer'] ?? $action['name'] ?? ''));
        $rawPhone = trim((string) ($action['customer_phone'] ?? $action['customer_mobile'] ?? $action['phone'] ?? $action['mobile'] ?? ''));
        $phone = preg_replace('/\s+|-/', '', $rawPhone);

        if ($name === '' || $phone === '') {
            throw ValidationException::withMessages([
                'customer' => 'Invoice draft ke liye customer name aur mobile number zaroori hai.',
            ]);
        }

        $customer = Customer::query()->firstOrCreate(
            ['mobile' => $phone],
            [
                'name' => $name,
                'address' => 'Store Counter Sale',
                'city' => 'Local',
                'vault_token' => Customer::generateVaultToken(),
            ],
        );

        if ($customer->name === 'Walk-in Customer' && $name !== '') {
            $customer->update(['name' => $name]);
        }

        return $customer;
    }

    /** @return array<string, mixed> */
    private function emptyDraftData(Customer $customer): array
    {
        $rates = $this->invoiceRates->currentRates();

        return [
            'customer_id' => $customer->id,
            'customer_obj' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'mobile' => $customer->mobile,
            ],
            'date' => Carbon::today()->toDateString(),
            'gold_rate' => (float) ($rates?->gold_sell ?? 0),
            'silver_rate' => (float) ($rates?->silver_sell ?? 0),
            'discount_type' => 'amount',
            'discount_value' => 0,
            'items' => [],
            'payment_cash' => 0,
            'payment_card' => 0,
            'card_note' => '',
        ];
    }

    /** @return array<string, mixed> */
    private function buildDraftItem(string $type, Product|SilverProduct $item, array $action, array $draftData): array
    {
        $weight = (float) ($item->net_weight ?: $item->gross_weight);
        $makingType = (string) ($action['making_type'] ?? $item->making_charge_type ?? ($type === 'product' ? 'percentage' : 'per_gram'));
        $makingValue = array_key_exists('making_value', $action) && $action['making_value'] !== null
            ? max(0, (float) $action['making_value'])
            : max(0, (float) ($item->making_charge ?? 0));

        if ($type === 'silver_product' && $item->pricing_mode === 'PIECE') {
            $quantity = min(max(1, (int) ($action['quantity'] ?? 1)), max(1, (int) $item->quantity));
            $rate = max(0, (float) ($item->piece_price ?? 0));
            $base = $rate * $quantity;
            $effectiveWeight = $weight * $quantity;
        } else {
            $quantity = $type === 'silver_product' ? max(1, (int) ($item->quantity ?? 1)) : 1;
            $rate = $this->resolveWeightRate($type, $item, $action, $draftData);
            $base = $weight * $rate;
            $effectiveWeight = $weight;
        }

        $makingTotal = match ($makingType) {
            'flat', 'lump_sum' => $makingValue,
            'per_gram' => $effectiveWeight * $makingValue,
            default => $base * ($makingValue / 100),
        };

        return [
            'type' => $type,
            'id' => $item->id,
            'description' => $item->name.($item->barcode ? " ({$item->barcode})" : ''),
            'weight' => $weight,
            'quantity' => $quantity,
            'quantity_available' => $type === 'silver_product' ? (int) ($item->quantity ?? 0) : null,
            'pricing_mode' => $type === 'silver_product' ? $item->pricing_mode : null,
            'purity' => $type === 'product' ? ($item->purity?->name ?? '22K') : 'Silver',
            'rate' => round($rate, 2),
            'rate_multiplier' => $this->invoiceRates->multiplierFor($type, $item),
            'making_charges' => round($makingValue, 2),
            'making_charge_type' => $makingType === 'lump_sum' ? 'flat' : $makingType,
            'final_price' => round($base + $makingTotal, 2),
            'draft_valid' => true,
            'draft_issue' => null,
        ];
    }

    private function resolveWeightRate(string $type, Product|SilverProduct $item, array $action, array $draftData): float
    {
        return $this->invoiceRates->rateFor(
            $type,
            $item,
            max(0, (float) ($draftData['gold_rate'] ?? 0)),
            max(0, (float) ($draftData['silver_rate'] ?? 0)),
        );
    }

    private function calculateGrandTotal(array $data): float
    {
        $subtotal = collect($data['items'] ?? [])->sum(fn (array $item) => (float) ($item['final_price'] ?? 0));
        $discountValue = max(0, (float) ($data['discount_value'] ?? 0));
        $discount = ($data['discount_type'] ?? 'amount') === 'percentage'
            ? $subtotal * (min($discountValue, 100) / 100)
            : min($discountValue, $subtotal);
        $taxable = max(0, $subtotal - $discount);

        return round($taxable + ($taxable * 0.03), 2);
    }

    private function ensureAvailable(string $type, Product|SilverProduct $item): void
    {
        $soldOut = (bool) $item->is_sold
            || ($type === 'silver_product' && $item->pricing_mode === 'PIECE' && (int) $item->quantity <= 0);

        if ($soldOut) {
            throw ValidationException::withMessages([
                'barcode' => "{$item->barcode} already sold hai aur invoice draft me add nahi ho sakta.",
            ]);
        }
    }
}
