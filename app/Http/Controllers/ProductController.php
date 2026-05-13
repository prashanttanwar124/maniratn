<?php

namespace App\Http\Controllers;

use App\Services\BarcodeLabelPdfService;
use App\Models\Product;
use App\Models\Category;
use App\Models\Purity;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()->with(['category', 'purity', 'supplier']);
        $statsBaseQuery = Product::query()->with('category');
        $applyFilters = function ($builder) use ($request) {
            if ($request->filled('search')) {
                $search = $request->string('search')->toString();

                $builder->where(function ($productQuery) use ($search) {
                    $productQuery->where('name', 'like', '%' . $search . '%')
                        ->orWhere('barcode', 'like', '%' . $search . '%');
                });
            }

            if ($request->filled('category_id')) {
                $builder->where('category_id', (int) $request->input('category_id'));
            }

            if ($request->filled('supplier_id')) {
                $builder->where('supplier_id', (int) $request->input('supplier_id'));
            }

            if ($request->filled('purity_id')) {
                $builder->where('purity_id', (int) $request->input('purity_id'));
            }

            if ($request->filled('stock_status')) {
                $isSold = $request->string('stock_status')->toString() === 'sold';
                $builder->where('is_sold', $isSold);
            }
        };

        $applyFilters($query);
        $applyFilters($statsBaseQuery);

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
            'products'    => $query
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(10),
            'suppliers'   => Supplier::all(),
            'categories'  => Category::gold()->orderBy('name')->get(),
            'purities'    => Purity::all(),
            'filters'     => $request->only(['search', 'category_id', 'supplier_id', 'purity_id', 'stock_status']),
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
            'gross_weight'  => 'required_without:batch_items|nullable|numeric|min:0.001',
            'net_weight'    => 'required_without:batch_items|nullable|numeric|min:0.001',
            'making_charge' => 'required|numeric|min:0|max:100',
            'image_path'         => 'nullable|image|max:2048',
            'batch_items' => ['nullable', 'array', 'min:1', 'max:10'],
            'batch_items.*.gross_weight' => ['required_with:batch_items', 'numeric', 'min:0.001'],
            'batch_items.*.net_weight' => ['required_with:batch_items', 'numeric', 'min:0.001'],
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('products', 'public');
        }

        if (! empty($validated['batch_items'])) {
            $batchItems = $validated['batch_items'];
            $baseName = trim((string) $validated['name']);
            unset($validated['batch_items'], $validated['gross_weight'], $validated['net_weight']);

            foreach ($batchItems as $item) {
                $product = Product::create([
                    ...$validated,
                    'name' => $baseName,
                    'gross_weight' => $item['gross_weight'],
                    'net_weight' => $item['net_weight'],
                ]);

                $product->updateQuietly([
                    'name' => $baseName . ' - ' . $product->barcode,
                ]);
            }

            return redirect()->back()->with('message', count($batchItems) . ' Products Created Successfully');
        }

        unset($validated['batch_items']);

        $baseName = trim((string) $validated['name']);
        $validated['name'] = $baseName;

        $product = Product::create($validated);
        $product->updateQuietly([
            'name' => $baseName . ' - ' . $product->barcode,
        ]);

        return redirect()->back()->with('message', 'Product Created Successfully');
    }

    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string'],
        ]);

        $normalizedBarcode = strtoupper(trim($validated['barcode']));

        $product = Product::query()
            ->with(['category', 'purity', 'supplier'])
            ->where('barcode', $normalizedBarcode)
            ->first();

        if (! $product && preg_match('/^G(\d{5})$/', $normalizedBarcode, $matches)) {
            $product = Product::query()
                ->with(['category', 'purity', 'supplier'])
                ->find((int) $matches[1]);
        }

        abort_unless($product, 404, 'Product not found.');

        return response()->json([
            'product' => $product,
        ]);
    }

    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['required', 'integer', 'exists:products,id'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('metal_type', 'GOLD'))],
            'purity_id' => ['nullable', 'exists:purities,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'making_charge' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $updates = collect($validated)
            ->only(['category_id', 'purity_id', 'supplier_id', 'making_charge'])
            ->filter(fn ($value) => $value !== null)
            ->all();

        if ($updates === []) {
            return back()->withErrors([
                'bulk_update' => 'Select at least one field to update.',
            ]);
        }

        Product::query()
            ->whereIn('id', $validated['product_ids'])
            ->update($updates);

        return back()->with('message', count($validated['product_ids']) . ' products updated successfully.');
    }

    public function history(Product $product): JsonResponse
    {
        $product->load([
            'category',
            'purity',
            'supplier',
            'invoiceItems.invoice.customer',
            'verificationTags.customer',
            'verificationTags.createdBy',
            'verificationTags.writtenBy',
        ]);

        $events = collect([
            [
                'type' => 'created',
                'title' => 'Product created',
                'occurred_at' => optional($product->created_at)?->toISOString(),
                'meta' => [
                    'barcode' => $product->barcode,
                    'category' => $product->category?->name,
                    'purity' => $product->purity?->name,
                    'supplier' => $product->supplier?->company_name,
                ],
            ],
            $product->updated_at && $product->updated_at->ne($product->created_at) ? [
                'type' => 'updated',
                'title' => 'Product updated',
                'occurred_at' => optional($product->updated_at)?->toISOString(),
                'meta' => [
                    'status' => $product->is_sold ? 'Sold' : 'In Stock',
                ],
            ] : null,
        ])->filter();

        $saleEvents = $product->invoiceItems->map(function ($invoiceItem) {
            return [
                'type' => 'sold',
                'title' => 'Product billed',
                'occurred_at' => optional($invoiceItem->invoice?->created_at ?? $invoiceItem->created_at)?->toISOString(),
                'meta' => [
                    'invoice_number' => $invoiceItem->invoice?->invoice_number,
                    'customer_name' => $invoiceItem->invoice?->customer?->name ?? 'Walk-in',
                    'invoice_status' => $invoiceItem->invoice?->status,
                    'weight' => $invoiceItem->weight,
                ],
            ];
        });

        $tagEvents = $product->verificationTags->flatMap(function ($tag) {
            return collect([
                [
                    'type' => 'verification_tag_created',
                    'title' => 'Verification tag created',
                    'occurred_at' => optional($tag->created_at)?->toISOString(),
                    'meta' => [
                        'token' => $tag->token,
                        'status' => $tag->status,
                        'customer_name' => $tag->customer?->name,
                        'created_by' => $tag->createdBy?->name,
                    ],
                ],
                $tag->written_at ? [
                    'type' => 'verification_tag_written',
                    'title' => 'Verification tag written',
                    'occurred_at' => optional($tag->written_at)?->toISOString(),
                    'meta' => [
                        'token' => $tag->token,
                        'written_by' => $tag->writtenBy?->name,
                    ],
                ] : null,
                $tag->locked_at ? [
                    'type' => 'verification_tag_locked',
                    'title' => 'Verification tag locked',
                    'occurred_at' => optional($tag->locked_at)?->toISOString(),
                    'meta' => [
                        'token' => $tag->token,
                    ],
                ] : null,
            ])->filter();
        });

        $timeline = $events
            ->concat($saleEvents)
            ->concat($tagEvents)
            ->sortByDesc(fn ($event) => $event['occurred_at'] ?? '')
            ->values();

        return response()->json([
            'product' => [
                'id' => $product->id,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'category' => $product->category?->name,
                'purity' => $product->purity?->name,
                'supplier' => $product->supplier?->company_name,
                'gross_weight' => (float) $product->gross_weight,
                'net_weight' => (float) $product->net_weight,
                'making_charge' => (float) $product->making_charge,
                'status' => $product->is_sold ? 'Sold' : 'In Stock',
            ],
            'timeline' => $timeline,
        ]);
    }

    public function duplicate(Product $product)
    {
        $duplicate = Product::create([
            'name' => $product->name,
            'category_id' => $product->category_id,
            'purity_id' => $product->purity_id,
            'supplier_id' => $product->supplier_id,
            'gross_weight' => $product->gross_weight,
            'net_weight' => $product->net_weight,
            'making_charge' => $product->making_charge,
        ]);

        $baseName = preg_replace('/\s*-\s*G\d{5}$/', '', (string) $product->name) ?: $product->name;

        $duplicate->updateQuietly([
            'name' => trim($baseName) . ' - ' . $duplicate->barcode,
        ]);

        return back()->with('message', 'Product duplicated successfully.');
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
            'making_charge' => 'required|numeric|min:0|max:100',
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


    public function printBarcodes(Request $request, BarcodeLabelPdfService $barcodeLabelPdfService)
    {
        $ids = array_filter(explode(',', (string) $request->query('ids')));
        $products = Product::whereIn('id', $ids)->get();

        $labels = [];
        foreach ($products as $product) {
            $codeStr = $product->barcode ?: ('MJ-' . str_pad((string) $product->id, 5, '0', STR_PAD_LEFT));

            $labels[] = [
                'category' => strtoupper((string) ($product->category?->name ?? 'GOLD')),
                'purity' => strtoupper((string) ($product->purity?->name ?? '')),
                'gross_weight' => (float) $product->gross_weight,
                'net_weight' => (float) $product->net_weight,
                'code' => $codeStr,
            ];
        }

        return $barcodeLabelPdfService->stream($labels, 'product-barcodes.pdf');
    }
}
