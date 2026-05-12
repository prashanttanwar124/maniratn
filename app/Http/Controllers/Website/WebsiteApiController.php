<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class WebsiteApiController extends Controller
{
    public function products(): JsonResponse
    {
        $products = Product::query()
            ->with(['category:id,name', 'purity:id,name'])
            ->where('is_sold', false)
            ->where('is_visible_on_website', true)
            ->latest()
            ->get()
            ->map(function (Product $product) {
                return [
                    'id' => $product->id,
                    'barcode' => $product->barcode,
                    'name' => $product->name,
                    'category' => $product->category?->name,
                    'purity' => $product->purity?->name,
                    'gross_weight' => (float) $product->gross_weight,
                    'net_weight' => (float) $product->net_weight,
                    'making_charge' => (float) $product->making_charge,
                    'image_url' => $product->image_path ? url(Storage::url($product->image_path)) : null,
                    'created_at' => optional($product->created_at)?->toISOString(),
                ];
            })
            ->values();

        return response()->json([
            'products' => $products,
        ]);
    }
}
