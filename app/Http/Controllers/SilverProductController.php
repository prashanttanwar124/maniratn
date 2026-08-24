<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Counter;
use App\Models\SilverProduct;
use App\Models\Supplier;
use App\Services\BarcodeLabelPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SilverProductController extends Controller
{
    public function index(Request $request)
    {
        $query = SilverProduct::query()->with([
            'category',
            'supplier',
            'counter',
            'invoiceItems.invoice.customer',
            'invoiceItems.invoice.user',
        ]);
        $statsBaseQuery = SilverProduct::query()->with('category');
        $applyFilters = function ($builder) use ($request) {
            if ($request->filled('search')) {
                $search = $request->string('search')->toString();

                $builder->where(function ($silverQuery) use ($search) {
                    $silverQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('barcode', 'like', '%'.$search.'%');
                });
            }

            if ($request->filled('category_id')) {
                $builder->where('category_id', (int) $request->input('category_id'));
            }

            if ($request->filled('supplier_id')) {
                $builder->where('supplier_id', (int) $request->input('supplier_id'));
            }

            if ($request->filled('counter_id')) {
                $builder->where('counter_id', (int) $request->input('counter_id'));
            }

            if ($request->filled('stock_status')) {
                $isSold = $request->string('stock_status')->toString() === 'sold';
                $builder->where('is_sold', $isSold);
            }

            if ($request->filled('pricing_mode')) {
                $builder->where('pricing_mode', $request->string('pricing_mode')->toString());
            }
        };

        $applyFilters($query);
        $applyFilters($statsBaseQuery);

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

        $paginatedProducts = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->through(function (SilverProduct $product) {
                $soldInvoice = null;
                if ($product->is_sold) {
                    $activeItem = $product->invoiceItems
                        ->filter(fn ($item) => $item->invoice && $item->invoice->status !== 'CANCELLED')
                        ->sortByDesc('created_at')
                        ->first();

                    if ($activeItem && $activeItem->invoice) {
                        $soldInvoice = [
                            'invoice_id' => $activeItem->invoice->id,
                            'invoice_number' => $activeItem->invoice->invoice_number,
                            'date' => $activeItem->invoice->date,
                            'customer_name' => $activeItem->invoice->customer?->name ?? 'Walk-in',
                            'customer_mobile' => $activeItem->invoice->customer?->mobile,
                            'customer_city' => $activeItem->invoice->customer?->city,
                            'rate' => (float) $activeItem->rate,
                            'making_charges' => (float) $activeItem->making_charges,
                            'making_charge_type' => $activeItem->making_charge_type ?: 'per_gram',
                            'weight' => (float) $activeItem->weight,
                            'quantity' => (int) ($activeItem->quantity ?? 1),
                            'total_price' => (float) ($activeItem->final_price ?? $activeItem->total_price ?? 0),
                            'billed_by' => $activeItem->invoice->user?->name,
                            'invoice_total' => (float) $activeItem->invoice->total_amount,
                            'invoice_status' => $activeItem->invoice->status,
                        ];
                    }
                }

                $productData = $product->toArray();
                $productData['sold_invoice'] = $soldInvoice;

                return $productData;
            });

        return Inertia::render('silver-products/Index', [
            'silverProducts' => $paginatedProducts,
            'suppliers' => Supplier::all(),
            'categories' => Category::silver()->orderBy('name')->get(),
            'counters' => Counter::query()->orderBy('name')->get(),
            'filters' => $request->only(['search', 'category_id', 'supplier_id', 'counter_id', 'stock_status', 'pricing_mode']),
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

    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string'],
        ]);

        $normalizedBarcode = strtoupper(trim($validated['barcode']));

        $product = SilverProduct::query()
            ->with(['category', 'supplier', 'counter'])
            ->where('barcode', $normalizedBarcode)
            ->first();

        if (! $product && preg_match('/^S(\d{5})$/', $normalizedBarcode, $matches)) {
            $product = SilverProduct::query()
                ->with(['category', 'supplier', 'counter'])
                ->find((int) $matches[1]);
        }

        abort_unless($product, 404, 'Silver product not found.');

        return response()->json([
            'product' => $product,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        unset($validated['image']);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('silver-products', 'public');
        }

        if (! empty($validated['batch_items'])) {
            $batchItems = $validated['batch_items'];
            unset($validated['batch_items'], $validated['gross_weight'], $validated['net_weight']);

            foreach ($batchItems as $index => $item) {
                SilverProduct::create([
                    ...$validated,
                    'name' => count($batchItems) > 1 ? $validated['name'].' #'.($index + 1) : $validated['name'],
                    'quantity' => 1,
                    'gross_weight' => $item['gross_weight'],
                    'net_weight' => $item['net_weight'],
                ]);
            }

            return redirect()->back()->with('message', count($batchItems).' Silver Products Created Successfully');
        }

        unset($validated['batch_items']);

        SilverProduct::create($validated);

        return redirect()->back()->with('message', 'Silver Product Created Successfully');
    }

    public function update(Request $request, SilverProduct $silverProduct)
    {
        if ($silverProduct->is_sold) {
            return redirect()->back()->withErrors([
                'silver_product' => 'Sold silver products cannot be edited.',
            ]);
        }

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

    public function bulkUpdate(Request $request)
    {
        $makingType = $request->input('making_charge_type', 'per_gram');
        $makingMaxRule = ($makingType === 'percentage') ? '|max:100' : '';

        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['required', 'integer', 'exists:silver_products,id'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('metal_type', 'SILVER'))],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'counter_id' => ['nullable', 'exists:counters,id'],
            'pricing_mode' => ['nullable', 'in:PIECE,WEIGHT'],
            'making_charge' => 'nullable|numeric|min:0'.$makingMaxRule,
            'making_charge_type' => ['nullable', 'string', 'in:percentage,flat,per_gram'],
            'piece_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $updates = collect($validated)
            ->only(['category_id', 'supplier_id', 'counter_id', 'pricing_mode', 'making_charge', 'making_charge_type', 'piece_price', 'notes'])
            ->filter(fn ($value) => $value !== null)
            ->all();

        if ($updates === []) {
            return back()->withErrors([
                'bulk_update' => 'Select at least one field to update.',
            ]);
        }

        SilverProduct::query()
            ->whereIn('id', $validated['product_ids'])
            ->where('is_sold', false)
            ->update($updates);

        return back()->with('message', count($validated['product_ids']).' silver products updated successfully.');
    }

    public function history(SilverProduct $silverProduct): JsonResponse
    {
        $silverProduct->load([
            'category',
            'supplier',
            'counter',
            'invoiceItems.invoice.customer',
            'verificationTags.customer',
            'verificationTags.createdBy',
            'verificationTags.writtenBy',
        ]);

        $events = collect([
            [
                'type' => 'created',
                'title' => 'Silver product created',
                'occurred_at' => optional($silverProduct->created_at)?->toISOString(),
                'meta' => [
                    'barcode' => $silverProduct->barcode,
                    'category' => $silverProduct->category?->name,
                    'supplier' => $silverProduct->supplier?->company_name,
                    'counter' => $silverProduct->counter?->name,
                ],
            ],
            $silverProduct->updated_at && $silverProduct->updated_at->ne($silverProduct->created_at) ? [
                'type' => 'updated',
                'title' => 'Silver product updated',
                'occurred_at' => optional($silverProduct->updated_at)?->toISOString(),
            ] : null,
        ])->filter();

        $saleEvents = $silverProduct->invoiceItems->map(function ($item) {
            return $item->invoice && $item->invoice->status !== 'CANCELLED' ? [
                'type' => 'sold',
                'title' => 'Sold via invoice #'.$item->invoice->invoice_number,
                'occurred_at' => optional($item->created_at)?->toISOString(),
                'meta' => [
                    'customer' => $item->invoice->customer?->name,
                    'amount' => (float) $item->final_price,
                ],
            ] : null;
        })->filter();

        $tagEvents = $silverProduct->verificationTags->flatMap(function ($tag) {
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
                'id' => $silverProduct->id,
                'barcode' => $silverProduct->barcode,
                'name' => $silverProduct->name,
                'category' => $silverProduct->category?->name,
                'supplier' => $silverProduct->supplier?->company_name,
                'counter' => $silverProduct->counter?->name,
                'pricing_mode' => $silverProduct->pricing_mode,
                'quantity' => (int) $silverProduct->quantity,
                'gross_weight' => (float) ($silverProduct->gross_weight ?? 0),
                'net_weight' => (float) ($silverProduct->net_weight ?? 0),
                'piece_price' => (float) ($silverProduct->piece_price ?? 0),
                'making_charge' => (float) $silverProduct->making_charge,
                'making_charge_type' => $silverProduct->making_charge_type ?? 'per_gram',
                'status' => $silverProduct->is_sold ? 'Sold' : 'In Stock',
            ],
            'timeline' => $timeline,
        ]);
    }

    public function duplicate(SilverProduct $silverProduct)
    {
        SilverProduct::create([
            'name' => $silverProduct->name,
            'category_id' => $silverProduct->category_id,
            'supplier_id' => $silverProduct->supplier_id,
            'counter_id' => $silverProduct->counter_id,
            'pricing_mode' => $silverProduct->pricing_mode,
            'quantity' => $silverProduct->quantity,
            'gross_weight' => $silverProduct->gross_weight,
            'net_weight' => $silverProduct->net_weight,
            'piece_price' => $silverProduct->piece_price,
            'making_charge' => $silverProduct->making_charge,
            'making_charge_type' => $silverProduct->making_charge_type ?? 'per_gram',
            'notes' => $silverProduct->notes,
        ]);

        return back()->with('message', 'Silver product duplicated successfully.');
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
            $codeStr = $product->barcode ?: ('MS-'.str_pad((string) $product->id, 5, '0', STR_PAD_LEFT));

            $labels[] = [
                'category' => strtoupper((string) ($product->category?->name ?? 'SILVER')),
                'purity' => 'SILVER',
                'gross_weight' => (float) ($product->gross_weight ?: 0),
                'net_weight' => (float) ($product->net_weight ?: 0),
                'code' => $codeStr,
            ];
        }

        return $barcodeLabelPdfService->stream($labels, 'silver-product-barcodes.pdf');
    }

    protected function validatePayload(Request $request): array
    {
        $makingType = $request->input('making_charge_type', 'per_gram');
        $makingMaxRule = ($makingType === 'percentage') ? '|max:100' : '';

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('metal_type', 'SILVER'))],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'counter_id' => ['nullable', 'exists:counters,id'],
            'pricing_mode' => ['required', 'in:PIECE,WEIGHT'],
            'quantity' => ['required', 'integer', 'min:1'],
            'gross_weight' => ['nullable', 'numeric', 'min:0'],
            'net_weight' => ['nullable', 'numeric', 'min:0'],
            'piece_price' => ['nullable', 'numeric', 'min:0'],
            'making_charge' => ['required', 'numeric', 'min:0'.$makingMaxRule],
            'making_charge_type' => ['nullable', 'string', 'in:percentage,flat,per_gram'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'max:2048'],
            'batch_items' => ['nullable', 'array', 'min:1', 'max:10'],
            'batch_items.*.gross_weight' => ['required_with:batch_items', 'numeric', 'min:0.001'],
            'batch_items.*.net_weight' => ['required_with:batch_items', 'numeric', 'min:0.001'],
        ]);

        $validated['making_charge_type'] = $validated['making_charge_type'] ?? 'per_gram';

        if (! empty($validated['batch_items'])) {
            if ($validated['pricing_mode'] === 'PIECE' && empty($validated['piece_price'])) {
                $validated['piece_price'] = 0;
            }

            return $validated;
        }

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
