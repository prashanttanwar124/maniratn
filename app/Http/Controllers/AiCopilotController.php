<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DailyRate;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Purity;
use App\Models\SilverProduct;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Services\Ai\AiActionDispatcher;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiCopilotController extends Controller
{
    public function __construct(
        protected AiActionDispatcher $dispatcher
    ) {}

    /**
     * Send user message to Central AI Hub (maniratn-ai) and execute ERP actions
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'history' => 'nullable|array',
            'voice' => 'nullable|string',
            'include_audio' => 'nullable|boolean',
        ]);

        $message = trim((string) $request->input('message'));
        $history = $request->input('history', []);
        $voice = $request->input('voice', 'Aoede');
        $includeAudio = $request->boolean('include_audio', true);

        $setting = BusinessSetting::first();
        $aiHubUrl = rtrim($setting?->ai_hub_url ?: 'http://127.0.0.1:8001', '/');
        $apiKey = $setting?->ai_api_key ?: env('MANIRATN_AI_KEY', 'mn_live_d8f4e2a1c90b6732e45a89f0');

        // Fetch live ERP Context to ensure 100% data sync with AI Hub
        $todayRate = DailyRate::whereDate('date', Carbon::today())->where('gold_sell', '>', 0)->first();
        if (! $todayRate) {
            $todayRate = DailyRate::where('gold_sell', '>', 0)->latest('date')->first();
        }

        $erpContext = [
            'today_rates' => $todayRate ? [
                'gold_24k' => floatval($todayRate->gold_sell),
                'gold_22k' => round(floatval($todayRate->gold_sell) * 0.916, 2),
                'silver' => floatval($todayRate->silver_sell),
            ] : null,
            'vault_balance' => [
                'cash' => '₹' . number_format(\App\Models\Vault::whereIn('type', ['CASH', 'cash'])->sum('balance'), 2),
                'gold' => number_format(\App\Models\Vault::whereIn('type', ['GOLD', 'gold'])->sum('balance'), 3) . ' g',
                'silver' => number_format(\App\Models\Vault::whereIn('type', ['SILVER', 'silver'])->sum('balance'), 3) . ' g',
            ],
        ];

        try {
            // Forward request to AI Hub
            $response = Http::timeout(40)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $apiKey,
                ])
                ->post("{$aiHubUrl}/api/ai/chat", [
                    'message' => $message,
                    'history' => $history,
                    'voice' => $voice,
                    'include_audio' => $includeAudio,
                    'erp_context' => $erpContext,
                    'store_url' => config('app.url'),
                    'session_id' => 'erp_user_' . (auth()->id() ?: 'guest'),
                ]);

            if (! $response->successful()) {
                Log::error('AI Hub Error Response: ' . $response->body());
                return response()->json([
                    'reply' => 'AI Server par error aayi: ' . ($response->json()['message'] ?? 'Response error'),
                    'actions' => [],
                    'audio' => null,
                ], 502);
            }

            $aiResult = $response->json();
            $actions = $aiResult['actions'] ?? [];
            $executedActions = [];
            $finalReply = $aiResult['reply'] ?? 'Done.';

            // Dispatch tool actions through dedicated Action handlers
            foreach ($actions as $action) {
                $tool = $action['tool'] ?? '';
                $args = $action['args'] ?? [];

                try {
                    $realData = $this->dispatcher->dispatch($tool, $args);
                } catch (\Throwable $e) {
                    Log::warning("AI Tool dispatch failed for [{$tool}]: " . $e->getMessage());
                    $realData = ['error' => $e->getMessage(), 'status' => 'FAILED'];
                }

                // If tool is get_daily_rates, guarantee 100% exact match between speech/reply & card
                if ($tool === 'get_daily_rates' && ! empty($realData['found'])) {
                    $finalReply = "Aaj 24K Gold ₹" . number_format($realData['gold_24k_per_gm']) . ", 22K ₹" . number_format($realData['gold_22k_per_gm'], 2) . " aur Silver ₹" . number_format($realData['silver_per_gm'], 2) . " per gram hai.";
                }

                $executedActions[] = [
                    'tool' => $tool,
                    'args' => $args,
                    'result' => $realData,
                ];
            }

            return response()->json([
                'reply' => $finalReply,
                'actions' => ! empty($executedActions) ? $executedActions : $actions,
                'audio' => $aiResult['audio'] ?? null,
                'cached' => $aiResult['cached'] ?? false,
            ]);
        } catch (\Throwable $e) {
            Log::error('ERP AI Copilot Exception: ' . $e->getMessage());

            return response()->json([
                'reply' => 'AI Server unreachable: ' . $e->getMessage(),
                'actions' => [],
                'audio' => null,
            ]);
        }
    }

    /**
     * Get paginated chat history from Central AI Hub (maniratn-ai)
     */
    public function history(Request $request): JsonResponse
    {
        $setting = BusinessSetting::first();
        $aiHubUrl = rtrim($setting?->ai_hub_url ?: 'http://127.0.0.1:8001', '/');
        $apiKey = $setting?->ai_api_key ?: env('MANIRATN_AI_KEY', 'mn_live_d8f4e2a1c90b6732e45a89f0');

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $apiKey,
                ])
                ->get("{$aiHubUrl}/api/ai/history", [
                    'limit' => $request->input('limit', 10),
                    'before_id' => $request->input('before_id'),
                    'store_url' => config('app.url'),
                    'session_id' => 'erp_user_' . (auth()->id() ?: 'guest'),
                ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'messages' => [],
                'has_more' => false,
                'error' => 'Could not fetch history from AI Hub',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'messages' => [],
                'has_more' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clear chat history on Central AI Hub
     */
    public function clearHistory(Request $request): JsonResponse
    {
        $setting = BusinessSetting::first();
        $aiHubUrl = rtrim($setting?->ai_hub_url ?: 'http://127.0.0.1:8001', '/');
        $apiKey = $setting?->ai_api_key ?: env('MANIRATN_AI_KEY', 'mn_live_d8f4e2a1c90b6732e45a89f0');

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $apiKey,
                ])
                ->delete("{$aiHubUrl}/api/ai/history", [
                    'store_url' => config('app.url'),
                    'session_id' => 'erp_user_' . (auth()->id() ?: 'guest'),
                ]);

            return response()->json($response->json());
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Human-in-the-loop: Confirm & Create Real Invoice in Database
     */
    public function confirmBill(Request $request): JsonResponse
    {
        $customerName = trim((string) $request->input('customer_name', 'Walk-in Customer'));
        $customerPhone = trim((string) $request->input('customer_phone', ''));
        $barcode = trim((string) $request->input('barcode', ''));
        $itemName = trim((string) $request->input('item_name', 'Jewellery Ornament'));
        $weight = floatval($request->input('weight', 1));
        $metal = strtoupper((string) $request->input('metal', 'GOLD'));
        $purityStr = strtoupper((string) $request->input('purity', '22K'));
        $effectiveRate = floatval($request->input('rate_per_gm', 7000));
        $makingType = (string) $request->input('making_type', 'percentage');
        $makingValue = floatval($request->input('making_value', 12));
        $discountAmount = floatval($request->input('discount_amount', 0));
        $paymentMode = strtoupper((string) $request->input('payment_mode', 'CASH'));
        $paymentAmount = $request->has('payment_amount') ? floatval($request->input('payment_amount')) : null;

        $matchedProduct = null;
        $matchedSilverProduct = null;

        if (! empty($barcode)) {
            $matchedProduct = Product::where('barcode', $barcode)->first();
            if (! $matchedProduct) {
                $matchedSilverProduct = SilverProduct::where('barcode', $barcode)->first();
            }

            if ($matchedProduct && $matchedProduct->is_sold) {
                return response()->json([
                    'success' => false,
                    'message' => "Product '{$matchedProduct->name}' (Barcode: {$barcode}) pehle se hi bik chuka hai!",
                ], 422);
            }
            if ($matchedSilverProduct && $matchedSilverProduct->is_sold) {
                return response()->json([
                    'success' => false,
                    'message' => "Silver item '{$matchedSilverProduct->name}' (Barcode: {$barcode}) pehle se sold hai!",
                ], 422);
            }
        }

        // 1. Customer
        $customer = null;
        if (! empty($customerPhone)) {
            $customer = Customer::where('mobile', $customerPhone)->first();
        }
        if (! $customer && ! empty($customerName) && strtolower($customerName) !== 'walk-in customer') {
            $customer = Customer::where('name', 'like', "%{$customerName}%")->first();
        }
        if (! $customer) {
            $customer = Customer::create([
                'name' => ! empty($customerName) ? $customerName : 'Walk-in Customer',
                'mobile' => ! empty($customerPhone) ? $customerPhone : ('98' . rand(10000000, 99999999)),
                'address' => 'Store Counter Sale',
                'city' => 'Local',
                'vault_token' => Customer::generateVaultToken(),
            ]);
        }

        // 2. Calculations
        $metalValue = round($weight * $effectiveRate, 2);

        if ($makingType === 'flat') {
            $makingTotal = round($makingValue, 2);
            $makingLabel = "(₹{$makingValue} Flat)";
        } elseif ($makingType === 'per_gram') {
            $makingTotal = round($weight * $makingValue, 2);
            $makingLabel = "(@ ₹{$makingValue}/g)";
        } else {
            $makingType = 'percentage';
            $makingTotal = round($metalValue * ($makingValue / 100), 2);
            $makingLabel = "({$makingValue}%)";
        }

        $subtotal = max(0, $metalValue + $makingTotal - $discountAmount);
        $gstAmount = round($subtotal * 0.03, 2);
        $grandTotal = round($subtotal + $gstAmount, 2);

        $actingUserId = Auth::id() ?: \App\Models\User::first()?->id;

        // 3. Create Invoice
        $invoice = Invoice::create([
            'invoice_number' => 'TMP-' . Str::uuid(),
            'customer_id' => $customer->id,
            'gold_rate_applied' => $effectiveRate,
            'silver_rate_applied' => 89.0,
            'tax_amount' => $gstAmount,
            'discount_type' => $discountAmount > 0 ? 'fixed' : null,
            'discount_value' => $discountAmount,
            'discount_amount' => $discountAmount,
            'date' => Carbon::today()->format('Y-m-d'),
            'total_amount' => $grandTotal,
            'user_id' => $actingUserId,
        ]);

        $invoiceNumber = sprintf('INV-%s-%06d', now()->format('Ymd'), $invoice->id);
        $invoice->update(['invoice_number' => $invoiceNumber]);

        // 4. Create InvoiceItem & Mark Stock Sold
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $matchedProduct?->id,
            'silver_product_id' => $matchedSilverProduct?->id,
            'description' => ! empty($itemName) ? $itemName : "{$purityStr} {$metal} Item ({$weight}g)",
            'quantity' => 1,
            'weight' => $weight,
            'purity' => $purityStr,
            'rate' => $effectiveRate,
            'making_charges' => $makingValue,
            'making_charge_type' => $makingType,
            'final_price' => $subtotal,
        ]);

        if ($matchedProduct) {
            $matchedProduct->update(['is_sold' => true]);
        }
        if ($matchedSilverProduct) {
            $matchedSilverProduct->update(['is_sold' => true]);
        }

        // 5. Transactions (SALE Debit & PAYMENT Credit)
        Transaction::create([
            'transactable_type' => Customer::class,
            'transactable_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'type' => 'SALE',
            'amount' => $grandTotal,
            'description' => "Bill #" . $invoiceNumber . ($barcode ? " (Barcode: {$barcode})" : "") . " (Confirmed via Karat AI)",
            'date' => Carbon::today()->format('Y-m-d'),
            'user_id' => $actingUserId,
            'entry_type_code' => 'INVOICE_SALE',
        ]);

        $actualPaid = ($paymentAmount !== null && $paymentAmount >= 0) ? min($grandTotal, $paymentAmount) : $grandTotal;

        if (in_array($paymentMode, ['CASH', 'UPI', 'CARD', 'BANK_TRANSFER', 'ONLINE', 'BANK']) && $actualPaid > 0) {
            Transaction::create([
                'transactable_type' => Customer::class,
                'transactable_id' => $customer->id,
                'invoice_id' => $invoice->id,
                'type' => 'PAYMENT',
                'amount' => $actualPaid,
                'description' => "{$paymentMode} Payment received (Bill #{$invoiceNumber})",
                'date' => Carbon::today()->format('Y-m-d'),
                'user_id' => $actingUserId,
                'payment_method' => $paymentMode,
                'entry_type_code' => 'INVOICE_PAYMENT',
            ]);
        }

        return response()->json([
            'success' => true,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoiceNumber,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->mobile,
            'item_name' => $itemName,
            'weight' => round($weight, 3),
            'metal' => $metal,
            'purity' => $purityStr,
            'rate_per_gm' => round($effectiveRate, 2),
            'metal_value' => round($metalValue, 2),
            'making_charges' => round($makingTotal, 2),
            'making_label' => $makingLabel,
            'subtotal' => round($subtotal, 2),
            'gst_3_percent' => round($gstAmount, 2),
            'grand_total' => round($grandTotal, 2),
            'payment_mode' => $paymentMode,
            'view_url' => "/invoices/{$invoice->id}",
            'print_url' => "/invoices/{$invoice->id}/print",
            'message' => "Done! Bill #{$invoiceNumber} database me successfully save ho gaya hai.",
        ]);
    }

    /**
     * Human-in-the-loop: Confirm & Add Product into Stock Database
     */
    public function confirmProduct(Request $request): JsonResponse
    {
        $name = trim((string) $request->input('name', 'Gold Ornament'));
        $weight = floatval($request->input('weight', 0));
        $metal = strtoupper((string) $request->input('metal', 'GOLD'));
        $purityName = (string) $request->input('purity', ($metal === 'GOLD' ? '22K' : '92.5'));
        $catName = (string) $request->input('category', 'General');
        $makingCharge = floatval($request->input('making_charge_per_gm', 450));

        $category = Category::firstOrCreate(['name' => $catName], [
            'metal_type' => strtolower($metal),
        ]);

        $purity = Purity::where('name', 'like', "%{$purityName}%")->first()
            ?? Purity::firstOrCreate(['name' => $purityName], ['purity_percent' => 91.6]);

        $supplier = Supplier::first() ?? Supplier::create([
            'name' => 'Self Stock',
            'contact_person' => 'Store Owner',
            'phone' => '0000000000',
        ]);

        $product = Product::create([
            'name' => $name,
            'category_id' => $category->id,
            'purity_id' => $purity->id,
            'supplier_id' => $supplier->id,
            'gross_weight' => $weight,
            'net_weight' => $weight,
            'making_charge' => $makingCharge,
            'is_sold' => false,
        ]);

        return response()->json([
            'success' => true,
            'product_id' => $product->id,
            'barcode' => $product->barcode,
            'name' => $product->name,
            'metal' => $metal,
            'purity' => $purity->name,
            'weight' => floatval($product->gross_weight),
            'category' => $category->name,
            'making_charge_per_gm' => floatval($makingCharge),
            'message' => "Done! Product '{$product->name}' (Barcode: {$product->barcode}) showroom stock me add ho gaya hai.",
        ]);
    }

    /**
     * Human-in-the-loop: Confirm & Update Live Daily Rates in Database
     */
    public function confirmRates(Request $request): JsonResponse
    {
        $today = date('Y-m-d');
        $goldSell = floatval($request->input('gold_24k_sell', 7450));
        $goldBuy = floatval($request->input('gold_24k_buy', round($goldSell * 0.98, 2)));
        $silverSell = floatval($request->input('silver_sell', 88.50));

        $rate = DailyRate::updateOrCreate(
            ['date' => $today],
            [
                'gold_buy' => $goldBuy,
                'gold_sell' => $goldSell,
                'silver_sell' => $silverSell,
            ]
        );

        return response()->json([
            'success' => true,
            'date' => $today,
            'gold_24k_sell' => $goldSell,
            'gold_22k_sell' => round($goldSell * 0.916, 2),
            'silver_sell' => $silverSell,
            'message' => "Done! Aaj ka 24K rate ₹" . number_format($goldSell) . " aur Silver ₹" . number_format($silverSell, 2) . " database me update ho gaya.",
        ]);
    }
}
