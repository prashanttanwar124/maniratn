<?php

namespace App\Http\Controllers;

use App\Enums\VaultType;
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
use App\Services\LedgerImpactService;
use App\Services\VaultService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        $aiHubUrl = rtrim($setting?->ai_hub_url ?: config('services.maniratn_ai.url', 'http://127.0.0.1:8001'), '/');
        $apiKey = $setting?->ai_api_key ?: config('services.maniratn_ai.key', env('MANIRATN_AI_KEY'));

        // Fetch live ERP Context to ensure 100% data sync with AI Hub
        $todayRate = DailyRate::whereDate('date', Carbon::today())->where('gold_sell', '>', 0)->first();
        if (! $todayRate) {
            $todayRate = DailyRate::where('gold_sell', '>', 0)->latest('date')->first();
        }

        $matchedProduct = null;
        if (preg_match('/\b(G\d{5}|S\d{5}|MS-\d{5})\b/i', $message, $matches)) {
            $b = strtoupper($matches[1]);
            $prod = Product::with(['category', 'purity'])->where('barcode', $b)->first();
            if (! $prod && preg_match('/^G(\d{5})$/', $b, $m)) {
                $prod = Product::with(['category', 'purity'])->find((int) $m[1]);
            }
            if (! $prod) {
                $prod = SilverProduct::with('category')->where('barcode', $b)->first();
                if (! $prod && preg_match('/^S(\d{5})$/', $b, $m)) {
                    $prod = SilverProduct::with('category')->find((int) $m[1]);
                }
            }
            if ($prod) {
                $matchedProduct = [
                    'barcode' => $prod->barcode,
                    'name' => $prod->name,
                    'category' => $prod->category?->name ?? 'Jewellery',
                    'purity' => $prod->purity?->name ?? '916 Hallmark',
                    'weight' => floatval($prod->net_weight ?: $prod->gross_weight),
                    'gross_weight' => floatval($prod->gross_weight),
                    'net_weight' => floatval($prod->net_weight),
                    'making_charge' => floatval($prod->making_charge),
                    'making_charge_type' => $prod->making_charge_type ?? 'percentage',
                    'metal' => ($prod instanceof SilverProduct) ? 'SILVER' : 'GOLD',
                ];
            }
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
            'matched_product' => $matchedProduct,
        ];

        try {
            $response = Http::timeout(25)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $apiKey,
                ])
                ->post("{$aiHubUrl}/api/ai/chat", [
                    'message' => $message,
                    'history' => $history,
                    'voice' => $voice,
                    'include_audio' => $includeAudio,
                    'store_url' => config('app.url'),
                    'session_id' => 'erp_user_' . (auth()->id() ?: 'guest'),
                    'erp_context' => $erpContext,
                ]);

            if ($response->failed()) {
                $err = $response->json('error') ?? $response->json('message') ?? 'AI Service returned error ' . $response->status();
                Log::error('AI Hub Error Response: ' . json_encode($response->json()));

                return response()->json([
                    'reply' => 'Maaf kijiye, AI Server se response nahi mil paya: ' . $err,
                    'actions' => [],
                    'audio' => null,
                ], 200);
            }

            $aiResult = $response->json();
            $actions = $aiResult['actions'] ?? [];
            $executedActions = [];

            foreach ($actions as $act) {
                $tool = $act['tool'] ?? '';
                $args = $act['args'] ?? [];

                $realData = $this->dispatcher->dispatch($tool, $args);

                if (! empty($realData)) {
                    $aiResult['reply'] = match (true) {
                        $tool === 'calculate_estimate' || $tool === 'calculate_estimation'
                            => "15g 22K chain ka total estimate ₹" . number_format($realData['grand_total'] ?? 0) . " banega. (Gold Value: ₹" . number_format($realData['metal_value'] ?? 0) . " + Making: ₹" . number_format($realData['making_charges'] ?? 0) . " + GST: ₹" . number_format($realData['gst_3_percent'] ?? 0) . ").",
                        $tool === 'calculate_old_gold'
                            => "Old Gold Valuation: Total ₹" . number_format($realData['final_payout'] ?? 0) . " banega ({$realData['net_weight']}g @ ₹{$realData['effective_rate_per_gm']}/g).",
                        $tool === 'get_stock_info' || $tool === 'stock_check'
                            => "Showroom me total {$realData['total_count']} items uplabdh hain. Gold: {$realData['gold_items_count']} items ({$realData['gold_total_weight']}g), Silver: {$realData['silver_items_count']} items ({$realData['silver_total_weight']}g).",
                        $tool === 'get_customer_khata' || $tool === 'customer_balance_check'
                            => "{$realData['customer_name']} ji ka khata balance: {$realData['status_text']}. Total Purchases: {$realData['total_purchases']}, Total Paid: {$realData['total_paid']}.",
                        $tool === 'get_sales_summary' || $tool === 'daily_sales_report'
                            => "{$realData['period_label']} Showroom Report: Total Revenue {$realData['total_sales']} ({$realData['total_bills']} Bills). Gold: {$realData['gold_weight_sold']}, Silver: {$realData['silver_weight_sold']}.",
                        $tool === 'search_invoices' || $tool === 'get_customer_invoices'
                            => "Maine {$realData['count']} pichle purchase bills dhoond liye hain. Niche card me details aur print receipt check karein.",
                        default => $aiResult['reply'] ?? 'Done.',
                    };
                }

                $executedActions[] = [
                    'tool' => $tool,
                    'args' => $args,
                    'result' => $realData,
                ];
            }

            $msgId = $aiResult['message_id'] ?? ($aiResult['log_id'] ?? null);
            if (! empty($executedActions) && ! empty($msgId)) {
                try {
                    Http::timeout(5)
                        ->withHeaders([
                            'Accept' => 'application/json',
                            'Authorization' => 'Bearer ' . $apiKey,
                        ])
                        ->post("{$aiHubUrl}/api/ai/history/update-action", [
                            'message_id' => $msgId,
                            'actions' => $executedActions,
                            'reply' => $aiResult['reply'] ?? null,
                        ]);
                } catch (\Throwable $syncErr) {
                    Log::warning('Failed to sync executed AI actions to AI Hub: ' . $syncErr->getMessage());
                }
            }

            return response()->json([
                'reply' => $aiResult['reply'] ?? 'Done.',
                'actions' => ! empty($executedActions) ? $executedActions : $actions,
                'audio' => $aiResult['audio'] ?? null,
                'cached' => $aiResult['cached'] ?? false,
                'message_id' => $msgId,
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
        $aiHubUrl = rtrim($setting?->ai_hub_url ?: config('services.maniratn_ai.url', 'http://127.0.0.1:8001'), '/');
        $apiKey = $setting?->ai_api_key ?: config('services.maniratn_ai.key', env('MANIRATN_AI_KEY'));

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
        $aiHubUrl = rtrim($setting?->ai_hub_url ?: config('services.maniratn_ai.url', 'http://127.0.0.1:8001'), '/');
        $apiKey = $setting?->ai_api_key ?: config('services.maniratn_ai.key', env('MANIRATN_AI_KEY'));

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
        $validated = $request->validate([
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'barcode' => 'nullable|string|max:50',
            'item_name' => 'nullable|string|max:255',
            'weight' => 'required|numeric|gt:0',
            'metal' => 'nullable|string|in:GOLD,SILVER,Gold,Silver,gold,silver',
            'purity' => 'nullable|string|max:50',
            'rate_per_gm' => 'required|numeric|gt:0',
            'making_type' => 'nullable|string|in:percentage,per_gram,flat,lump_sum',
            'making_value' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'payment_mode' => 'nullable|string|in:CASH,UPI,CARD,BANK_TRANSFER,ONLINE,BANK,cash,upi,card,bank_transfer,online,bank',
            'payment_amount' => 'nullable|numeric|min:0',
            'message_id' => 'nullable|string|max:100',
            'draft_id' => 'nullable|string|max:100',
        ]);

        return DB::transaction(function () use ($request, $validated) {
            $messageId = $validated['message_id'] ?? ($validated['draft_id'] ?? null);

            // Idempotency: Avoid duplicate billing if this message was already confirmed
            if (! empty($messageId)) {
                $existingTx = Transaction::where('description', 'LIKE', "%[AI_MSG:{$messageId}]%")->first();
                if ($existingTx && $existingTx->invoice_id) {
                    $existingInvoice = Invoice::find($existingTx->invoice_id);
                    if ($existingInvoice) {
                        return response()->json([
                            'success' => true,
                            'invoice_id' => $existingInvoice->id,
                            'invoice_number' => $existingInvoice->invoice_number,
                            'already_confirmed' => true,
                            'grand_total' => floatval($existingInvoice->total_amount),
                            'view_url' => "/invoices?view={$existingInvoice->id}",
                            'print_url' => "/invoices/{$existingInvoice->id}/print",
                            'message' => "Bill #{$existingInvoice->invoice_number} pehle se confirm ho chuka hai.",
                        ]);
                    }
                }
            }

            $customerName = trim((string) ($validated['customer_name'] ?? 'Walk-in Customer'));
            $customerPhone = trim((string) ($validated['customer_phone'] ?? ''));
            $barcode = trim((string) ($validated['barcode'] ?? ''));
            $itemName = trim((string) ($validated['item_name'] ?? 'Jewellery Ornament'));
            $weight = floatval($validated['weight']);
            $metal = strtoupper((string) ($validated['metal'] ?? 'GOLD'));
            $purityStr = strtoupper((string) ($validated['purity'] ?? '22K'));
            $effectiveRate = floatval($validated['rate_per_gm']);
            $makingType = (string) ($validated['making_type'] ?? 'percentage');
            $makingValue = floatval($validated['making_value'] ?? 12);
            $discountAmount = floatval($validated['discount_amount'] ?? 0);
            $paymentMode = strtoupper((string) ($validated['payment_mode'] ?? 'CASH'));
            $paymentAmount = isset($validated['payment_amount']) ? floatval($validated['payment_amount']) : null;

            $matchedProduct = null;
            $matchedSilverProduct = null;

            if (! empty($barcode)) {
                $matchedProduct = Product::where('barcode', $barcode)->first();
                if (! $matchedProduct && preg_match('/^G(\d{5})$/', $barcode, $m)) {
                    $matchedProduct = Product::find((int) $m[1]);
                }
                if (! $matchedProduct) {
                    $matchedSilverProduct = SilverProduct::where('barcode', $barcode)->first();
                    if (! $matchedSilverProduct && preg_match('/^S(\d{5})$/', $barcode, $m)) {
                        $matchedSilverProduct = SilverProduct::find((int) $m[1]);
                    }
                }

                if ($matchedProduct) {
                    if ($matchedProduct->is_sold) {
                        return response()->json([
                            'success' => false,
                            'message' => "Product '{$matchedProduct->name}' (Barcode: {$barcode}) pehle se hi bik chuka hai!",
                        ], 422);
                    }
                    $itemName = $matchedProduct->name;
                    $weight = floatval($matchedProduct->net_weight ?: $matchedProduct->gross_weight);
                    $metal = 'GOLD';
                    $purityStr = $matchedProduct->purity?->name ?? $purityStr;
                    if (! $request->filled('making_value')) {
                        $makingValue = floatval($matchedProduct->making_charge);
                        $makingType = $matchedProduct->making_charge_type ?? 'percentage';
                    }
                } elseif ($matchedSilverProduct) {
                    if ($matchedSilverProduct->is_sold || ($matchedSilverProduct->pricing_mode === 'PIECE' && $matchedSilverProduct->quantity <= 0)) {
                        return response()->json([
                            'success' => false,
                            'message' => "Silver item '{$matchedSilverProduct->name}' (Barcode: {$barcode}) pehle se sold hai!",
                        ], 422);
                    }
                    $itemName = $matchedSilverProduct->name;
                    $weight = floatval($matchedSilverProduct->net_weight ?: $matchedSilverProduct->gross_weight);
                    $metal = 'SILVER';
                    $purityStr = 'Silver (92.5)';
                    if (! $request->filled('making_value')) {
                        $makingValue = floatval($matchedSilverProduct->making_charge);
                        $makingType = 'per_gram';
                    }
                }
            }

            $customer = null;
            $isWalkIn = empty($customerName) || in_array(strtolower($customerName), ['walk-in customer', 'walk in customer', 'walk in', 'walkin', 'customer']);
            if ($isWalkIn && empty($customerPhone)) {
                $customer = Customer::where('mobile', '0000000000')->orWhere('name', 'Walk-in Customer')->first();
                if (! $customer) {
                    $customer = Customer::create([
                        'name' => 'Walk-in Customer',
                        'mobile' => '0000000000',
                        'address' => 'Store Counter Sale',
                        'city' => 'Showroom',
                        'vault_token' => Customer::generateVaultToken(),
                    ]);
                }
            } else {
                if (! empty($customerPhone)) {
                    $customer = Customer::where('mobile', $customerPhone)->first();
                }
                if (! $customer && ! empty($customerName)) {
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
            }

            $metalValue = round($weight * $effectiveRate, 2);
            if ($makingType === 'flat' || $makingType === 'lump_sum') {
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

            $todayRate = DailyRate::whereDate('date', Carbon::today())->first() ?? DailyRate::latest('date')->first();
            $appliedGoldRate = $metal === 'GOLD' ? $effectiveRate : (floatval($todayRate?->gold_sell) ?: 7450.0);
            $appliedSilverRate = $metal === 'SILVER' ? $effectiveRate : (floatval($todayRate?->silver_sell) ?: 90.0);

            $invoice = Invoice::create([
                'invoice_number' => 'TMP-' . Str::uuid(),
                'customer_id' => $customer->id,
                'gold_rate_applied' => $appliedGoldRate,
                'silver_rate_applied' => $appliedSilverRate,
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
                if ($matchedSilverProduct->pricing_mode === 'PIECE' && $matchedSilverProduct->quantity > 1) {
                    $matchedSilverProduct->decrement('quantity', 1);
                } else {
                    $matchedSilverProduct->update([
                        'quantity' => 0,
                        'is_sold' => true,
                    ]);
                }
            }

            // If selling loose/non-barcoded metal, debit physical raw metal vault
            if (! $matchedProduct && ! $matchedSilverProduct) {
                if ($metal === 'GOLD' && $weight > 0) {
                    VaultService::debit(VaultType::GOLD, $weight, [
                        'source_type' => Invoice::class,
                        'source_id' => $invoice->id,
                        'reference' => $invoiceNumber,
                        'user_id' => $actingUserId,
                        'note' => "Gold sold in {$invoiceNumber} via Karat AI",
                    ]);
                } elseif ($metal === 'SILVER' && $weight > 0) {
                    VaultService::debit(VaultType::SILVER, $weight, [
                        'source_type' => Invoice::class,
                        'source_id' => $invoice->id,
                        'reference' => $invoiceNumber,
                        'user_id' => $actingUserId,
                        'note' => "Silver sold in {$invoiceNumber} via Karat AI",
                    ]);
                }
            }

            Transaction::create([
                'transactable_type' => Customer::class,
                'transactable_id' => $customer->id,
                'invoice_id' => $invoice->id,
                'type' => 'SALE',
                'amount' => $grandTotal,
                'description' => "Bill #" . $invoiceNumber . ($barcode ? " (Barcode: {$barcode})" : "") . (! empty($messageId) ? " [AI_MSG:{$messageId}]" : ""),
                'date' => Carbon::today()->format('Y-m-d'),
                'user_id' => $actingUserId,
                'entry_type_code' => 'INVOICE_SALE',
            ]);

            $actualPaid = ($paymentAmount !== null && $paymentAmount >= 0) ? min($grandTotal, $paymentAmount) : $grandTotal;
            if (in_array($paymentMode, ['CASH', 'UPI', 'CARD', 'BANK_TRANSFER', 'ONLINE', 'BANK']) && $actualPaid > 0) {
                $paymentTx = Transaction::create([
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
                LedgerImpactService::applyCashTransaction($paymentTx);
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
                'view_url' => "/invoices?view={$invoice->id}",
                'print_url' => "/invoices/{$invoice->id}/print",
                'message' => "Done! Bill #{$invoiceNumber} database me successfully save ho gaya hai.",
            ]);
        });
    }

    /**
     * Human-in-the-loop: Confirm & Add Product into Stock Database
     */
    public function confirmProduct(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'weight' => 'required|numeric|gt:0',
            'metal' => 'nullable|string|in:GOLD,SILVER,Gold,Silver,gold,silver',
            'purity' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:100',
            'making_charge_per_gm' => 'nullable|numeric|min:0',
            'making_charge_type' => 'nullable|string|in:percentage,per_gram,flat,lump_sum',
            'message_id' => 'nullable|string|max:100',
            'draft_id' => 'nullable|string|max:100',
        ]);

        return DB::transaction(function () use ($validated) {
            $name = trim($validated['name']);
            $weight = floatval($validated['weight']);
            $metal = strtoupper((string) ($validated['metal'] ?? 'GOLD'));
            $purityName = (string) ($validated['purity'] ?? ($metal === 'GOLD' ? '22K (916 Hallmark)' : '92.5 Silver'));
            $catName = trim((string) ($validated['category'] ?? 'General'));
            $makingCharge = floatval($validated['making_charge_per_gm'] ?? 450);
            $makingType = $validated['making_charge_type'] ?? 'per_gram';

            $category = Category::where('name', 'like', $catName)->first();
            if (! $category) {
                $baseCode = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $catName), 0, 4)) ?: 'CAT';
                $code = $baseCode;
                $suffix = 1;
                while (Category::where('code', $code)->exists()) {
                    $code = substr($baseCode, 0, 3) . $suffix;
                    $suffix++;
                }
                $category = Category::create([
                    'name' => $catName,
                    'code' => $code,
                ]);
            }

            $supplier = Supplier::first();
            if (! $supplier) {
                $supplier = Supplier::create([
                    'company_name' => 'Store Internal Stock',
                    'contact_person' => 'Store Owner',
                    'mobile' => '9999999999',
                    'type' => $metal,
                ]);
            }

            if ($metal === 'SILVER') {
                $silverProduct = SilverProduct::create([
                    'name' => $name,
                    'category_id' => $category->id,
                    'supplier_id' => $supplier->id,
                    'pricing_mode' => 'WEIGHT',
                    'quantity' => 1,
                    'gross_weight' => $weight,
                    'net_weight' => $weight,
                    'making_charge' => $makingCharge,
                    'is_sold' => false,
                ]);
                return response()->json([
                    'success' => true,
                    'product_id' => $silverProduct->id,
                    'barcode' => $silverProduct->barcode,
                    'name' => $silverProduct->name,
                    'metal' => 'SILVER',
                    'purity' => 'Silver (92.5)',
                    'weight' => floatval($silverProduct->gross_weight),
                    'category' => $category->name,
                    'making_charge_per_gm' => floatval($makingCharge),
                    'message' => "Done! Silver item '{$silverProduct->name}' (Barcode: {$silverProduct->barcode}) silver stock me add ho gaya hai.",
                ]);
            }

            $purity = Purity::where('name', 'like', "%{$purityName}%")->first()
                ?? Purity::firstOrCreate(['name' => $purityName]);

            $product = Product::create([
                'name' => $name,
                'category_id' => $category->id,
                'purity_id' => $purity->id,
                'supplier_id' => $supplier->id,
                'gross_weight' => $weight,
                'net_weight' => $weight,
                'making_charge' => $makingCharge,
                'making_charge_type' => $makingType,
                'is_sold' => false,
            ]);

            return response()->json([
                'success' => true,
                'product_id' => $product->id,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'metal' => 'GOLD',
                'purity' => $purity->name,
                'weight' => floatval($product->gross_weight),
                'category' => $category->name,
                'making_charge_per_gm' => floatval($makingCharge),
                'message' => "Done! Product '{$product->name}' (Barcode: {$product->barcode}) showroom stock me add ho gaya hai.",
            ]);
        });
    }

    /**
     * Human-in-the-loop: Confirm & Update Live Daily Rates in Database
     */
    public function confirmRates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gold_24k_sell' => 'required|numeric|gt:0',
            'gold_24k_buy' => 'nullable|numeric|gt:0',
            'silver_sell' => 'required|numeric|gt:0',
        ]);

        $today = date('Y-m-d');
        $goldSell = floatval($validated['gold_24k_sell']);
        $goldBuy = floatval($validated['gold_24k_buy'] ?? round($goldSell * 0.98, 2));
        $silverSell = floatval($validated['silver_sell']);

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
