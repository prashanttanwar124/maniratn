<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\DailyRate;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class WebsiteApiController extends Controller
{
    public function products(): JsonResponse
    {
        $products = Product::query()
            ->with(['category:id,name', 'purity:id,name'])
            ->where('is_sold', false)
            ->where('is_visible_on_website', true)
            ->latest()
            ->get()
            ->map(function (Product $product) {
                return [
                    'id' => $product->id,
                    'barcode' => $product->barcode,
                    'name' => $product->name,
                    'category' => $product->category?->name,
                    'purity' => $product->purity?->name,
                    'gross_weight' => (float) $product->gross_weight,
                    'net_weight' => (float) $product->net_weight,
                    'making_charge' => (float) $product->making_charge,
                    'image_url' => $product->image_path ? url(Storage::url($product->image_path)) : null,
                    'created_at' => optional($product->created_at)?->toISOString(),
                ];
            })
            ->values();

        return response()->json([
            'products' => $products,
        ]);
    }

    /**
     * Public API for maniratn-web to fetch Customer Digital Vault details by token.
     */
    public function vault(string $token): JsonResponse
    {
        $customer = Customer::query()
            ->where('vault_token', $token)
            ->first();

        if (! $customer || $customer->card_status === 'DISABLED') {
            return response()->json([
                'success' => false,
                'message' => 'Vault card is inactive or not found.',
            ], 404);
        }

        // Record scan/access statistics
        $customer->increment('card_access_count');
        $customer->update(['card_last_accessed_at' => now()]);

        // 1. Fetch non-cancelled invoices with purchased items
        $invoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->where('status', '!=', 'CANCELLED')
            ->with([
                'items.product.category',
                'items.silverProduct.category',
                'items.orderItem',
            ])
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $goldWeight = 0;
        $silverWeight = 0;
        $items = [];
        $invoicesList = [];

        foreach ($invoices as $invoice) {
            $invoiceItemCount = $invoice->items->count();
            $invSign = substr(hash_hmac('sha256', "invoice_{$invoice->id}_{$token}", config('app.key') ?: 'maniratn_vault_secret'), 0, 12);
            $secureKey = "inv_{$invoice->id}_{$invSign}";

            $invoicesList[] = [
                'id' => $invoice->id,
                'secure_key' => $secureKey,
                'invoice_number' => $invoice->invoice_number,
                'date' => $invoice->date,
                'total_amount' => (float) $invoice->total_amount,
                'tax_amount' => (float) $invoice->tax_amount,
                'discount_amount' => (float) $invoice->discount_amount,
                'items_count' => $invoiceItemCount,
                'download_url' => route('website.vault.invoice-print', ['token' => $token, 'invoice' => $secureKey]),
            ];

            foreach ($invoice->items as $item) {
                $isSilver = $item->silver_product_id !== null
                    || str_contains(strtolower($item->purity ?? ''), 'silver')
                    || str_contains(strtolower($item->purity ?? ''), '925')
                    || str_contains(strtolower($item->description ?? ''), 'silver');

                $weight = (float) ($item->net_weight > 0 ? $item->net_weight : $item->weight);

                if ($isSilver) {
                    $silverWeight += $weight;
                    $metal = 'SILVER';
                } else {
                    $goldWeight += $weight;
                    $metal = 'GOLD';
                }

                $items[] = [
                    'id' => $item->id,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'date' => $invoice->date,
                    'description' => $item->description ?: ($item->product?->name ?? $item->silverProduct?->name ?? $item->orderItem?->item_name ?? 'Jewellery Item'),
                    'category' => $item->product?->category?->name ?? $item->silverProduct?->category?->name ?? ($metal === 'GOLD' ? 'Gold Jewellery' : 'Silver Jewellery'),
                    'metal' => $metal,
                    'gross_weight' => (float) $item->weight,
                    'net_weight' => (float) ($item->net_weight > 0 ? $item->net_weight : $item->weight),
                    'purity' => $item->purity ?: ($metal === 'GOLD' ? '22K (916)' : '92.5 Sterling'),
                    'huid' => $item->huid ?? null,
                    'rate' => (float) $item->rate,
                    'making_charges' => (float) $item->making_charges,
                    'total_price' => (float) $item->total_price,
                ];
            }
        }

        // 2. Active Gold Schemes
        $goldSchemes = $customer->goldSchemes()
            ->with(['scheme', 'installments'])
            ->get()
            ->map(function ($scheme) {
                return [
                    'id' => $scheme->id,
                    'scheme_name' => $scheme->scheme?->name ?? 'Gold Savings Scheme',
                    'start_date' => $scheme->start_date,
                    'monthly_installment' => (float) $scheme->monthly_installment,
                    'total_installments' => (int) $scheme->total_installments,
                    'paid_installments' => $scheme->installments->count(),
                    'total_paid' => (float) $scheme->installments->sum('amount'),
                    'accumulated_weight' => (float) $scheme->installments->sum('gold_weight_credited'),
                    'status' => $scheme->status,
                ];
            })
            ->values();

        // 3. Current Daily Rates
        $latestRate = DailyRate::orderBy('date', 'desc')->first();
        $goldRatePerGram = $latestRate ? ((float) $latestRate->gold_sell / 10) : 0;
        $silverRatePerGram = $latestRate ? ((float) $latestRate->silver_sell / 1000) : 0;

        $estimatedGoldValue = $goldWeight * $goldRatePerGram;
        $estimatedSilverValue = $silverWeight * $silverRatePerGram;
        $totalEstimatedValue = $estimatedGoldValue + $estimatedSilverValue;

        // 4. Store settings
        $business = BusinessSetting::first();

        return response()->json([
            'success' => true,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'city' => $customer->city,
                'membership_id' => $customer->membership_id,
                'card_status' => $customer->card_status,
                'card_issued_at' => optional($customer->card_issued_at)?->toISOString(),
            ],
            'stats' => [
                'total_items' => count($items),
                'total_gold_weight' => round($goldWeight, 3),
                'total_silver_weight' => round($silverWeight, 3),
                'total_invoices' => count($invoicesList),
                'gold_rate_per_gram' => round($goldRatePerGram, 2),
                'silver_rate_per_gram' => round($silverRatePerGram, 2),
                'estimated_gold_value' => round($estimatedGoldValue, 2),
                'estimated_silver_value' => round($estimatedSilverValue, 2),
                'total_portfolio_value' => round($totalEstimatedValue, 2),
            ],
            'items' => $items,
            'invoices' => $invoicesList,
            'gold_schemes' => $goldSchemes,
            'store' => [
                'name' => $business?->store_name ?? 'Maniratn Jewellers',
                'phone' => $business?->phone,
                'email' => $business?->email,
                'address' => $business?->address,
                'website' => $business?->website,
                'gst_number' => $business?->gst_number,
            ],
            'latest_rate' => $latestRate ? [
                'date' => $latestRate->date,
                'gold_sell_per_10g' => (float) $latestRate->gold_sell,
                'silver_sell_per_kg' => (float) $latestRate->silver_sell,
            ] : null,
        ]);
    }

    public function downloadInvoice(string $token, string $invoiceKey)
    {
        $customer = Customer::where('vault_token', $token)->first();

        if (! $customer || $customer->vault_status === 'DISABLED') {
            return response()->json([
                'success' => false,
                'message' => 'Vault card is inactive or not found.',
            ], 404);
        }

        // 1. Resolve invoice ID and verify signature if HMAC format
        if (preg_match('/^inv_(\d+)_([a-f0-9]+)$/', $invoiceKey, $matches)) {
            $invoiceId = (int) $matches[1];
            $providedSign = $matches[2];
            $expectedSign = substr(hash_hmac('sha256', "invoice_{$invoiceId}_{$token}", config('app.key') ?: 'maniratn_vault_secret'), 0, 12);

            if (! hash_equals($expectedSign, $providedSign)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or tampered invoice signature.',
                ], 403);
            }
        } elseif (is_numeric($invoiceKey)) {
            $invoiceId = (int) $invoiceKey;
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Invalid invoice identifier format.',
            ], 404);
        }

        $invoice = Invoice::find($invoiceId);

        // 2. Strict IDOR ownership verification: invoice MUST belong to this customer
        if (! $invoice || $invoice->customer_id !== $customer->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access: this invoice does not belong to your account.',
            ], 403);
        }

        $invoice->load([
            'customer',
            'items.product.category',
            'items.silverProduct.category',
            'items.orderItem',
            'transactions',
            'user',
        ]);

        $business = BusinessSetting::first();

        return response()->json([
            'success' => true,
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'date' => $invoice->date,
                'total_amount' => (float) $invoice->total_amount,
                'tax_amount' => (float) $invoice->tax_amount,
                'discount_amount' => (float) $invoice->discount_amount,
                'customer' => [
                    'name' => $invoice->customer?->name,
                    'mobile' => $invoice->customer?->mobile,
                    'city' => $invoice->customer?->city,
                ],
                'items' => $invoice->items->map(function ($item) {
                    return [
                        'description' => $item->description ?: ($item->product?->name ?? $item->silverProduct?->name ?? $item->orderItem?->item_name ?? 'Jewellery Item'),
                        'purity' => $item->purity ?: '22K (916)',
                        'net_weight' => (float) ($item->net_weight > 0 ? $item->net_weight : $item->weight),
                        'huid' => $item->huid,
                        'final_price' => (float) ($item->final_price ?? $item->total_price ?? 0),
                    ];
                })->values(),
            ],
            'business' => [
                'store_name' => $business?->store_name ?? 'Maniratn Jewellers',
                'phone' => $business?->phone,
                'email' => $business?->email,
                'address' => $business?->address,
                'gst_number' => $business?->gst_number,
            ],
        ]);
    }
}
