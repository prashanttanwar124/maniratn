<?php

namespace App\Http\Controllers;

use App\Services\BarcodeLabelPdfService;
use App\Models\Category;
use App\Models\SilverProduct;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SilverProductController extends Controller
{
    public function index(Request $request)
    {
        $query = SilverProduct::query()->with(['category', 'supplier']);
        $statsBaseQuery = SilverProduct::query()->with('category');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($silverQuery) use ($search) {
                $silverQuery->where('name', 'like', '%' . $search . '%')
                    ->orWhere('barcode', 'like', '%' . $search . '%');
            });

            $statsBaseQuery->where(function ($silverQuery) use ($search) {
                $silverQuery->where('name', 'like', '%' . $search . '%')
                    ->orWhere('barcode', 'like', '%' . $search . '%');
            });
        }

        $statsProducts = $statsBaseQuery->get();
        $categoryBreakdown = $statsProducts
            ->groupBy(fn (SilverProduct $product) => $product->category?->name ?? 'Uncategorized')
            ->map(function ($products, $categoryName) {
                return [
                    'category' => $categoryName,
                    'items_count' => $products->count(),
                    'sold_count' => $products->where('is_sold', true)->count(),
                    'quantity' => (int) $products->sum('quantity'),
                    'gross_weight' => round((float) $products->sum('gross_weight'), 3),
                    'net_weight' => round((float) $products->sum('net_weight'), 3),
                ];
            })
            ->sortByDesc('items_count')
            ->values();

        return Inertia::render('silver-products/Index', [
            'silverProducts' => $query->latest()->paginate(10),
            'suppliers' => Supplier::all(),
            'categories' => Category::silver()->orderBy('name')->get(),
            'filters' => $request->only(['search']),
            'summary' => [
                'total_items' => $statsProducts->count(),
                'sold_items' => $statsProducts->where('is_sold', true)->count(),
                'available_items' => $statsProducts->where('is_sold', false)->count(),
                'total_quantity' => (int) $statsProducts->sum('quantity'),
                'gross_weight' => round((float) $statsProducts->sum('gross_weight'), 3),
                'net_weight' => round((float) $statsProducts->sum('net_weight'), 3),
            ],
            'category_breakdown' => $categoryBreakdown,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        unset($validated['image']);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('silver-products', 'public');
        }

        SilverProduct::create($validated);

        return redirect()->back()->with('message', 'Silver Product Created Successfully');
    }

    public function update(Request $request, SilverProduct $silverProduct)
    {
        $validated = $this->validatePayload($request);
        unset($validated['image']);

        if ($request->hasFile('image')) {
            if ($silverProduct->image_path) {
                Storage::disk('public')->delete($silverProduct->image_path);
            }

            $validated['image_path'] = $request->file('image')->store('silver-products', 'public');
        }

        if ($silverProduct->is_sold && (
            (int) $silverProduct->quantity !== (int) $validated['quantity']
            || (float) ($silverProduct->net_weight ?? 0) !== (float) ($validated['net_weight'] ?? 0)
        )) {
            return redirect()->back()->withErrors([
                'quantity' => 'Sold silver products cannot have their stock quantity or net weight changed.',
            ]);
        }

        $silverProduct->update($validated);

        return redirect()->back()->with('message', 'Silver Product Updated Successfully');
    }

    public function destroy(SilverProduct $silverProduct)
    {
        if ($silverProduct->is_sold) {
            return redirect()->back()->withErrors([
                'silver_product' => 'Sold silver products cannot be deleted.',
            ]);
        }

        if ($silverProduct->image_path) {
            Storage::disk('public')->delete($silverProduct->image_path);
        }

        $silverProduct->delete();

        return redirect()->back()->with('message', 'Silver Product Deleted');
    }

    public function printBarcodes(Request $request, BarcodeLabelPdfService $barcodeLabelPdfService)
    {
        $ids = array_filter(explode(',', (string) $request->query('ids')));
        $products = SilverProduct::whereIn('id', $ids)->get();

        $labels = [];
        foreach ($products as $product) {
            $codeStr = $product->barcode ?: ('MS-' . str_pad((string) $product->id, 5, '0', STR_PAD_LEFT));

            $labels[] = [
                'category' => strtoupper((string) ($product->category?->name ?? 'SILVER')),
                'gross_weight' => (float) ($product->gross_weight ?: 0),
                'net_weight' => (float) ($product->net_weight ?: 0),
                'code' => $codeStr,
            ];
        }

        return $barcodeLabelPdfService->stream($labels, 'silver-product-barcodes.pdf');
    }

    protected function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('metal_type', 'SILVER'))],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'pricing_mode' => ['required', 'in:PIECE,WEIGHT'],
            'quantity' => ['required', 'integer', 'min:1'],
            'gross_weight' => ['nullable', 'numeric', 'min:0'],
            'net_weight' => ['nullable', 'numeric', 'min:0'],
            'piece_price' => ['nullable', 'numeric', 'min:0'],
            'making_charge' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($validated['pricing_mode'] === 'PIECE' && empty($validated['piece_price'])) {
            $validated['piece_price'] = 0;
        }

        if ($validated['pricing_mode'] === 'WEIGHT' && empty($validated['net_weight'])) {
            $request->validate([
                'net_weight' => ['required', 'numeric', 'min:0.001'],
            ]);
        }

        return $validated;
    }
}
