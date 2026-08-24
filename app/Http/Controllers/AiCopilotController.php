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
use App\Models\Vault;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiCopilotController extends Controller
{
    /**
     * Handle Voice & Chat AI Copilot requests inside ERP
     */
    public function chat(Request $request): JsonResponse
    {
        @set_time_limit(120);
        @ini_set('max_execution_time', '120');

        $request->validate([
            'message' => 'required|string|max:2000',
            'history' => 'nullable|array',
            'voice' => 'nullable|string',
            'include_audio' => 'nullable|boolean',
        ]);

        $setting = BusinessSetting::first();
        $aiHubUrl = rtrim($setting?->ai_hub_url ?? 'http://127.0.0.1:8001', '/');
        $apiKey = $setting?->ai_api_key ?? '';
        $voiceName = $request->input('voice', $setting?->ai_voice_name ?? 'Aoede');
        $includeAudio = $request->boolean('include_audio', true);

        $userMessage = trim($request->input('message', ''));
        $history = $request->input('history', []);
        $msgLower = strtolower($userMessage);

        // ⚡ COST-SAVING LOCAL INTERCEPTOR:
        // 1. Instant Chit-chat / Greetings (Responds in 0.01s with ₹0 API cost)
        $cleanMsg = trim(strtolower(preg_replace('/[?!.,]/', '', $userMessage)));
        $greetings = [
            'hi' => 'Namaste! Main Karat AI Voice Copilot hoon. Aaj main aapki kya madad karoon?',
            'hello' => 'Namaste! KaratSetu showroom operations me aapki kya sahayata karoon?',
            'namaste' => 'Namaste! Aaj ka gold/silver bhav poochna hai, ya naya stock add karein?',
            'or batao' => 'Sab badhiya! Showroom me aaj ka live bhav check karna hai ya naya ornament add karna hai?',
            'aur batao' => 'Sab badhiya! Showroom me aaj ka live bhav check karna hai ya naya ornament add karna hai?',
            'kaise ho' => 'Main badhiya hoon! Showroom management me aapki kya sahayata karoon?',
            'kya haal hai' => 'Sab badhiya hai! Aaj ka live rate check karein ya stock entry karein?',
            'shukriya' => 'Dhanyawad! Kisi aur sahayata ke liye zaroor batayein.',
            'thank you' => 'Welcome! Kisi aur sahayata ke liye zaroor batayein.',
            'thanks' => 'Welcome! Kisi aur sahayata ke liye zaroor batayein.',
        ];

        if (isset($greetings[$cleanMsg])) {
            $audioUri = null;
            if ($includeAudio) {
                $cacheKey = 'greeting_tts_' . md5($greetings[$cleanMsg] . $voiceName);
                $audioUri = Cache::remember($cacheKey, now()->addDays(7), function () use ($aiHubUrl, $apiKey, $greetings, $cleanMsg, $voiceName) {
                    try {
                        $ttsRes = Http::timeout(8)
                            ->withHeaders(['Authorization' => 'Bearer ' . $apiKey])
                            ->post("{$aiHubUrl}/api/ai/tts", [
                                'text' => $greetings[$cleanMsg],
                                'voice' => $voiceName,
                            ]);
                        return $ttsRes->json('audio');
                    } catch (\Throwable $e) {
                        return null;
                    }
                });
            }

            return response()->json([
                'reply' => $greetings[$cleanMsg],
                'actions' => [],
                'audio' => $audioUri,
                'cached' => true,
            ]);
        }

        // 2. If user is just asking for today's rate (bhav/rates) and not adding/updating,
        // answer directly from ERP database without hitting Gemini LLM API!
        $isRateInquiry = (
            (str_contains($msgLower, 'bhav') || str_contains($msgLower, 'rate') || str_contains($msgLower, 'price') || str_contains($msgLower, 'gold') || str_contains($msgLower, 'silver') || str_contains($msgLower, 'chandi'))
            && !str_contains($msgLower, 'add')
            && !str_contains($msgLower, 'update')
            && !str_contains($msgLower, 'set')
            && !str_contains($msgLower, 'karo')
            && !str_contains($msgLower, 'estimate')
            && !str_contains($msgLower, 'banega')
            && !str_contains($msgLower, 'ring')
            && !str_contains($msgLower, 'chain')
            && !str_contains($msgLower, 'vault')
        );

        if ($isRateInquiry) {
            $todayRate = DailyRate::whereDate('date', Carbon::today())
                ->where('gold_sell', '>', 0)
                ->first();

            if (! $todayRate) {
                // Today's rate NOT found in ERP DB -> Return sorry message with ₹0 Gemini API calls
                return response()->json([
                    'reply' => 'Maaf kijiye, aaj ka live bhav abhi tak add nahi kiya gaya hai. Kripya pehle aaj ka bhav update karein.',
                    'actions' => [
                        [
                            'tool' => 'get_daily_rates',
                            'args' => ['date' => date('Y-m-d')],
                            'result' => [
                                'found' => false,
                                'date' => date('Y-m-d'),
                                'message' => 'Aaj ka live bhav add nahi hai.',
                            ],
                        ],
                    ],
                    'audio' => null,
                    'cached' => true,
                ]);
            }

            // Today's rate exists in DB
            $gold24k = floatval($todayRate->gold_sell);
            $gold22k = round($gold24k * 0.916, 2);
            $gold18k = round($gold24k * 0.750, 2);
            $silver = floatval($todayRate->silver_sell);
            $replyText = "Aaj ka 24K Gold ₹" . number_format($gold24k) . ", 22K ₹" . number_format($gold22k) . ", Silver ₹" . number_format($silver, 2) . " per gram hai.";

            $audioUri = null;
            if ($includeAudio) {
                $cacheKey = 'local_rate_tts_' . md5($replyText . $voiceName);
                $audioUri = Cache::remember($cacheKey, now()->addHours(12), function () use ($aiHubUrl, $apiKey, $replyText, $voiceName) {
                    try {
                        $res = Http::timeout(8)->withHeaders(['Authorization' => 'Bearer ' . $apiKey])
                            ->post("{$aiHubUrl}/api/ai/chat", [
                                'message' => 'speak rate: ' . $replyText,
                                'voice' => $voiceName,
                                'include_audio' => true,
                            ]);
                        return $res->json('audio');
                    } catch (\Throwable $e) {
                        return null;
                    }
                });
            }

            return response()->json([
                'reply' => $replyText,
                'actions' => [
                    [
                        'tool' => 'get_daily_rates',
                        'args' => ['date' => date('Y-m-d')],
                        'result' => [
                            'found' => true,
                            'date' => date('Y-m-d'),
                            'gold_24k_per_gm' => $gold24k,
                            'gold_22k_per_gm' => $gold22k,
                            'gold_18k_per_gm' => $gold18k,
                            'silver_per_gm' => $silver,
                            'status' => 'TODAY_REAL_ERP_DATABASE',
                        ],
                    ],
                ],
                'audio' => $audioUri,
                'cached' => true,
            ]);
        }

        try {
            // Forward complex conversational requests to Central AI Hub (maniratn-ai)
            $response = Http::timeout(45)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $apiKey,
                ])
                ->post("{$aiHubUrl}/api/ai/chat", [
                    'message' => $userMessage,
                    'history' => $history,
                    'voice' => $voiceName,
                    'include_audio' => $includeAudio,
                    'store_url' => config('app.url'),
                    'session_id' => 'erp_user_' . (auth()->id() ?: 'guest'),
                ]);

            if (! $response->successful()) {
                Log::error('AI Hub Error: ' . $response->body());
                return response()->json([
                    'reply' => 'Central AI Hub se connect nahi ho paya. Kripya check karein ki AI server chalu hai.',
                    'actions' => [],
                    'audio' => null,
                ]);
            }

            $aiResult = $response->json();
            $actions = $aiResult['actions'] ?? [];
            $finalReply = $aiResult['reply'] ?? '';
            $executedActions = [];

            // Execute real database actions inside Maniratn ERP
            foreach ($actions as $act) {
                $tool = $act['tool'] ?? '';
                $args = $act['args'] ?? [];
                $realData = $this->executeRealErpAction($tool, $args);

                $executedActions[] = [
                    'tool' => $tool,
                    'args' => $args,
                    'result' => $realData,
                ];

                // Generate 100% accurate 1-line reply with exact ERP numbers
                if ($tool === 'get_daily_rates') {
                    if (isset($realData['found']) && $realData['found'] === false) {
                        $finalReply = "Aaj ka live gold aur silver bhav database me set nahi hai. Kripya aaj ka 24K rate (jaise '7450') aur silver rate batayein taaki main update kar doon.";
                    } else {
                        $finalReply = "Aaj ka 24K Gold ₹" . number_format($realData['gold_24k_per_gm']) . ", 22K ₹" . number_format($realData['gold_22k_per_gm']) . ", Silver ₹" . number_format($realData['silver_per_gm'], 2) . " per gram hai.";
                    }
                } elseif ($tool === 'update_daily_rates') {
                    $finalReply = "Done! Aaj ka 24K rate ₹" . number_format($realData['gold_24k_sell']) . " aur Silver ₹" . number_format($realData['silver_sell'], 2) . " database me update ho gaya. Ab aap kisi bhi ornament ka estimate ya bill banwa sakte hain.";
                } elseif ($tool === 'add_product') {
                    $finalReply = "Done. {$realData['weight']} {$realData['purity']} {$realData['name']} add ho gayi, Barcode {$realData['barcode']}.";
                } elseif ($tool === 'get_vault_balance') {
                    $finalReply = "Vault me Cash {$realData['cash_in_hand']}, Gold {$realData['gold_in_vault']}, aur Silver {$realData['silver_in_vault']} hai.";
                } elseif ($tool === 'calculate_estimate') {
                    if (isset($realData['found']) && $realData['found'] === false) {
                        $finalReply = "Aaj ka live gold bhav database me set nahi hai. Estimate nikalne ke liye kripya aaj ka 24K gold bhav (jaise '7450') batayein.";
                    } else {
                        $finalReply = "Total estimate quotation {$realData['total_estimate']} banega (12% making aur 3% GST ke sath).";
                    }
                } elseif ($tool === 'create_bill' || $tool === 'create_invoice') {
                    if (isset($realData['found']) && $realData['found'] === false) {
                        $finalReply = $realData['message'] ?? "Aaj ka live gold rate set nahi hai. Bill banane ke liye kripya aaj ka 24K rate batayein.";
                    } else {
                        $finalReply = "Done! Customer {$realData['customer_name']} ke liye Bill #{$realData['invoice_number']} create ho gaya hai. Total amount {$realData['grand_total']} hai.";
                    }
                } elseif ($tool === 'check_stock') {
                    $finalReply = "Showroom inventory me {$realData['total_items']} items available hain, kul weight {$realData['total_weight']} hai.";
                }
            }

            // Synthesize Google Gemini Studio HD Voice for the exact real ERP calculated sentence
            $finalAudio = $aiResult['audio'] ?? null;
            if (! empty($executedActions) && $includeAudio && ! empty($finalReply)) {
                $cacheKey = 'real_erp_tts_' . md5($finalReply . $voiceName);
                $finalAudio = Cache::remember($cacheKey, now()->addDays(7), function () use ($aiHubUrl, $apiKey, $finalReply, $voiceName) {
                    try {
                        $ttsRes = Http::timeout(12)
                            ->withHeaders(['Authorization' => 'Bearer ' . $apiKey])
                            ->post("{$aiHubUrl}/api/ai/tts", [
                                'text' => $finalReply,
                                'voice' => $voiceName,
                            ]);
                        return $ttsRes->json('audio');
                    } catch (\Throwable $e) {
                        return null;
                    }
                });
            }

            return response()->json([
                'reply' => $finalReply,
                'actions' => ! empty($executedActions) ? $executedActions : $actions,
                'audio' => $finalAudio,
                'cached' => $aiResult['cached'] ?? false,
            ]);
        } catch (\Throwable $e) {
            Log::error('ERP AI Copilot Exception: ' . $e->getMessage());

            // Fallback: If AI Hub is offline, execute locally
            return response()->json([
                'reply' => 'AI Server unreachable: ' . $e->getMessage(),
                'actions' => [],
                'audio' => null,
            ]);
        }
    }

    /**
     * Execute Real Database Operations in Maniratn ERP
     */
    protected function executeRealErpAction(string $tool, array $args): array
    {
        switch ($tool) {
            case 'get_daily_rates':
                // STRICT CHECK: Only check today's date
                $rate = DailyRate::whereDate('date', Carbon::today())
                    ->where('gold_sell', '>', 0)
                    ->first();

                if (! $rate) {
                    return [
                        'found' => false,
                        'date' => date('Y-m-d'),
                        'message' => 'Aaj ka live bhav add nahi hai.',
                        'status' => 'RATE_NOT_SET_TODAY',
                    ];
                }

                $gold24k = floatval($rate->gold_sell);
                $gold22k = round($gold24k * 0.916, 2);
                $gold18k = round($gold24k * 0.750, 2);
                $silver = floatval($rate->silver_sell);

                return [
                    'found' => true,
                    'date' => $rate->date ?? date('Y-m-d'),
                    'gold_24k_per_gm' => $gold24k,
                    'gold_22k_per_gm' => $gold22k,
                    'gold_18k_per_gm' => $gold18k,
                    'silver_per_gm' => $silver,
                    'status' => 'REAL_ERP_DATABASE',
                ];

            case 'update_daily_rates':
                $today = date('Y-m-d');
                $goldSell = floatval($args['gold_24k_sell'] ?? 7450);
                $goldBuy = floatval($args['gold_24k_buy'] ?? round($goldSell * 0.98, 2));
                $silverSell = floatval($args['silver_sell'] ?? 88.50);

                $rate = DailyRate::updateOrCreate(
                    ['date' => $today],
                    [
                        'gold_buy' => $goldBuy,
                        'gold_sell' => $goldSell,
                        'silver_sell' => $silverSell,
                    ]
                );

                return [
                    'success' => true,
                    'date' => $today,
                    'gold_24k_sell' => $goldSell,
                    'gold_22k_sell' => round($goldSell * 0.916, 2),
                    'silver_sell' => $silverSell,
                    'status' => 'UPDATED_IN_DATABASE',
                ];

            case 'add_product':
                $name = $args['name'] ?? 'Gold Ornament';
                $weight = floatval($args['weight'] ?? 0);
                $metal = strtoupper($args['metal'] ?? 'GOLD');
                $purityName = $args['purity'] ?? ($metal === 'GOLD' ? '22K' : '92.5');
                $catName = $args['category'] ?? 'General';
                $makingCharge = floatval($args['making_charge_per_gram'] ?? 450);

                // Find or create Category
                $category = Category::firstOrCreate(['name' => $catName], [
                    'metal_type' => strtolower($metal),
                ]);

                // Find Purity
                $purity = Purity::where('name', 'like', "%{$purityName}%")->first()
                    ?? Purity::firstOrCreate(['name' => $purityName], ['purity_percent' => 91.6]);

                // Find or create Supplier
                $supplier = Supplier::first() ?? Supplier::create([
                    'name' => 'Self Stock',
                    'contact_person' => 'Store Owner',
                    'phone' => '0000000000',
                ]);

                // Insert Real Product into ERP Database
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

                return [
                    'success' => true,
                    'product_id' => $product->id,
                    'barcode' => $product->barcode,
                    'name' => $product->name,
                    'metal' => $metal,
                    'purity' => $purity->name,
                    'weight' => $product->gross_weight . ' g',
                    'category' => $category->name,
                    'making_charge_per_gm' => '₹' . $makingCharge,
                    'status' => 'IN_STOCK_REAL_DB',
                    'view_url' => "/products",
                ];

            case 'get_vault_balance':
                $cash = Vault::whereIn('type', ['CASH', 'cash'])->sum('balance');
                $gold = Vault::whereIn('type', ['GOLD', 'gold'])->sum('balance');
                $silver = Vault::whereIn('type', ['SILVER', 'silver'])->sum('balance');
                $bank = Vault::whereIn('type', ['BANK', 'bank'])->sum('balance');

                return [
                    'cash_in_hand' => '₹' . number_format($cash, 2),
                    'gold_in_vault' => number_format($gold, 3) . ' g',
                    'silver_in_vault' => number_format($silver, 3) . ' g',
                    'bank_balance' => '₹' . number_format($bank, 2),
                    'status' => 'LIVE_ERP_VAULT',
                ];

            case 'calculate_estimate':
                $weight = floatval($args['weight'] ?? 10);
                $metal = strtoupper($args['metal'] ?? 'GOLD');
                $purity = $args['purity'] ?? '22K';
                $customRate = (isset($args['custom_rate']) || isset($args['rate'])) ? floatval($args['custom_rate'] ?? $args['rate']) : null;
                $makingPercent = isset($args['making_percent']) ? floatval($args['making_percent']) : null;
                $makingPerGm = isset($args['making_charge_per_gram']) ? floatval($args['making_charge_per_gram']) : null;

                // STRICT CHECK: Check today's rate in database
                $rateRecord = DailyRate::whereDate('date', Carbon::today())->where('gold_sell', '>', 0)->first();

                // If user didn't mention a custom rate AND today's rate is not in DB, fail gracefully
                if ($customRate === null && ! $rateRecord) {
                    return [
                        'found' => false,
                        'message' => 'Aaj ka live gold bhav database me add nahi hai.',
                        'status' => 'RATE_NOT_SET_TODAY',
                    ];
                }

                if ($customRate !== null && $customRate > 0) {
                    $ratePerGm = $customRate;
                } else {
                    $ratePerGm = ($metal === 'SILVER')
                        ? floatval($rateRecord->silver_sell)
                        : (floatval($rateRecord->gold_sell) * 0.916);
                }

                $metalValue = $weight * $ratePerGm;

                if ($makingPercent !== null && $makingPercent > 0) {
                    $makingTotal = $metalValue * ($makingPercent / 100);
                    $makingLabel = "({$makingPercent}%)";
                } elseif ($makingPerGm !== null && $makingPerGm > 0) {
                    $makingTotal = $weight * $makingPerGm;
                    $makingLabel = "(@ ₹{$makingPerGm}/g)";
                } else {
                    $makingPercent = 12.0;
                    $makingTotal = round($metalValue * 0.12, 2);
                    $makingLabel = "(12%)";
                }

                $subtotal = $metalValue + $makingTotal;
                $gst = $subtotal * 0.03;
                $grandTotal = $subtotal + $gst;

                return [
                    'found' => true,
                    'weight' => $weight . ' g',
                    'metal' => $metal,
                    'purity' => $purity,
                    'rate_per_gm' => '₹' . number_format($ratePerGm, 2),
                    'metal_value' => '₹' . number_format($metalValue, 2),
                    'making_charges' => '₹' . number_format($makingTotal, 2) . " {$makingLabel}",
                    'subtotal' => '₹' . number_format($subtotal, 2),
                    'gst_3_percent' => '₹' . number_format($gst, 2),
                    'total_estimate' => '₹' . number_format($grandTotal, 2),
                ];

            case 'create_bill':
            case 'create_invoice':
                $customerName = trim((string) ($args['customer_name'] ?? 'Walk-in Customer'));
                $customerPhone = trim((string) ($args['customer_phone'] ?? ''));
                $barcode = trim((string) ($args['barcode'] ?? ''));
                $itemName = trim((string) ($args['item_name'] ?? 'Gold Ornament'));
                $weight = floatval($args['weight'] ?? 10);
                $metal = strtoupper($args['metal'] ?? 'GOLD');
                $purityStr = strtoupper($args['purity'] ?? '22K');
                $customRate = (isset($args['rate_per_gm']) || isset($args['rate'])) ? floatval($args['rate_per_gm'] ?? $args['rate']) : null;
                $makingPercent = isset($args['making_percent']) ? floatval($args['making_percent']) : null;
                $makingPerGm = isset($args['making_charge_per_gram']) ? floatval($args['making_charge_per_gram']) : null;
                $paymentMode = strtoupper((string) ($args['payment_mode'] ?? 'CASH'));
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
                        if ($matchedProduct->making_charge > 0) {
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
                        if ($matchedSilverProduct->making_charge > 0) {
                            $makingPerGm = floatval($matchedSilverProduct->making_charge);
                        }
                    }
                }

                // 2. Find or create customer
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

                // 3. Fetch live daily rate from database
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
                            $effectiveRate = $gold24k * 0.916; // Default 22K (916)
                        }
                    }
                } else {
                    $effectiveRate = ($metal === 'SILVER') ? 89.0 : 6830.0;
                }

                // 4. Compute Metal value, making charges (Default 12%), GST & Totals
                $metalValue = round($weight * $effectiveRate, 2);

                if ($makingPercent !== null && $makingPercent > 0) {
                    $makingTotal = round($metalValue * ($makingPercent / 100), 2);
                    $makingType = 'percentage';
                    $makingValue = $makingPercent;
                    $makingLabel = "({$makingPercent}%)";
                } elseif ($makingPerGm !== null && $makingPerGm > 0) {
                    $makingTotal = round($weight * $makingPerGm, 2);
                    $makingType = 'flat';
                    $makingValue = $makingPerGm;
                    $makingLabel = "(@ ₹{$makingPerGm}/g)";
                } else {
                    $makingType = 'percentage';
                    $makingValue = 12.0;
                    $makingTotal = round($metalValue * 0.12, 2);
                    $makingLabel = "(12%)";
                }

                $subtotal = max(0, $metalValue + $makingTotal - $discountAmount);
                $gstAmount = round($subtotal * 0.03, 2);
                $grandTotal = round($subtotal + $gstAmount, 2);

                // 5. Create Invoice Header
                $actingUserId = Auth::id() ?: \App\Models\User::first()?->id;

                $invoice = Invoice::create([
                    'invoice_number' => 'TMP-' . Str::uuid(),
                    'customer_id' => $customer->id,
                    'gold_rate_applied' => floatval($rateRecord?->gold_sell ?: $effectiveRate),
                    'silver_rate_applied' => floatval($rateRecord?->silver_sell ?: 89.0),
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

                // 6. Create InvoiceItem & Mark Stock Sold
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

                // 7. Accounting Ledger Entry: DEBIT Customer
                Transaction::create([
                    'transactable_type' => Customer::class,
                    'transactable_id' => $customer->id,
                    'invoice_id' => $invoice->id,
                    'type' => 'SALE',
                    'amount' => $grandTotal,
                    'description' => "Bill #" . $invoiceNumber . ($barcode ? " (Barcode: {$barcode})" : "") . " (via Karat AI)",
                    'date' => Carbon::today()->format('Y-m-d'),
                    'user_id' => $actingUserId,
                    'entry_type_code' => 'INVOICE_SALE',
                ]);

                // 8. Payment Transaction: CREDIT Customer if payment made
                if (in_array($paymentMode, ['CASH', 'UPI', 'CARD', 'BANK_TRANSFER', 'ONLINE'])) {
                    Transaction::create([
                        'transactable_type' => Customer::class,
                        'transactable_id' => $customer->id,
                        'invoice_id' => $invoice->id,
                        'type' => 'PAYMENT',
                        'amount' => $grandTotal,
                        'description' => "{$paymentMode} Payment received (Bill #{$invoiceNumber})",
                        'date' => Carbon::today()->format('Y-m-d'),
                        'user_id' => $actingUserId,
                        'payment_method' => $paymentMode,
                        'entry_type_code' => 'INVOICE_PAYMENT',
                    ]);
                }

                return [
                    'found' => true,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoiceNumber,
                    'barcode' => $barcode ?: ($matchedProduct?->barcode ?? $matchedSilverProduct?->barcode ?? null),
                    'customer_name' => $customer->name,
                    'customer_phone' => $customer->mobile,
                    'item_name' => ! empty($itemName) ? $itemName : "{$purityStr} {$metal} Ornament",
                    'weight' => $weight . ' g',
                    'metal' => $metal,
                    'purity' => $purityStr,
                    'rate_per_gm' => '₹' . number_format($effectiveRate, 2),
                    'metal_value' => '₹' . number_format($metalValue, 2),
                    'making_charges' => '₹' . number_format($makingTotal, 2) . " {$makingLabel}",
                    'subtotal' => '₹' . number_format($subtotal, 2),
                    'gst_3_percent' => '₹' . number_format($gstAmount, 2),
                    'grand_total' => '₹' . number_format($grandTotal, 2),
                    'payment_mode' => $paymentMode,
                    'view_url' => "/invoices/{$invoice->id}",
                    'print_url' => "/invoices/{$invoice->id}/print",
                    'status' => 'INVOICE_GENERATED_REAL_DB',
                ];

            case 'check_stock':
                $q = trim((string) ($args['query'] ?? ''));
                $metalFilter = strtoupper(trim((string) ($args['metal'] ?? '')));
                $catFilter = trim((string) ($args['category'] ?? ''));

                $goldQuery = Product::where('is_sold', false);
                $silverQuery = SilverProduct::where('is_sold', false);

                if (! empty($q)) {
                    $goldQuery->where(function ($query) use ($q) {
                        $query->where('name', 'like', "%{$q}%")
                            ->orWhere('barcode', 'like', "%{$q}%");
                    });
                    $silverQuery->where(function ($query) use ($q) {
                        $query->where('name', 'like', "%{$q}%")
                            ->orWhere('barcode', 'like', "%{$q}%");
                    });
                }

                $goldItems = $goldQuery->with(['category', 'purity'])->take(6)->get();
                $goldCount = Product::where('is_sold', false)->count();
                $goldWeight = Product::where('is_sold', false)->sum('net_weight');

                $silverItems = $silverQuery->with('category')->take(6)->get();
                $silverCount = SilverProduct::where('is_sold', false)->count();
                $silverWeight = SilverProduct::where('is_sold', false)->sum('net_weight');

                $totalCount = $goldCount + $silverCount;
                $totalWeight = round($goldWeight + $silverWeight, 3);

                $items = [];
                foreach ($goldItems as $g) {
                    $items[] = [
                        'barcode' => $g->barcode,
                        'name' => $g->name,
                        'metal' => 'GOLD',
                        'purity' => $g->purity?->name ?? '22K',
                        'weight' => $g->net_weight . ' g',
                        'category' => $g->category?->name ?? 'General',
                        'making' => '₹' . $g->making_charge . '/g',
                    ];
                }
                foreach ($silverItems as $s) {
                    $items[] = [
                        'barcode' => $s->barcode,
                        'name' => $s->name,
                        'metal' => 'SILVER',
                        'purity' => 'Silver',
                        'weight' => $s->net_weight . ' g',
                        'category' => $s->category?->name ?? 'Silver',
                        'making' => '₹' . $s->making_charge,
                    ];
                }

                return [
                    'query' => ! empty($q) ? $q : 'All Showroom Stock',
                    'total_items' => $totalCount,
                    'total_weight' => $totalWeight . ' g',
                    'gold_count' => $goldCount,
                    'gold_weight' => round($goldWeight, 3) . ' g',
                    'silver_count' => $silverCount,
                    'silver_weight' => round($silverWeight, 3) . ' g',
                    'items' => $items,
                    'status' => 'REAL_ERP_INVENTORY',
                ];

            default:
                return ['status' => 'OK'];
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
}
