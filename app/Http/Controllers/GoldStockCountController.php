<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\DailyRegister;
use App\Models\GoldStockCountEntry;
use App\Models\GoldStockCountSession;
use App\Models\Product;
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
        $categoryId = $request->filled('category_id')
            ? Category::query()->gold()->whereKey((int) $request->input('category_id'))->value('id')
            : null;
        $register = $this->currentOpenRegister();
        $session = $register
            ? GoldStockCountSession::query()
                ->with(['entries.product.category', 'entries.product.purity'])
                ->where('daily_register_id', $register->id)
                ->first()
            : null;

        return Inertia::render('gold-stock-count/Index', [
            'dayOpen' => (bool) $register,
            'session' => $session ? [
                'id' => $session->id,
                'status' => $session->status,
                'started_at' => optional($session->started_at)->toISOString(),
                'completed_at' => optional($session->completed_at)->toISOString(),
            ] : null,
            'categories' => Category::query()->gold()->orderBy('name')->get(['id', 'name']),
            'selectedCategoryId' => $categoryId,
            'summary' => $register ? $this->buildSummary($register, $session, $categoryId) : null,
            'recentCounted' => $register ? $this->recentCounted($session, $categoryId) : [],
            'missingProducts' => $register ? $this->missingProducts($session, $categoryId) : [],
        ]);
    }

    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('metal_type', 'GOLD'))],
        ]);

        $categoryId = isset($validated['category_id']) ? (int) $validated['category_id'] : null;

        $register = $this->currentOpenRegister();
        abort_unless($register, 422, 'Open the shop day before counting stock.');

        $session = GoldStockCountSession::query()->firstOrCreate(
            ['daily_register_id' => $register->id],
            [
                'count_date' => $register->date,
                'status' => 'OPEN',
                'started_by' => Auth::id(),
                'started_at' => now(),
            ]
        );

        if ($session->status === 'COMPLETED') {
            abort(422, 'Today\'s gold stock count is already marked complete.');
        }

        $normalizedBarcode = strtoupper(trim($validated['barcode']));

        $product = Product::query()
            ->with(['category', 'purity'])
            ->where('is_sold', false)
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->where(function ($query) use ($normalizedBarcode) {
                $query->where('barcode', $normalizedBarcode);

                if (preg_match('/^G(\d{5})$/', $normalizedBarcode, $matches)) {
                    $query->orWhere('id', (int) $matches[1]);
                }
            })
            ->first();

        abort_unless($product, 422, $categoryId
            ? 'Gold product not found in the selected category and current open stock.'
            : 'Gold product not found in current open stock.');

        $alreadyCounted = GoldStockCountEntry::query()
            ->where('session_id', $session->id)
            ->where('product_id', $product->id)
            ->exists();

        abort_if($alreadyCounted, 422, 'This gold product is already counted.');

        GoldStockCountEntry::query()->create([
            'session_id' => $session->id,
            'product_id' => $product->id,
            'scanned_barcode' => $product->barcode,
            'scanned_by' => Auth::id(),
            'scanned_at' => now(),
        ]);

        $session->load(['entries.product.category', 'entries.product.purity']);

        return response()->json([
            'countedProduct' => [
                'id' => $product->id,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'category' => $product->category?->name,
                'purity' => $product->purity?->name,
                'net_weight' => (float) $product->net_weight,
            ],
            'summary' => $this->buildSummary($register, $session, $categoryId),
            'recentCounted' => $this->recentCounted($session, $categoryId),
            'missingProducts' => $this->missingProducts($session, $categoryId),
        ]);
    }

    public function complete(): JsonResponse
    {
        $register = $this->currentOpenRegister();
        abort_unless($register, 422, 'Open the shop day before completing stock count.');

        $session = GoldStockCountSession::query()
            ->with('entries:id,session_id,product_id')
            ->where('daily_register_id', $register->id)
            ->firstOrFail();

        $expectedIds = Product::query()->where('is_sold', false)->pluck('id');
        $countedIds = $session->entries->pluck('product_id');
        abort_if($expectedIds->diff($countedIds)->isNotEmpty(), 422, 'Count all open gold stock before marking the session complete.');

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

    private function buildSummary(DailyRegister $register, ?GoldStockCountSession $session, ?int $categoryId = null): array
    {
        $expectedProducts = Product::query()
            ->where('is_sold', false)
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->get(['id', 'gross_weight', 'net_weight']);

        $countedProducts = $session
            ? Product::query()
                ->whereIn('id', $session->entries->pluck('product_id'))
                ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
                ->get(['id', 'gross_weight', 'net_weight'])
            : collect();

        $expectedCount = $expectedProducts->count();
        $countedCount = $countedProducts->count();
        $allExpectedIds = Product::query()->where('is_sold', false)->pluck('id');
        $allCountedIds = $session ? $session->entries->pluck('product_id') : collect();

        return [
            'register_date' => optional($register->date)->toDateString(),
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
            ->take(50)
            ->get()
            ->map(fn (GoldStockCountEntry $entry) => [
                'id' => $entry->id,
                'barcode' => $entry->product?->barcode,
                'name' => $entry->product?->name,
                'category' => $entry->product?->category?->name,
                'purity' => $entry->product?->purity?->name,
                'net_weight' => (float) ($entry->product?->net_weight ?? 0),
                'scanned_at' => optional($entry->scanned_at)->toISOString(),
                'scanned_by' => $entry->scannedBy?->name,
            ])
            ->values()
            ->all();
    }

    private function missingProducts(?GoldStockCountSession $session, ?int $categoryId = null): array
    {
        $countedIds = $session ? $session->entries->pluck('product_id') : collect();

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
                'purity' => $product->purity?->name,
                'gross_weight' => (float) $product->gross_weight,
                'net_weight' => (float) $product->net_weight,
            ])
            ->values()
            ->all();
    }
}
