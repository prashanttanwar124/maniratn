<?php

namespace App\Http\Controllers;

use App\Models\DailyRegister;
use App\Models\GoldStockCountEntry;
use App\Models\GoldStockCountSession;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class GoldStockCountController extends Controller
{
    public function index(): Response
    {
        $register = $this->currentOpenRegister();
        $session = $register
            ? GoldStockCountSession::query()
                ->with(['entries.product.category'])
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
            'summary' => $register ? $this->buildSummary($register, $session) : null,
            'recentCounted' => $register ? $this->recentCounted($session) : [],
            'missingProducts' => $register ? $this->missingProducts($session) : [],
        ]);
    }

    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string'],
        ]);

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
            ->with('category')
            ->where('is_sold', false)
            ->where(function ($query) use ($normalizedBarcode) {
                $query->where('barcode', $normalizedBarcode);

                if (preg_match('/^G(\d{5})$/', $normalizedBarcode, $matches)) {
                    $query->orWhere('id', (int) $matches[1]);
                }
            })
            ->first();

        abort_unless($product, 422, 'Gold product not found in current open stock.');

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

        $session->load('entries.product.category');

        return response()->json([
            'countedProduct' => [
                'id' => $product->id,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'category' => $product->category?->name,
            ],
            'summary' => $this->buildSummary($register, $session),
            'recentCounted' => $this->recentCounted($session),
            'missingProducts' => $this->missingProducts($session),
        ]);
    }

    public function complete(): JsonResponse
    {
        $register = $this->currentOpenRegister();
        abort_unless($register, 422, 'Open the shop day before completing stock count.');

        $session = GoldStockCountSession::query()
            ->where('daily_register_id', $register->id)
            ->firstOrFail();

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

    private function buildSummary(DailyRegister $register, ?GoldStockCountSession $session): array
    {
        $expectedProducts = Product::query()
            ->where('is_sold', false)
            ->get(['id', 'gross_weight', 'net_weight']);

        $countedProducts = $session
            ? Product::query()
                ->whereIn('id', $session->entries->pluck('product_id'))
                ->get(['id', 'gross_weight', 'net_weight'])
            : collect();

        $expectedCount = $expectedProducts->count();
        $countedCount = $countedProducts->count();

        return [
            'register_date' => optional($register->date)->toDateString(),
            'expected_items' => $expectedCount,
            'counted_items' => $countedCount,
            'remaining_items' => max($expectedCount - $countedCount, 0),
            'match_percentage' => $expectedCount > 0 ? round(($countedCount / $expectedCount) * 100, 1) : 100,
            'expected_gross_weight' => round((float) $expectedProducts->sum('gross_weight'), 3),
            'expected_net_weight' => round((float) $expectedProducts->sum('net_weight'), 3),
            'counted_gross_weight' => round((float) $countedProducts->sum('gross_weight'), 3),
            'counted_net_weight' => round((float) $countedProducts->sum('net_weight'), 3),
        ];
    }

    private function recentCounted(?GoldStockCountSession $session): array
    {
        if (! $session) {
            return [];
        }

        return $session->entries()
            ->with(['product.category', 'scannedBy'])
            ->latest('scanned_at')
            ->take(50)
            ->get()
            ->map(fn (GoldStockCountEntry $entry) => [
                'id' => $entry->id,
                'barcode' => $entry->product?->barcode,
                'name' => $entry->product?->name,
                'category' => $entry->product?->category?->name,
                'scanned_at' => optional($entry->scanned_at)->toISOString(),
                'scanned_by' => $entry->scannedBy?->name,
            ])
            ->values()
            ->all();
    }

    private function missingProducts(?GoldStockCountSession $session): array
    {
        $countedIds = $session ? $session->entries->pluck('product_id') : collect();

        return Product::query()
            ->with('category')
            ->where('is_sold', false)
            ->when($countedIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $countedIds))
            ->orderBy('barcode')
            ->take(100)
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'category' => $product->category?->name,
                'gross_weight' => (float) $product->gross_weight,
                'net_weight' => (float) $product->net_weight,
            ])
            ->values()
            ->all();
    }
}
