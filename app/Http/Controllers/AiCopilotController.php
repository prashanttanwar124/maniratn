<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DailyRate;
use App\Models\Product;
use App\Models\Purity;
use App\Models\Supplier;
use App\Models\Vault;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
                        $finalReply = "Maaf kijiye, aaj ka bhav abhi tak add nahi kiya gaya hai. Kripya pehle aaj ka bhav update karein.";
                    } else {
                        $finalReply = "Aaj ka 24K Gold ₹" . number_format($realData['gold_24k_per_gm']) . ", 22K ₹" . number_format($realData['gold_22k_per_gm']) . ", Silver ₹" . number_format($realData['silver_per_gm'], 2) . " per gram hai.";
                    }
                } elseif ($tool === 'update_daily_rates') {
                    $finalReply = "Done. Aaj ka 24K rate ₹" . number_format($realData['gold_24k_sell']) . " aur Silver ₹" . number_format($realData['silver_sell'], 2) . " database me update ho gaya.";
                } elseif ($tool === 'add_product') {
                    $finalReply = "Done. {$realData['weight']} {$realData['purity']} {$realData['name']} add ho gayi, Barcode {$realData['barcode']}.";
                } elseif ($tool === 'get_vault_balance') {
                    $finalReply = "Vault me Cash {$realData['cash_in_hand']}, Gold {$realData['gold_in_vault']}, aur Silver {$realData['silver_in_vault']} hai.";
                } elseif ($tool === 'calculate_estimate') {
                    $finalReply = "Total estimate quotation {$realData['total_estimate']} banega.";
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
                $makingPercent = isset($args['making_percent']) ? floatval($args['making_percent']) : null;
                $makingPerGm = isset($args['making_charge_per_gram']) ? floatval($args['making_charge_per_gram']) : null;

                // Prefer today's rate if available, else latest non-zero
                $rateRecord = DailyRate::whereDate('date', Carbon::today())->where('gold_sell', '>', 0)->first()
                    ?? DailyRate::where('gold_sell', '>', 0)->orderByDesc('date')->first();

                $ratePerGm = ($metal === 'SILVER')
                    ? ($rateRecord ? floatval($rateRecord->silver_sell) : 88.50)
                    : ($rateRecord ? floatval($rateRecord->gold_sell) * 0.916 : 6830);

                $metalValue = $weight * $ratePerGm;

                if ($makingPercent !== null && $makingPercent > 0) {
                    $makingTotal = $metalValue * ($makingPercent / 100);
                    $makingLabel = "({$makingPercent}%)";
                } else {
                    $makingPerGmVal = $makingPerGm ?? 450;
                    $makingTotal = $weight * $makingPerGmVal;
                    $makingLabel = "(@ ₹{$makingPerGmVal}/g)";
                }

                $subtotal = $metalValue + $makingTotal;
                $gst = $subtotal * 0.03;
                $grandTotal = $subtotal + $gst;

                return [
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

            default:
                return ['status' => 'OK'];
        }
    }
}
