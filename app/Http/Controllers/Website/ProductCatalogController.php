<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductCatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Product::query()->with(['category', 'purity']);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($productQuery) use ($search) {
                $productQuery->where('name', 'like', '%' . $search . '%')
                    ->orWhere('barcode', 'like', '%' . $search . '%');
            });
        }

        $products = $query->latest()->paginate(12)->withQueryString();

        $statsBaseQuery = Product::query();
        $statsProducts = $statsBaseQuery->get();

        return Inertia::render('website-products/Index', [
            'products' => $products,
            'filters' => $request->only(['search']),
            'endpointUrl' => url('/api/website/products'),
            'summary' => [
                'total_items' => $statsProducts->count(),
                'visible_items' => $statsProducts->where('is_visible_on_website', true)->where('is_sold', false)->count(),
                'hidden_items' => $statsProducts->where('is_visible_on_website', false)->where('is_sold', false)->count(),
                'sold_items' => $statsProducts->where('is_sold', true)->count(),
            ],
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'is_visible_on_website' => ['required', 'boolean'],
        ]);

        $product->update([
            'is_visible_on_website' => $validated['is_visible_on_website'],
        ]);

        return back()->with('message', 'Website visibility updated successfully.');
    }
}
