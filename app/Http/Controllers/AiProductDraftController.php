<?php

namespace App\Http\Controllers;

use App\Models\AiProductDraftConfirmation;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Counter;
use App\Models\Product;
use App\Models\Purity;
use App\Models\SilverProduct;
use App\Models\Supplier;
use App\Support\Inventory\GoldProductRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AiProductDraftController extends Controller
{
    /**
     * Master data used by the global AI product draft tray.
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'categories' => Category::query()
                ->select(['id', 'name', 'metal_type'])
                ->orderBy('metal_type')
                ->orderBy('name')
                ->get(),
            'purities' => Purity::query()
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get(),
            'suppliers' => Supplier::query()
                ->select(['id', 'company_name', 'type'])
                ->orderBy('company_name')
                ->get(),
            'counters' => Counter::query()
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Confirm one or more reviewed AI drafts in a single atomic transaction.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message_id' => ['required', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.draft_id' => ['required', 'string', 'max:100', 'distinct'],
            'items.*.action_index' => ['required', 'integer', 'min:0'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.metal' => ['required', 'string', 'in:GOLD,SILVER'],
            'items.*.category_id' => ['required', 'integer', 'exists:categories,id'],
            'items.*.purity_id' => ['nullable', 'integer', 'exists:purities,id'],
            'items.*.supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'items.*.counter_id' => ['nullable', 'integer', 'exists:counters,id'],
            'items.*.gross_weight' => ['required', 'numeric', 'min:0.001'],
            'items.*.net_weight' => ['required', 'numeric', 'min:0.001'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'items.*.making_charge' => ['required', 'numeric', 'min:0'],
            'items.*.making_charge_type' => ['nullable', 'string', 'in:percentage,flat,per_gram'],
            'items.*.image' => ['nullable', 'image', 'max:2048'],
        ]);

        $validated['items'] = collect($validated['items'])->map(function (array $item) {
            $item['making_charge_type'] = $item['making_charge_type']
                ?? ((float) $item['making_charge'] > 100 ? 'per_gram' : 'percentage');

            return $item;
        })->all();

        $this->validateMasterDataForMetal($validated['items']);

        $storedImagePaths = [];

        try {
            $results = DB::transaction(function () use ($request, $validated, &$storedImagePaths) {
                return collect($validated['items'])->map(function (array $item, int $index) use ($request, $validated, &$storedImagePaths) {
                    $existing = AiProductDraftConfirmation::query()
                        ->where('message_id', $validated['message_id'])
                        ->where('draft_id', $item['draft_id'])
                        ->first();

                    if ($existing) {
                        return $existing->result;
                    }

                    $imagePath = null;
                    $image = $request->file("items.{$index}.image");

                    if ($image) {
                        $directory = $item['metal'] === 'SILVER' ? 'silver-products' : 'products';
                        $imagePath = $image->store($directory, 'public');
                        $storedImagePaths[] = $imagePath;
                    }

                    $result = $this->createProducts($item, $imagePath);

                    AiProductDraftConfirmation::create([
                        'message_id' => $validated['message_id'],
                        'draft_id' => $item['draft_id'],
                        'action_index' => $item['action_index'],
                        'result' => $result,
                        'confirmed_by' => $request->user()?->id,
                    ]);

                    return $result;
                })->values()->all();
            });
        } catch (\Throwable $exception) {
            foreach ($storedImagePaths as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }

        if (! empty($validated['message_id'])) {
            $this->syncToAiHub($validated['message_id'], $results);
        }

        return response()->json([
            'success' => true,
            'items' => $results,
            'created_count' => collect($results)->sum('quantity'),
            'message' => collect($results)->sum('quantity').' product(s) stock mein save ho gaye.',
        ]);
    }

    private function validateMasterDataForMetal(array $items): void
    {
        $categoryIds = collect($items)->pluck('category_id')->unique()->values();
        $categories = Category::query()->whereIn('id', $categoryIds)->get()->keyBy('id');
        $errors = [];

        foreach ($items as $index => $item) {
            $category = $categories->get($item['category_id']);

            if ($item['metal'] === 'GOLD') {
                $validator = Validator::make($item, GoldProductRules::rules(
                    makingChargeType: $item['making_charge_type'] ?? null,
                ));

                foreach ($validator->errors()->toArray() as $field => $messages) {
                    $errors["items.{$index}.{$field}"] = $messages[0];
                }

                continue;
            }

            if ($category && strtoupper((string) $category->metal_type) !== $item['metal']) {
                $errors["items.{$index}.category_id"] = "Selected category {$item['metal']} product ke liye valid nahi hai.";
            }

            if ((float) $item['net_weight'] > (float) $item['gross_weight']) {
                $errors["items.{$index}.net_weight"] = 'Net weight gross weight se zyada nahi ho sakta.';
            }

            if ($item['making_charge_type'] === 'percentage' && (float) $item['making_charge'] > 100) {
                $errors["items.{$index}.making_charge"] = 'Making charge percentage 100 se zyada nahi ho sakta.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function createProducts(array $item, ?string $imagePath): array
    {
        $barcodes = [];
        $productIds = [];
        $baseName = trim($item['name']);

        for ($piece = 0; $piece < $item['quantity']; $piece++) {
            if ($item['metal'] === 'SILVER') {
                $product = SilverProduct::create([
                    'name' => $item['quantity'] > 1 ? $baseName.' #'.($piece + 1) : $baseName,
                    'category_id' => $item['category_id'],
                    'supplier_id' => $item['supplier_id'],
                    'counter_id' => $item['counter_id'] ?? null,
                    'pricing_mode' => 'WEIGHT',
                    'quantity' => 1,
                    'gross_weight' => $item['gross_weight'],
                    'net_weight' => $item['net_weight'],
                    'making_charge' => $item['making_charge'],
                    'making_charge_type' => $item['making_charge_type'],
                    'image_path' => $imagePath,
                    'is_sold' => false,
                ]);
            } else {
                $product = Product::create([
                    'name' => $baseName,
                    'category_id' => $item['category_id'],
                    'purity_id' => $item['purity_id'],
                    'supplier_id' => $item['supplier_id'],
                    'counter_id' => $item['counter_id'] ?? null,
                    'gross_weight' => $item['gross_weight'],
                    'net_weight' => $item['net_weight'],
                    'making_charge' => $item['making_charge'],
                    'making_charge_type' => $item['making_charge_type'],
                    'image_path' => $imagePath,
                    'is_sold' => false,
                ]);

                $product->updateQuietly(['name' => $baseName.' - '.$product->barcode]);
            }

            $productIds[] = $product->id;
            $barcodes[] = $product->barcode;
        }

        return [
            'draft_id' => $item['draft_id'],
            'action_index' => $item['action_index'],
            'success' => true,
            'name' => $baseName,
            'metal' => $item['metal'],
            'quantity' => $item['quantity'],
            'weight' => (float) $item['net_weight'],
            'total_weight' => round((float) $item['net_weight'] * $item['quantity'], 3),
            'product_id' => $productIds[0],
            'product_ids' => $productIds,
            'barcode' => implode(', ', $barcodes),
            'barcodes' => $barcodes,
            'is_preview' => false,
            'status' => 'IN_STOCK_REAL_DB',
        ];
    }

    private function syncToAiHub(string $messageId, array $results): void
    {
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
                    'actions' => collect($results)->map(fn (array $result) => [
                        'tool' => 'add_product',
                        'args' => [],
                        'result' => $result,
                    ])->values()->all(),
                    'reply' => collect($results)->sum('quantity').' product(s) stock mein save ho gaye.',
                ]);
        } catch (\Throwable $exception) {
            Log::warning('Failed to sync AI product drafts: '.$exception->getMessage());
        }
    }
}
