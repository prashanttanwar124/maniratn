<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SilverProduct;
use App\Models\DailyRate;
use App\Enums\VaultType;
use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\InvoiceItem;
use App\Models\InvoiceDraft;
use App\Models\BusinessSetting;
use Illuminate\Http\Request;
use App\Services\VaultService;
use App\Services\LedgerImpactService;
use App\Services\InvoiceRateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Transaction; // <--- The Ledger Model
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceRateService $invoiceRates,
    ) {}

    private function validateDraftItemsPayload(array $items): array
    {
        return collect($items)->map(function (array $item) {
            $validatedItem = $item;
            $validatedItem['draft_valid'] = true;
            $validatedItem['draft_issue'] = null;

            if (($item['type'] ?? null) === 'product') {
                $product = Product::find($item['id'] ?? 0);

                if ($product) {
                    $validatedItem['purity'] = $product->purity?->name ?? '22K';
                    $validatedItem['rate_multiplier'] = $this->invoiceRates->multiplierFor('product', $product);
                }

                if (! $product) {
                    $validatedItem['draft_valid'] = false;
                    $validatedItem['draft_issue'] = 'This gold stock item no longer exists.';
                } elseif ($product->is_sold) {
                    $validatedItem['draft_valid'] = false;
                    $validatedItem['draft_issue'] = 'This gold stock item has already been sold.';
                }
            } elseif (($item['type'] ?? null) === 'silver_product') {
                $silverProduct = SilverProduct::find($item['id'] ?? 0);

                if (! $silverProduct) {
                    $validatedItem['draft_valid'] = false;
                    $validatedItem['draft_issue'] = 'This silver stock item no longer exists.';
                } elseif ($silverProduct->is_sold) {
                    $validatedItem['draft_valid'] = false;
                    $validatedItem['draft_issue'] = 'This silver stock item has already been sold.';
                } else {
                    $validatedItem['purity'] = $validatedItem['purity'] ?? 'Silver 925';
                    $validatedItem['rate_multiplier'] = 1;
                    $validatedItem['quantity_available'] = (int) $silverProduct->quantity;
                    $validatedItem['pricing_mode'] = $silverProduct->pricing_mode;

                    if ($silverProduct->pricing_mode === 'PIECE') {
                        $requestedQuantity = max(1, (int) ($item['quantity'] ?? 1));
                        $availableQuantity = (int) $silverProduct->quantity;

                        if ($requestedQuantity > $availableQuantity) {
                            $validatedItem['draft_valid'] = false;
                            $validatedItem['draft_issue'] = $availableQuantity > 0
                                ? "Only {$availableQuantity} piece(s) left in stock."
                                : 'This silver piece item is now out of stock.';
                        }
                    }
                }
            } elseif (($item['type'] ?? null) === 'order_item') {
                $orderItem = OrderItem::find($item['id'] ?? 0);

                if (! $orderItem) {
                    $validatedItem['draft_valid'] = false;
                    $validatedItem['draft_issue'] = 'This custom order item no longer exists.';
                } elseif ($orderItem->status !== 'READY') {
                    $validatedItem['draft_valid'] = false;
                    $validatedItem['draft_issue'] = 'This custom order item is no longer ready for billing.';
                } elseif (! $orderItem->finished_weight || (float) $orderItem->finished_weight <= 0) {
                    $validatedItem['draft_valid'] = false;
                    $validatedItem['draft_issue'] = 'This custom order item is missing finished weight.';
                } else {
                    $validatedItem['weight'] = (float) $orderItem->finished_weight;
                    $validatedItem['metal_type'] = strtoupper((string) ($orderItem->metal_type ?? 'GOLD'));
                    $validatedItem['rate_multiplier'] = 1;
                }
            }

            return $validatedItem;
        })->values()->all();
    }

    private function getUserDrafts()
    {
        return InvoiceDraft::with('customer')
            ->where('user_id', Auth::id())
            ->latest('updated_at')
            ->get();
    }

    private function transformDraft(InvoiceDraft $draft): array
    {
        $data = $draft->draft_data ?? [];
        $customerObj = $data['customer_obj'] ?? null;

        if (! $customerObj && $draft->customer) {
            $customerObj = [
                'id' => $draft->customer->id,
                'name' => $draft->customer->name,
                'mobile' => $draft->customer->mobile,
            ];
        }

        return [
            'id' => $draft->id,
            'customerName' => $draft->customer_name ?: ($draft->customer?->name ?: 'No customer'),
            'itemCount' => (int) $draft->item_count,
            'grandTotal' => (float) $draft->grand_total,
            'savedAt' => optional($draft->updated_at)->toISOString(),
            'data' => [
                'customer_id' => $data['customer_id'] ?? null,
                'date' => $data['date'] ?? now()->toDateString(),
                'gold_rate' => (float) ($data['gold_rate'] ?? 0),
                'silver_rate' => (float) ($data['silver_rate'] ?? 0),
                'discount_type' => $data['discount_type'] ?? 'amount',
                'discount_value' => (float) ($data['discount_value'] ?? 0),
                'items' => $data['items'] ?? [],
                'payment_cash' => (float) ($data['payment_cash'] ?? 0),
                'payment_card' => (float) ($data['payment_card'] ?? 0),
                'card_note' => $data['card_note'] ?? '',
            ],
            'customerObj' => $customerObj,
        ];
    }


    public function index()
    {
        $invoices = Invoice::with('customer')
            ->with(['items.product', 'items.silverProduct', 'items.orderItem', 'transactions', 'cancelledBy', 'user'])
            ->orderBy('created_at', 'desc')
            ->get();

        $drafts = $this->getUserDrafts()->map(fn (InvoiceDraft $draft) => $this->transformDraft($draft))->values();

        return Inertia::render('invoices/Index', [
            'invoices' => $invoices->map(function (Invoice $invoice) {
                $paidAmount = (float) $invoice->transactions
                    ->where('type', 'PAYMENT')
                    ->sum('amount');

                $pendingAmount = $invoice->status === 'CANCELLED'
                    ? 0
                    : max((float) $invoice->total_amount - $paidAmount, 0);

                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'customer' => [
                        'id' => $invoice->customer?->id,
                        'name' => $invoice->customer?->name,
                        'mobile' => $invoice->customer?->mobile,
                        'city' => $invoice->customer?->city,
                        'address' => $invoice->customer?->address,
                    ],
                    'date' => $invoice->date,
                    'created_at' => optional($invoice->created_at)->toDateTimeString(),
                    'created_by' => $invoice->user?->name,
                    'gold_rate_applied' => (float) ($invoice->gold_rate_applied ?? 0),
                    'silver_rate_applied' => (float) ($invoice->silver_rate_applied ?? 0),
                    'status' => $invoice->status,
                    'total_amount' => (float) $invoice->total_amount,
                    'discount_type' => $invoice->discount_type,
                    'discount_value' => (float) ($invoice->discount_value ?? 0),
                    'discount_amount' => (float) ($invoice->discount_amount ?? 0),
                    'tax_amount' => (float) ($invoice->tax_amount ?? 0),
                    'paid_amount' => $paidAmount,
                    'pending_amount' => $pendingAmount,
                    'void_amount' => $invoice->status === 'CANCELLED' ? $paidAmount : 0,
                    'item_count' => $invoice->items->count(),
                    'items' => $invoice->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'item_name' => $item->description ?: ($item->product?->name ?? $item->silverProduct?->name ?? 'Jewellery Item'),
                            'weight' => (float) $item->weight,
                            'purity' => $item->purity,
                            'rate' => (float) $item->rate,
                            'making_charges' => (float) $item->making_charges,
                            'making_charge_type' => $item->making_charge_type ?: ($item->product_id ? 'percentage' : 'per_gram'),
                            'quantity' => (int) ($item->quantity ?? 1),
                            'total_price' => (float) ($item->final_price ?? $item->total_price ?? 0),
                            'product_barcode' => $item->product?->barcode,
                            'silver_product_barcode' => $item->silverProduct?->barcode,
                        ];
                    })->values()->all(),
                    'payments' => $invoice->transactions->where('type', 'PAYMENT')->map(function ($txn) {
                        return [
                            'id' => $txn->id,
                            'amount' => (float) $txn->amount,
                            'payment_method' => $txn->payment_method ?: 'CASH',
                            'date' => $txn->date,
                            'description' => $txn->description,
                            'created_at' => optional($txn->created_at)->toDateTimeString(),
                        ];
                    })->values()->all(),
                    'cancellation_mode' => $invoice->cancellation_mode,
                    'cancellation_reason' => $invoice->cancellation_reason,
                    'cancelled_at' => optional($invoice->cancelled_at)?->toDateTimeString(),
                    'cancelled_by' => $invoice->cancelledBy?->name,
                    'vault_url' => $invoice->customer?->vault_token ? ($this->publicBaseUrl() . '/vault/' . $invoice->customer->vault_token) : null,
                ];
            })->values(),
            'drafts' => $drafts,
            'business' => [
                'store_name' => BusinessSetting::first()?->store_name ?? 'Maniratn Jewellers',
                'google_review_url' => BusinessSetting::first()?->google_review_url,
                'phone' => BusinessSetting::first()?->phone,
            ],
        ]);
    }

    public function create(Request $request)
    {
        $prefilledItems = [];
        $customer = null;
        $lockCustomer = false;
        $todayRate = DailyRate::query()
            ->whereDate('date', now()->toDateString())
            ->first();

        // If we are coming from the Kanban Board with an Order ID
        if ($request->has('order_id')) {

            // 1. Find ALL sibling items for this Order that are READY
            $prefilledItems = \App\Models\OrderItem::where('order_id', $request->order_id)
                ->where('status', 'READY')
                // Optional: Ensure item hasn't been billed yet
                // ->whereDoesntHave('invoiceItems') 
                ->with('order') // Get purity/weight info
                ->get();

            // 2. Get the Customer details from the first item
            if ($prefilledItems->isNotEmpty()) {
                $customer = $prefilledItems->first()->order->customer;
                $lockCustomer = true;
            }
        }

        if (!$customer && $request->filled('customer_id')) {
            $customer = Customer::find($request->integer('customer_id'));
        }

        $draftToLoad = null;
        if ($request->filled('draft')) {
            $draft = InvoiceDraft::with('customer')
                ->where('user_id', Auth::id())
                ->find($request->integer('draft'));

            if ($draft) {
                $draftToLoad = $this->transformDraft($draft);
            }
        }

        return Inertia::render('invoices/Create', [
            'customers'      => \App\Models\Customer::all(),
            'defaultGoldRate' => (float) ($todayRate?->gold_sell ?? 0),
            'defaultSilverRate' => (float) ($todayRate?->silver_sell ?? 0),
            // Pass the ready items to the frontend
            'prefilledItems' => $prefilledItems,
            'prefilledCustomer' => $customer,
            'lockCustomer' => $lockCustomer,
            'drafts' => $this->getUserDrafts()->map(fn (InvoiceDraft $draft) => $this->transformDraft($draft))->values(),
            'draftToLoad' => $draftToLoad,
        ]);
    }

    public function saveDraft(Request $request)
    {
        $validated = $request->validate([
            'draft_id' => 'nullable|integer',
            'customer_id' => 'nullable|exists:customers,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_obj' => 'nullable|array',
            'customer_obj.id' => 'nullable|integer',
            'customer_obj.name' => 'nullable|string|max:255',
            'customer_obj.mobile' => 'nullable|string|max:50',
            'date' => 'required|date',
            'gold_rate' => 'nullable|numeric|min:0',
            'silver_rate' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:amount,percentage',
            'discount_value' => 'nullable|numeric|min:0',
            'items' => 'nullable|array',
            'items.*.type' => 'required_with:items|in:product,order_item,silver_product',
            'items.*.id' => 'required_with:items|integer',
            'items.*.description' => 'nullable|string',
            'items.*.weight' => 'nullable|numeric|min:0',
            'items.*.quantity' => 'nullable|integer|min:1',
            'items.*.quantity_available' => 'nullable|integer|min:0',
            'items.*.pricing_mode' => 'nullable|string|max:20',
            'items.*.purity' => 'nullable',
            'items.*.rate' => 'nullable|numeric|min:0',
            'items.*.rate_multiplier' => 'nullable|numeric|min:0|max:1',
            'items.*.making_charges' => 'nullable|numeric|min:0',
            'items.*.making_charge_type' => 'nullable|string|in:percentage,flat,lump_sum,per_gram',
            'items.*.final_price' => 'nullable|numeric|min:0',
            'payment_cash' => 'nullable|numeric|min:0',
            'payment_card' => 'nullable|numeric|min:0',
            'card_note' => 'nullable|string|max:100',
            'grand_total' => 'nullable|numeric|min:0',
        ]);

        $draft = null;
        if (! empty($validated['draft_id'])) {
            $draft = InvoiceDraft::where('user_id', Auth::id())->find($validated['draft_id']);
        }

        $customer = ! empty($validated['customer_id'])
            ? Customer::find($validated['customer_id'])
            : null;

        $draftPayload = [
            'customer_id' => $validated['customer_id'] ?? null,
            'date' => $validated['date'],
            'gold_rate' => (float) ($validated['gold_rate'] ?? 0),
            'silver_rate' => (float) ($validated['silver_rate'] ?? 0),
            'discount_type' => $validated['discount_type'] ?? 'amount',
            'discount_value' => (float) ($validated['discount_value'] ?? 0),
            'items' => $validated['items'] ?? [],
            'payment_cash' => (float) ($validated['payment_cash'] ?? 0),
            'payment_card' => (float) ($validated['payment_card'] ?? 0),
            'card_note' => $validated['card_note'] ?? '',
            'customer_obj' => $validated['customer_obj'] ?? ($customer ? [
                'id' => $customer->id,
                'name' => $customer->name,
                'mobile' => $customer->mobile,
            ] : null),
        ];

        $draft = InvoiceDraft::updateOrCreate(
            [
                'id' => $draft?->id,
            ],
            [
                'user_id' => Auth::id(),
                'customer_id' => $validated['customer_id'] ?? null,
                'customer_name' => $validated['customer_name'] ?? $customer?->name ?? 'No customer',
                'item_count' => count($validated['items'] ?? []),
                'grand_total' => (float) ($validated['grand_total'] ?? 0),
                'draft_data' => $draftPayload,
            ],
        );

        $draft->load('customer');

        return response()->json([
            'draft' => $this->transformDraft($draft),
        ]);
    }

    public function destroyDraft(InvoiceDraft $invoiceDraft)
    {
        abort_unless($invoiceDraft->user_id === Auth::id(), 403);

        $invoiceDraft->delete();

        return response()->json(['success' => true]);
    }

    public function validateDraftItems(Request $request)
    {
        $validated = $request->validate([
            'items' => 'nullable|array',
            'items.*.type' => 'required_with:items|in:product,order_item,silver_product',
            'items.*.id' => 'required_with:items|integer',
            'items.*.quantity' => 'nullable|integer|min:1',
            'items.*.quantity_available' => 'nullable|integer|min:0',
            'items.*.pricing_mode' => 'nullable|string|max:20',
            'items.*.description' => 'nullable|string',
            'items.*.weight' => 'nullable|numeric|min:0',
            'items.*.purity' => 'nullable',
            'items.*.rate' => 'nullable|numeric|min:0',
            'items.*.rate_multiplier' => 'nullable|numeric|min:0|max:1',
            'items.*.making_charges' => 'nullable|numeric|min:0',
            'items.*.making_charge_type' => 'nullable|string|in:percentage,flat,lump_sum,per_gram',
            'items.*.final_price' => 'nullable|numeric|min:0',
        ]);

        $items = $this->validateDraftItemsPayload($validated['items'] ?? []);
        $hasInvalidItems = collect($items)->contains(fn (array $item) => ($item['draft_valid'] ?? true) === false);

        return response()->json([
            'items' => $items,
            'has_invalid_items' => $hasInvalidItems,
        ]);
    }

    private function calculateItemTotalPrice(string $type, float $weight, float $rate, float $making, string $makingType = 'percentage', int $quantity = 1, ?string $pricingMode = null): float
    {
        $makingAmount = 0.0;

        if ($type === 'silver_product' && $pricingMode === 'PIECE') {
            $base = $rate * $quantity;
            if ($makingType === 'percentage') {
                $makingAmount = $base * ($making / 100);
            } elseif ($makingType === 'flat' || $makingType === 'lump_sum') {
                $makingAmount = $making;
            } else {
                $makingAmount = $weight * $making;
            }
            return round($base + $makingAmount, 2);
        }

        $metalValue = $weight * $rate;

        if ($makingType === 'flat' || $makingType === 'lump_sum') {
            $makingAmount = $making;
        } elseif ($makingType === 'per_gram') {
            $makingAmount = $weight * $making;
        } else {
            $makingAmount = $metalValue * ($making / 100);
        }

        return round($metalValue + $makingAmount, 2);
    }

    private function publicBaseUrl(): string
    {
        $website = trim((string) \App\Models\BusinessSetting::query()->value('website'));

        return rtrim($website !== '' ? $website : config('app.url'), '/');
    }

    public function print(Invoice $invoice)
    {
        $invoice->load([
            'customer',
            'items.product',
            'items.silverProduct',
            'items.orderItem',
            'transactions',
            'user',
        ]);

        $paidAmount = (float) $invoice->transactions
            ->where('type', 'PAYMENT')
            ->sum('amount');

        $balanceDue = $invoice->status === 'CANCELLED'
            ? 0
            : max((float) $invoice->total_amount - $paidAmount, 0);

        $vaultUrl = null;
        $qrCodeBase64 = null;
        $qrSvg = null;

        if ($invoice->customer) {
            $customer = $invoice->customer;
            if (! $customer->vault_token) {
                $customer->vault_token = Customer::generateVaultToken();
                if (! $customer->card_status || $customer->card_status === 'NOT_ISSUED') {
                    $customer->card_status = 'ISSUED';
                    $customer->card_issued_at = now();
                }
                $customer->save();
            }

            $vaultUrl = $this->publicBaseUrl() . '/vault/' . $customer->vault_token;

            try {
                $barcode = new \TCPDF2DBarcode($vaultUrl, 'QRCODE,M');
                $rawSvg = $barcode->getBarcodeSVGcode(2.1, 2.1, 'black');
                $qrSvg = preg_replace('/^<\?xml[^>]*\?>\s*(<!DOCTYPE[^>]*>)?\s*/i', '', (string) $rawSvg);
                $qrSvg = preg_replace('/(<svg[^>]*>)/i', '$1<rect width="100%" height="100%" fill="#ffffff"/>', (string) $qrSvg);

                $pngData = $barcode->getBarcodePngData(4, 4);
                if ($pngData) {
                    $qrCodeBase64 = 'data:image/png;base64,' . base64_encode($pngData);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed generating invoice QR code: ' . $e->getMessage());
            }
        }

        $business = BusinessSetting::first();
        $googleReviewUrl = $business?->google_review_url;
        $googleReviewQrBase64 = null;
        $googleReviewQrSvg = null;

        if ($googleReviewUrl) {
            try {
                $reviewBarcode = new \TCPDF2DBarcode($googleReviewUrl, 'QRCODE,M');
                $rawReviewSvg = $reviewBarcode->getBarcodeSVGcode(1.8, 1.8, 'black');
                $googleReviewQrSvg = preg_replace('/^<\?xml[^>]*\?>\s*(<!DOCTYPE[^>]*>)?\s*/i', '', (string) $rawReviewSvg);
                $googleReviewQrSvg = preg_replace('/(<svg[^>]*>)/i', '$1<rect width="100%" height="100%" fill="#ffffff"/>', (string) $googleReviewQrSvg);

                $reviewPngData = $reviewBarcode->getBarcodePngData(4, 4);
                if ($reviewPngData) {
                    $googleReviewQrBase64 = 'data:image/png;base64,' . base64_encode($reviewPngData);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed generating Google Review QR code: ' . $e->getMessage());
            }
        }

        return view('print.invoice', [
            'invoice' => $invoice,
            'paidAmount' => $paidAmount,
            'balanceDue' => $balanceDue,
            'vaultUrl' => $vaultUrl,
            'qrCodeBase64' => $qrCodeBase64,
            'qrSvg' => $qrSvg,
            'googleReviewUrl' => $googleReviewUrl,
            'googleReviewQrBase64' => $googleReviewQrBase64,
            'googleReviewQrSvg' => $googleReviewQrSvg,
        ]);
    }



    public function store(Request $request)
    {
        // 1. Validate: Accept a generic 'items' array containing mixed types
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'gold_rate'   => 'nullable|numeric|min:0',
            'date'        => 'required|date',
            'discount_type' => 'nullable|in:amount,percentage',
            'discount_value' => 'nullable|numeric|min:0',
            'silver_rate' => 'nullable|numeric|min:0',

            // MIXED ITEMS LIST (Can be Product OR OrderItem)
            'items'       => 'required|array',
            'items.*.type' => 'required|in:product,order_item,silver_product', // Identify the type
            'items.*.id'   => 'required|integer', // The ID of the Product or OrderItem
            'items.*.rate' => 'nullable|numeric|min:0',
            'items.*.making_charges' => 'required|numeric|min:0', // We need this from frontend
            'items.*.making_charge_type' => 'nullable|string|in:percentage,flat,lump_sum,per_gram',
            'items.*.quantity' => 'nullable|integer|min:1',
            'draft_id' => 'nullable|integer',

            'payment_cash' => 'nullable|numeric|min:0',
            'payment_card' => 'nullable|numeric|min:0',
            'card_note'    => 'nullable|string|max:100',
        ]);

        return DB::transaction(function () use ($validated) {

            $totalBillAmount = 0;
            $totalVaultGoldSoldWeight = 0;
            $totalVaultSilverSoldWeight = 0;
            $defaultGoldRate = (float) ($validated['gold_rate'] ?? 0);
            $defaultSilverRate = (float) ($validated['silver_rate'] ?? 0);
            $items = collect($validated['items']);

            if ($items->contains(function ($item) use ($defaultGoldRate, $defaultSilverRate) {
                $itemType = $item['type'] ?? '';
                if ($itemType === 'order_item') {
                    $orderItem = OrderItem::find($item['id']);
                    $metalType = strtoupper((string) ($orderItem?->metal_type ?? 'GOLD'));
                    $itemRate = (float) ($item['rate'] ?? ($metalType === 'SILVER' ? $defaultSilverRate : $defaultGoldRate));
                    return $itemRate <= 0;
                }
                $itemRate = (float) ($item['rate'] ?? ($itemType === 'silver_product' ? $defaultSilverRate : $defaultGoldRate));
                return $itemRate <= 0;
            })) {
                throw ValidationException::withMessages([
                    'items' => 'A valid rate is required for every invoice item.',
                ]);
            }

            if ($items->contains(function ($item) {
                if (($item['type'] ?? null) !== 'silver_product') {
                    return false;
                }

                $silverProduct = SilverProduct::find($item['id']);

                return $silverProduct?->pricing_mode === 'PIECE' && (float) ($item['rate'] ?? 0) <= 0;
            })) {
                throw ValidationException::withMessages([
                    'items' => 'A valid piece rate is required for every silver piece item.',
                ]);
            }

            // 2. Create Invoice Header
            $invoice = Invoice::create([
                'invoice_number' => 'TMP-' . Str::uuid(),
                'customer_id'    => $validated['customer_id'],
                'gold_rate_applied' => (float) ($validated['gold_rate'] ?? 0),
                'silver_rate_applied' => (float) ($validated['silver_rate'] ?? 0),
                'discount_type' => $validated['discount_type'] ?? null,
                'discount_value' => (float) ($validated['discount_value'] ?? 0),
                'discount_amount' => 0,
                'date'           => $validated['date'],
                'total_amount'   => 0,
                'user_id'        => Auth::id(),
            ]);

            $invoice->update([
                'invoice_number' => sprintf('INV-%s-%06d', now()->format('Ymd'), $invoice->id),
            ]);

            // 3. LOOP THROUGH MIXED ITEMS
            foreach ($validated['items'] as $row) {

                $weight = 0;
                $purity = 0;
                $itemName = '';
                $makingType = $row['making_charge_type'] ?? null;
                $makingValue = (float) ($row['making_charges'] ?? 0);

                // --- CASE A: IT IS A STOCK PRODUCT (Ring from Showcase) ---
                if ($row['type'] === 'product') {
                    $product = Product::query()->lockForUpdate()->findOrFail($row['id']);
                    $rateApplied = (float) ($row['rate'] ?? ($validated['gold_rate'] ?? 0));
                    $makingType = $makingType ?: ($makingValue > 100 ? 'flat' : 'percentage');

                    if ($makingType === 'percentage' && $makingValue > 100) {
                        throw ValidationException::withMessages([
                            'items' => "Making percentage for {$product->name} cannot be greater than 100.",
                        ]);
                    }

                    // Validation: Check if already sold
                    if ($product->is_sold) {
                        abort(400, "Product {$product->name} is already sold!");
                    }

                    $weight = (float) $product->net_weight;
                    $purity = $product->purity; // e.g., 91.6
                    $itemName = $product->name;
                    $itemTotal = $this->calculateItemTotalPrice('product', $weight, $rateApplied, $makingValue, $makingType, 1);

                    // Mark Stock as SOLD
                    $product->update(['is_sold' => true]);

                    // Save to InvoiceItems
                    InvoiceItem::create([
                        'invoice_id'         => $invoice->id,
                        'product_id'         => $product->id, // Link Product
                        'description'        => $itemName,
                        'quantity'           => 1,
                        'weight'             => $weight,
                        'purity'             => $purity->name,
                        'rate'               => $rateApplied,
                        'making_charges'     => $makingValue,
                        'making_charge_type' => $makingType === 'lump_sum' ? 'flat' : $makingType,
                        'final_price'        => $itemTotal,
                    ]);

                    $totalBillAmount += $itemTotal;
                    continue;
                }

                elseif ($row['type'] === 'silver_product') {
                    $silverProduct = SilverProduct::query()->lockForUpdate()->findOrFail($row['id']);

                    if ($silverProduct->is_sold) {
                        abort(400, "Silver product {$silverProduct->name} is already sold!");
                    }

                    $saleQuantity = max(1, (int) ($row['quantity'] ?? 1));
                    $rateApplied = (float) ($row['rate'] ?? ($validated['silver_rate'] ?? 0));
                    $makingType = $makingType ?: ($silverProduct->pricing_mode === 'PIECE' ? 'per_gram' : 'per_gram');

                    if ($silverProduct->pricing_mode === 'PIECE') {
                        if ($saleQuantity > (int) $silverProduct->quantity) {
                            throw ValidationException::withMessages([
                                'items' => "Requested quantity for {$silverProduct->name} exceeds available stock.",
                            ]);
                        }

                        $weight = (float) ($silverProduct->net_weight ?? 0) * $saleQuantity;
                        $itemName = $silverProduct->name;
                        $itemTotal = $this->calculateItemTotalPrice('silver_product', $weight, $rateApplied, $makingValue, $makingType, $saleQuantity, 'PIECE');

                        $remainingQuantity = (int) $silverProduct->quantity - $saleQuantity;
                        $silverProduct->update([
                            'quantity' => $remainingQuantity,
                            'is_sold' => $remainingQuantity <= 0,
                        ]);

                        InvoiceItem::create([
                            'invoice_id'         => $invoice->id,
                            'silver_product_id'  => $silverProduct->id,
                            'description'        => $itemName,
                            'quantity'           => $saleQuantity,
                            'weight'             => $weight,
                            'purity'             => 'Silver',
                            'rate'               => $rateApplied,
                            'making_charges'     => $makingValue,
                            'making_charge_type' => $makingType === 'lump_sum' ? 'flat' : $makingType,
                            'final_price'        => $itemTotal,
                        ]);

                        $totalBillAmount += $itemTotal;
                        continue;
                    }

                    $weight = (float) $silverProduct->net_weight;
                    $originalQuantity = max(1, (int) $silverProduct->quantity);
                    $itemName = $silverProduct->name;
                    $itemTotal = $this->calculateItemTotalPrice('silver_product', $weight, $rateApplied, $makingValue, $makingType, $originalQuantity, 'WEIGHT');

                    $silverProduct->update([
                        'quantity' => 0,
                        'is_sold' => true,
                    ]);

                    InvoiceItem::create([
                        'invoice_id'         => $invoice->id,
                        'silver_product_id'  => $silverProduct->id,
                        'description'        => $itemName,
                        'quantity'           => $originalQuantity,
                        'weight'             => $weight,
                        'purity'             => 'Silver',
                        'rate'               => $rateApplied,
                        'making_charges'     => $makingValue,
                        'making_charge_type' => $makingType === 'lump_sum' ? 'flat' : $makingType,
                        'final_price'        => $itemTotal,
                    ]);

                    $totalBillAmount += $itemTotal;
                    continue;
                }

                // --- CASE B: IT IS A CUSTOM ORDER (Made by Karigar) ---
                elseif ($row['type'] === 'order_item') {
                    $orderItem = OrderItem::query()->lockForUpdate()->findOrFail($row['id']);

                    if ($orderItem->status !== 'READY') {
                        throw ValidationException::withMessages([
                            'items' => "Order item {$orderItem->item_name} is not ready for billing.",
                        ]);
                    }

                    if (! $orderItem->finished_weight || (float) $orderItem->finished_weight <= 0) {
                        throw ValidationException::withMessages([
                            'items' => "Order item {$orderItem->item_name} does not have a finished weight yet.",
                        ]);
                    }

                    $weight = (float) $orderItem->finished_weight;
                    $purity = $orderItem->purity;
                    $itemName = $orderItem->item_name;
                    $orderItemMetalType = strtoupper((string) ($orderItem->metal_type ?? 'GOLD'));
                    $rateApplied = (float) ($row['rate'] ?? ($orderItemMetalType === 'SILVER' ? ($validated['silver_rate'] ?? 0) : ($validated['gold_rate'] ?? 0)));
                    $makingType = $makingType ?: ($makingValue > 100 ? 'flat' : 'per_gram');

                    $itemTotal = $this->calculateItemTotalPrice('order_item', $weight, $rateApplied, $makingValue, $makingType, 1);

                    // Mark Item as DELIVERED
                    $orderItem->update([
                        'status' => 'DELIVERED',
                    ]);

                    // Save to InvoiceItems
                    InvoiceItem::create([
                        'invoice_id'         => $invoice->id,
                        'order_item_id'      => $orderItem->id, // Link Order Item
                        'description'        => $itemName . " (Order #" . $orderItem->order->order_number . ")",
                        'quantity'           => 1,
                        'weight'             => $weight,
                        'purity'             => $purity,
                        'rate'               => $rateApplied,
                        'making_charges'     => $makingValue,
                        'making_charge_type' => $makingType === 'lump_sum' ? 'flat' : $makingType,
                        'final_price'        => $itemTotal,
                    ]);

                    if ($orderItemMetalType === 'SILVER') {
                        $totalVaultSilverSoldWeight += (float) $weight;
                    } else {
                        $totalVaultGoldSoldWeight += (float) $weight;
                    }

                    $totalBillAmount += $itemTotal;
                    continue;
                }
            }

            // 4. Calculate Discount, Tax & Final Total
            $discountType = $validated['discount_type'] ?? null;
            $discountValue = round((float) ($validated['discount_value'] ?? 0), 2);
            $discountAmount = 0;

            if ($discountType === 'percentage') {
                if ($discountValue > 100) {
                    throw ValidationException::withMessages([
                        'discount_value' => 'Percentage discount cannot be greater than 100.',
                    ]);
                }

                $discountAmount = round($totalBillAmount * ($discountValue / 100), 2);
            } elseif ($discountType === 'amount') {
                $discountAmount = $discountValue;
            }

            if ($discountAmount > $totalBillAmount) {
                throw ValidationException::withMessages([
                    'discount_value' => 'Discount cannot be greater than the item subtotal.',
                ]);
            }

            $taxableAmount = round($totalBillAmount - $discountAmount, 2);
            $gst = round($taxableAmount * 0.03, 2); // 3% GST after discount
            $finalTotal = round($taxableAmount + $gst, 2);
            $totalPaid = (float) ($validated['payment_cash'] ?? 0) + (float) ($validated['payment_card'] ?? 0);

            if ($totalPaid > $finalTotal) {
                throw ValidationException::withMessages([
                    'payment_cash' => 'Received amount cannot be greater than the invoice total.',
                ]);
            }

            $invoice->update([
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => $discountAmount,
                'total_amount' => $finalTotal,
                'tax_amount'   => $gst
            ]);

            if ($totalVaultGoldSoldWeight > 0) {
                VaultService::debit(VaultType::GOLD, $totalVaultGoldSoldWeight, [
                    'source_type' => Invoice::class,
                    'source_id' => $invoice->id,
                    'reference' => $invoice->invoice_number,
                    'user_id' => Auth::id(),
                    'note' => "Gold sold in {$invoice->invoice_number}",
                ]);
            }

            if ($totalVaultSilverSoldWeight > 0) {
                VaultService::debit(VaultType::SILVER, $totalVaultSilverSoldWeight, [
                    'source_type' => Invoice::class,
                    'source_id' => $invoice->id,
                    'reference' => $invoice->invoice_number,
                    'user_id' => Auth::id(),
                    'note' => "Silver sold in {$invoice->invoice_number}",
                ]);
            }

            // 5. ACCOUNTING (Ledger Entries)

            // A. DEBIT THE CUSTOMER (Sale Entry)
            Transaction::create([
                'transactable_type' => Customer::class,
                'transactable_id'   => $validated['customer_id'],
                'invoice_id'        => $invoice->id,
                'type'              => 'SALE',
                'amount'            => $finalTotal,
                'description'       => "Bill #" . $invoice->invoice_number,
                'date'              => $validated['date'],
                'user_id'           => Auth::id(),
                'entry_type_code'   => 'INVOICE_SALE',
            ]);

            // B. CREDIT THE CUSTOMER (Cash Payment)
            if (!empty($validated['payment_cash']) && $validated['payment_cash'] > 0) {
                $transaction = Transaction::create([
                    'transactable_type' => Customer::class,
                    'transactable_id'   => $validated['customer_id'],
                    'invoice_id'        => $invoice->id,
                    'type'              => 'PAYMENT',
                    'amount'            => $validated['payment_cash'],
                    'description'       => "Cash Payment",
                    'date'              => $validated['date'],
                    'user_id'           => Auth::id(),
                    'payment_method' => 'CASH',
                    'entry_type_code' => 'INVOICE_PAYMENT',
                ]);
                LedgerImpactService::applyCashTransaction($transaction);
            }

            // C. CREDIT THE CUSTOMER (Card Payment)
            if (!empty($validated['payment_card']) && $validated['payment_card'] > 0) {
                $transaction = Transaction::create([
                    'transactable_type' => Customer::class,
                    'transactable_id'   => $validated['customer_id'],
                    'invoice_id'        => $invoice->id,
                    'type'              => 'PAYMENT',
                    'amount'            => $validated['payment_card'],
                    'description'       => "Card Payment " . ($validated['card_note'] ?? ''),
                    'date'              => $validated['date'],
                    'user_id'           => Auth::id(),
                    'payment_method' => 'CARD',
                    'entry_type_code' => 'INVOICE_PAYMENT',

                ]);
                LedgerImpactService::applyCashTransaction($transaction);
            }

            if (! empty($validated['draft_id'])) {
                InvoiceDraft::where('user_id', Auth::id())->where('id', $validated['draft_id'])->delete();
            }

            return redirect()
                ->route('invoices.index')
                ->with('success', "Invoice {$invoice->invoice_number} generated successfully.");
        });
    }


    public function cancel(Request $request, $id)
    {
        $validated = $request->validate([
            'mode' => 'nullable|in:keep_advance,refund,none',
            'reason' => 'required|string|max:500',
        ]);

        $invoice = Invoice::with(['items.product', 'items.silverProduct', 'items.orderItem', 'transactions'])->findOrFail($id);

        if ($invoice->status === 'CANCELLED') {
            return back()->with('error', 'This invoice is already cancelled.');
        }

        $paidAmount = (float) $invoice->transactions->where('type', 'PAYMENT')->sum('amount');
        $effectiveMode = $paidAmount > 0 ? ($validated['mode'] ?? 'keep_advance') : 'none';

        try {
            DB::transaction(function () use ($invoice, $validated, $effectiveMode) {
            $invoice->update([
                'status' => 'CANCELLED',
                'cancellation_mode' => $effectiveMode,
                'cancellation_reason' => $validated['reason'],
                'cancelled_by' => Auth::id(),
                'cancelled_at' => now(),
            ]);

            $restoredGoldWeight = (float) $invoice->items
                ->filter(fn ($item) => $item->order_item_id !== null && strtoupper((string) ($item->orderItem?->metal_type ?? 'GOLD')) !== 'SILVER')
                ->sum('weight');

            if ($restoredGoldWeight > 0) {
                VaultService::credit(VaultType::GOLD, $restoredGoldWeight, [
                    'source_type' => Invoice::class,
                    'source_id' => $invoice->id,
                    'reference' => $invoice->invoice_number,
                    'user_id' => Auth::id(),
                    'note' => "Gold restored after voiding {$invoice->invoice_number}",
                ]);
            }

            $restoredSilverWeight = (float) $invoice->items
                ->filter(fn ($item) => $item->order_item_id !== null && strtoupper((string) ($item->orderItem?->metal_type ?? 'GOLD')) === 'SILVER')
                ->sum('weight');

            if ($restoredSilverWeight > 0) {
                VaultService::credit(VaultType::SILVER, $restoredSilverWeight, [
                    'source_type' => Invoice::class,
                    'source_id' => $invoice->id,
                    'reference' => $invoice->invoice_number,
                    'user_id' => Auth::id(),
                    'note' => "Silver restored after voiding {$invoice->invoice_number}",
                ]);
            }

            foreach ($invoice->items as $item) {
                if ($item->product) {
                    $item->product->update(['is_sold' => false]);
                }

                if ($item->silverProduct) {
                    if ($item->silverProduct->pricing_mode === 'PIECE') {
                        $item->silverProduct->update([
                            'quantity' => (int) $item->silverProduct->quantity + (int) ($item->quantity ?? 1),
                            'is_sold' => false,
                        ]);
                    } else {
                        $item->silverProduct->update([
                            'quantity' => max(1, (int) ($item->quantity ?? 1)),
                            'is_sold' => false,
                        ]);
                    }
                }

                if ($item->orderItem) {
                    $item->orderItem->update(['status' => 'READY']);
                }
            }

            foreach ($invoice->transactions as $transaction) {
                if ($transaction->type === 'SALE') {
                    $transaction->update([
                        'type' => 'VOID',
                        'description' => "Voided sale for {$invoice->invoice_number}",
                        'entry_type_code' => 'VOID_INVOICE_SALE',
                    ]);
                    continue;
                }

                if ($transaction->type !== 'PAYMENT') {
                    continue;
                }

                if ($effectiveMode === 'refund') {
                    LedgerImpactService::reverseCashTransaction($transaction);

                    $transaction->update([
                        'type' => 'VOID',
                        'description' => "Refunded payment for {$invoice->invoice_number}",
                        'entry_type_code' => 'INVOICE_REFUND',
                    ]);
                } else {
                    $transaction->update([
                        'description' => trim(($transaction->description ?: 'Payment') . " | Kept as customer advance after void {$invoice->invoice_number}"),
                    ]);
                }
            }
            });
        } catch (\Throwable $e) {
            return back()->withErrors([
                'reason' => $e->getMessage(),
            ]);
        }

        if ($paidAmount <= 0) {
            $message = 'Invoice voided and stock restored (No payment was collected on this bill).';
        } else {
            $message = $effectiveMode === 'refund'
                ? 'Invoice voided, stock restored, and paid amount refunded.'
                : 'Invoice voided, stock restored, and paid amount kept as customer advance.';
        }

        return back()->with('success', $message);
    }

    public function addPayment(Request $request, Invoice $invoice)
    {
        if ($invoice->status === 'CANCELLED') {
            return back()->withErrors([
                'amount' => 'Cannot add payment to a voided invoice.',
            ]);
        }

        $paidAmount = (float) $invoice->transactions()
            ->where('type', 'PAYMENT')
            ->sum('amount');

        $pendingAmount = max((float) $invoice->total_amount - $paidAmount, 0);

        if ($pendingAmount <= 0) {
            return back()->withErrors([
                'amount' => 'This invoice is already fully paid.',
            ]);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:' . $pendingAmount],
            'payment_method' => ['required', 'string', Rule::in(['CASH', 'CARD', 'UPI', 'BANK'])],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'amount.max' => 'Payment amount cannot exceed the pending balance of ₹' . number_format($pendingAmount, 2),
        ]);

        try {
            DB::transaction(function () use ($invoice, $validated) {
                $paymentMethod = strtoupper($validated['payment_method']);
                $note = trim($validated['note'] ?? '');
                $description = "Payment for Invoice #{$invoice->invoice_number}" . ($note !== '' ? " ({$note})" : '');

                $transaction = Transaction::create([
                    'transactable_type' => Customer::class,
                    'transactable_id'   => $invoice->customer_id,
                    'invoice_id'        => $invoice->id,
                    'type'              => 'PAYMENT',
                    'amount'            => (float) $validated['amount'],
                    'description'       => $description,
                    'date'              => $validated['date'],
                    'user_id'           => Auth::id(),
                    'payment_method'    => $paymentMethod,
                    'entry_source'      => 'MANUAL',
                    'entry_type_code'   => 'INVOICE_PAYMENT',
                ]);

                LedgerImpactService::applyCashTransaction($transaction);
            });
        } catch (\Throwable $e) {
            return back()->withErrors([
                'amount' => 'Failed to record payment: ' . $e->getMessage(),
            ]);
        }

        return back()->with('success', "Payment of ₹" . number_format($validated['amount'], 2) . " recorded for {$invoice->invoice_number}.");
    }
}
