<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DailyRate;
use App\Models\Product;
use App\Models\Purity;
use App\Models\SilverProduct;
use App\Models\Supplier;
use App\Services\Ai\AiActionDispatcher;
use App\Services\AiInvoiceDraftService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AiCopilotController extends Controller
{
    public function __construct(
        protected AiActionDispatcher $dispatcher,
        protected AiInvoiceDraftService $invoiceDrafts,
        protected \App\Services\InventoryBarcodeService $inventoryBarcodes,
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
        if (preg_match('/\b(G\d+|S\d+|MS-\d+)\b/i', $message, $matches)) {
            $inventory = $this->inventoryBarcodes->find($matches[1]);
            if ($inventory) {
                $prod = $inventory['item'];
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
                    'is_sold' => (bool) $prod->is_sold,
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
                'cash' => '₹'.number_format(\App\Models\Vault::whereIn('type', ['CASH', 'cash'])->sum('balance'), 2),
                'gold' => number_format(\App\Models\Vault::whereIn('type', ['GOLD', 'gold'])->sum('balance'), 3).' g',
                'silver' => number_format(\App\Models\Vault::whereIn('type', ['SILVER', 'silver'])->sum('balance'), 3).' g',
            ],
            'matched_product' => $matchedProduct,
        ];

        try {
            $response = Http::timeout(40)
                ->connectTimeout(10)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer '.$apiKey,
                ])
                ->post("{$aiHubUrl}/api/ai/chat", [
                    'message' => $message,
                    'history' => $history,
                    'voice' => $voice,
                    'include_audio' => $includeAudio,
                    'store_url' => config('app.url'),
                    'session_id' => 'erp_user_'.(auth()->id() ?: 'guest'),
                    'erp_context' => $erpContext,
                ]);

            if ($response->failed()) {
                $err = $response->json('error') ?? $response->json('message') ?? 'AI Service returned error '.$response->status();
                Log::error('AI Hub Error Response: '.json_encode($response->json()));

                return response()->json([
                    'reply' => 'Maaf kijiye, AI Server se response milne me vilamb ho raha hai. Kripya ek baar dobara try karein.',
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
                    if (isset($realData['found']) && $realData['found'] === false) {
                        $aiResult['reply'] = '⚠️ '.($realData['message'] ?? 'Product inventory me uplabdh nahi hai.');
                    } else {
                        $aiResult['reply'] = match (true) {
                            $tool === 'create_bill' || $tool === 'create_invoice' || $tool === 'create_bill_draft' => ($realData['customer_name'] ?? 'Customer').' ji ke liye '.($realData['item_name'] ?? 'item').' regular billing draft me add kiya ja raha hai.',
                            $tool === 'calculate_estimate' || $tool === 'calculate_estimation' => "{$realData['weight']} {$realData['purity']} ".($realData['item_name'] ?? 'item')." ka total estimate {$realData['total_estimate']} banega. (Metal Value: {$realData['metal_value']} + Making: {$realData['making_charges']} + GST: {$realData['gst_3_percent']}).",
                            $tool === 'calculate_old_gold' => "Old Gold Valuation: Total {$realData['total_estimate']} banega ({$realData['weight']} {$realData['purity']} @ {$realData['rate_per_gm']}).",
                            $tool === 'get_stock_info' || $tool === 'stock_check' => "Showroom me total {$realData['total_items']} items uplabdh hain. Gold: {$realData['gold_count']} items ({$realData['gold_weight']}), Silver: {$realData['silver_count']} items ({$realData['silver_weight']}).",
                            $tool === 'get_customer_khata' || $tool === 'customer_balance_check' => "{$realData['customer_name']} ji ka khata balance: {$realData['status_text']}. Total Purchases: {$realData['total_purchases']}, Total Paid: {$realData['total_paid']}.",
                            $tool === 'get_sales_summary' || $tool === 'daily_sales_report' => "{$realData['period_label']} Showroom Report: Total Revenue {$realData['total_sales']} ({$realData['total_bills']} Bills). Gold: {$realData['gold_weight_sold']}, Silver: {$realData['silver_weight_sold']}.",
                            $tool === 'search_invoices' || $tool === 'get_customer_invoices' => "Maine {$realData['count']} pichle purchase bills dhoond liye hain. Niche card me details aur print receipt check karein.",
                            $tool === 'get_vault_balance' || $tool === 'vault_balance' => "Showroom Vault Holdings: Cash: {$realData['cash_in_hand']}, Gold: {$realData['gold_in_vault']}, Silver: {$realData['silver_in_vault']}, Bank: {$realData['bank_balance']}.",
                            $tool === 'get_tasks' || $tool === 'create_task' || $tool === 'tasks' => $realData['message'] ?? ('Showroom tasks list me total '.($realData['count'] ?? 0).' tasks hain. Niche board par details check karein.'),
                            default => $aiResult['reply'] ?? 'Done.',
                        };
                    }
                }

                $executedActions[] = [
                    'tool' => $tool,
                    'args' => $args,
                    'result' => $realData,
                ];
            }

            $msgId = $aiResult['message_id']
                ?? ($aiResult['log_id'] ?? 'local_'.substr(hash('sha256', (string) auth()->id().'|'.$message.'|'.json_encode($actions)), 0, 40));
            if (! empty($executedActions) && ! empty($msgId)) {
                [$executedActions, $invoiceDraftReply] = $this->persistInvoiceDraftActions(
                    $request,
                    $executedActions,
                    (string) $msgId,
                );

                if ($invoiceDraftReply !== null) {
                    $aiResult['reply'] = $invoiceDraftReply;
                }
            }

            if (! empty($executedActions) && ! empty($msgId)) {
                try {
                    Http::timeout(5)
                        ->withHeaders([
                            'Accept' => 'application/json',
                            'Authorization' => 'Bearer '.$apiKey,
                        ])
                        ->post("{$aiHubUrl}/api/ai/history/update-action", [
                            'message_id' => $msgId,
                            'actions' => $executedActions,
                            'reply' => $aiResult['reply'] ?? null,
                        ]);
                } catch (\Throwable $syncErr) {
                    Log::warning('Failed to sync executed AI actions to AI Hub: '.$syncErr->getMessage());
                }
            }

            return response()->json([
                'reply' => $aiResult['reply'] ?? 'Done.',
                'actions' => ! empty($executedActions) ? $executedActions : $actions,
                'audio' => $aiResult['audio'] ?? null,
                'cached' => $aiResult['cached'] ?? false,
                'message_id' => $msgId,
                'duration' => $aiResult['duration'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('ERP AI Copilot Exception: '.$e->getMessage());

            return response()->json([
                'reply' => 'Maaf kijiye, AI Server se connect hone me samasya aa rahi hai. Kripya check karein ki AI server chalu hai.',
                'actions' => [],
                'audio' => null,
            ]);
        }
    }

    /**
     * Persist AI billing commands into the same draft model used by the regular invoice screen.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: string|null}
     */
    private function persistInvoiceDraftActions(Request $request, array $actions, string $messageId): array
    {
        $invoiceTools = ['create_bill', 'create_invoice', 'create_bill_draft'];
        $reply = null;

        foreach ($actions as $index => $action) {
            if (! in_array($action['tool'] ?? '', $invoiceTools, true)) {
                continue;
            }

            $result = (array) ($action['result'] ?? []);
            if (($result['found'] ?? false) !== true) {
                continue;
            }

            if (! $request->user()?->can('manage_invoices')) {
                $result = array_merge($result, [
                    'found' => false,
                    'status' => 'INVOICE_PERMISSION_REQUIRED',
                    'message' => 'Invoice draft banane ki permission nahi hai.',
                ]);
                $actions[$index]['result'] = $result;
                $reply = 'Invoice draft banane ki permission nahi hai.';

                continue;
            }

            $phone = preg_replace('/\D+/', '', (string) ($result['customer_phone'] ?? '')) ?: 'customer';
            $sourceReference = "{$messageId}:{$phone}";

            try {
                $draftResult = $this->invoiceDrafts->createOrAppend(
                    $request->user(),
                    $result,
                    $sourceReference,
                );
                $actions[$index]['result'] = array_merge($result, $draftResult);
                $reply = sprintf(
                    '%s ji ka invoice draft ready hai. %d item billing draft me hai. Regular invoice screen par details aur payment verify karke invoice generate karein.',
                    $draftResult['customer_name'],
                    $draftResult['item_count'],
                );
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first()
                    ?? 'Invoice draft prepare nahi ho saka.';
                $actions[$index]['result'] = array_merge($result, [
                    'found' => false,
                    'status' => 'INVOICE_DRAFT_FAILED',
                    'message' => $message,
                ]);
                $reply = "Invoice draft prepare nahi hua: {$message}";
            }
        }

        return [$actions, $reply];
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
                    'Authorization' => 'Bearer '.$apiKey,
                ])
                ->get("{$aiHubUrl}/api/ai/history", [
                    'limit' => $request->input('limit', 10),
                    'before_id' => $request->input('before_id'),
                    'store_url' => config('app.url'),
                    'session_id' => 'erp_user_'.(auth()->id() ?: 'guest'),
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
                    'Authorization' => 'Bearer '.$apiKey,
                ])
                ->delete("{$aiHubUrl}/api/ai/history", [
                    'store_url' => config('app.url'),
                    'session_id' => 'erp_user_'.(auth()->id() ?: 'guest'),
                ]);

            return response()->json($response->json());
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Human-in-the-loop: Confirm & Add Product into Stock Database
     */
    public function confirmProduct(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'weight' => 'required|numeric|gt:0',
            'quantity' => 'nullable|integer|min:1|max:100',
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
            $quantity = max(1, intval($validated['quantity'] ?? 1));
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
                    $code = substr($baseCode, 0, 3).$suffix;
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
                $createdSilverProducts = [];
                $barcodes = [];
                for ($i = 0; $i < $quantity; $i++) {
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
                    $createdSilverProducts[] = $silverProduct;
                    $barcodes[] = $silverProduct->barcode;
                }

                $silverResponseData = [
                    'success' => true,
                    'quantity' => $quantity,
                    'product_id' => $createdSilverProducts[0]->id,
                    'product_ids' => array_column($createdSilverProducts, 'id'),
                    'barcode' => implode(', ', $barcodes),
                    'barcodes' => $barcodes,
                    'name' => $name,
                    'metal' => 'SILVER',
                    'purity' => 'Silver (92.5)',
                    'weight' => $weight,
                    'total_weight' => round($weight * $quantity, 3),
                    'category' => $category->name,
                    'making_charge_per_gm' => floatval($makingCharge),
                    'is_preview' => false,
                    'status' => 'IN_STOCK_REAL_DB',
                    'message' => ($quantity > 1)
                        ? "Done! {$quantity} items '{$name}' (Barcodes: ".implode(', ', $barcodes).') silver stock me add ho gaye hain.'
                        : "Done! Silver item '{$name}' (Barcode: {$barcodes[0]}) silver stock me add ho gaya hai.",
                ];

                if (! empty($validated['message_id'])) {
                    $this->syncActionToAiHub($validated['message_id'], [
                        [
                            'tool' => 'add_product',
                            'args' => $validated,
                            'result' => array_merge($validated, $silverResponseData),
                        ],
                    ], $silverResponseData['message']);
                }

                return response()->json($silverResponseData);
            }

            $purity = Purity::where('name', 'like', "%{$purityName}%")->first()
                ?? Purity::firstOrCreate(['name' => $purityName]);

            $createdProducts = [];
            $barcodes = [];
            for ($i = 0; $i < $quantity; $i++) {
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
                $createdProducts[] = $product;
                $barcodes[] = $product->barcode;
            }

            $goldResponseData = [
                'success' => true,
                'quantity' => $quantity,
                'product_id' => $createdProducts[0]->id,
                'product_ids' => array_column($createdProducts, 'id'),
                'barcode' => implode(', ', $barcodes),
                'barcodes' => $barcodes,
                'name' => $name,
                'metal' => 'GOLD',
                'purity' => $purity->name,
                'weight' => $weight,
                'total_weight' => round($weight * $quantity, 3),
                'category' => $category->name,
                'making_charge_per_gm' => floatval($makingCharge),
                'is_preview' => false,
                'status' => 'IN_STOCK_REAL_DB',
                'message' => ($quantity > 1)
                    ? "Done! {$quantity} items '{$name}' (Barcodes: ".implode(', ', $barcodes).') showroom stock me add ho gaye hain.'
                    : "Done! Product '{$name}' (Barcode: {$barcodes[0]}) showroom stock me add ho gaya hai.",
            ];

            if (! empty($validated['message_id'])) {
                $this->syncActionToAiHub($validated['message_id'], [
                    [
                        'tool' => 'add_product',
                        'args' => $validated,
                        'result' => array_merge($validated, $goldResponseData),
                    ],
                ], $goldResponseData['message']);
            }

            return response()->json($goldResponseData);
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
            'message_id' => 'nullable|string|max:100',
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

        $ratesResponseData = [
            'success' => true,
            'date' => $today,
            'gold_24k_sell' => $goldSell,
            'gold_22k_sell' => round($goldSell * 0.916, 2),
            'silver_sell' => $silverSell,
            'is_preview' => false,
            'status' => 'UPDATED_IN_DATABASE',
            'message' => 'Done! Aaj ka 24K rate ₹'.number_format($goldSell).' aur Silver ₹'.number_format($silverSell, 2).' database me update ho gaya.',
        ];

        if (! empty($validated['message_id'])) {
            $this->syncActionToAiHub($validated['message_id'], [
                [
                    'tool' => 'update_daily_rates',
                    'args' => $validated,
                    'result' => array_merge($validated, $ratesResponseData),
                ],
            ], $ratesResponseData['message']);
        }

        return response()->json($ratesResponseData);
    }

    /**
     * Human-in-the-loop: Discard AI Action and sync state to AI Hub
     */
    public function discardAction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message_id' => 'required|string|max:100',
            'action_tool' => 'nullable|string|max:100',
        ]);

        $this->syncActionToAiHub($validated['message_id'], [
            [
                'tool' => $validated['action_tool'] ?? 'action',
                'args' => [],
                'result' => [
                    'is_preview' => false,
                    'is_discarded' => true,
                    'status' => 'DISCARDED',
                ],
            ],
        ], 'Action draft discard kar diya gaya.');

        return response()->json(['success' => true, 'message' => 'Action discarded.']);
    }

    /**
     * Sync updated/confirmed/discarded action state to Central AI Hub so history persists across reloads
     */
    private function syncActionToAiHub(?string $messageId, array $actions, ?string $reply = null): void
    {
        if (empty($messageId)) {
            return;
        }

        try {
            $setting = BusinessSetting::first();
            $aiHubUrl = rtrim($setting?->ai_hub_url ?: config('services.maniratn_ai.url', 'http://127.0.0.1:8001'), '/');
            $apiKey = $setting?->ai_api_key ?: config('services.maniratn_ai.key', env('MANIRATN_AI_KEY'));

            Http::timeout(5)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer '.$apiKey,
                ])
                ->post("{$aiHubUrl}/api/ai/history/update-action", [
                    'message_id' => $messageId,
                    'actions' => $actions,
                    'reply' => $reply,
                ]);
        } catch (\Throwable $syncErr) {
            Log::warning('Failed to sync updated action state to AI Hub: '.$syncErr->getMessage());
        }
    }
}
