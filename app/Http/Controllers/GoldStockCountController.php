<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Counter;
use App\Models\DailyRegister;
use App\Models\GoldStockCountEntry;
use App\Models\GoldStockCountSession;
use App\Models\Product;
use App\Models\Purity;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GoldStockCountController extends Controller
{
    public function index(Request $request): Response
    {
        $selectedDate = $request->filled('date')
            ? Carbon::parse($request->input('date'))->toDateString()
            : Carbon::today()->toDateString();

        $isToday = ($selectedDate === Carbon::today()->toDateString());

        $categoryId = $request->filled('category_id')
            ? Category::query()->gold()->whereKey((int) $request->input('category_id'))->value('id')
            : null;

        $openRegisterToday = $this->currentOpenRegister();

        $register = DailyRegister::query()
            ->whereDate('date', $selectedDate)
            ->latest('id')
            ->first();

        $session = GoldStockCountSession::query()
            ->with(['entries.product.category', 'entries.product.purity'])
            ->where(function ($query) use ($register, $selectedDate) {
                if ($register) {
                    $query->where('daily_register_id', $register->id)
                        ->orWhereDate('count_date', $selectedDate);
                } else {
                    $query->whereDate('count_date', $selectedDate);
                }
            })
            ->latest('id')
            ->first();

        $dayOpen = $isToday ? (bool) $openRegisterToday : ((bool) $register && is_null($register->closed_at));

        return Inertia::render('gold-stock-count/Index', [
            'dayOpen' => $dayOpen,
            'isToday' => $isToday,
            'selectedDate' => $selectedDate,
            'session' => $session ? [
                'id' => $session->id,
                'status' => $session->status,
                'count_date' => optional($session->count_date)->toDateString() ?? $selectedDate,
                'started_at' => optional($session->started_at)->toISOString(),
                'completed_at' => optional($session->completed_at)->toISOString(),
            ] : null,
            'categories' => Category::query()->gold()->orderBy('name')->get(['id', 'name']),
            'purities' => Purity::query()->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::query()->orderBy('company_name')->get(['id', 'company_name', 'contact_person']),
            'counters' => Counter::query()->orderBy('name')->get(['id', 'name']),
            'selectedCategoryId' => $categoryId,
            'summary' => ($register || $session) ? $this->buildSummary($register, $session, $categoryId, $selectedDate) : null,
            'categoryBreakdown' => ($register || $session) ? $this->buildCategoryBreakdown($session) : [],
            'recentCounted' => ($register || $session) ? $this->recentCounted($session, $categoryId) : [],
            'missingProducts' => ($register || $session) ? $this->missingProducts($session, $categoryId) : [],
        ]);
    }

    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('metal_type', 'GOLD'))],
            'date' => ['nullable', 'date'],
        ]);

        $categoryId = isset($validated['category_id']) ? (int) $validated['category_id'] : null;
        $targetDate = isset($validated['date'])
            ? Carbon::parse($validated['date'])->toDateString()
            : Carbon::today()->toDateString();

        $register = DailyRegister::query()->whereDate('date', $targetDate)->latest('id')->first()
            ?: $this->currentOpenRegister();

        $session = GoldStockCountSession::query()
            ->where(function ($query) use ($register, $targetDate) {
                if ($register) {
                    $query->where('daily_register_id', $register->id)
                        ->orWhereDate('count_date', $targetDate);
                } else {
                    $query->whereDate('count_date', $targetDate);
                }
            })
            ->latest('id')
            ->first();

        if (! $session) {
            $session = GoldStockCountSession::query()->create([
                'daily_register_id' => $register?->id,
                'count_date' => $targetDate,
                'status' => 'OPEN',
                'started_by' => Auth::id(),
                'started_at' => now(),
            ]);
        }

        if ($session->status === 'COMPLETED') {
            abort(422, "The gold stock count for {$targetDate} is already marked complete.");
        }

        $rawBarcode = trim((string) $validated['barcode']);
        $normalizedBarcode = strtoupper($rawBarcode);
        $cleanCode = preg_replace('/[^A-Z0-9\-]/', '', $normalizedBarcode);

        $productQuery = Product::query()
            ->with(['category', 'purity'])
            ->where('is_sold', false)
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId));

        // 1. Direct barcode match
        $product = (clone $productQuery)->where('barcode', $cleanCode)->first();

        // 2. Flexible variations match (G00025, G25, MJ-00025, MJ-25, numeric 25)
        if (! $product) {
            if (preg_match('/^(?:G|MJ-)?0*(\d+)$/', $cleanCode, $matches)) {
                $id = (int) $matches[1];
                $paddedG = 'G' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
                $paddedMJ = 'MJ-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);

                $product = (clone $productQuery)
                    ->where(function ($q) use ($id, $paddedG, $paddedMJ, $cleanCode) {
                        $q->where('id', $id)
                            ->orWhere('barcode', $paddedG)
                            ->orWhere('barcode', $paddedMJ)
                            ->orWhere('barcode', $cleanCode);
                    })
                    ->first();
            }
        }

        abort_unless($product, 422, $categoryId
            ? 'Gold product not found in the selected category and current open stock.'
            : 'Gold product not found in current open stock.');

        $alreadyCounted = GoldStockCountEntry::query()
            ->where('session_id', $session->id)
            ->where('product_id', $product->id)
            ->exists();

        abort_if($alreadyCounted, 422, "Product {$product->barcode} is already counted in this session.");

        GoldStockCountEntry::query()->create([
            'session_id' => $session->id,
            'product_id' => $product->id,
            'scanned_barcode' => $product->barcode,
            'scanned_by' => Auth::id(),
            'scanned_at' => now(),
        ]);

        $session->load(['entries.product.category', 'entries.product.purity']);

        // If filtering by a specific category and scanned product is in that category, keep it;
        // if user scanned an item in a different category, switch effective category to that item's category so it shows immediately
        $effectiveCategoryId = ($categoryId && $product->category_id === $categoryId) ? $categoryId : ($categoryId ? $product->category_id : null);

        return response()->json([
            'category_id' => $effectiveCategoryId,
            'countedProduct' => [
                'id' => $product->id,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'category_id' => $product->category_id,
                'category' => $product->category?->name,
                'purity' => $product->purity?->name,
                'net_weight' => (float) $product->net_weight,
            ],
            'summary' => $this->buildSummary($register, $session, $effectiveCategoryId, $targetDate),
            'categoryBreakdown' => $this->buildCategoryBreakdown($session),
            'recentCounted' => $this->recentCounted($session, $effectiveCategoryId),
            'missingProducts' => $this->missingProducts($session, $effectiveCategoryId),
        ]);
    }

    public function complete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $targetDate = isset($validated['date'])
            ? Carbon::parse($validated['date'])->toDateString()
            : Carbon::today()->toDateString();

        $register = DailyRegister::query()->whereDate('date', $targetDate)->latest('id')->first()
            ?: $this->currentOpenRegister();

        $session = GoldStockCountSession::query()
            ->with('entries:id,session_id,product_id')
            ->where(function ($query) use ($register, $targetDate) {
                if ($register) {
                    $query->where('daily_register_id', $register->id)
                        ->orWhereDate('count_date', $targetDate);
                } else {
                    $query->whereDate('count_date', $targetDate);
                }
            })
            ->latest('id')
            ->first();

        abort_unless($session, 422, "No gold stock count session found for {$targetDate}.");

        if ($session->status === 'COMPLETED') {
            abort(422, "The stock count for {$targetDate} is already marked complete.");
        }

        $allExpectedCount = Product::query()->where('is_sold', false)->count();
        $countedCount = $session->entries()->count();

        if ($countedCount < $allExpectedCount) {
            abort(422, "Cannot mark complete: {$countedCount} of {$allExpectedCount} gold items counted.");
        }

        $session->update([
            'status' => 'COMPLETED',
            'completed_by' => Auth::id(),
            'completed_at' => now(),
        ]);

        return response()->json([
            'session' => [
                'id' => $session->id,
                'status' => $session->status,
                'completed_at' => optional($session->completed_at)->toISOString(),
            ],
        ]);
    }

    private function currentOpenRegister(): ?DailyRegister
    {
        return DailyRegister::query()
            ->whereDate('date', Carbon::today())
            ->whereNull('closed_at')
            ->latest('id')
            ->first();
    }

    private function buildSummary(?DailyRegister $register, ?GoldStockCountSession $session, ?int $categoryId = null, ?string $date = null): array
    {
        $expectedProductsQuery = Product::query()
            ->where('is_sold', false);

        $expectedProducts = (clone $expectedProductsQuery)
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->get(['id', 'gross_weight', 'net_weight']);

        $allCountedIds = $session ? $session->entries()->pluck('product_id') : collect();
        $countedProducts = ($session && $allCountedIds->isNotEmpty())
            ? (clone $expectedProductsQuery)
                ->whereIn('id', $allCountedIds)
                ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
                ->get(['id', 'gross_weight', 'net_weight'])
            : collect();

        $expectedCount = $expectedProducts->count();
        $countedCount = $countedProducts->count();
        $allExpectedIds = (clone $expectedProductsQuery)->pluck('id');

        return [
            'register_date' => $register ? optional($register->date)->toDateString() : ($date ?? Carbon::today()->toDateString()),
            'expected_items' => $expectedCount,
            'counted_items' => $countedCount,
            'remaining_items' => max($expectedCount - $countedCount, 0),
            'overall_expected_items' => $allExpectedIds->count(),
            'overall_remaining_items' => $allExpectedIds->diff($allCountedIds)->count(),
            'match_percentage' => $expectedCount > 0 ? round(($countedCount / $expectedCount) * 100, 1) : 100,
            'expected_gross_weight' => round((float) $expectedProducts->sum('gross_weight'), 3),
            'expected_net_weight' => round((float) $expectedProducts->sum('net_weight'), 3),
            'counted_gross_weight' => round((float) $countedProducts->sum('gross_weight'), 3),
            'counted_net_weight' => round((float) $countedProducts->sum('net_weight'), 3),
        ];
    }

    private function recentCounted(?GoldStockCountSession $session, ?int $categoryId = null): array
    {
        if (! $session) {
            return [];
        }

        return $session->entries()
            ->with(['product.category', 'product.purity', 'scannedBy'])
            ->when($categoryId, fn ($query) => $query->whereHas('product', fn ($productQuery) => $productQuery->where('category_id', $categoryId)))
            ->latest('scanned_at')
            ->latest('id')
            ->take(100)
            ->get()
            ->map(fn (GoldStockCountEntry $entry) => [
                'id' => $entry->product?->id ?? $entry->product_id,
                'entry_id' => $entry->id,
                'product_id' => $entry->product_id,
                'barcode' => $entry->product?->barcode,
                'name' => $entry->product?->name,
                'category' => $entry->product?->category?->name,
                'category_id' => $entry->product?->category_id,
                'purity' => $entry->product?->purity?->name,
                'purity_id' => $entry->product?->purity_id,
                'supplier_id' => $entry->product?->supplier_id,
                'counter_id' => $entry->product?->counter_id,
                'gross_weight' => (float) ($entry->product?->gross_weight ?? 0),
                'net_weight' => (float) ($entry->product?->net_weight ?? 0),
                'making_charge' => (float) ($entry->product?->making_charge ?? 0),
                'image_path' => $entry->product?->image_path,
                'scanned_at' => optional($entry->scanned_at)->toISOString(),
                'scanned_by' => $entry->scannedBy?->name,
            ])
            ->values()
            ->all();
    }

    private function missingProducts(?GoldStockCountSession $session, ?int $categoryId = null): array
    {
        $countedIds = $session ? $session->entries()->pluck('product_id') : collect();

        return Product::query()
            ->with(['category', 'purity'])
            ->where('is_sold', false)
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->when($countedIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $countedIds))
            ->orderBy('barcode')
            ->take(100)
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'category' => $product->category?->name,
                'category_id' => $product->category_id,
                'purity' => $product->purity?->name,
                'purity_id' => $product->purity_id,
                'supplier_id' => $product->supplier_id,
                'counter_id' => $product->counter_id,
                'gross_weight' => (float) $product->gross_weight,
                'net_weight' => (float) $product->net_weight,
                'making_charge' => (float) $product->making_charge,
                'image_path' => $product->image_path,
            ])
            ->values()
            ->all();
    }

    private function buildCategoryBreakdown(?GoldStockCountSession $session): array
    {
        $categories = Category::query()->gold()->orderBy('name')->get(['id', 'name']);
        $allCountedIds = $session ? $session->entries()->pluck('product_id') : collect();

        $openProducts = Product::query()
            ->where('is_sold', false)
            ->get(['id', 'category_id', 'gross_weight', 'net_weight']);

        return $categories->map(function (Category $category) use ($openProducts, $allCountedIds) {
            $catProducts = $openProducts->where('category_id', $category->id);
            $expectedCount = $catProducts->count();
            $catCountedProducts = $catProducts->whereIn('id', $allCountedIds);
            $countedCount = $catCountedProducts->count();
            $remainingCount = max($expectedCount - $countedCount, 0);

            return [
                'id' => $category->id,
                'name' => $category->name,
                'expected_items' => $expectedCount,
                'counted_items' => $countedCount,
                'remaining_items' => $remainingCount,
                'expected_net_weight' => round((float) $catProducts->sum('net_weight'), 3),
                'counted_net_weight' => round((float) $catCountedProducts->sum('net_weight'), 3),
                'is_complete' => $expectedCount > 0 && $remainingCount === 0,
            ];
        })->values()->all();
    }
}
