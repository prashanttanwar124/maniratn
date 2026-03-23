<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Product;
use App\Models\Category;
use App\Models\Purity;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Picqer\Barcode\BarcodeGeneratorPNG;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()->with(['category', 'purity']);
        $statsBaseQuery = Product::query()->with('category');

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('barcode', 'like', '%' . $request->search . '%');

            $statsBaseQuery->where(function ($productQuery) use ($request) {
                $productQuery->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('barcode', 'like', '%' . $request->search . '%');
            });
        }

        $statsProducts = $statsBaseQuery->get();
        $categoryBreakdown = $statsProducts
            ->groupBy(fn (Product $product) => $product->category?->name ?? 'Uncategorized')
            ->map(function ($products, $categoryName) {
                return [
                    'category' => $categoryName,
                    'items_count' => $products->count(),
                    'sold_count' => $products->where('is_sold', true)->count(),
                    'gross_weight' => round((float) $products->sum('gross_weight'), 3),
                    'net_weight' => round((float) $products->sum('net_weight'), 3),
                ];
            })
            ->sortByDesc('items_count')
            ->values();

        return Inertia::render('products/Index', [
            'products'    => $query->latest()->paginate(10),
            'suppliers'   => Supplier::all(),
            'categories'  => Category::gold()->orderBy('name')->get(),
            'purities'    => Purity::all(),
            'filters'     => $request->only(['search']), // Pass search term back
            'summary' => [
                'total_items' => $statsProducts->count(),
                'sold_items' => $statsProducts->where('is_sold', true)->count(),
                'available_items' => $statsProducts->where('is_sold', false)->count(),
                'gross_weight' => round((float) $statsProducts->sum('gross_weight'), 3),
                'net_weight' => round((float) $statsProducts->sum('net_weight'), 3),
                'sold_weight' => round((float) $statsProducts->where('is_sold', true)->sum('net_weight'), 3),
            ],
            'category_breakdown' => $categoryBreakdown,
        ]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required',
            'category_id'   => ['required', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('metal_type', 'GOLD'))],
            'purity_id'     => 'required|exists:purities,id',
            'supplier_id'     => 'required|exists:suppliers,id',
            'gross_weight'  => 'required|numeric',
            'net_weight'    => 'required|numeric',
            'making_charge' => 'required|numeric',
            'image_path'         => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('products', 'public');
        }

        Product::create($validated);

        return redirect()->back()->with('message', 'Product Created Successfully');
    }

    public function update(Request $request, Product $product)
    {
        // Note: Rules are sometimes 'nullable' on update so user doesn't have to re-upload image
        $validated = $request->validate([
            'name'          => 'required',
            'category_id'   => ['required', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('metal_type', 'GOLD'))],
            'purity_id'     => 'required|exists:purities,id',
            'supplier_id'     => 'required|exists:suppliers,id',
            'gross_weight'  => 'required|numeric',
            'net_weight'    => 'required|numeric',
            'making_charge' => 'required|numeric',
            'image_path'         => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('products', 'public');
        }

        if ($product->is_sold && (float) $product->net_weight !== (float) $validated['net_weight']) {
            return redirect()->back()->withErrors([
                'net_weight' => 'Sold products cannot have their stock weight changed.',
            ]);
        }

        $product->update($validated);

        return redirect()->back()->with('message', 'Product Updated Successfully');
    }

    public function destroy(Product $product)
    {
        if ($product->is_sold) {
            return redirect()->back()->withErrors([
                'product' => 'Sold products cannot be deleted.',
            ]);
        }

        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        $product->delete();

        return redirect()->back()->with('message', 'Product Deleted');
    }


    public function printBarcodes(Request $request)
    {
        $ids = array_filter(explode(',', (string) $request->query('ids')));
        $products = Product::whereIn('id', $ids)->get();
        $generator = new BarcodeGeneratorPNG();

        $barcodes = [];
        foreach ($products as $product) {
            $codeStr = $product->barcode ?: ('MJ-' . str_pad((string) $product->id, 5, '0', STR_PAD_LEFT));

            $barcodes[] = [
                'name' => Str::limit($product->name, 18),
                'weight' => $product->gross_weight,
                'purity' => $product->purity->name ?? '',
                'code' => $codeStr,
                'barcode_png' => base64_encode($generator->getBarcode($codeStr, $generator::TYPE_CODE_128, 3, 72)),
            ];
        }

        $pdf = Pdf::loadView('pdf.barcodes', ['barcodes' => $barcodes])
            ->setPaper([0, 0, $this->mmToPoints(100), $this->mmToPoints(15)]);

        return $pdf->stream('product-barcodes.pdf');
    }

    protected function mmToPoints(float $mm): float
    {
        return $mm * 2.834645669;
    }
}
